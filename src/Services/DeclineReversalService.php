<?php

namespace Boi\Backend\Services;

use Boi\Backend\Contracts\ChecksReopenConflicts;
use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Enums\StatusChangeRequestStatus;
use Boi\Backend\Events\DeclineReversalApproved;
use Boi\Backend\Events\DeclineReversalRejected;
use Boi\Backend\Events\DeclineReversalRequested;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Exceptions\UnauthorizedStatusChangeException;
use Boi\Backend\Models\StatusChangeRequest;
use Boi\Backend\Support\ApprovedStatusTransition;
use Boi\Backend\Support\DeclineReversalAuthorizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Maker-checker reversal of a declined application (declined → returned).
 *
 *   request()  maker (Admin) raises a pending request; the application stays declined.
 *   approve()  a different checker (Super Admin) approves; the application is saved
 *              as returned through Eloquent, so the app's observer reopens it
 *              (status → incomplete) and notifies the applicant as for any Return.
 *   reject()   a checker rejects; the application stays declined.
 *
 * Each step runs in a transaction that locks the request and application rows,
 * and claims the request with a conditional `status = pending` update, so
 * concurrent or replayed approvals/rejections cannot both succeed.
 */
class DeclineReversalService
{
    /** Application attributes recorded on the request when it is raised. */
    protected const SNAPSHOT_ATTRIBUTES = [
        'status',
        'internal_status',
        'rejection_reason',
        'project_officer_comments',
        'project_officer_id',
    ];

    public function __construct(protected DeclineReversalAuthorizer $authorizer) {}

    /**
     * @param  string  $justification  internal reason for reversing (staff only)
     * @param  string  $returnReason  customer-facing return reason, applied on approval
     * @param  string|null  $comments  optional comments for the applicant, applied on approval
     */
    public function request(Model $application, $maker, string $justification, string $returnReason, ?string $comments = null): StatusChangeRequest
    {
        if (! $this->authorizer->canRequest($maker)) {
            throw UnauthorizedStatusChangeException::maker();
        }

        $justification = trim($justification);
        $returnReason = trim($returnReason);
        $comments = $comments !== null && trim($comments) !== '' ? trim($comments) : null;

        if ($justification === '') {
            throw StatusChangeRequestException::missing('justification');
        }
        if ($returnReason === '') {
            throw StatusChangeRequestException::missing('return reason');
        }

        try {
            $request = $application->getConnection()->transaction(function () use ($application, $maker, $justification, $returnReason, $comments) {
                $locked = $application->newQuery()->lockForUpdate()->find($application->getKey());
                if ($locked === null) {
                    throw StatusChangeRequestException::applicationMissing();
                }

                $this->assertReversible($locked);
                $this->assertNoReopenConflict($locked);

                $pendingKey = StatusChangeRequest::pendingKeyFor(StatusChangeRequest::TYPE_DECLINE_REVERSAL, $locked);
                if (StatusChangeRequest::query()->where('pending_key', $pendingKey)->exists()) {
                    throw StatusChangeRequestException::alreadyPending();
                }

                $request = new StatusChangeRequest;
                $request->forceFill([
                    'requestable_type' => $locked->getMorphClass(),
                    'requestable_id' => $locked->getKey(),
                    'type' => StatusChangeRequest::TYPE_DECLINE_REVERSAL,
                    'status' => StatusChangeRequestStatus::PENDING,
                    'from_status' => ApplicationStatus::DECLINED,
                    'to_status' => ApplicationStatus::RETURNED,
                    'reason' => $justification,
                    'payload' => [
                        'rejection_reason' => $returnReason,
                        'comments' => $comments,
                    ],
                    'snapshot' => $this->snapshot($locked),
                    'requested_by' => $maker->getAuthIdentifier(),
                    'pending_key' => $pendingKey,
                ])->save();

                event(new DeclineReversalRequested($request));

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            // Another maker won the race for this application's pending slot.
            throw StatusChangeRequestException::alreadyPending();
        }

        logAction($application, 'decline_reversal_requested', [
            'status_change_request_id' => $request->getKey(),
            'reason' => $justification,
        ], $maker->getAuthIdentifier());

        return $request;
    }

    public function approve(StatusChangeRequest $request, $checker, ?string $comment = null): StatusChangeRequest
    {
        $this->authorizeReview($checker);
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        try {
            [$approved, $application] = $this->approveInTransaction($request, $checker, $comment);
        } catch (UniqueConstraintViolationException $e) {
            // The app's own constraints refused the reopened row (e.g. one
            // incomplete application per applicant). The transaction rolled
            // back, so the request is still pending and the application declined.
            Log::warning('Decline reversal approval conflicted with an app constraint', [
                'status_change_request_id' => $request->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw StatusChangeRequestException::reopenConflict($e);
        }

        logAction($application, 'decline_reversal_approved', [
            'status_change_request_id' => $approved->getKey(),
            'internal_status' => ['from' => ApplicationStatus::DECLINED, 'to' => ApplicationStatus::RETURNED],
        ], $checker->getAuthIdentifier());

        return $approved;
    }

    /** @return array{0: StatusChangeRequest, 1: Model} */
    protected function approveInTransaction(StatusChangeRequest $request, $checker, ?string $comment): array
    {
        return $request->getConnection()->transaction(function () use ($request, $checker, $comment) {
            $locked = $this->lockPending($request, $checker);
            $this->assertDeclineReversal($locked);

            $application = $locked->requestable()->lockForUpdate()->first();
            $this->assertStillDeclined($application);
            // Before any save: the app's Returned observer notifies the applicant
            // during `updating`, so a reopening must not be attempted if it would fail.
            $this->assertNoReopenConflict($application);

            $this->claim($locked, StatusChangeRequestStatus::APPROVED, $checker, $comment);

            $payload = $locked->payload ?? [];
            ApprovedStatusTransition::run($application, function () use ($application, $payload): void {
                $application->forceFill([
                    'internal_status' => ApplicationStatus::RETURNED,
                    'rejection_reason' => $payload['rejection_reason'] ?? null,
                    'project_officer_comments' => $payload['comments'] ?? null,
                ])->save();
            });

            $locked->refresh();
            event(new DeclineReversalApproved($locked));

            return [$locked, $application];
        });
    }

    public function reject(StatusChangeRequest $request, $checker, string $comment): StatusChangeRequest
    {
        $this->authorizeReview($checker);

        $comment = trim($comment);
        if ($comment === '') {
            throw StatusChangeRequestException::missing('rejection reason');
        }

        $rejected = $request->getConnection()->transaction(function () use ($request, $checker, $comment) {
            $locked = $this->lockPending($request, $checker);
            $this->claim($locked, StatusChangeRequestStatus::REJECTED, $checker, $comment);

            $locked->refresh();
            event(new DeclineReversalRejected($locked));

            return $locked;
        });

        if ($application = $rejected->requestable) {
            logAction($application, 'decline_reversal_rejected', [
                'status_change_request_id' => $rejected->getKey(),
                'reason' => $comment,
            ], $checker->getAuthIdentifier());
        }

        return $rejected;
    }

    protected function authorizeReview($checker): void
    {
        if (! $this->authorizer->canReview($checker)) {
            throw UnauthorizedStatusChangeException::checker();
        }
    }

    /**
     * Re-read the request under a row lock; only a pending request may proceed,
     * and never for the user who raised it (checked on the persisted row).
     */
    protected function lockPending(StatusChangeRequest $request, $checker): StatusChangeRequest
    {
        $locked = StatusChangeRequest::query()->lockForUpdate()->find($request->getKey());

        if ($locked === null || ! $locked->isPending()) {
            throw StatusChangeRequestException::notPending();
        }

        if ($this->authorizer->isRequester($checker, $locked)) {
            throw UnauthorizedStatusChangeException::selfReview();
        }

        return $locked;
    }

    /**
     * Compare-and-swap pending → final. If a concurrent reviewer already
     * claimed it (e.g. on a database without row locks) nothing is updated.
     */
    protected function claim(StatusChangeRequest $locked, string $outcome, $checker, ?string $comment): void
    {
        $now = $locked->freshTimestamp();

        $claimed = StatusChangeRequest::query()
            ->whereKey($locked->getKey())
            ->where('status', StatusChangeRequestStatus::PENDING)
            ->update([
                'status' => $outcome,
                'reviewed_by' => $checker->getAuthIdentifier(),
                'reviewed_at' => $now,
                'review_comment' => $comment,
                'pending_key' => null,
                'updated_at' => $now,
            ]);

        if ($claimed !== 1) {
            throw StatusChangeRequestException::notPending();
        }
    }

    protected function assertReversible(Model $application): void
    {
        if ($application->internal_status === ApplicationStatus::STEPPED_DOWN) {
            throw StatusChangeRequestException::steppedDown();
        }

        if ($application->status !== ApplicationStatus::DECLINED
            || $application->internal_status !== ApplicationStatus::DECLINED) {
            throw StatusChangeRequestException::notDeclined();
        }
    }

    protected function assertNoReopenConflict(Model $application): void
    {
        if ($application instanceof ChecksReopenConflicts && ($reason = $application->reopenConflict()) !== null) {
            throw StatusChangeRequestException::cannotReopen($reason);
        }
    }

    protected function assertDeclineReversal(StatusChangeRequest $request): void
    {
        if ($request->type !== StatusChangeRequest::TYPE_DECLINE_REVERSAL
            || $request->from_status !== ApplicationStatus::DECLINED
            || $request->to_status !== ApplicationStatus::RETURNED) {
            throw StatusChangeRequestException::unsupportedType();
        }
    }

    /** The application must still be in the declined state the request was raised against. */
    protected function assertStillDeclined(?Model $application): void
    {
        if ($application === null) {
            throw StatusChangeRequestException::applicationMissing();
        }

        if ($application->status !== ApplicationStatus::DECLINED
            || $application->internal_status !== ApplicationStatus::DECLINED) {
            throw StatusChangeRequestException::stateChanged();
        }
    }

    protected function snapshot(Model $application): array
    {
        $snapshot = [];
        foreach (self::SNAPSHOT_ATTRIBUTES as $attribute) {
            $snapshot[$attribute] = $application->getAttribute($attribute);
        }
        $updatedAt = $application->getAttribute('updated_at');
        $snapshot['updated_at'] = $updatedAt instanceof \DateTimeInterface ? $updatedAt->format(DATE_ATOM) : $updatedAt;

        return $snapshot;
    }
}
