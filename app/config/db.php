<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => "pgsql:host=db;port=5432;dbname={$_ENV['POSTGRES_DB']}",
    'username' => $_ENV['POSTGRES_USER'],
    'password' => $_ENV['POSTGRES_PASSWORD'],
    'charset' => 'utf8',
];
