<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$esEdicion = is_array($editar);

$valor = static function (string $campo, $defecto = '') use ($editar, $old) {
    if (is_array($old) && array_key_exists($campo, $old)) {
        return $old[$campo];
    }
    if (is_array($editar) && array_key_exists($campo, $editar)) {
        return $editar[$campo];
    }
    return $defecto;
};

require __DIR__ . '/../layout/header.php';
?>

<div class="grid-sidebar">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
                <p class="panel-sub">
                    <?= $esEdicion
                        ? 'Deja la contraseña vacía para conservar la actual.'
                        : 'La contraseña es obligatoria al crear la cuenta.' ?>
                </p>
            </div>
        </div>

        <?php if ($esEdicion): ?>
            <div class="meta-row">
                <span class="badge <?= (int) $editar['activo'] === 1 ? 'badge-ok' : 'badge-muted' ?>">
                    <?= (int) $editar['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                </span>
                <span class="badge <?= $editar['rol'] === 'ADMIN' ? 'badge-info' : 'badge-muted' ?>">
                    <?= e($editar['rol']) ?>
                </span>
                <small class="muted">Creado el <?= e(date('d/m/Y', strtotime((string) $editar['creado']))) ?></small>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('usuarios')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_usuario" value="<?= $esEdicion ? (int) $editar['id_usuario'] : 0 ?>">

            <label class="field field-full">
                <span>Nombre completo</span>
                <input type="text" name="nombre_completo" maxlength="100" required
                       value="<?= e($valor('nombre_completo')) ?>">
            </label>

            <label class="field">
                <span>Cédula</span>
                <input type="text" name="cedula" maxlength="15" required
                       placeholder="V-12345678"
                       value="<?= e($valor('cedula')) ?>">
            </label>

            <label class="field">
                <span>Usuario de acceso</span>
                <input type="text" name="usuario_login" maxlength="40" required
                       placeholder="ej. mcajera"
                       value="<?= e($valor('usuario_login')) ?>">
            </label>

            <label class="field">
                <span>Rol</span>
                <select name="rol">
                    <option value="CAJERO" <?= $valor('rol', 'CAJERO') === 'CAJERO' ? 'selected' : '' ?>>
                        CAJERO — vende y consulta
                    </option>
                    <option value="ADMIN" <?= $valor('rol', 'CAJERO') === 'ADMIN' ? 'selected' : '' ?>>
                        ADMIN — acceso total
                    </option>
                </select>
            </label>

            <label class="field">
                <span><?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></span>
                <input type="password" name="password" minlength="6" maxlength="72"
                       autocomplete="new-password"
                       <?= $esEdicion ? '' : 'required' ?>>
            </label>

            <div class="form-actions field-full">
                <button type="submit" class="btn btn-primary">
                    <?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>
                <?php if ($esEdicion): ?>
                    <a class="btn btn-ghost" href="<?= e(url('usuarios')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Listado</h2>
                <p class="panel-sub"><?= count($usuarios) ?> usuario(s)</p>
            </div>
        </div>

        <form method="GET" action="<?= e(url('usuarios')) ?>" class="filters">
            <label class="field field-grow">
                <span>Buscar</span>
                <input type="search" name="q" value="<?= e($busqueda) ?>"
                       placeholder="Nombre, usuario o cédula...">
            </label>
            <button type="submit" class="btn btn-ghost">Filtrar</button>
            <a class="btn btn-ghost" href="<?= e(url('usuarios')) ?>">Limpiar</a>
        </form>

        <?php if ($usuarios): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Acceso</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['nombre_completo']) ?></strong>
                                <small class="muted"><?= e($u['cedula']) ?></small>
                            </td>
                            <td><?= e($u['usuario_login']) ?></td>
                            <td>
                                <span class="badge <?= $u['rol'] === 'ADMIN' ? 'badge-info' : 'badge-muted' ?>">
                                    <?= e($u['rol']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int) $u['activo'] === 1 ? 'badge-ok' : 'badge-muted' ?>">
                                    <?= (int) $u['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="acciones">
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('usuarios', ['editar' => (int) $u['id_usuario']])) ?>">
                                    Editar
                                </a>

                                <?php if ((int) $u['id_usuario'] === current_user_id()): ?>
                                    <span class="badge badge-muted">Tu cuenta</span>
                                <?php else: ?>
                                    <form method="POST" action="<?= e(url('usuarios')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                        <input type="hidden" name="activo" value="<?= (int) $u['activo'] === 1 ? 0 : 1 ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= (int) $u['activo'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                data-confirm="<?= (int) $u['activo'] === 1 ? '¿Desactivar este usuario? No podrá iniciar sesión.' : '¿Reactivar este usuario?' ?>">
                                            <?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(url('usuarios')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                data-confirm="¿Eliminar este usuario de forma permanente? Si tiene facturas, tasas, mermas o compras no se podrá borrar.">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">⚙</span>
                <p>No hay usuarios con esa búsqueda.</p>
                <a class="btn btn-primary" href="<?= e(url('usuarios')) ?>">Crear el primero</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
