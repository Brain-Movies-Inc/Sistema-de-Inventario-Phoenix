<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Conexión PDO
|--------------------------------------------------------------------------
| Ajusta estas constantes si tu MySQL usa otro host, usuario o contraseña.
| La base de datos la crea el archivo sql/de_todito_reyes.sql.
*/
const DB_HOST    = 'localhost';
const DB_NAME    = 'de_todito_reyes';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Venezuela (UTC-4) para que NOW() y las fechas coincidan con PHP.
        $pdo->exec("SET time_zone = '-04:00'");
    } catch (PDOException $e) {
        // El detalle técnico queda solo en el registro del servidor.
        error_log(sprintf(
            '[De Todito Reyes] Error de conexión a MySQL: %s en %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        http_response_code(500);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
        echo '<title>Error de conexión</title></head><body style="font-family:Arial;padding:40px">';
        echo '<h1>No se pudo conectar con la base de datos</h1>';
        echo '<p>Verifica que MySQL esté activo en XAMPP y que hayas importado ';
        echo '<strong>sql/de_todito_reyes.sql</strong> en phpMyAdmin.</p>';
        echo '<p>Si el problema continúa, contacta al administrador del sistema.</p>';
        echo '</body></html>';
        exit;
    }

    return $pdo;
}
