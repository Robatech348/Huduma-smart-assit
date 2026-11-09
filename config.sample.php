<?php
/**
 * Huduma SmartAssist configuration.
 * Copy this file to config.php and adjust the values for your environment.
 * Never commit config.php with real secrets.
 */
return [
    'app' => [
        'name'     => 'Huduma SmartAssist',
        // Full public URL of the app, without trailing slash. Used in email links.
        'url'      => 'http://localhost/huduma%20smart%20assist',
        'timezone' => 'Africa/Nairobi',
        'debug'    => true,   // set false in production
        // How many days ahead citizens can book.
        'booking_window_days' => 21,
        // Bookings close this many minutes before a slot starts.
        'booking_cutoff_minutes' => 60,
        'support_phone' => '+254 20 000 0000',
        'support_email' => 'help@smartassist.local',
        // Show a notice that service details are sample content (turn off once agencies approve content).
        'sample_content' => true,
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'huduma_smartassist',
        'user'     => 'root',
        'password' => '',
    ],

    'mail' => [
        // 'log'  = store in the notifications outbox and write .eml files to storage/mail (no delivery)
        // 'mail' = PHP mail() (configure sendmail in php.ini)
        // 'smtp' = built-in SMTP client (works with Gmail app passwords, Mailtrap, SendGrid, etc.)
        'driver'      => 'log',
        'from_email'  => 'no-reply@smartassist.local',
        'from_name'   => 'Huduma SmartAssist',
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'encryption' => 'tls',   // 'tls' (STARTTLS), 'ssl', or '' for none
            'username'   => '',
            'password'   => '',
            'timeout'    => 15,
        ],
    ],

    'assistant' => [
        // AI answers need the Anthropic PHP SDK (composer require anthropic-ai/sdk)
        // and an API key. Without them the assistant answers from the approved
        // service catalogue only.
        'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
        'model'             => 'claude-opus-5-5',
        'effort'            => 'low',
        'max_tokens'        => 4000,
        'log_questions'     => true, // stores questions (no personal data fields) to improve content
    ],
];
