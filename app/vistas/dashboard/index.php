<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;
$valorCostoUsd = (float) ($resumen['valor_costo_usd'] ?? 0);
$valorVentaUsd = (float) ($resumen['valor_venta_usd'] ?? 0);
$vendidoUsd    = (float) ($resumen['total_vendido_usd'] ?? 0);
$vendidoVes    = (float) ($resumen['total_vendido_ves'] ?? 0);

$mermaCantidad   = (int) ($mermas['cantidad'] ?? 0);
$mermaMes        = (int) ($mermas['cantidad_mes'] ?? 0);
$mermaPerdida    = (float) ($mermas['perdida_usd'] ?? 0);
$mermaPerdidaMes = (float) ($mermas['perdida_mes_usd'] ?? 0);
$mermaUltimas    = is_array($mermas['ultimas'] ?? null) ? $mermas['ultimas'] : [];

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
            <?php if (is_admin()): ?>
                <a href="<?= e(url('tasas')) ?>">Actualiza la tasa del día</a> para que las nuevas ventas usen el valor correcto.
            <?php else: ?>
                Solicita al administrador que actualice la tasa del día.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<section class="stats-grid">
    <article class="stat-card">
        <span class="stat-label">Productos activos</span>
        <strong class="stat-value"><?= (int) ($resumen['productos'] ?? 0) ?></strong>
        <small class="stat-foot"><?= (int) ($resumen['categorias'] ?? 0) ?> categorías</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Clientes</span>
        <strong class="stat-value"><?= (int) ($resumen['clientes'] ?? 0) ?></strong>
        <small class="stat-foot">Registrados para facturar</small>
    </article>

    <article class="stat-card">
        <span class="stat-label">Facturas emitidas</span>
        <strong class="stat-value"><?= (int) ($resumen['facturas'] ?? 0) ?></strong>
        <small class="stat-foot">Solo ventas emitidas</small>
    </article>

    <article class="stat-card stat-card-rate">
        <span class="stat-label">Tasa BCV vigente</span>
        <strong class="stat-value"><?= $tasa ? 'Bs ' . numero($tasaValor, 4) : '—' ?></strong>
        <small class="stat-foot">
            <?= $tasa ? e(date('d/m/Y', strtotime((string) $tasa['fecha_vigencia']))) : 'Sin tasa registrada' ?>
            <?php if (!empty($tasaDesactualizada)): ?>
                · <a href="<?= e(url('tasas')) ?>">desactualizada</a>
            <?php endif; ?>
        </small>
    </article>
</section>

<section class="stats-grid stats-grid-3">
    <article class="stat-card stat-card-money">
        <span class="stat-label">Inventario a costo</span>
        <strong class="stat-value">$ <?= money($valorCostoUsd) ?></strong>
        <small class="stat-foot">Bs <?= money($valorCostoUsd * $tasaValor) ?></small>
    </article>

    <article class="stat-card stat-card-money">
        <span class="stat-label">Inventario a precio de venta</span>
        <strong class="stat-value">$ <?= money($valorVentaUsd) ?></strong>
        <small class="stat-foot">Bs <?= money($valorVentaUsd * $tasaValor) ?></small>
    </article>

    <article class="stat-card stat-card-money">
        <span class="stat-label">Total vendido</span>
        <strong class="stat-value">$ <?= money($vendidoUsd) ?></strong>
        <small class="stat-foot">Bs <?= money($vendidoVes) ?></small>
    </article>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Merma</h2>
            <p class="panel-sub">Bajas de inventario y pérdida valorizada</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('mermas')) ?>">Registrar / ver mermas</a>
    </div>

    <div class="merma-resumen">
        <div>
            <span class="stat-label">Mermas registradas</span>
            <strong class="merma-valor"><?= $mermaCantidad ?></strong>
        </div>
        <div>
            <span class="stat-label">Mermas del mes</span>
            <strong class="merma-valor"><?= $mermaMes ?></strong>
        </div>
        <div>
            <span class="stat-label">Pérdida acumulada</span>
            <strong class="merma-valor">$ <?= money($mermaPerdida) ?></strong>
            <small class="muted">Bs <?= money($mermaPerdida * $tasaValor) ?></small>
        </div>
        <div>
            <span class="stat-label">Pérdida del mes</span>
            <strong class="merma-valor">$ <?= money($mermaPerdidaMes) ?></strong>
            <small class="muted">Bs <?= money($mermaPerdidaMes * $tasaValor) ?></small>
        </div>
    </div>

    <?php if ($mermaUltimas): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th class="num">Cantidad</th>
                        <th>Motivo</th>
                        <th>Reportó</th>
                        <th class="num">Pérdida $</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mermaUltimas as $m): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $m['fecha_registro']))) ?></td>
                        <td><?= e($m['nombre_producto']) ?></td>
                        <td class="num">
                            <?= cantidad($m['cantidad_baja']) ?> <?= e($m['unidad_medida']) ?>
                        </td>
                        <td><?= e(MermaModel::MOTIVOS[$m['tipo_motivo']] ?? $m['tipo_motivo']) ?></td>
                        <td><?= e($m['usuario']) ?></td>
                        <td class="num">
                            $ <?= money((float) $m['cantidad_baja'] * (float) $m['costo_unitario_usd']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <span class="empty-icon">▼</span>
            <p>No hay mermas registradas.</p>
            <a class="btn btn-primary" href="<?= e(url('mermas')) ?>">Reportar la primera</a>
        </div>
    <?php endif; ?>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Stock bajo</h2>
                <p class="panel-sub">Productos en o por debajo del mínimo</p>
            </div>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('productos', ['bajo' => 1])) ?>">Ver todos</a>
        </div>

        <?php if ($stockBajo): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="num">Stock</th>
                            <th class="num">Faltante</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stockBajo as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['nombre_producto']) ?></strong>
                                <small class="muted"><?= e($p['codigo_qr_barras']) ?></small>
                            </td>
                            <td><?= e($p['nombre_categoria']) ?></td>
                            <td class="num">
                                <span class="badge <?= (float) $p['stock_actual'] <= 0 ? 'badge-danger' : 'badge-warn' ?>">
                                    <?= cantidad($p['stock_actual']) ?> <?= e($p['unidad_medida']) ?>
                                </span>
                            </td>
                            <td class="num"><?= cantidad($p['faltante']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">✓</span>
                <p>Todos los productos tienen stock suficiente.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Más vendidos</h2>
                <p class="panel-sub">Top 5 por cantidad vendida</p>
            </div>
        </div>

        <?php if ($topProductos): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Producto</th>
                            <th class="num">Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topProductos as $i => $p): ?>
                        <tr>
                            <td><strong><?= $i + 1 ?></strong></td>
                            <td><?= e($p['nombre_producto']) ?></td>
                            <td class="num">
                                <span class="badge badge-info">
                                    <?= cantidad($p['total_vendido']) ?> <?= e($p['unidad_medida']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">▤</span>
                <p>Aún no hay ventas registradas.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Últimas facturas</h2>
            <p class="panel-sub">Movimientos recientes del punto de venta</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('facturacion')) ?>">Ver historial y descargas</a>
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
            <p>Aún no se han emitido facturas.</p>
            <a class="btn btn-primary" href="<?= e(url('facturacion/nueva')) ?>">Emitir la primera</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
