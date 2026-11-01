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
    <title>Página no encontrada · De Todito Reyes</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
</head>
<body>
<main class="main" style="max-width:720px;margin:80px auto;padding:0 20px">
    <div class="card">
        <h1>404 · Página no encontrada</h1>
        <p>La dirección que buscas no existe o fue movida.</p>
        <p>
            <a class="btn btn-primary" href="<?= e(url('panel')) ?>">Volver al panel</a>
        </p>
    </div>
</main>
</body>
</html>
