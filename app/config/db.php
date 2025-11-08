<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=db;port=5432;dbname=' . ($_ENV['POSTGRES_DB'] ?? 'loans'),
    'username' => $_ENV['POSTGRES_USER'] ?? 'user',
    'password' => $_ENV['POSTGRES_PASSWORD'] ?? 'password',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
