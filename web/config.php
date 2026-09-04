<?php
// Путь к файлу: /opt/ishimura/web/config.php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'ishimura');
define('DB_USER', 'ishimura_admin');
define('DB_PASS', 'Nh0uk0lbn@');

if (!function_exists('getDBConnection')) {
function getDBConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Web DB Connection failed: ' . $e->getMessage()]);
        exit;
    }
}

}