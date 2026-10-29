<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DE CATEGORÍAS
require_once __DIR__ . '/../controladores/CategoriasController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $categorias (array), $mensaje, $error,
   $id_editar, $nombre_editar */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD Categorías</title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:700px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
        button{margin-top:15px;padding:10px 20px;background:#2980b9;color:#fff;border:none;border-radius:4px;cursor:pointer}
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
    <h2>🗂️ CRUD Categorías</h2>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST" action="categorias.php">
        <h3><?= $id_editar ? 'Editar Categoría' : 'Nueva Categoría' ?></h3>
        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= htmlspecialchars($id_editar) ?>">
        <?php endif; ?>
        <label>Nombre de la categoría:</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($nombre_editar) ?>">
        <button type="submit"><?= $id_editar ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($id_editar): ?><a href="categorias.php">Cancelar</a><?php endif; ?>
    </form>

    <h3>Lista</h3>
    <table>
        <tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr>
        <?php foreach ($categorias as $c): ?>
            <tr>
                <td><?= $c['idCategoria'] ?></td>
                <td><?= htmlspecialchars($c['Nombre_Categoria']) ?></td>
                <td>
                    <a href="categorias.php?action=editar&id=<?= $c['idCategoria'] ?>">Editar</a>
                    <a href="categorias.php?action=eliminar&id=<?= $c['idCategoria'] ?>"
                       onclick="return confirm('¿Eliminar categoría?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
