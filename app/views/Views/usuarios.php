<?php
/* ============================================================
   CONEXIÓN CON EL CONTROLADOR
   ============================================================ */
// AQUÍ VA EL CONTROLADOR DE USUARIOS
require_once __DIR__ . '/../controladores/UsuariosController.php';

/* El controlador debe dejar listas estas variables para la vista:
   $usuarios (array), $mensaje, $error,
   $id_editar, $nombre_editar, $apellido_editar, $cedula_editar, $rol_editar */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRUD Usuarios</title>
    <style>
        body{font-family:Arial;background:#f4f4f4;margin:20px}
        .container{max-width:1000px;margin:auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 0 8px rgba(0,0,0,.1)}
        label{display:block;margin-top:10px;font-weight:bold}
        input,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
        button{margin-top:15px;padding:10px 20px;background:#8e44ad;color:#fff;border:none;border-radius:4px;cursor:pointer}
        button:hover{background:#71368a}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:8px;border:1px solid #ddd;text-align:left}
        th{background:#34495e;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .ok{color:green;font-weight:bold}
        .err{color:red;font-weight:bold}
        a{text-decoration:none;margin-right:8px}
        .nav{margin-bottom:15px}
        .badge{padding:3px 8px;border-radius:10px;color:#fff;font-size:12px}
        .Admin{background:#e74c3c}
        .Vendedor{background:#3498db}
        .Cliente{background:#27ae60}
        .nota{font-size:12px;color:#666;margin-top:3px}
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="categorias.php">Categorías</a> |
        <a href="productos.php">Productos</a> |
        <a href="usuarios.php">Usuarios</a>
    </div>

    <h2>👤 CRUD Usuarios</h2>

    <?php if ($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- ================= FORMULARIO ================= -->
    <form method="POST" action="usuarios.php">
        <h3><?= $id_editar ? 'Editar Usuario' : 'Nuevo Usuario' ?></h3>

        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= htmlspecialchars($id_editar) ?>">
        <?php endif; ?>

        <label>Nombre:</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($nombre_editar) ?>">

        <label>Apellido:</label>
        <input type="text" name="apellido" required value="<?= htmlspecialchars($apellido_editar) ?>">

        <label>Cédula:</label>
        <input type="text" name="cedula" required value="<?= htmlspecialchars($cedula_editar) ?>">

        <label>Rol:</label>
        <select name="rol" required>
            <option value="">-- Seleccione --</option>
            <?php foreach (['Admin','Vendedor','Cliente'] as $r): ?>
                <option value="<?= $r ?>" <?= ($rol_editar === $r) ? 'selected' : '' ?>>
                    <?= $r ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Contraseña:</label>
        <input type="password" name="password"
               placeholder="<?= $id_editar ? 'Dejar vacío para no cambiar' : 'Obligatoria' ?>"
               <?= $id_editar ? '' : 'required' ?>>
        <?php if ($id_editar): ?>
            <p class="nota">Deja este campo vacío si no quieres cambiar la contraseña.</p>
        <?php endif; ?>

        <button type="submit"><?= $id_editar ? 'Actualizar Usuario' : 'Guardar Usuario' ?></button>
        <?php if ($id_editar): ?>
            <a href="usuarios.php" style="margin-left:10px;">Cancelar</a>
        <?php endif; ?>
    </form>

    <!-- ================= TABLA ================= -->
    <h3>Lista de Usuarios</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Cédula</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($usuarios) > 0): ?>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['idUsuarios'] ?></td>
                    <td><?= htmlspecialchars($u['Nombre']) ?></td>
                    <td><?= htmlspecialchars($u['Apellido']) ?></td>
                    <td><?= htmlspecialchars($u['Cedula']) ?></td>
                    <td>
                        <span class="badge <?= htmlspecialchars($u['Rol']) ?>">
                            <?= htmlspecialchars($u['Rol']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="usuarios.php?action=editar&id=<?= $u['idUsuarios'] ?>">Editar</a>
                        <a href="usuarios.php?action=eliminar&id=<?= $u['idUsuarios'] ?>"
                           onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No hay usuarios registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
