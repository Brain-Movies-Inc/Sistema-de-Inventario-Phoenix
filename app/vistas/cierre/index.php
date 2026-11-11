<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$cantidad   = (int) ($resumen['cantidad'] ?? 0);
$totalVes   = (float) ($resumen['total_ves'] ?? 0);
$totalUsd   = (float) ($resumen['total_usd'] ?? 0);
$igtfVes    = (float) ($resumen['igtf_ves'] ?? 0);
$primera    = $resumen['primera'] ?? null;
$ultima     = $resumen['ultima'] ?? null;
$porMetodo  = is_array($resumen['por_metodo'] ?? null) ? $resumen['por_metodo'] : [];
$anuladas   = is_array($resumen['anuladas'] ?? null) ? $resumen['anuladas'] : [];
$anuladasN  = (int) ($anuladas['cantidad'] ?? 0);
$anuladasBs = (float) ($anuladas['total_ves'] ?? 0);

require __DIR__ . '/../layout/header.php';
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Corte de caja</h2>
            <p class="panel-sub">
                <?= $esAdmin
                    ? 'Ventas emitidas por todos los usuarios en la fecha seleccionada.'
                    : 'Solo se incluyen tus ventas de la fecha seleccionada.' ?>
            </p>
        </div>
        <form method="GET" action="<?= e(url('cierre')) ?>" class="filters">
            <label class="field">
                <span>Fecha del cierre</span>
                <input type="date" name="fecha" value="<?= e($fecha) ?>" required>
            </label>
            <button type="submit" class="btn btn-ghost">Consultar</button>
            <a class="btn btn-ghost" href="<?= e(url('cierre')) ?>">Hoy</a>
        </form>
    </div>

    <section class="stats-grid stats-grid-3">
        <article class="stat-card">
            <span class="stat-label">Facturas emitidas</span>
            <strong class="stat-value"><?= $cantidad ?></strong>
            <small class="stat-foot">Solo ventas EMITIDA</small>
        </article>

        <article class="stat-card stat-card-money">
            <span class="stat-label">Total vendido (Bs)</span>
            <strong class="stat-value">Bs <?= money($totalVes) ?></strong>
            <small class="stat-foot">$ <?= money($totalUsd) ?></small>
        </article>

        <article class="stat-card">
            <span class="stat-label">IGTF recaudado</span>
            <strong class="stat-value">Bs <?= money($igtfVes) ?></strong>
            <small class="stat-foot">Solo pagos en divisa</small>
        </article>
    </section>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Arqueo por método de pago</h2>
                    <p class="panel-sub">Separa efectivo, pago móvil y otros medios.</p>
                </div>
            </div>

            <?php if ($porMetodo): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Método de pago</th>
                                <th class="num">Facturas</th>
                                <th class="num">Total Bs</th>
                                <th class="num">Total $</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porMetodo as $fila): ?>
                            <tr>
                                <td><?= e(metodo_pago_label((string) $fila['metodo_pago'])) ?></td>
                                <td class="num"><?= (int) $fila['cantidad'] ?></td>
                                <td class="num"><?= money($fila['total_ves']) ?></td>
                                <td class="num">$ <?= money($fila['total_usd']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="num"><?= $cantidad ?></th>
                                <th class="num"><?= money($totalVes) ?></th>
                                <th class="num">$ <?= money($totalUsd) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <span class="empty-icon">∑</span>
                    <p>No hay ventas emitidas en esa fecha.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Datos del cierre</h2>
                    <p class="panel-sub">Horario y facturas anuladas del día.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr>
                            <th>Fecha</th>
                            <td class="num"><?= e(date('d/m/Y', strtotime($fecha))) ?></td>
                        </tr>
                        <tr>
                            <th>Primera venta</th>
                            <td class="num">
                                <?= $primera ? e(date('H:i', strtotime((string) $primera))) : '—' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Última venta</th>
                            <td class="num">
                                <?= $ultima ? e(date('H:i', strtotime((string) $ultima))) : '—' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Facturas anuladas</th>
                            <td class="num"><?= $anuladasN ?></td>
                        </tr>
                        <tr>
                            <th>Monto anulado</th>
                            <td class="num">Bs <?= money($anuladasBs) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="muted small-note">
                Las facturas anuladas no se suman al total vendido ni al arqueo por
                método de pago. Se muestran aparte para facilitar la conciliación.
            </p>
        </section>
    </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
