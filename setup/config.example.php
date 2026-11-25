<?php
// Copy to a directory outside the web root as config.php. Replace placeholders.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'eventease_db',
        'username' => 'REPLACE_WITH_DEDICATED_DB_USER',
        'password' => 'REPLACE_WITH_PRIVATE_DB_PASSWORD',
    ],
];
