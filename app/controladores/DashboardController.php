<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/DashboardModel.php';

$modelo = new DashboardModel();

$resumen         = $modelo->resumen();
$tasa            = $modelo->tasaVigente();
$stockBajo       = $modelo->stockBajo(8);
$ultimasFacturas = $modelo->ultimasFacturas(6);
$topProductos    = $modelo->topProductos(5);

$titulo    = 'Panel principal';
$subtitulo = 'Resumen operativo del negocio';
$activo    = 'inicio';

require __DIR__ . '/../vistas/dashboard/index.php';
