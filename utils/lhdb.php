<?php
// ============================================================
//  utils/lhdb.php  –  Centralized DB Connection (PDO)
//  Requirement: Centralized db_config using PDO, try-catch
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'listahub_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    $dsn = "mysql:host=" . DB_HOST
         . ";dbname="    . DB_NAME
         . ";charset="   . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("[ListaHub DB Error] " . $e->getMessage());
        http_response_code(500);
        die("A database error occurred. Please try again later.");
    }
}
?>