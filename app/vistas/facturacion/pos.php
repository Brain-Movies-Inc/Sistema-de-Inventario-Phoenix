<?php
if (!defined('SISTEMA_DR')) {
    http_response_code(403);
    exit('Acceso no permitido.');
}

$oldCliente = (int) ($posOld['id_cliente'] ?? 0);
$oldMetodo  = (string) ($posOld['metodo_pago'] ?? 'EFECTIVO');
$oldIgtf    = !empty($posOld['igtf']);
$oldItems   = (string) ($posOld['items'] ?? '[]');

$metodos = [
    'EFECTIVO'      => 'Efectivo',
    'PAGO_MOVIL'    => 'Pago móvil',
    'PUNTO_VENTA'   => 'Punto de venta',
    'TRANSFERENCIA' => 'Transferencia',
    'ZELLE'         => 'Zelle',
    'MIXTO'         => 'Mixto',
];

require __DIR__ . '/../layout/header.php';
?>

<?php if (!$tasa): ?>
    <div class="alert alert-error">
        No hay una tasa BCV registrada. Un administrador debe registrar la tasa del día antes de facturar.
    </div>
<?php elseif (!empty($tasaDesactualizada)): ?>
    <div class="alert alert-warn">
        <span>
            La tasa BCV vigente es del
            <?= e(date('d/m/Y H:i', strtotime((string) $tasa['fecha_vigencia']))) ?>.
            <?php if (is_admin()): ?>
                <a href="<?= e(url('tasas')) ?>">Actualiza la tasa del día</a> antes de emitir nuevas ventas.
            <?php else: ?>
                Solicita al administrador que actualice la tasa del día.
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!$clientes): ?>
    <div class="alert alert-warn">
        No hay clientes registrados. <a href="<?= e(url('clientes')) ?>">Registra un cliente</a> para poder emitir facturas.
    </div>
<?php endif; ?>

<?php if (!$productos): ?>
    <div class="alert alert-warn">
        No hay productos activos. <a href="<?= e(url('productos')) ?>">Crea un producto</a> para comenzar a vender.
    </div>
<?php endif; ?>

<div class="pos" data-pos data-tasa="<?= e(input_number($tasaValor, 4)) ?>"
     data-carrito-inicial="<?= e($oldItems) ?>">

    <section class="panel pos-catalogo">
        <div class="panel-head">
            <div>
                <h2>Productos</h2>
                <p class="panel-sub">Haz clic en «Agregar» o busca por nombre/código.</p>
            </div>
            <input type="search" class="search" data-pos-buscar
                   placeholder="Buscar producto..." autocomplete="off">
        </div>

        <?php if ($productos): ?>
            <div class="table-wrap pos-tabla">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="num">Precio</th>
                            <th class="num">Stock</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $stock = (float) $p['stock_actual'];
                        $minimo = (float) $p['stock_minimo'];
                        $claseStock = $stock <= 0 ? 'badge-danger' : ($stock <= $minimo ? 'badge-warn' : 'badge-ok');
                        ?>
                        <tr data-producto
                            data-id="<?= (int) $p['id_producto'] ?>"
                            data-nombre="<?= e($p['nombre_producto']) ?>"
                            data-precio="<?= e(input_number($p['precio_venta_usd'], 3)) ?>"
                            data-iva="<?= e(input_number($p['alicuota_iva'], 2)) ?>"
                            data-unidad="<?= e($p['unidad_medida']) ?>"
                            data-stock="<?= e(input_number($p['stock_actual'], 3)) ?>">
                            <td>
                                <strong><?= e($p['nombre_producto']) ?></strong>
                                <small class="muted"><?= e($p['codigo_qr_barras']) ?> · <?= e($p['unidad_medida']) ?></small>
                            </td>
                            <td class="num">
                                $ <?= money($p['precio_venta_usd']) ?>
                                <small class="muted">Bs <?= money((float) $p['precio_venta_usd'] * $tasaValor) ?></small>
                            </td>
                            <td class="num">
                                <span class="badge <?= $claseStock ?>"><?= cantidad($stock) ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-agregar
                                    <?= $stock <= 0 ? 'disabled' : '' ?>>
                                    Agregar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty-icon">▣</span>
                <p>No hay productos para vender.</p>
                <a class="btn btn-primary" href="<?= e(url('productos')) ?>">Crear producto</a>
            </div>
        <?php endif; ?>
    </section>

    <aside class="panel pos-carrito">
        <div class="panel-head">
            <div>
                <h2>Carrito</h2>
                <p class="panel-sub">Los totales se recalculan en el servidor al emitir.</p>
            </div>
            <span class="badge badge-info" data-contador>0 ítems</span>
        </div>

        <div class="carrito-vacio" data-vacio>
            Agrega productos para comenzar la venta.
        </div>

        <div class="table-wrap" data-carrito-wrap hidden>
            <table class="tabla-carrito">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="num">Cant.</th>
                        <th class="num">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-carrito></tbody>
            </table>
        </div>

        <form method="POST" action="<?= e(url('facturacion')) ?>" class="pos-form" data-form-venta>
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="emitir">
            <input type="hidden" name="token_emision" value="<?= e($tokenEmision ?? '') ?>">
            <input type="hidden" name="items" value="[]" data-items>

            <label class="field">
                <span>Cliente</span>
                <select name="id_cliente" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int) $c['id_cliente'] ?>"
                            <?= $oldCliente === (int) $c['id_cliente'] ? 'selected' : '' ?>>
                            <?= e($c['rif_cedula']) ?> · <?= e($c['nombre_razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Método de pago</span>
                <select name="metodo_pago" data-metodo>
                    <?php foreach ($metodos as $codigo => $etiqueta): ?>
                        <option value="<?= e($codigo) ?>" <?= $oldMetodo === $codigo ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="check">
                <input type="checkbox" name="igtf" value="1" data-igtf <?= $oldIgtf ? 'checked' : '' ?>>
                Pago en divisa (IGTF 3%)
            </label>

            <div class="resumen">
                <div><span>Tasa BCV</span><strong>Bs <?= numero($tasaValor, 4) ?></strong></div>
                <div><span>Exento</span><strong data-resumen-exento>Bs 0,00</strong></div>
                <div><span>Base imponible</span><strong data-resumen-base>Bs 0,00</strong></div>
                <div><span>IVA</span><strong data-resumen-iva>Bs 0,00</strong></div>
                <div data-resumen-igtf-row <?= $oldIgtf ? '' : 'hidden' ?>>
                    <span>IGTF 3%</span><strong data-resumen-igtf>Bs 0,00</strong>
                </div>
                <div class="total"><span>Total</span><strong data-resumen-total-ves>Bs 0,00</strong></div>
                <div class="total-usd"><span>Equivalente</span><strong data-resumen-total-usd>$ 0,00</strong></div>
            </div>

            <button type="submit" class="btn btn-success btn-block" data-emitir disabled>
                Emitir factura
            </button>
        </form>
    </aside>
</div>

<?php $scripts = '<script src="' . e(url('assets/js/facturacion.js')) . '"></script>'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
