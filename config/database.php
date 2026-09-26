<?php
declare(strict_types=1);

$host = '127.0.0.1';
$dbname = 'devpanel';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('DevPanel database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('База данных временно недоступна. Запустите MySQL в панели XAMPP.');
}
