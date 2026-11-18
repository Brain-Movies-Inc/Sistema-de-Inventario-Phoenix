<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$esEmitida = $factura['estado'] === 'EMITIDA';

require __DIR__ . '/../layout/header.php';
?>

<div class="page-actions no-print">
    <a class="btn btn-ghost" href="<?= e(url('facturacion')) ?>">← Volver a facturación</a>
    <div class="page-actions-right">
        <a class="btn btn-primary"
           href="<?= e(url('facturacion/pdf/' . (int) $factura['id_factura'])) ?>">
            Descargar PDF
        </a>
        <a class="btn btn-ghost"
           href="<?= e(url('facturacion/imagen/' . (int) $factura['id_factura'])) ?>">
            Descargar imagen
        </a>
        <a class="btn btn-ghost"
           href="<?= e(url('facturacion/pdf/' . (int) $factura['id_factura'], ['ver' => 1])) ?>"
           target="_blank" rel="noopener">
            Imprimir
        </a>
        <?php if (!$esEmitida && is_admin()): ?>
            <form method="POST" action="<?= e(url('facturacion')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id_factura" value="<?= (int) $factura['id_factura'] ?>">
                <button type="submit" class="btn btn-danger"
                        data-confirm="¿Eliminar esta factura anulada? Se borrarán también sus líneas y no se puede deshacer.">
                    Eliminar factura
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<article class="ticket" id="ticket">
    <header class="ticket-head">
        <div class="ticket-empresa">
            <strong><?= e(EMPRESA_NOMBRE) ?></strong>
            <small>RIF: <?= e(EMPRESA_RIF) ?></small>
            <small><?= e(EMPRESA_DIRECCION) ?></small>
            <?php if (EMPRESA_TELEFONO !== ''): ?>
                <small>Teléfono: <?= e(EMPRESA_TELEFONO) ?></small>
            <?php endif; ?>
        </div>

        <div class="ticket-fiscal">
            <span class="ticket-tipo">FACTURA</span>
            <strong>N° <?= e($factura['nro_factura_consecutivo']) ?></strong>
            <small>Control: <?= e($factura['nro_control_fiscal']) ?></small>
            <small>Código: <?= e($factura['codigo_verificacion']) ?></small>
            <small><?= e(date('d/m/Y H:i', strtotime((string) $factura['fecha_emision']))) ?></small>
            <span class="badge <?= estado_badge((string) $factura['estado']) ?>"><?= e($factura['estado']) ?></span>
        </div>
    </header>

    <section class="ticket-cliente">
        <div>
            <span>Cliente</span>
            <strong><?= e($factura['cliente']) ?></strong>
        </div>
        <div>
            <span>RIF / Cédula</span>
            <strong><?= e($factura['rif_cedula']) ?></strong>
        </div>
        <div>
            <span>Dirección fiscal</span>
            <strong><?= e($factura['direccion_fiscal'] ?? '—') ?></strong>
        </div>
        <div>
            <span>Método de pago</span>
            <strong><?= e(metodo_pago_label((string) $factura['metodo_pago'])) ?></strong>
        </div>
        <div>
            <span>Tasa BCV aplicada</span>
            <strong>Bs <?= numero($factura['tasa_bcv_aplicada'], 4) ?></strong>
        </div>
        <div>
            <span>Cajero</span>
            <strong><?= e($factura['cajero_nombre']) ?></strong>
        </div>
    </section>

    <div class="table-wrap">
        <table class="ticket-tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Precio $</th>
                    <th class="num">Precio Bs</th>
                    <th class="num">IVA</th>
                    <th class="num">Subtotal Bs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lineas as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?= e($l['nombre_producto']) ?>
                        <small class="muted"><?= e($l['codigo_qr_barras']) ?></small>
                    </td>
                    <td class="num"><?= cantidad($l['cantidad_o_peso']) ?> <?= e($l['unidad_medida']) ?></td>
                    <td class="num">$ <?= money($l['precio_unitario_usd']) ?></td>
                    <td class="num"><?= money($l['precio_unitario_ves']) ?></td>
                    <td class="num"><?= money($l['alicuota_iva_item'], 2) ?>%</td>
                    <td class="num"><?= money($l['subtotal_item_ves']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="ticket-totales">
        <div><span>Subtotal exento</span><strong>Bs <?= money($factura['subtotal_exento_ves']) ?></strong></div>
        <div><span>Base imponible</span><strong>Bs <?= money($factura['base_imponible_ves']) ?></strong></div>
        <div><span>IVA</span><strong>Bs <?= money($factura['monto_iva_ves']) ?></strong></div>
        <?php if ((float) $factura['monto_igtf_ves'] > 0): ?>
            <div><span>IGTF 3%</span><strong>Bs <?= money($factura['monto_igtf_ves']) ?></strong></div>
        <?php endif; ?>
        <div class="ticket-total"><span>Total Bs</span><strong>Bs <?= money($factura['total_ves']) ?></strong></div>
        <div class="ticket-total-usd"><span>Total USD</span><strong>$ <?= money($factura['total_usd']) ?></strong></div>
    </div>

    <?php if (!empty($pagos)): ?>
        <section class="ticket-pagos">
            <h3>Desglose de pagos</h3>
            <table class="ticket-tabla">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Moneda</th>
                        <th class="num">Monto</th>
                        <th class="num">Equivalente Bs</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td><?= e(metodo_pago_label((string) $pago['metodo'])) ?></td>
                        <td><?= $pago['moneda'] === 'USD' ? 'Divisa (USD)' : 'Bolívares' ?></td>
                        <td class="num">
                            <?= $pago['moneda'] === 'USD' ? '$ ' : 'Bs ' ?><?= money($pago['monto']) ?>
                        </td>
                        <td class="num">Bs <?= money($pago['monto_ves']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <footer class="ticket-foot">
        <p>Gracias por su compra. Documento generado por <?= e(EMPRESA_NOMBRE) ?>.</p>
        <?php if ($factura['estado'] === 'ANULADA'): ?>
            <p class="ticket-anulada">FACTURA ANULADA</p>
            <?php if (!empty($factura['motivo_anulacion'])): ?>
                <p><strong>Motivo:</strong> <?= e($factura['motivo_anulacion']) ?></p>
            <?php endif; ?>
            <?php if (!empty($factura['fecha_anulacion'])): ?>
                <small>
                    Anulada el <?= e(date('d/m/Y H:i', strtotime((string) $factura['fecha_anulacion']))) ?>
                    <?php if (!empty($factura['anulada_por_login'])): ?>
                        por <?= e($factura['anulada_por_login']) ?>
                    <?php endif; ?>
                </small>
            <?php endif; ?>
        <?php endif; ?>
        <small>Emisión: <?= e($factura['tipo_emision']) ?> · Cajero: <?= e($factura['cajero_login']) ?></small>
    </footer>
</article>

<?php if ($esEmitida && is_admin()): ?>
    <section class="panel no-print panel-anulacion">
        <div class="panel-head">
            <div>
                <h2>Anular factura</h2>
                <p class="panel-sub">
                    Explica el motivo. El stock vuelve al inventario y la anulación no se puede revertir.
                </p>
            </div>
        </div>

        <form method="POST" action="<?= e(url('facturacion')) ?>" class="form-anulacion">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="anular">
            <input type="hidden" name="id_factura" value="<?= (int) $factura['id_factura'] ?>">

            <label class="field">
                <span>Motivo de la anulación</span>
                <input type="text" name="motivo_anulacion" minlength="5" maxlength="200" required
                       placeholder="Ej.: error en el precio cobrado">
            </label>

            <button type="submit" class="btn btn-danger"
                    data-confirm="¿Anular esta factura? Se devolverá el stock al inventario y no se podrá revertir.">
                Anular factura
            </button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
