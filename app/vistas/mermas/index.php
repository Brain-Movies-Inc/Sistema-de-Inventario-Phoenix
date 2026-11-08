<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$valor = static function (string $campo, $defecto = '') use ($old) {
    if (is_array($old) && array_key_exists($campo, $old)) {
        return $old[$campo];
    }

    return $defecto;
};

require __DIR__ . '/../layout/header.php';
?>

<div class="grid-sidebar">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Reportar merma</h2>
                <p class="panel-sub">La cantidad se descuenta del inventario al guardar.</p>
            </div>
        </div>

        <form method="POST" action="<?= e(url('mermas')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="registrar">

            <label class="field field-full">
                <span>Producto</span>
                <select name="id_producto" required>
                    <option value="">Selecciona un producto...</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int) $p['id_producto'] ?>"
                            <?= (int) $valor('id_producto', 0) === (int) $p['id_producto'] ? 'selected' : '' ?>>
                            <?= e($p['nombre_producto']) ?>
                            — stock: <?= cantidad($p['stock_actual']) ?> <?= e($p['unidad_medida']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Cantidad dada de baja</span>
                <input type="number" name="cantidad_baja" min="0.001" step="0.001" required
                       value="<?= e($valor('cantidad_baja')) ?>">
            </label>

            <label class="field">
                <span>Motivo</span>
                <select name="tipo_motivo" required>
                    <?php foreach ($motivos as $codigo => $etiqueta): ?>
                        <option value="<?= e($codigo) ?>"
                            <?= $valor('tipo_motivo', 'VENCIMIENTO') === $codigo ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field field-full">
                <span>Observación (opcional)</span>
                <input type="text" name="observacion" maxlength="200"
                       placeholder="Ej. producto vencido retirado del estante"
                       value="<?= e($valor('observacion')) ?>">
            </label>

            <div class="form-actions field-full">
                <button type="submit" class="btn btn-primary">Registrar merma</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Mermas registradas</h2>
                <p class="panel-sub"><?= count($mermas) ?> movimiento(s)</p>
            </div>
        </div>

        <?php if ($mermas): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th class="num">Cantidad</th>
                            <th>Motivo</th>
                            <th>Reportado por</th>
                            <th>Observación</th>
                            <?php if (is_admin()): ?>
                                <th class="num">Costo unit. $</th>
                                <th class="num">Pérdida $</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mermas as $m): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $m['fecha_registro']))) ?></td>
                            <td><?= e($m['nombre_producto']) ?></td>
                            <td class="num">
                                <?= cantidad($m['cantidad_baja']) ?> <?= e($m['unidad_medida']) ?>
                            </td>
                            <td><?= e($motivos[$m['tipo_motivo']] ?? $m['tipo_motivo']) ?></td>
                            <td><?= e($m['usuario']) ?></td>
                            <td><?= e($m['observacion'] ?? '—') ?></td>
                            <?php if (is_admin()): ?>
                                <td class="num">$ <?= money($m['costo_unitario_usd']) ?></td>
                                <td class="num">
                                    $ <?= money((float) $m['cantidad_baja'] * (float) $m['costo_unitario_usd']) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">▼</span>
                <p>Aún no hay mermas registradas.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
