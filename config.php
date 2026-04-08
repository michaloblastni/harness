<?php

return [
    'version' => getenv('HARNESS_VERSION') ?: '0.1.0',

    'support_email' => getenv('HARNESS_SUPPORT_EMAIL') ?: 'michaloblastni@gmail.com',

    'db' => [
        'dsn' => getenv('HARNESS_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=harness;charset=utf8mb4',
        'user' => getenv('HARNESS_DB_USER') ?: 'root',
        'pass' => getenv('HARNESS_DB_PASS') ?: '',
    ],
];
