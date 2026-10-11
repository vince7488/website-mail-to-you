<?php

declare(strict_types=1);

return [
    'application' => [
        // >>> ENTER YOUR APP ENVIRONMENT AND BASE URL HERE <<<
        'environment' => 'production',
        'debug' => false,
        'public_url' => 'https://forms.example.com',
        'request_max_bytes' => 32768,
        'storage_path' => dirname(__DIR__) . '/storage',
    ],

    'smtp' => [
        // >>> ENTER YOUR CPANEL SMTP SERVER DETAILS HERE <<<
        'host' => 'mail.example.com',
        // Common ports: 465 (with 'smtps') or 587 (with 'starttls')
        'port' => 465,

        // Valid values: "smtps", "starttls", or "none".
        'encryption' => 'smtps',

        'authenticate' => true,
        // >>> ENTER YOUR CPANEL EMAIL USERNAME AND PASSWORD HERE <<<
        'username' => 'website@example.com',
        'password' => 'replace-with-real-password',

        // null lets PHPMailer negotiate.
        // Other supported values: "LOGIN", "PLAIN", "CRAM-MD5".
        'auth_type' => null,

        'timeout_seconds' => 15,

        'tls' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,

            // Optional custom certificate-authority bundle.
            'ca_file' => null,

            // Optional expected certificate hostname.
            'peer_name' => null,

            // Optional mutual-TLS client credentials.
            'client_certificate' => null,
            'client_private_key' => null,
            'client_private_key_passphrase' => null,
        ],
    ],

    'mail' => [
        // >>> ENTER THE OUTGOING SENDER NAME AND EMAIL ADDRESS HERE <<<
        'from' => [
            'address' => 'website@example.com',
            'name' => 'Example Website',
        ],

        // Normally the same address as "from".
        'return_path' => 'website@example.com',
    ],

    'forms' => [
        'contact' => [
            // >>> ENTER WHO SHOULD RECEIVE NOTIFICATIONS FROM THIS FORM HERE <<<
            'recipients' => [
                [
                    'address' => 'team@example.com',
                    'name' => 'Example Team',
                ],
            ],

            'cc' => [],
            'bcc' => [],

            // >>> ENTER YOUR DESIRED EMAIL SUBJECT LINE HERE <<<
            'email_subject' => 'New website inquiry',

            // The validated visitor address becomes Reply-To.
            'reply_to_field' => 'email',

            // >>> ENTER ALL DOMAINS / ORIGINS ALLOWED TO SUBMIT THIS FORM HERE <<<
            // e.g. 'https://yourdomain.com', 'http://localhost:3000'
            'allowed_origins' => [
                'https://example.com',
                'https://www.example.com',
                'http://localhost:3000',
            ],

            // Used by non-JavaScript HTML forms.
            'success_redirect_url' =>
                'https://example.com/contact/success/',
            'error_redirect_url' =>
                'https://example.com/contact/error/',

            'honeypot_field' => 'company_website',

            'timing' => [
                'field' => '_started_at',
                'required' => false,
                'minimum_seconds' => 3,
                'maximum_seconds' => 7200,
            ],

            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 2,
                    'maximum_length' => 120,
                ],

                'email' => [
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'maximum_length' => 254,
                ],

                'topic' => [
                    'label' => 'Topic',
                    'type' => 'string',
                    'required' => false,
                    'maximum_length' => 100,
                ],

                'message' => [
                    'label' => 'Message',
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 10,
                    'maximum_length' => 5000,
                ],
            ],
        ],
    ],

    'rate_limit' => [
        'enabled' => true,
        'maximum_attempts' => 5,
        'window_seconds' => 900,

        // >>> ENTER A UNIQUE RANDOM STRING (AT LEAST 32 CHARACTERS) HERE FOR IP HASHING <<<
        'ip_hash_key' => 'replace-with-at-least-32-random-bytes',

        // Do not trust X-Forwarded-For unless the actual proxy is listed.
        'trusted_proxies' => [],
    ],

    'logging' => [
        'enabled' => true,
        'retain_days' => 30,

        // Never log form contents, email addresses, or SMTP credentials.
        'log_submission_contents' => false,
    ],
];
