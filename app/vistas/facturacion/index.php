<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;
$hoyCantidad = (int) ($resumenHoy['cantidad'] ?? 0);
$hoyVes = (float) ($resumenHoy['total_ves'] ?? 0);
$hoyUsd = (float) ($resumenHoy['total_usd'] ?? 0);

$filtrosExportar = array_filter(
    [
        'estado' => $estado,
        'q'      => $busqueda,
        'desde'  => $desde,
        'hasta'  => $hasta,
    ],
    static fn ($valor): bool => $valor !== null && $valor !== ''
);

$urlResumen = url('facturacion/exportar', $filtrosExportar + ['tipo' => 'resumen']);
$urlDetalle = url('facturacion/exportar', $filtrosExportar + ['tipo' => 'detalle']);

require __DIR__ . '/../layout/header.php';
?>

<section class="stats-grid stats-grid-3">
    <article class="stat-card">
        <span class="stat-label">Facturas de hoy</span>
        <strong class="stat-value"><?= $hoyCantidad ?></strong>
        <small class="stat-foot">Solo facturas emitidas</small>
    </article>

    <article class="stat-card stat-card-money">
        <span class="stat-label">Vendido hoy (Bs)</span>
        <strong class="stat-value">Bs <?= money($hoyVes) ?></strong>
        <small class="stat-foot">$ <?= money($hoyUsd) ?></small>
    </article>

    <article class="stat-card stat-card-rate">
        <span class="stat-label">Tasa BCV</span>
        <strong class="stat-value"><?= $tasa ? 'Bs ' . numero($tasaValor, 4) : '—' ?></strong>
        <small class="stat-foot"><?= $tasa ? e(date('d/m/Y', strtotime((string) $tasa['fecha_vigencia']))) : 'Sin tasa' ?></small>
    </article>
</section>

<?php if (!empty($tasaDesactualizada)): ?>
    <div class="alert alert-warn">
        <span>
            <?php if ($tasa): ?>
                La tasa BCV vigente es del
                <?= e(date('d/m/Y H:i', strtotime((string) $tasa['fecha_vigencia']))) ?>.
            <?php else: ?>
                No hay una tasa BCV registrada.
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <a href="<?= e(url('tasas')) ?>">Actualiza la tasa del día</a> antes de emitir nuevas ventas.
            <?php else: ?>
                Solicita al administrador que actualice la tasa del día.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Historial de ventas</h2>
            <p class="panel-sub"><?= count($facturas) ?> factura(s) encontrada(s)</p>
        </div>
        <div class="acciones">
            <a class="btn btn-ghost" href="<?= e(url('cierre')) ?>">Cierre de caja</a>
            <?php if (is_admin()): ?>
                <a class="btn btn-ghost" href="<?= e($urlResumen) ?>">↓ Resumen CSV</a>
                <a class="btn btn-ghost" href="<?= e($urlDetalle) ?>">↓ Detalle CSV</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= e(url('facturacion/nueva')) ?>">＋ Nueva venta</a>
        </div>
    </div>

    <form method="GET" action="<?= e(url('facturacion')) ?>" class="filters">
        <label class="field field-grow">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($busqueda) ?>"
                   placeholder="N° factura, control, cliente o RIF...">
        </label>

        <label class="field">
            <span>Desde</span>
            <input type="date" name="desde" value="<?= e($desde ?? '') ?>">
        </label>

        <label class="field">
            <span>Hasta</span>
            <input type="date" name="hasta" value="<?= e($hasta ?? '') ?>">
        </label>

        <label class="field">
            <span>Estado</span>
            <select name="estado">
                <option value="">Todos</option>
                <option value="EMITIDA" <?= $estado === 'EMITIDA' ? 'selected' : '' ?>>Emitidas</option>
                <option value="ANULADA" <?= $estado === 'ANULADA' ? 'selected' : '' ?>>Anuladas</option>
            </select>
        </label>

        <button type="submit" class="btn btn-ghost">Filtrar</button>
        <a class="btn btn-ghost" href="<?= e(url('facturacion')) ?>">Limpiar</a>
    </form>

    <?php if ($facturas): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Cajero</th>
                        <th class="num">Líneas</th>
                        <th>Pago</th>
                        <th class="num">Total Bs</th>
                        <th class="num">Total $</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($facturas as $f): ?>
                    <tr>
                        <td>
                            <a class="link-strong" href="<?= e(url('facturacion/ver/' . (int) $f['id_factura'])) ?>">
                                #<?= e($f['nro_factura_consecutivo']) ?>
                            </a>
                            <small class="muted">
                                <?= e($f['nro_control_fiscal']) ?> · Código <?= e($f['codigo_verificacion']) ?>
                            </small>
                        </td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $f['fecha_emision']))) ?></td>
                        <td>
                            <?= e($f['cliente']) ?>
                            <small class="muted"><?= e($f['rif_cedula']) ?></small>
                        </td>
                        <td><?= e($f['cajero']) ?></td>
                        <td class="num"><?= (int) $f['lineas'] ?></td>
                        <td><?= e(metodo_pago_label((string) $f['metodo_pago'])) ?></td>
                        <td class="num"><?= money($f['total_ves']) ?></td>
                        <td class="num">$ <?= money($f['total_usd']) ?></td>
                        <td><span class="badge <?= estado_badge((string) $f['estado']) ?>"><?= e($f['estado']) ?></span></td>
                        <td class="acciones">
                            <a class="btn btn-sm btn-ghost" href="<?= e(url('facturacion/ver/' . (int) $f['id_factura'])) ?>">
                                Ver
                            </a>
                            <?php if ($f['estado'] === 'ANULADA' && is_admin()): ?>
                                <form method="POST" action="<?= e(url('facturacion')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_factura" value="<?= (int) $f['id_factura'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="¿Eliminar esta factura anulada? Se borrarán también sus líneas y no se puede deshacer.">
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
            <span class="empty-icon">▤</span>
            <p>No hay facturas con esos filtros.</p>
            <a class="btn btn-primary" href="<?= e(url('facturacion/nueva')) ?>">Emitir una factura</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
