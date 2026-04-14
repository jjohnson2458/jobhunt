<?php
return [
    'name'             => env('APP_NAME', 'Foot Traffic Analytics'),
    'env'              => env('APP_ENV', 'local'),
    'auth_bypass_ips'  => array_filter(array_map('trim', explode(',', env('AUTH_BYPASS_IPS', '127.0.0.1,::1')))),
    'anthropic_key'    => env('ANTHROPIC_API_KEY', ''),
    'claude_model'     => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
    'admin_email'      => env('ADMIN_EMAIL', 'email4johnson@gmail.com'),
    'messenger_project'=> env('MESSENGER_PROJECT', 'claude_jobhunt'),
    'gmail_address'    => env('GMAIL_ADDRESS', ''),
    'gmail_password'   => env('GMAIL_APP_PASSWORD', ''),
    'gmail_folder'     => env('GMAIL_FOLDER', 'INBOX'),
];
