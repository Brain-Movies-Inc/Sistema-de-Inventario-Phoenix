<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$flash = flashes();
$login = $login ?? '';
$error = $error ?? null;
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

        <?php if (is_logged_in()): ?>
            <div class="alert alert-ok">
                <span>
                    Ya tienes una sesión activa como <?= e(current_user()['nombre_completo']) ?>.
                    <a href="<?= e(url('panel')) ?>">Ir al panel</a>
                </span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['cerrado'])): ?>
            <div class="alert alert-ok">
                <span>Cerraste sesión correctamente.</span>
            </div>
        <?php endif; ?>

        <?php foreach ($flash as $item): ?>
            <div class="alert alert-<?= e($item['tipo']) ?>">
                <span><?= e($item['mensaje']) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

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
