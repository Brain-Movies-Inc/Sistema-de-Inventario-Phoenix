<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$meses = [
    1  => 'Enero',
    2  => 'Febrero',
    3  => 'Marzo',
    4  => 'Abril',
    5  => 'Mayo',
    6  => 'Junio',
    7  => 'Julio',
    8  => 'Agosto',
    9  => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];

require __DIR__ . '/../layout/header.php';
?>

<div class="page-actions no-print">
    <div>
        <p class="muted">
            Cierra un mes para bloquear la anulación y eliminación de sus facturas.
            La reapertura queda registrada en la bitácora.
        </p>
    </div>
    <div class="page-actions-right">
        <form method="GET" action="<?= e(url('periodos')) ?>" class="inline-form">
            <label class="field field-inline">
                <span>Año</span>
                <select name="anio" onchange="this.form.submit()">
                    <?php foreach ($anios as $a): ?>
                        <option value="<?= (int) $a ?>" <?= (int) $a === (int) $anio ? 'selected' : '' ?>>
                            <?= (int) $a ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Períodos fiscales de <?= (int) $anio ?></h2>
            <p class="panel-sub">Estado mensual, ventas emitidas y anulaciones.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th class="num">Facturas</th>
                    <th class="num">Anuladas</th>
                    <th class="num">Total Bs</th>
                    <th class="num">Total $</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periodos as $p): ?>
                <tr>
                    <td><strong><?= e($meses[(int) $p['mes']] ?? (string) $p['mes']) ?></strong></td>
                    <td class="num"><?= (int) $p['cantidad'] ?></td>
                    <td class="num"><?= (int) $p['anuladas'] ?></td>
                    <td class="num"><?= money($p['total_ves']) ?></td>
                    <td class="num">$ <?= money($p['total_usd']) ?></td>
                    <td>
                        <?php if ($p['cerrado']): ?>
                            <span class="badge badge-danger">Cerrado</span>
                            <?php if (!empty($p['cerrado_por'])): ?>
                                <small class="muted">
                                    por <?= e($p['cerrado_por']) ?>
                                    <?php if (!empty($p['fecha_cierre'])): ?>
                                        · <?= e(date('d/m/Y H:i', strtotime((string) $p['fecha_cierre']))) ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-ok">Abierto</span>
                        <?php endif; ?>
                    </td>
                    <td class="acciones">
                        <?php if ($p['cerrado']): ?>
                            <form method="POST" action="<?= e(url('periodos')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="reabrir">
                                <input type="hidden" name="anio" value="<?= (int) $p['anio'] ?>">
                                <input type="hidden" name="mes" value="<?= (int) $p['mes'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost"
                                        data-confirm="¿Reabrir este período? Se podrán anular o eliminar facturas otra vez.">
                                    Reabrir
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= e(url('periodos')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="cerrar">
                                <input type="hidden" name="anio" value="<?= (int) $p['anio'] ?>">
                                <input type="hidden" name="mes" value="<?= (int) $p['mes'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        data-confirm="¿Cerrar este período? No se podrán anular ni eliminar sus facturas hasta reabrirlo.">
                                    Cerrar
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
