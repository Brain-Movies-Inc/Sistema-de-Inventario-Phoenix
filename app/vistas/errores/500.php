<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error interno · De Todito Reyes</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
</head>
<body>
<main class="main" style="max-width:720px;margin:80px auto;padding:0 20px">
    <div class="card">
        <h1>500 · Error interno</h1>
        <p>Ocurrió un problema inesperado. El detalle técnico quedó registrado en el servidor.</p>
        <p>
            <a class="btn btn-primary" href="<?= e(url(is_logged_in() ? 'panel' : 'login')) ?>">
                <?= is_logged_in() ? 'Volver al panel' : 'Volver al inicio de sesión' ?>
            </a>
        </p>
    </div>
</main>
</body>
</html>
