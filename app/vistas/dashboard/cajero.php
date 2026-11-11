<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$tasaValor     = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;
$facturasHoy   = (int) ($resumen['facturas_hoy'] ?? 0);
$vendidoHoyVes = (float) ($resumen['vendido_hoy_ves'] ?? 0);
$vendidoHoyUsd = (float) ($resumen['vendido_hoy_usd'] ?? 0);
$facturasMes   = (int) ($resumen['facturas_mes'] ?? 0);
$vendidoMesVes = (float) ($resumen['vendido_mes_ves'] ?? 0);
$facturasTotal = (int) ($resumen['facturas_total'] ?? 0);
$vendidoTotal  = (float) ($resumen['vendido_total_ves'] ?? 0);
$ultimaVenta   = $resumen['ultima_venta'] ?? null;

require __DIR__ . '/../layout/header.php';
?>

<?php if (!empty($tasaDesactualizada)): ?>
    <div class="alert alert-warn">
        <span>
            <?php if ($tasa): ?>
                La tasa BCV vigente es del
                <?= e(date('d/m/Y H:i', strtotime((string) $tasa['fecha_vigencia']))) ?>.
            <?php else: ?>
                No hay una tasa BCV registrada.
            <?php endif; ?>
            Solicita al administrador que actualice la tasa del día antes de emitir nuevas ventas.
        </span>
    </div>
<?php endif; ?>

<section class="stats-grid">
    <article class="stat-card">
        <span class="stat-label">Tus facturas de hoy</span>
        <strong class="stat-value"><?= $facturasHoy ?></strong>
        <small class="stat-foot">Solo ventas emitidas</small>
    </article>

    <article class="stat-card stat-card-money">
        <span class="stat-label">Vendido hoy</span>
        <strong class="stat-value">Bs <?= money($vendidoHoyVes) ?></strong>
        <small class="stat-foot">$ <?= money($vendidoHoyUsd) ?></small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Facturas del mes</span>
        <strong class="stat-value"><?= $facturasMes ?></strong>
        <small class="stat-foot">Bs <?= money($vendidoMesVes) ?> vendidos</small>
    </article>

    <article class="stat-card stat-card-rate">
        <span class="stat-label">Tasa BCV vigente</span>
        <strong class="stat-value"><?= $tasa ? 'Bs ' . numero($tasaValor, 4) : '—' ?></strong>
        <small class="stat-foot">
            <?= $tasa ? e(date('d/m/Y', strtotime((string) $tasa['fecha_vigencia']))) : 'Sin tasa registrada' ?>
        </small>
    </article>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Accesos rápidos</h2>
                <p class="panel-sub">Todo lo que necesitas para atender el punto de venta</p>
            </div>
        </div>

        <div class="acciones">
            <a class="btn btn-primary" href="<?= e(url('facturacion/nueva')) ?>">＋ Nueva venta</a>
            <a class="btn btn-ghost" href="<?= e(url('facturacion')) ?>">Historial de ventas</a>
            <a class="btn btn-ghost" href="<?= e(url('cierre')) ?>">Cierre de caja</a>
        </div>

        <p class="muted small-note">
            Desde Facturación puedes consultar las facturas y ver comprobantes.
            El cierre de caja resume tus ventas del día.
        </p>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Tu actividad</h2>
                <p class="panel-sub">Ventas acumuladas de tu usuario</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <tbody>
                    <tr>
                        <th>Facturas emitidas</th>
                        <td class="num"><strong><?= $facturasTotal ?></strong></td>
                    </tr>
                    <tr>
                        <th>Total vendido</th>
                        <td class="num"><strong>Bs <?= money($vendidoTotal) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Última venta</th>
                        <td class="num">
                            <?= $ultimaVenta
                                ? e(date('d/m/Y H:i', strtotime((string) $ultimaVenta)))
                                : 'Aún sin ventas' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Tus últimas facturas</h2>
            <p class="panel-sub">Movimientos recientes emitidos por tu usuario</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('facturacion')) ?>">Ver historial completo</a>
    </div>

    <?php if ($ultimasFacturas): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th class="num">Total Bs</th>
                        <th class="num">Total $</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ultimasFacturas as $f): ?>
                    <tr>
                        <td>
                            <a class="link-strong" href="<?= e(url('facturacion/ver/' . (int) $f['id_factura'])) ?>">
                                #<?= e($f['nro_factura_consecutivo']) ?>
                            </a>
                        </td>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $f['fecha_emision']))) ?></td>
                        <td><?= e($f['cliente']) ?></td>
                        <td class="num"><?= money($f['total_ves']) ?></td>
                        <td class="num">$ <?= money($f['total_usd']) ?></td>
                        <td><span class="badge <?= estado_badge((string) $f['estado']) ?>"><?= e($f['estado']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <span class="empty-icon">▤</span>
            <p>Aún no has emitido facturas.</p>
            <a class="btn btn-primary" href="<?= e(url('facturacion/nueva')) ?>">Emitir la primera</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
