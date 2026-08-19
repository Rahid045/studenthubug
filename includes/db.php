<?php
function getDbConnection() {
    // Defaults keep the local XAMPP setup working; production uses environment variables.
    $host = getenv('DB_HOST') ?: '';
    $dbname = getenv('DB_NAME') ?: '';
    $username = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    $mysqli = new mysqli($host, $username, $password, $dbname, $port);

    if ($mysqli->connect_error) {
        die('Database connection failed: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

$connect = getDbConnection();
