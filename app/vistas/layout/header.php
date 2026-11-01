<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$usuario = current_user();
$flash   = flashes();
$activo  = $activo ?? '';
$titulo  = $titulo ?? 'De Todito Reyes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · De Todito Reyes</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
</head>
<body>
<button class="menu-toggle" type="button" aria-label="Abrir menú" data-menu-toggle>☰</button>

<div class="app">
    <aside class="sidebar" data-sidebar>
        <div class="brand">
            <span class="brand-mark">DR</span>
            <div>
                <strong>De Todito Reyes</strong>
                <small>Gestión y facturación</small>
            </div>
        </div>

        <nav class="menu">
            <a class="<?= $activo === 'inicio' ? 'activo' : '' ?>" href="<?= e(url('panel')) ?>">
                <span>▦</span> Panel
            </a>
            <a class="<?= $activo === 'productos' ? 'activo' : '' ?>" href="<?= e(url('productos')) ?>">
                <span>▣</span> Productos
            </a>
            <a class="<?= $activo === 'clientes' ? 'activo' : '' ?>" href="<?= e(url('clientes')) ?>">
                <span>☺</span> Clientes
            </a>
            <a class="<?= $activo === 'facturacion' ? 'activo' : '' ?>" href="<?= e(url('facturacion')) ?>">
                <span>▤</span> Facturación
            </a>
            <?php if (is_admin()): ?>
                <a class="<?= $activo === 'usuarios' ? 'activo' : '' ?>" href="<?= e(url('usuarios')) ?>">
                    <span>⚙</span> Usuarios
                </a>
            <?php endif; ?>
            <a class="menu-cta" href="<?= e(url('facturacion/nueva')) ?>">
                <span>＋</span> Nueva venta
            </a>
        </nav>

        <div class="sidebar-foot">
            <div class="user-chip">
                <span class="avatar"><?= e(strtoupper(substr((string) $usuario['nombre_completo'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($usuario['nombre_completo']) ?></strong>
                    <small><?= e($usuario['rol']) ?> · <?= e($usuario['usuario_login']) ?></small>
                </div>
            </div>

            <form method="POST" action="<?= e(url('logout')) ?>" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= e($titulo) ?></h1>
                <?php if (!empty($subtitulo)): ?>
                    <p><?= e($subtitulo) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-date"><?= e(date('d/m/Y')) ?></div>
        </header>

        <?php if ($flash): ?>
            <div class="alerts">
                <?php foreach ($flash as $item): ?>
                    <div class="alert alert-<?= e($item['tipo']) ?>">
                        <span><?= e($item['mensaje']) ?></span>
                        <button type="button" class="alert-close" data-alert-close aria-label="Cerrar">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
