<?php
require_once __DIR__ . '/helpers.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $host = env_get('DB_HOST', '127.0.0.1');
        $name = env_get('DB_NAME', 'ywrloefq_gm_v3');
        $user = env_get('DB_USER', 'ywrloefq_gm_user');
        $pass = env_get('DB_PASS', '77453209**--Gm');
        $charset = env_get('DB_CHARSET', 'utf8mb4');

        // Rimuovi eventuali virgolette dalla password se presenti
        if ($pass && $pass[0] === '"' && substr($pass, -1) === '"') {
            $pass = substr($pass, 1, -1);
        }

        $dsn = "mysql:host=$host;dbname=$name;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("DB connection failed: " . $e->getMessage());
            http_response_code(500);
            exit('Database connection error');
        }
    }
    return $pdo;
}
