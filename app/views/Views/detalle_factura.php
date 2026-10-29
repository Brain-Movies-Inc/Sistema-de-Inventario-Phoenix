<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DEL DETALLE DE FACTURA
require_once __DIR__ . '/../controladores/DetalleFacturaController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $factura_id, $factura (array), $lineas (array), $productos (array),
   $mensaje, $error */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Factura #<?= $factura_id ?></title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:1100px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
        button{margin-top:15px;padding:10px 20px;background:#27ae60;color:#fff;border:none;border-radius:4px;cursor:pointer}
        button:hover{background:#1e8449}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:8px;border:1px solid #ddd;text-align:left}
        th{background:#34495e;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .ok{color:green;font-weight:bold}
        .err{color:red;font-weight:bold}
        a{text-decoration:none;margin-right:8px}
        .nav{margin-bottom:15px}
        .total-final{font-size:18px;font-weight:bold;color:#27ae60;text-align:right;margin-top:15px}
        .card{background:#ecf0f1;padding:15px;border-radius:6px;margin-bottom:20px}
        .info b{color:#2c3e50}
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="facturas.php">← Volver a Facturas</a>
    </div>

    <h2>🧾 Factura #<?= $factura_id ?></h2>

    <div class="card info">
        <p><b>Fecha:</b> <?= date('d/m/Y H:i', strtotime($factura['Fecha'])) ?></p>
        <p><b>Cliente:</b> <?= htmlspecialchars($factura['Nombre'] . ' ' . $factura['Apellido']) ?></p>
        <p><b>Total actual:</b> $<?= number_format($factura['Total_Factura'], 2) ?></p>
    </div>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- Agregar línea -->
    <form method="POST" action="detalle_factura.php">
        <input type="hidden" name="action" value="agregar">
        <input type="hidden" name="factura_id" value="<?= $factura_id ?>">

        <h3>➕ Agregar producto a la factura</h3>

        <label>Producto:</label>
        <select name="producto" required>
            <option value="">-- Seleccione producto --</option>
            <?php foreach ($productos as $p): ?>
                <option value="<?= $p['idProductos'] ?>">
                    <?= htmlspecialchars($p['Nombre_Producto']) ?>
                    — $<?= number_format($p['Precio_Actual'], 2) ?>
                    (stock: <?= $p['Stock'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Cantidad:</label>
        <input type="number" name="cantidad" min="1" required value="1">

        <button type="submit">Agregar al detalle</button>
    </form>

    <!-- Tabla de líneas -->
    <h3>📋 Líneas de la factura</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Subtotal</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($lineas) > 0): ?>
            <?php foreach ($lineas as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($l['Nombre_Producto']) ?></td>
                    <td><?= $l['Cantidad'] ?></td>
                    <td>$<?= number_format($l['Precio_Unitario'], 2) ?></td>
                    <td>$<?= number_format($l['Subtotal'], 2) ?></td>
                    <td>
                        <a href="detalle_factura.php?factura_id=<?= $factura_id ?>&action=eliminar_linea&linea_id=<?= $l['idDetalle_Factura'] ?>"
                           onclick="return confirm('¿Eliminar esta línea? Se devolverá el stock al producto.')">
                           Eliminar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr style="background:#dfe6e9;">
                <td colspan="4" style="text-align:right;font-weight:bold;">TOTAL:</td>
                <td colspan="2" style="font-weight:bold;color:#27ae60;">
                    $<?= number_format(array_sum(array_column($lineas, 'Subtotal')), 2) ?>
                </td>
            </tr>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">Aún no hay productos en esta factura.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
