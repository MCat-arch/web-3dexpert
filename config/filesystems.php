<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // The private disk must not have a public /storage route. Laravel
            // would otherwise register a signed-URL endpoint that answers 403
            // for direct requests in non-production environments and 404 only
            // in production. No feature uses temporary URLs on this disk.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/media'),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Applicant CVs and portfolios (P6, decision D3).
         *
         * Separate from 'media' because the two have different lifetimes and
         * different audiences: media is public marketing content served to
         * everyone, these are personal data served only to hiring staff and
         * purged on a retention schedule (D11).
         *
         * Under storage_path, so outside the document root. The legacy site's
         * RCE came from uploads landing somewhere the web server would execute;
         * nothing here is reachable without passing through
         * ApplicationDocumentController and its authorisation check.
         *
         * Swapping this to S3 later is a config change, not a rewrite -- which
         * is why job_application_documents records its disk per row.
         */
        'applications' => [
            'driver' => 'local',
            'root' => storage_path('app/private/applications'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Student assignment uploads. Deliberately outside the web root and
         * separate from the public media disk; every read goes through
         * AssignmentDownloadController after a policy check.
         */
        'submissions' => [
            'driver' => 'local',
            'root' => storage_path('app/submissions'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Manual payment proofs (bank transfer receipts).
         *
         * Private like 'applications': the file is customer PII (account
         * names, amounts) and must never be reachable from a public URL. Every
         * read goes through PaymentProofController after an owner/staff check.
         */
        'proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/payment-proofs'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Project tracker attachments (progress photos, final deliverables).
         *
         * Private: the tracker is public, but the files are customer
         * deliverables. They are only ever streamed through the signed route
         * in ProjectAttachmentController, which re-checks that the project is
         * still trackable.
         */
        'attachments' => [
            'driver' => 'local',
            'root' => storage_path('app/private/project-attachments'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
