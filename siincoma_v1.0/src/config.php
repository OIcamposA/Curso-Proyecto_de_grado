<?php
// Credenciales de MySQL. Edítelas o defina las variables de entorno DB_HOST, DB_NAME, DB_USER y DB_PASSWORD.
return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'siincoma',
    'db_user' => getenv('DB_USER') ?: 'siincoma',
    'db_pass' => getenv('DB_PASSWORD') ?: '4dm1nSIINCOMA',
];
