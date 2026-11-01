<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DE PRODUCTOS
require_once __DIR__ . '/../controladores/ProductosController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $productos (array), $categorias (array), $mensaje, $error,
   $id_editar, $nombre_editar, $precio_editar, $stock_editar, $categoria_editar */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD Productos</title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:1000px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
        button{margin-top:15px;padding:10px 20px;background:#27ae60;color:#fff;border:none;border-radius:4px;cursor:pointer}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:8px;border:1px solid #ddd;text-align:left}
        th{background:#34495e;color:#fff}
        .ok{color:green;font-weight:bold}.err{color:red;font-weight:bold}
        a{text-decoration:none;margin-right:8px}
        .nav{margin-bottom:15px}
    </style>
</head>
<body>
<div class="container">
    <div class="nav"><a href="categorias.php">Categorías</a> | <a href="productos.php">Productos</a></div>
    <h2>🥗 CRUD Productos</h2>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST" action="productos.php">
        <h3><?= $id_editar ? 'Editar Producto' : 'Nuevo Producto' ?></h3>
        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= htmlspecialchars($id_editar) ?>">
        <?php endif; ?>

        <label>Nombre del producto:</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($nombre_editar) ?>">

        <label>Precio:</label>
        <input type="number" step="0.01" min="0" name="precio" required value="<?= htmlspecialchars($precio_editar) ?>">

        <label>Stock:</label>
        <input type="number" min="0" name="stock" required value="<?= htmlspecialchars($stock_editar) ?>">

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="">-- Seleccione --</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['idCategoria'] ?>"
                    <?= ($categoria_editar == $c['idCategoria']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['Nombre_Categoria']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"><?= $id_editar ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($id_editar): ?><a href="productos.php">Cancelar</a><?php endif; ?>
    </form>

    <h3>Lista de Productos</h3>
    <table>
        <tr>
            <th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Categoría</th><th>Acciones</th>
        </tr>
        <?php foreach ($productos as $p): ?>
            <tr>
                <td><?= $p['idProductos'] ?></td>
                <td><strong><?= htmlspecialchars($p['Nombre_Producto']) ?></strong></td>
                <td>$<?= number_format($p['Precio_Actual'], 2) ?></td>
                <td><?= $p['Stock'] ?></td>
                <td><?= htmlspecialchars($p['Nombre_Categoria']) ?></td>
                <td>
                    <a href="productos.php?action=editar&id=<?= $p['idProductos'] ?>">Editar</a>
                    <a href="productos.php?action=eliminar&id=<?= $p['idProductos'] ?>"
                       onclick="return confirm('¿Eliminar producto?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
