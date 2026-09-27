<?php

if (!function_exists('mysqli_init')) {
    error_log('The mysqli PHP extension is not enabled.');
    http_response_code(500);
    die('Database driver is not available.');
}

$dbHost = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
$dbPort = (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306);
$dbUser = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$dbPass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';
$dbName = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'villa_eusebio_db';
$dbUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';

if ($dbUrl !== '') {
    $parts = parse_url($dbUrl);
    if (is_array($parts)) {
        $dbHost = $parts['host'] ?? $dbHost;
        $dbPort = isset($parts['port']) ? (int)$parts['port'] : $dbPort;
        $dbUser = isset($parts['user']) ? rawurldecode($parts['user']) : $dbUser;
        $dbPass = isset($parts['pass']) ? rawurldecode($parts['pass']) : $dbPass;
        $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : $dbName;
    }
}

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

if (!@mysqli_real_connect($conn, $dbHost, $dbUser, $dbPass, $dbName, $dbPort)) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    die('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');
