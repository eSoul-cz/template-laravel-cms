<?php

declare(strict_types=1);

return [
    /*
     * Path mode of the admin, a documented exception: the backend shares the domain of the static site under /admin
     * instead of living on its own subdomain (the default). Set it together with SESSION_PATH=/admin: Livewire's update
     * endpoint then moves under /admin (App\Infrastructure\Cms\AdminPathMode), where the session cookie is sent.
     */
    'admin_path_mode' => (bool) env('CMS_ADMIN_PATH_MODE', false),

    /*
     * Bearer token of GET /cms-api/v1/export. Read by the frontend build (Jenkins, local development),
     * read-only. An empty token disables the export (404).
     */
    'export_token' => env('CMS_EXPORT_TOKEN'),

    /*
     * Addresses (IP or CIDR, comma separated) that may call /cms-api: the build agent fetches the export and
     * Jenkins reports the publish result. The build uses the public URL, so a changed agent address breaks
     * publishing until it is added here. Empty = no restriction.
     */
    'api_allowed_ips' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CMS_API_ALLOWED_IPS', ''))))),

    /*
     * Disks. `media` holds the uploads of the admin (private, never served directly). `public` is the content
     * addressed copy that the export writes and the web server serves at /_cms/.
     */
    'media_root' => storage_path('app/cms-media'),
    'public_media_root' => env('CMS_PUBLIC_MEDIA_ROOT') ?: storage_path('app/cms-public'),

    /*
     * The Publish button starts a Jenkins job that builds and deploys the static site.
     */
    'jenkins' => [
        'job_url' => env('CMS_JENKINS_JOB_URL'),
        'user' => env('CMS_JENKINS_USER'),
        'api_token' => env('CMS_JENKINS_API_TOKEN'),
        // Token with which Jenkins reports the result from its `post {}` block.
        'callback_token' => env('CMS_JENKINS_CALLBACK_TOKEN'),
        // A publish without a result for longer than this many minutes is marked failed, so it cannot block
        // the next publish forever (the callback is best effort).
        'timeout_minutes' => env('CMS_PUBLISH_TIMEOUT', 30),
    ],

    /*
     * POST /forms/contact: the contact form of the static site (docs/forms.md). Pipeline: honeypot, rate limit,
     * reCAPTCHA v3, delivery (database record + e-mail), last-resort file.
     */
    'forms' => [
        /*
         * Sites (origins, comma separated, e.g. https://example.com) whose browsers may post to the endpoint and
         * that `return_to` may point at. The Origin header (or the origin of the Referer) must be listed.
         * Empty = every post is rejected (403) in production; any origin is accepted in a local environment.
         */
        'allowed_origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CMS_FORMS_ALLOWED_ORIGINS', ''))))),

        /*
         * E-mail recipients of a submission (comma separated). Empty = the contact e-mail of the site settings.
         * SMTP itself is the normal MAIL_* configuration.
         */
        'recipients' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CMS_FORMS_RECIPIENTS', ''))))),

        // Name of the hidden trap field of the form. A filled field gets a fake success and nothing is sent.
        'honeypot_field' => 'website',

        // At most `attempts` submissions per client address (known by its hash only) in `decay_seconds`. The
        // counters live in the cache store: use `file` or `database`, never a directory under the webroot.
        'rate_limit' => [
            'attempts' => 5,
            'decay_seconds' => 600,
        ],

        /*
         * reCAPTCHA v3 (action `contact`). Without a secret the check is skipped with a logged warning outside
         * production, and the endpoint answers 503 in production.
         */
        'recaptcha' => [
            'secret' => env('CMS_FORMS_RECAPTCHA_SECRET'),
            'min_score' => (float) (env('CMS_FORMS_RECAPTCHA_MIN_SCORE') ?: 0.5),
        ],

        // Last resort when neither the database nor the e-mail took a submission: one JSON line per submission,
        // YYYY-MM.jsonl, outside the webroot.
        'last_resort_path' => storage_path('app/private/forms-last-resort'),
    ],
];
