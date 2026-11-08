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

<?php if (!is_admin()): ?>
    <div class="alert alert-warn">
        <span>Puedes registrar clientes nuevos. Para editar o eliminar, solicita ayuda a un administrador.</span>
    </div>
<?php endif; ?>

<div class="grid-sidebar">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
                <p class="panel-sub">El RIF o cédula debe ser único.</p>
            </div>
        </div>

        <form method="POST" action="<?= e(url('clientes')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_cliente" value="<?= $esEdicion ? (int) $editar['id_cliente'] : 0 ?>">

            <label class="field">
                <span>Cédula o RIF</span>
                <input type="text" name="rif_cedula" maxlength="15" required
                       placeholder="V-12345678 / J-12345678-9"
                       value="<?= e($valor('rif_cedula')) ?>">
            </label>

            <label class="field">
                <span>Nombre o razón social</span>
                <input type="text" name="nombre_razon_social" maxlength="120" required
                       value="<?= e($valor('nombre_razon_social')) ?>">
            </label>

            <label class="field field-full">
                <span>Dirección fiscal</span>
                <input type="text" name="direccion_fiscal" maxlength="180"
                       value="<?= e($valor('direccion_fiscal')) ?>">
            </label>

            <label class="field">
                <span>Teléfono</span>
                <input type="text" name="telefono" maxlength="25"
                       placeholder="0414-1234567"
                       value="<?= e($valor('telefono')) ?>">
            </label>

            <div class="form-actions field-full">
                <button type="submit" class="btn btn-primary">
                    <?= $esEdicion ? 'Guardar cambios' : 'Registrar cliente' ?>
                </button>
                <?php if ($esEdicion): ?>
                    <a class="btn btn-ghost" href="<?= e(url('clientes')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Listado</h2>
                <p class="panel-sub"><?= count($clientes) ?> cliente(s) registrado(s)</p>
            </div>
        </div>

        <form method="GET" action="<?= e(url('clientes')) ?>" class="filters">
            <label class="field field-grow">
                <span>Buscar</span>
                <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre, cédula o RIF...">
            </label>
            <button type="submit" class="btn btn-ghost">Filtrar</button>
            <a class="btn btn-ghost" href="<?= e(url('clientes')) ?>">Limpiar</a>
        </form>

        <?php if ($clientes): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>RIF / Cédula</th>
                            <th>Teléfono</th>
                            <th class="num">Facturas</th>
                            <?php if (is_admin()): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['nombre_razon_social']) ?></strong>
                                <small class="muted"><?= e($c['direccion_fiscal'] ?? 'Sin dirección') ?></small>
                            </td>
                            <td><?= e($c['rif_cedula']) ?></td>
                            <td><?= e($c['telefono'] ?? '—') ?></td>
                            <td class="num">
                                <span class="badge <?= (int) $c['facturas'] > 0 ? 'badge-info' : 'badge-muted' ?>">
                                    <?= (int) $c['facturas'] ?>
                                </span>
                            </td>
                            <?php if (is_admin()): ?>
                            <td class="acciones">
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('clientes', ['editar' => (int) $c['id_cliente']])) ?>">
                                    Editar
                                </a>
                                <?php if ((int) $c['facturas'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-danger" disabled
                                            title="No se puede eliminar: tiene facturas asociadas.">
                                        Eliminar
                                    </button>
                                <?php else: ?>
                                    <form method="POST" action="<?= e(url('clientes')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_cliente" value="<?= (int) $c['id_cliente'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                data-confirm="¿Eliminar este cliente? Esta acción no se puede deshacer.">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">☺</span>
                <p>No hay clientes con esa búsqueda.</p>
                <a class="btn btn-primary" href="<?= e(url('clientes')) ?>">Registrar el primero</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
