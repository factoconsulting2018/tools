<?php

// Configuración de MySQL para Docker
// Para usar esta configuración, renombra este archivo a db.php
// o actualiza config/web.php para usar esta configuración

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=db;dbname=facto_en_la_nube',
    'username' => 'facto_user',
    'password' => 'facto_password',
    'charset' => 'utf8mb4',
];

