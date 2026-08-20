<?php
function loadEnvironmentFile($filePath) {
    if (!is_readable($filePath)) {
        return;
    }

    foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

loadEnvironmentFile(dirname(__DIR__) . '/.env');

function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = (int) (getenv('DB_PORT') ?: 3306);
    $dbname = getenv('DB_NAME') ?: 'educonnect';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $mysqli = new mysqli($host, $username, $password, $dbname, $port);

    if ($mysqli->connect_error) {
        die('Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD in .env.');
    }

    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

$connect = getDbConnection();