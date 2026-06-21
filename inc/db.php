<?php

function getPDO(): PDO
{
    return getPDOConnection('db_dwhfinalrevisi2');
}

function getPDOOltp(): PDO
{
    return getPDOConnection('tugas_dw1');
}

function getPDOConnection(string $db): PDO
{
    $host = getenv('PGHOST');
    $port = getenv('PGPORT');
    $user = getenv('PGUSER');
    $pass = getenv('PGPASSWORD');
    $db   = getenv('PGDATABASE');

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
        echo "<p>Gagal konek ke database <strong>{$db}</strong>.</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        exit;
    }
}
