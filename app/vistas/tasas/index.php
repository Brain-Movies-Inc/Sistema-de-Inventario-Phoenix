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

$fechaValor = (string) $valor('fecha_vigencia');

if ($fechaValor === '') {
    $fechaValor = date('Y-m-d\TH:i');
} else {
    $fechaTimestamp = strtotime($fechaValor);
    $fechaValor = $fechaTimestamp !== false ? date('Y-m-d\TH:i', $fechaTimestamp) : date('Y-m-d\TH:i');
}

$tasaInput = (string) $valor('tasa_bcv_ves');
$tasaInput = $tasaInput !== '' ? input_cantidad($tasaInput, 4) : '';

require __DIR__ . '/../layout/header.php';
?>

<?php if ($tasaDesactualizada): ?>
    <div class="alert alert-warn">
        <span>
            <?= $tasaActual
                ? 'La tasa vigente es del ' . e(date('d/m/Y H:i', strtotime((string) $tasaActual['fecha_vigencia']))) . '.'
                : 'No hay una tasa BCV registrada.' ?>
            <?= is_admin()
                ? 'Registra el valor del día para que las ventas usen la tasa correcta.'
                : 'Solicita al administrador que actualice la tasa del día.' ?>
        </span>
    </div>
<?php endif; ?>

<section class="stats-grid stats-grid-3">
    <article class="stat-card stat-card-rate">
        <span class="stat-label">Tasa BCV vigente</span>
        <strong class="stat-value"><?= $tasaActual ? 'Bs ' . numero($tasaActual['tasa_bcv_ves'], 4) : '—' ?></strong>
        <small class="stat-foot">Bolívares por 1 dólar</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Última actualización</span>
        <strong class="stat-value" style="font-size:1.25rem">
            <?= $tasaActual ? e(date('d/m/Y H:i', strtotime((string) $tasaActual['fecha_vigencia']))) : 'Sin registro' ?>
        </strong>
        <small class="stat-foot">
            <?= $tasaActual ? e($tasaActual['usuario_nombre'] ?? $tasaActual['usuario_login'] ?? '') : 'Registra la primera tasa' ?>
        </small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Estado</span>
        <strong class="stat-value" style="font-size:1.25rem">
            <?php if ($tasaActual): ?>
                <span class="badge <?= $tasaDesactualizada ? 'badge-warn' : 'badge-ok' ?>">
                    <?= $tasaDesactualizada ? 'Desactualizada' : 'Al día' ?>
                </span>
            <?php else: ?>
                <span class="badge badge-danger">Sin tasa</span>
            <?php endif; ?>
        </strong>
        <small class="stat-foot">Se compara con la fecha de hoy</small>
    </article>
</section>

<div class="grid-sidebar<?= is_admin() ? '' : ' grid-sidebar-single' ?>">
    <?php if (is_admin()): ?>
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $esEdicion ? 'Corregir tasa' : 'Registrar nueva tasa' ?></h2>
                <p class="panel-sub">Valor en bolívares por 1 dólar (Bs/USD).</p>
            </div>
            <a class="btn btn-sm btn-ghost" href="https://www.bcv.org.ve/" target="_blank" rel="noopener noreferrer">
                Consultar BCV
            </a>
        </div>

        <form method="POST" action="<?= e(url('tasas')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_tasa" value="<?= $esEdicion ? (int) $editar['id_tasa'] : 0 ?>">

            <label class="field">
                <span>Tasa (Bs por 1 USD)</span>
                <input type="number" name="tasa_bcv_ves" min="0.0001" step="0.0001" required
                       inputmode="decimal" placeholder="36.5000"
                       value="<?= e($tasaInput) ?>">
            </label>

            <label class="field">
                <span>Fecha y hora de vigencia</span>
                <input type="datetime-local" name="fecha_vigencia" required
                       value="<?= e($fechaValor) ?>">
            </label>

            <label class="field field-full">
                <span>Observación (opcional)</span>
                <input type="text" name="observacion" maxlength="100"
                       placeholder="Ej.: Tasa BCV del día"
                       value="<?= e($valor('observacion')) ?>">
            </label>

            <div class="form-actions field-full">
                <button type="submit" class="btn btn-primary">
                    <?= $esEdicion ? 'Guardar corrección' : 'Registrar tasa' ?>
                </button>
                <?php if ($esEdicion): ?>
                    <a class="btn btn-ghost" href="<?= e(url('tasas')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="muted small-note">
            Las facturas ya emitidas conservan la tasa que tenían al momento de la venta.
        </p>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Historial de tasas</h2>
                <p class="panel-sub"><?= count($tasas) ?> registro(s) más reciente(s)</p>
            </div>
        </div>

        <?php if ($tasas): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Vigencia</th>
                            <th class="num">Bs / USD</th>
                            <th>Observación</th>
                            <th>Registrada por</th>
                            <?php if (is_admin()): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasas as $t): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime((string) $t['fecha_vigencia']))) ?></td>
                            <td class="num"><strong>Bs <?= numero($t['tasa_bcv_ves'], 4) ?></strong></td>
                            <td><?= e($t['observacion'] ?? '—') ?></td>
                            <td>
                                <?= e($t['usuario_nombre'] ?? '') ?>
                                <small class="muted"><?= e($t['usuario_login'] ?? '') ?></small>
                            </td>
                            <?php if (is_admin()): ?>
                            <td class="acciones">
                                <a class="btn btn-sm btn-ghost"
                                   href="<?= e(url('tasas', ['editar' => (int) $t['id_tasa']])) ?>">
                                    Corregir
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">Bs</span>
                <p>No hay tasas registradas.</p>
                <?php if (is_admin()): ?>
                    <a class="btn btn-primary" href="<?= e(url('tasas')) ?>">Registrar la primera</a>
                <?php else: ?>
                    <p class="muted">Solicita al administrador que registre la tasa del día.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
