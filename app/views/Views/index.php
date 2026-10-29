<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DEL PANEL PRINCIPAL
require_once __DIR__ . '/../controladores/IndexController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $totalCategorias, $totalProductos, $totalUsuarios, $totalFacturas, $totalMermas,
   $valorInventario, $totalVendido, $totalPerdidas,
   $productosStockBajo (array), $ultimasFacturas (array), $topProductos (array) */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Sistema de Inventario | Panel Principal </title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eef2f5;
            color: #2c3e50;
            padding: 20px;
        }
        header {
            background: linear-gradient(135deg, #2c3e50, #4ca1af);
            color: #fff;
            padding: 25px 30px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        header h1 { font-size: 26px; margin-bottom: 5px; }
        header p  { opacity: .9; font-size: 14px; }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }
        nav a {
            padding: 10px 18px;
            background: #fff;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,.08);
            transition: all .2s;
        }
        nav a:hover {
            background: #2c3e50;
            color: #fff;
            transform: translateY(-2px);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            border-left: 5px solid #3498db;
        }
        .card.green  { border-left-color: #27ae60; }
        .card.orange { border-left-color: #e67e22; }
        .card.red    { border-left-color: #c0392b; }
        .card.purple { border-left-color: #8e44ad; }
        .card h3 { font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: .5px; }
        .card .valor { font-size: 26px; font-weight: bold; margin-top: 8px; color: #2c3e50; }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 800px) { .grid-2 { grid-template-columns: 1fr; } }

        .panel {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            margin-bottom: 20px;
        }
        .panel h2 {
            font-size: 17px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #ecf0f1;
            color: #2c3e50;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ecf0f1; font-size: 14px; }
        th { color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        tr:hover { background: #f8f9fa; }

        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-ok   { background: #d4edda; color: #155724; }
        .badge-warn { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        .vacio { text-align: center; color: #95a5a6; padding: 15px; font-style: italic; }
        footer { text-align: center; color: #95a5a6; font-size: 13px; margin-top: 30px; }
    </style>
</head>
<body>

<header>
    <h1> 🥗 Sistema de Administracion y facturacion </h1>
    <p>Panel principal  · Base de datos <strong>NuevaVersion</strong></p>
</header>

<nav>
    <a href="index.php">🏠 Inicio</a>
    <a href="categorias.php">🗂️ Categorías</a>
    <a href="productos.php">📦 Productos</a>
    <a href="usuarios.php">👤 Usuarios</a>
    <a href="facturas.php">🧾 Facturas</a>
    <a href="merma.php">🗑️ Mermas</a>
</nav>

<!-- ============ TARJETAS DE ESTADÍSTICAS ============ -->
<section class="cards">
    <div class="card">
        <h3>Categorías</h3>
        <div class="valor"><?= $totalCategorias ?></div>
    </div>
    <div class="card green">
        <h3>Productos</h3>
        <div class="valor"><?= $totalProductos ?></div>
    </div>
    <div class="card purple">
        <h3>Usuarios</h3>
        <div class="valor"><?= $totalUsuarios ?></div>
    </div>
    <div class="card orange">
        <h3>Facturas</h3>
        <div class="valor"><?= $totalFacturas ?></div>
    </div>
    <div class="card red">
        <h3>Mermas</h3>
        <div class="valor"><?= $totalMermas ?></div>
    </div>
</section>

<section class="cards">
    <div class="card green">
        <h3>Valor del Inventario</h3>
        <div class="valor">$<?= number_format($valorInventario, 2) ?></div>
    </div>
    <div class="card">
        <h3>Total Vendido</h3>
        <div class="valor">$<?= number_format($totalVendido, 2) ?></div>
    </div>
    <div class="card red">
        <h3>Pérdidas por Merma</h3>
        <div class="valor">$<?= number_format($totalPerdidas, 2) ?></div>
    </div>
</section>

<div class="grid-2">

    <!-- ============ STOCK BAJO ============ -->
    <div class="panel">
        <h2>⚠️ Productos con Stock Bajo (≤ 5)</h2>
        <?php if (count($productosStockBajo) > 0): ?>
            <table>
                <thead>
                    <tr><th>Producto</th><th>Categoría</th><th>Stock</th></tr>
                </thead>
                <tbody>
                <?php foreach ($productosStockBajo as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['Nombre_Producto']) ?></td>
                        <td><?= htmlspecialchars($p['Nombre_Categoria']) ?></td>
                        <td>
                            <?php if ($p['Stock'] == 0): ?>
                                <span class="badge badge-danger">Sin stock</span>
                            <?php else: ?>
                                <span class="badge badge-warn"><?= $p['Stock'] ?> und.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="vacio">✅ Todos los productos tienen stock suficiente.</p>
        <?php endif; ?>
    </div>

    <!-- ============ TOP PRODUCTOS ============ -->
    <div class="panel">
        <h2>🏆 Top 5 Productos Más Vendidos</h2>
        <?php if (count($topProductos) > 0): ?>
            <table>
                <thead>
                    <tr><th>#</th><th>Producto</th><th>Unidades</th></tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($topProductos as $t): ?>
                    <tr>
                        <td><strong><?= $i++ ?></strong></td>
                        <td><?= htmlspecialchars($t['Nombre_Producto']) ?></td>
                        <td><span class="badge badge-ok"><?= $t['TotalVendido'] ?> und.</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="vacio">Aún no hay ventas registradas.</p>
        <?php endif; ?>
    </div>

</div>

<!-- ============ ÚLTIMAS FACTURAS ============ -->
<div class="panel">
    <h2>🧾 Últimas 5 Facturas</h2>
    <?php if (count($ultimasFacturas) > 0): ?>
        <table>
            <thead>
                <tr><th>N° Factura</th><th>Fecha</th><th>Cliente</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ultimasFacturas as $f): ?>
                <tr>
                    <td>#<?= $f['idFacturas'] ?></td>
                    <td><?= htmlspecialchars($f['Fecha']) ?></td>
                    <td><?= htmlspecialchars($f['Cliente']) ?></td>
                    <td><strong>$<?= number_format($f['Total_Factura'], 2) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="vacio">Aún no se han generado facturas.</p>
    <?php endif; ?>
</div>

<footer>
    Sistema de Inventario &copy; <?= date('Y') ?> · Práctica CRUD con PDO
</footer>

</body>
</html>
