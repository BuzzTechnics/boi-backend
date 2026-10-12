<?php

namespace Boi\Backend\Services;

use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Enums\StatusChangeRequestStatus;
use Boi\Backend\Events\ReturnApproved;
use Boi\Backend\Events\ReturnRejected;
use Boi\Backend\Events\ReturnRequested;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Exceptions\UnauthorizedStatusChangeException;
use Boi\Backend\Models\StatusChangeRequest;
use Boi\Backend\Support\ApprovedStatusTransition;
use Boi\Backend\Support\ReturnRequestAuthorizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Maker-checker "Return to Customer" (under review → returned).
 *
 *   request()  maker (Monitor) raises a pending request; the application keeps
 *              its current status and is not yet returned.
 *   approve()  a different checker (Super Admin) approves; the application is
 *              saved with internal_status = returned through Eloquent, exactly as
 *              the direct ReturnApplication action does, so the app's observer
 *              notifies the applicant.
 *   reject()   a checker rejects; the application is unchanged.
 *
 * Each step runs in a transaction that locks the request and application rows,
 * and claims the request with a conditional `status = pending` update, so
 * concurrent or replayed approvals/rejections cannot both succeed. This runs
 * alongside — and does not replace — the direct ReturnApplication action.
 */
class ReturnApprovalService
{
    /** Application attributes recorded on the request when it is raised. */
    protected const SNAPSHOT_ATTRIBUTES = [
        'status',
        'internal_status',
        'rejection_reason',
        'project_officer_comments',
        'project_officer_id',
    ];

    public function __construct(protected ReturnRequestAuthorizer $authorizer) {}

    /**
     * @param  string  $justification  internal reason for the return (staff only, seen by the checker)
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

                $this->assertReturnable($locked);

                $pendingKey = StatusChangeRequest::pendingKeyFor(StatusChangeRequest::TYPE_RETURN, $locked);
                if (StatusChangeRequest::query()->where('pending_key', $pendingKey)->exists()) {
                    throw StatusChangeRequestException::returnAlreadyPending();
                }

                $request = new StatusChangeRequest;
                $request->forceFill([
                    'requestable_type' => $locked->getMorphClass(),
                    'requestable_id' => $locked->getKey(),
                    'type' => StatusChangeRequest::TYPE_RETURN,
                    'status' => StatusChangeRequestStatus::PENDING,
                    'from_status' => $locked->status,
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

                event(new ReturnRequested($request));

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            // Another maker won the race for this application's pending slot.
            throw StatusChangeRequestException::returnAlreadyPending();
        }

        logAction($application, 'return_requested', [
            'status_change_request_id' => $request->getKey(),
            'reason' => $justification,
        ], $maker->getAuthIdentifier());

        return $request;
    }

    public function approve(StatusChangeRequest $request, $checker, ?string $comment = null): StatusChangeRequest
    {
        $this->authorizeReview($checker);
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        [$approved, $application] = $request->getConnection()->transaction(function () use ($request, $checker, $comment) {
            $locked = $this->lockPending($request, $checker);
            $this->assertReturn($locked);

            $application = $locked->requestable()->lockForUpdate()->first();
            $this->assertStillReturnable($application);

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
            event(new ReturnApproved($locked));

            return [$locked, $application];
        });

        logAction($application, 'return_approved', [
            'status_change_request_id' => $approved->getKey(),
            'internal_status' => ['to' => ApplicationStatus::RETURNED],
        ], $checker->getAuthIdentifier());

        return $approved;
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
            event(new ReturnRejected($locked));

            return $locked;
        });

        if ($application = $rejected->requestable) {
            logAction($application, 'return_rejected', [
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

    /**
     * Mirror of ReturnApplication::assertReturnable: a declined application is
     * never returned here (use the decline reversal), nor an approved one, nor a
     * plain incomplete draft — only an under-review application (or one returned
     * from SharePoint) can be returned to the customer.
     */
    protected function assertReturnable(Model $application): void
    {
        $canReturnFromSharepoint = $application->status === ApplicationStatus::INCOMPLETE
            && $application->internal_status === ApplicationStatus::RETURN_FROM_SHAREPOINT;

        if ($application->status === ApplicationStatus::INCOMPLETE && ! $canReturnFromSharepoint) {
            throw StatusChangeRequestException::notReturnable('Cannot return: the application is incomplete.');
        }

        if ($application->status === ApplicationStatus::APPROVED) {
            throw StatusChangeRequestException::notReturnable('Cannot return: the application is already approved.');
        }

        if ($application->status === ApplicationStatus::DECLINED) {
            throw StatusChangeRequestException::notReturnable('Cannot return: the application is declined. Use "Request Decline Reversal"; it requires Super Admin approval.');
        }

        if ($application->internal_status === ApplicationStatus::RETURNED) {
            throw StatusChangeRequestException::notReturnable('Cannot return: the application has already been returned to the customer.');
        }
    }

    protected function assertReturn(StatusChangeRequest $request): void
    {
        if ($request->type !== StatusChangeRequest::TYPE_RETURN
            || $request->to_status !== ApplicationStatus::RETURNED) {
            throw StatusChangeRequestException::unsupportedReturnType();
        }
    }

    /** The application must still be in a returnable state the request was raised against. */
    protected function assertStillReturnable(?Model $application): void
    {
        if ($application === null) {
            throw StatusChangeRequestException::applicationMissing();
        }

        if ($application->internal_status === ApplicationStatus::RETURNED) {
            throw StatusChangeRequestException::returnStateChanged();
        }

        try {
            $this->assertReturnable($application);
        } catch (StatusChangeRequestException) {
            throw StatusChangeRequestException::returnStateChanged();
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
