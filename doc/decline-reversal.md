# Decline reversal (maker-checker)

A declined application can be reopened only through a two-person workflow:

```
Admin requests reversal (maker)
        ↓
request is PENDING — application stays DECLINED
        ↓
a different Super Admin approves (checker)        … or rejects → stays DECLINED
        ↓
application saved as internal_status = returned
        ↓
app's ApplicationObserver reopens it (status → incomplete) and sends the
existing "Application Returned" notice; the applicant updates and resubmits
```

Stepped-down applications (`status = declined`, `internal_status = stepped_down`)
are out of scope and cannot be reversed this way.

## What the package provides

| Piece | Purpose |
|-------|---------|
| `status_change_requests` table (auto-loaded migration) | One row per request: requester, justification, the values to apply, a snapshot of the declined state, reviewer, outcome. `pending_key` is unique while pending, so only one pending request per application can exist (enforced by the database). |
| `Models\StatusChangeRequest` | The request (`pending` / `approved` / `rejected`, see `Enums\StatusChangeRequestStatus`). Not mass assignable. |
| `Services\DeclineReversalService` | `request()`, `approve()`, `reject()`. Each runs in a transaction that row-locks the request and the application and claims the request with a conditional `status = pending` update, so concurrent or replayed approvals/rejections cannot both succeed. |
| `Support\DeclineReversalAuthorizer` | Maker = configured role **and** permission; checker likewise; a checker can never review a request they raised. Rebind in the container to change the rules. |
| `Models\Concerns\GuardsDeclinedStatus` | Model guard: any Eloquent save that moves a declined application off `declined` or to `internal_status = returned` throws `DeclinedApplicationLockedException`, unless it is the service's approved save. Covers Nova forms, actions, tinker and jobs. (Query-builder mass updates bypass model events.) |
| `Models\Concerns\HasStatusChangeRequests` | `statusChangeRequests()` relation and `pendingDeclineReversal()`. |
| `Nova\Actions\RequestDeclineReversal` | Maker action on the Application resource. |
| `Nova\Actions\ApproveDeclineReversal`, `RejectDeclineReversal` | Checker actions on the request resource. |
| `Events\DeclineReversalRequested/Approved/Rejected` | Dispatched after commit, for app notifications. |

`Nova\Actions\ReturnApplication` refuses declined applications and now validates
**every** selected application before changing any (Nova's `sole()` is only
enforced in the UI).

The Nova actions set their own `canSee` / `canRun` from the authorizer, and the
service re-checks everything, so an app that forgets a gate is still safe.

## Configuration

`config('boi_backend.decline_reversal')`:

```php
'decline_reversal' => [
    'maker_roles' => ['Admin'],
    'maker_permission' => 'decline_reversal_request',
    'checker_roles' => ['Super Admin'],
    'checker_permission' => 'decline_reversal_approve',
],
```

Roles are matched by name with the app User's `hasRoleName()` (falling back to
Spatie `hasRole()`); permissions with `hasPermissionTo()`. Both must pass.

## Integrating an app

1. `Application` model: `use GuardsDeclinedStatus, HasStatusChangeRequests;`
2. Nova: empty subclasses of the three actions; register `RequestDeclineReversal`
   on the Application resource; add an `App\Nova` resource for
   `Boi\Backend\Models\StatusChangeRequest` with the Approve/Reject actions
   (read-only policy, registered with `Gate::policy` — see
   [Nova & authorization](nova-and-authorization.md)).
3. Permissions: create `decline_reversal_request` / `decline_reversal_approve`;
   grant each to exactly one role, and keep both out of derived "all
   permissions" role presets.
4. Listen for the events to notify checkers (requested) and the maker
   (approved / rejected). The applicant is notified by the app's existing
   Returned flow.

## Approval conflicts

If the app's own constraints refuse the reopened row (GLOW/ADF allow one
incomplete application per applicant, so an applicant who already started a new
application cannot have the old one reopened), `approve()` throws
`StatusChangeRequestException` with a clear message. The transaction is rolled
back: the request stays pending and the application stays declined.
