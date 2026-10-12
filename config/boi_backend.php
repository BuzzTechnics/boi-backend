<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Register boi-api proxy route (`/api/boi-api/{path}`)
    |--------------------------------------------------------------------------
    |
    | Independent of file routes. Set false on boi-api itself (no self-proxy).
    |
    */
    'register_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Register /api/files/upload and /api/files/view
    |--------------------------------------------------------------------------
    |
    | Uses the host app’s S3 disk. Host apps that register their own `/api/files/*`
    | (e.g. boi-api with auth.proxy) should set this to false in AppServiceProvider.
    |
    */
    'register_file_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Extra route middleware (optional)
    |--------------------------------------------------------------------------
    |
    | Appended to the package defaults. Publish this config only when you need
    | additional middleware.
    |
    */
    'extra_proxy_middleware' => [],

    'extra_api_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Project name used in edoc_calls.project
    |--------------------------------------------------------------------------
    |
    | Stamped on every row written by EdocCallLogger so a single dashboard can
    | distinguish Edoc traffic across boi-api / glow / spaf / boi-online-portal.
    | Falls back to config('app.name') when unset.
    |
    */
    'project' => env('BOI_BACKEND_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | Decline reversal (maker-checker)
    |--------------------------------------------------------------------------
    |
    | A declined application can only be reopened (declined → returned) through
    | a reversal request raised by a maker and approved by a different checker.
    | See Boi\Backend\Services\DeclineReversalService.
    |
    | A user must hold one of the roles AND the permission. Roles are matched by
    | name via the app User's hasRoleName() (falling back to Spatie hasRole()).
    |
    */
    'decline_reversal' => [
        'maker_roles' => ['Monitor'],
        'maker_permission' => 'decline_reversal_request',
        'checker_roles' => ['Super Admin'],
        'checker_permission' => 'decline_reversal_approve',
    ],

    /*
    |--------------------------------------------------------------------------
    | Return approval (maker-checker)
    |--------------------------------------------------------------------------
    |
    | A maker-checker variant of "Return to Customer": a Monitor raises a
    | request to return an under-review application, and a Super Admin approves
    | it (the application is then saved as returned, so the app's observer
    | notifies the applicant exactly as for a direct return). Runs alongside the
    | direct ReturnApplication action. See Boi\Backend\Services\ReturnApprovalService.
    |
    | Same shape as decline_reversal: a user needs one of the roles AND the
    | permission; a checker may never review a request they raised.
    |
    */
    'return_approval' => [
        'maker_roles' => ['Monitor'],
        'maker_permission' => 'return_request',
        'checker_roles' => ['Super Admin'],
        'checker_permission' => 'return_approve',
    ],

];
