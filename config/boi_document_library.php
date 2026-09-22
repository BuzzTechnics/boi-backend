<?php

/**
 * Shared Document Library for BOI intervention portals.
 *
 * Everything here is a default a fund overrides in its own config. A fund turns the
 * capability on by setting a resolver (how to find the case a request belongs to)
 * and its stages (the document sets a Project Officer can request).
 */
return [
    // Names this fund in the shared tables (same convention as boi_sla.app).
    'app' => env('BOI_APP', env('APP_NAME')),

    // Null keeps the tables on the fund's own database; a connection name (boi-api's)
    // puts every fund's document records in one place, told apart by `app`.
    'connection' => env('BOI_DOCUMENT_LIBRARY_CONNECTION'),

    // The workflow endpoint a submitted package is pushed to. Blank records the
    // submission without transmitting it (re-pushable from Nova).
    'submit_url' => env('BOI_DOCUMENT_LIBRARY_SUBMIT_URL'),

    // Resolves the case + customer a webhook request belongs to. A fund binds its own
    // implementation of Boi\Backend\DocumentLibrary\Contracts\DocumentOwnerResolver.
    'resolver' => null,

    // Where the customer's portal lives, used in notification links.
    'portal_url' => env('APP_URL'),

    // Register the inbound webhook routes (api middleware, X-Webhook-Key auth). The
    // applicant upload/submit routes are mounted by each fund behind its own auth.
    'register_webhook_routes' => true,
    'webhook_prefix' => 'api/webhooks/document-library',

    // The shared secret the workflow authenticates webhooks with.
    'webhook_key' => env('BOI_DOCUMENT_LIBRARY_WEBHOOK_KEY', env('SHAREPOINT_WEBHOOK_KEY')),

    // Verify TLS when transmitting a package to the workflow.
    'verify_ssl' => env('BOI_DOCUMENT_LIBRARY_VERIFY_SSL', true),

    // Default extensions a customer may upload when a document names none.
    'default_formats' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],

    // Per-stage default document sets. A webhook may name a stage (built from its set)
    // or spell out documents explicitly. Each fund defines its own stages, e.g.:
    //   'disbursement_docs' => [
    //       'label' => 'Disbursement Documentation',
    //       'documents' => [
    //           ['reference' => 'offer_letter', 'name' => 'Executed Offer Letter', 'mandatory' => true, 'permittedFormats' => 'pdf'],
    //       ],
    //   ],
    'stages' => [],
];
