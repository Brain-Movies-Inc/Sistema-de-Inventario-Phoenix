<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DE FACTURAS
require_once __DIR__ . '/../controladores/FacturasController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $facturas (array), $usuarios (array), $mensaje, $error */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD Facturas</title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:1100px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
        button{margin-top:15px;padding:10px 20px;background:#2980b9;color:#fff;border:none;border-radius:4px;cursor:pointer}
        button:hover{background:#21618c}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:8px;border:1px solid #ddd;text-align:left}
        th{background:#34495e;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .ok{color:green;font-weight:bold}
        .err{color:red;font-weight:bold}
        a{text-decoration:none;margin-right:8px}
        .nav{margin-bottom:15px}
        .total{font-weight:bold;color:#27ae60}
        .btn-ver{color:#8e44ad}
        .btn-del{color:#c0392b}
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="categorias.php">Categorías</a> |
        <a href="productos.php">Productos</a> |
        <a href="usuarios.php">Usuarios</a> |
        <a href="merma.php">Merma</a> |
        <a href="facturas.php">Facturas</a>
    </div>

    <h2>🧾 Facturas</h2>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- Crear factura -->
    <form method="POST" action="facturas.php">
        <h3>➕ Nueva Factura</h3>
        <label>Fecha:</label>
        <input type="datetime-local" name="fecha" required value="<?= date('Y-m-d\TH:i') ?>">

        <label>Cliente / Usuario:</label>
        <select name="usuario" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['idUsuarios'] ?>">
                    <?= htmlspecialchars($u['Nombre'] . ' ' . $u['Apellido']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Crear Factura y agregar productos</button>
    </form>

    <h3>Lista de Facturas</h3>
    <table>
        <thead>
            <tr>
                <th># Factura</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th># Líneas</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($facturas) > 0): ?>
            <?php foreach ($facturas as $f): ?>
                <tr>
                    <td>#<?= $f['idFacturas'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($f['Fecha'])) ?></td>
                    <td><?= htmlspecialchars($f['Nombre'] . ' ' . $f['Apellido']) ?></td>
                    <td><?= $f['num_lineas'] ?></td>
                    <td class="total">$<?= number_format($f['Total_Factura'], 2) ?></td>
                    <td>
                        <a class="btn-ver" href="detalle_factura.php?factura_id=<?= $f['idFacturas'] ?>">
                            Ver / Editar Detalle
                        </a>
                        <a class="btn-del" href="facturas.php?action=eliminar&id=<?= $f['idFacturas'] ?>"
                           onclick="return confirm('¿Eliminar factura y todos sus detalles?')">
                            Eliminar
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No hay facturas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
