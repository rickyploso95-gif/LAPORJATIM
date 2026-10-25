<?php
// config/database.php - Dynamic Connection for Render (Supabase PostgreSQL) & Local MySQL

$host     = getenv('DB_HOST') ?: 'db.xxxxxxxx.supabase.co';
$port     = getenv('DB_PORT') ?: '5432';
$dbname   = getenv('DB_NAME') ?: 'postgres';
$user     = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: 'your-password';
$db_driver = getenv('DB_DRIVER') ?: 'pgsql';

try {
    if ($db_driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
