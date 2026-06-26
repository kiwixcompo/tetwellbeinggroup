<?php
// config/mail.php
return [
    'default' => 'smtp',
    'mailers' => [
        'smtp' => [
            'host'       => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'port'       => $_ENV['MAIL_PORT'] ?? 587,
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'username'   => $_ENV['MAIL_USERNAME'] ?? '',
            'password'   => $_ENV['MAIL_PASSWORD'] ?? '',
        ],
    ],
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@tetwellbeinggroup.com',
        'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'Tet Wellbeing Group',
    ]
];
