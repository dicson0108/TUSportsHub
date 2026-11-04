<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/setup.php';

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tu_sportshub');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        initialize_database($connection, DB_NAME);
        $pdo = $connection;
    }
    return $pdo;
}
