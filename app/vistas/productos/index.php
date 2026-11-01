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

$unidades = ['UND' => 'Unidad', 'KG' => 'Kilogramo', 'LT' => 'Litro', 'GR' => 'Gramo', 'PAQ' => 'Paquete'];

require __DIR__ . '/../layout/header.php';
?>

<?php if (!is_admin()): ?>
    <div class="alert alert-warn">
        <span>Modo consulta: solo un administrador puede crear, editar o desactivar productos.</span>
    </div>
<?php endif; ?>

<div class="grid-sidebar<?= is_admin() ? '' : ' grid-sidebar-single' ?>">
    <?php if (is_admin()): ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h2>
                <p class="panel-sub">Los precios se guardan en dólares; el POS los convierte con la tasa BCV.</p>
            </div>
        </div>

        <form method="POST" action="<?= e(url('productos')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_producto" value="<?= $esEdicion ? (int) $editar['id_producto'] : 0 ?>">

            <label class="field field-full">
                <span>Nombre del producto</span>
                <input type="text" name="nombre_producto" maxlength="120" required
                       value="<?= e($valor('nombre_producto')) ?>">
            </label>

            <label class="field">
                <span>Código de barras / QR</span>
                <input type="text" name="codigo_qr_barras" maxlength="64" required
                       value="<?= e($valor('codigo_qr_barras')) ?>">
            </label>

            <label class="field">
                <span>Categoría</span>
                <select name="id_categoria" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id_categoria'] ?>"
                            <?= (int) $valor('id_categoria', 0) === (int) $c['id_categoria'] ? 'selected' : '' ?>>
                            <?= e($c['nombre_categoria']) ?><?= (int) $c['activo'] === 1 ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Unidad de medida</span>
                <select name="unidad_medida">
                    <?php foreach ($unidades as $codigo => $etiqueta): ?>
                        <option value="<?= e($codigo) ?>"
                            <?= $valor('unidad_medida', 'UND') === $codigo ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?> (<?= e($codigo) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Alícuota IVA (%)</span>
                <input type="number" name="alicuota_iva" min="0" max="100" step="0.01" list="ivas"
                       value="<?= e($valor('alicuota_iva', '16.00')) ?>">
                <datalist id="ivas">
                    <option value="0">Exento</option>
                    <option value="8">Reducida</option>
                    <option value="16">General</option>
                </datalist>
            </label>

            <label class="field">
                <span>Costo USD</span>
                <input type="number" name="costo_usd" min="0" step="0.001" required
                       value="<?= e($valor('costo_usd', '0.000')) ?>">
            </label>

            <label class="field">
                <span>Precio de venta USD</span>
                <input type="number" name="precio_venta_usd" min="0" step="0.001" required
                       value="<?= e($valor('precio_venta_usd', '0.000')) ?>">
            </label>

            <label class="field">
                <span>Stock actual</span>
                <input type="number" name="stock_actual" min="0" step="0.001" required
                       value="<?= e($valor('stock_actual', '0.000')) ?>">
            </label>

            <label class="field">
                <span>Stock mínimo</span>
                <input type="number" name="stock_minimo" min="0" step="0.001" required
                       value="<?= e($valor('stock_minimo', '0.000')) ?>">
            </label>

            <label class="field">
                <span>Stock máximo (opcional)</span>
                <input type="number" name="stock_maximo" min="0" step="0.001"
                       value="<?= e($valor('stock_maximo', '')) ?>">
            </label>

            <div class="form-actions field-full">
                <button type="submit" class="btn btn-primary">
                    <?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?>
                </button>
                <?php if ($esEdicion): ?>
                    <a class="btn btn-ghost" href="<?= e(url('productos')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Listado</h2>
                <p class="panel-sub"><?= count($productos) ?> producto(s) encontrado(s)</p>
            </div>
        </div>

        <form method="GET" action="<?= e(url('productos')) ?>" class="filters">
            <label class="field field-grow">
                <span>Buscar</span>
                <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Nombre o código...">
            </label>

            <label class="field">
                <span>Categoría</span>
                <select name="categoria">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id_categoria'] ?>"
                            <?= $idCategoria === (int) $c['id_categoria'] ? 'selected' : '' ?>>
                            <?= e($c['nombre_categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="check check-inline">
                <input type="checkbox" name="bajo" value="1" <?= $soloBajo ? 'checked' : '' ?>>
                Solo stock bajo
            </label>

            <label class="check check-inline">
                <input type="checkbox" name="inactivos" value="1" <?= $incluirInactivos ? 'checked' : '' ?>>
                Incluir inactivos
            </label>

            <button type="submit" class="btn btn-ghost">Filtrar</button>
            <a class="btn btn-ghost" href="<?= e(url('productos')) ?>">Limpiar</a>
        </form>

        <?php if ($productos): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="num">Costo $</th>
                            <th class="num">Venta $</th>
                            <th class="num">IVA</th>
                            <th class="num">Stock</th>
                            <th>Estado</th>
                            <?php if (is_admin()): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $stock = (float) $p['stock_actual'];
                        $minimo = (float) $p['stock_minimo'];
                        $claseStock = $stock <= 0 ? 'badge-danger' : ($stock <= $minimo ? 'badge-warn' : 'badge-ok');
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($p['nombre_producto']) ?></strong>
                                <small class="muted"><?= e($p['codigo_qr_barras']) ?></small>
                            </td>
                            <td><?= e($p['nombre_categoria']) ?></td>
                            <td class="num">$ <?= money($p['costo_usd']) ?></td>
                            <td class="num">$ <?= money($p['precio_venta_usd']) ?></td>
                            <td class="num"><?= money($p['alicuota_iva'], 2) ?>%</td>
                            <td class="num">
                                <span class="badge <?= $claseStock ?>">
                                    <?= money3($stock) ?> <?= e($p['unidad_medida']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int) $p['activo'] === 1 ? 'badge-ok' : 'badge-muted' ?>">
                                    <?= (int) $p['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <?php if (is_admin()): ?>
                            <td class="acciones">
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('productos', ['editar' => (int) $p['id_producto']])) ?>">
                                    Editar
                                </a>
                                <form method="POST" action="<?= e(url('productos')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                                    <input type="hidden" name="activo" value="<?= (int) $p['activo'] === 1 ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= (int) $p['activo'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                            data-confirm="<?= (int) $p['activo'] === 1 ? '¿Desactivar este producto?' : '¿Reactivar este producto?' ?>">
                                        <?= (int) $p['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">▣</span>
                <p>No hay productos con esos filtros.</p>
                <a class="btn btn-primary" href="<?= e(url('productos')) ?>">Crear el primero</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
