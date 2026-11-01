<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DE MERMA
require_once __DIR__ . '/../controladores/MermaController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $mermas (array), $usuarios (array), $productos (array),
   $motivos_sugeridos (array), $mensaje, $error,
   $id_editar, $fecha_editar, $cantidad_editar, $motivo_editar,
   $usuario_editar, $producto_editar */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD Merma</title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:1100px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
        button{margin-top:15px;padding:10px 20px;background:#c0392b;color:#fff;border:none;border-radius:4px;cursor:pointer}
        button:hover{background:#a93226}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:8px;border:1px solid #ddd;text-align:left}
        th{background:#34495e;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .ok{color:green;font-weight:bold}
        .err{color:red;font-weight:bold}
        a{text-decoration:none;margin-right:8px}
        .nav{margin-bottom:15px}
        .badge{background:#e67e22;color:#fff;padding:3px 8px;border-radius:10px;font-size:12px}
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="categorias.php">Categorías</a> |
        <a href="productos.php">Productos</a> |
        <a href="usuarios.php">Usuarios</a> |
        <a href="merma.php">Merma</a>
    </div>

    <h2>🗑️ CRUD Merma (Pérdidas de Productos)</h2>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- ================= FORMULARIO ================= -->
    <form method="POST" action="merma.php">
        <h3><?= $id_editar ? 'Editar Merma' : 'Registrar Nueva Merma' ?></h3>

        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= htmlspecialchars($id_editar) ?>">
        <?php endif; ?>

        <label>Fecha y hora:</label>
        <input type="datetime-local" name="fecha" required value="<?= htmlspecialchars($fecha_editar) ?>">

        <label>Producto afectado:</label>
        <select name="producto" required>
            <option value="">-- Seleccione producto --</option>
            <?php foreach ($productos as $p): ?>
                <option value="<?= $p['idProductos'] ?>"
                    <?= ($producto_editar == $p['idProductos']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['Nombre_Producto']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Cantidad perdida (unidades):</label>
        <input type="number" min="1" name="cantidad" required
               value="<?= htmlspecialchars($cantidad_editar) ?>">

        <label>Motivo:</label>
        <select name="motivo" required>
            <option value="">-- Seleccione motivo --</option>
            <?php foreach ($motivos_sugeridos as $mo): ?>
                <option value="<?= $mo ?>" <?= ($motivo_editar === $mo) ? 'selected' : '' ?>>
                    <?= $mo ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Responsable (usuario que registra):</label>
        <select name="usuario" required>
            <option value="">-- Seleccione usuario --</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= $u['idUsuarios'] ?>"
                    <?= ($usuario_editar == $u['idUsuarios']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['Nombre'] . ' ' . $u['Apellido']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"><?= $id_editar ? 'Actualizar Merma' : 'Registrar Merma' ?></button>
        <?php if ($id_editar): ?>
            <a href="merma.php" style="margin-left:10px;">Cancelar</a>
        <?php endif; ?>
    </form>

    <!-- ================= TABLA ================= -->
    <h3>Historial de Mermas</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Motivo</th>
                <th>Responsable</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($mermas) > 0): ?>
            <?php foreach ($mermas as $m): ?>
                <tr>
                    <td><?= $m['idMerma'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($m['Fecha'])) ?></td>
                    <td><?= htmlspecialchars($m['Nombre_Producto']) ?></td>
                    <td>
                        <span class="badge"><?= $m['Cantidad_perdida'] ?> u.</span>
                    </td>
                    <td><?= htmlspecialchars($m['Motivo']) ?></td>
                    <td><?= htmlspecialchars($m['Nombre'] . ' ' . $m['Apellido']) ?></td>
                    <td>
                        <a href="merma.php?action=editar&id=<?= $m['idMerma'] ?>">Editar</a>
                        <a href="merma.php?action=eliminar&id=<?= $m['idMerma'] ?>"
                           onclick="return confirm('¿Eliminar este registro de merma?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No hay mermas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
