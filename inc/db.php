<?php
function getPDO(): PDO
{
    // Update these settings for your environment
    $host = 'localhost';
    $port = 5432;
    $db   = 'db_dwh3project';
    $user = 'postgres';
    $pass = 'admin';

    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Simple error page
        http_response_code(500);
        echo "<h1>Database connection error</h1>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit;
    }
}
