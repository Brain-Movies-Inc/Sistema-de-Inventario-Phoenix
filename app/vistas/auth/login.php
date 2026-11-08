<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$flash         = flashes();
$login         = $login ?? '';
$cerrado       = (bool) ($cerrado ?? false);
$reiniciado    = (bool) ($reiniciado ?? false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · De Todito Reyes</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
</head>
<body class="login-body">
<main class="login-page">
    <section class="login-card">
        <div class="login-brand">
            <span class="brand-mark">DR</span>
            <div>
                <strong>De Todito Reyes</strong>
                <small>Gestión y facturación</small>
            </div>
        </div>

        <h1>Iniciar sesión</h1>
        <p class="login-sub">Ingresa tus credenciales para continuar.</p>

        <?php if ($reiniciado): ?>
            <div class="alert alert-warn">
                <span>
                    Por seguridad, cerramos la sesión anterior.
                    Ingresa tu contraseña para continuar.
                </span>
            </div>
        <?php endif; ?>

        <?php if ($cerrado): ?>
            <div class="alert alert-ok">
                <span>Cerraste sesión correctamente.</span>
            </div>
        <?php endif; ?>

        <?php foreach ($flash as $item): ?>
            <div class="alert alert-<?= e($item['tipo']) ?>">
                <span><?= e($item['mensaje']) ?></span>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="<?= e(url('login')) ?>" class="login-form">
            <?= csrf_field() ?>

            <label class="field">
                <span>Usuario</span>
                <input type="text" name="usuario_login" maxlength="40" required autofocus
                       autocomplete="username" value="<?= e($login) ?>">
            </label>

            <label class="field">
                <span>Contraseña</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>

            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>

        <p class="login-foot">Acceso restringido · <?= e(date('Y')) ?></p>
    </section>
</main>
</body>
</html>
