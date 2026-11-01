<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/FacturaModel.php';

$modelo = new FacturaModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $accionPost = (string) ($_POST['accion'] ?? '');

    if ($accionPost === 'anular' && !is_admin()) {
        flash('error', 'Solo un administrador puede anular facturas.');
        redirect(url('facturacion'));
    }

    try {
        if ($accionPost === 'emitir') {
            $itemsJson = (string) ($_POST['items'] ?? '[]');
            $items     = json_decode($itemsJson, true);

            if (!is_array($items)) {
                throw new InvalidArgumentException('El carrito no tiene un formato válido.');
            }

            $idCliente  = (int) ($_POST['id_cliente'] ?? 0);
            $metodoPago = (string) ($_POST['metodo_pago'] ?? 'EFECTIVO');
            $aplicaIgtf = isset($_POST['igtf']);

            if ($idCliente <= 0) {
                throw new InvalidArgumentException('Selecciona un cliente.');
            }

            $resultado = $modelo->emitir(
                $items,
                $idCliente,
                $metodoPago,
                $aplicaIgtf,
                current_user_id()
            );

            flash(
                'ok',
                'Factura ' . $resultado['nro_factura'] . ' emitida por Bs ' .
                money($resultado['total_ves']) . ' ($ ' . money($resultado['total_usd']) . ').'
            );

            redirect(url('facturacion/ver/' . $resultado['id_factura']));
        }

        if ($accionPost === 'anular') {
            $idFactura = (int) ($_POST['id_factura'] ?? 0);

            if ($idFactura <= 0) {
                throw new InvalidArgumentException('Factura inválida.');
            }

            $modelo->anular($idFactura, current_user_id());
            flash('ok', 'Factura anulada. El stock fue devuelto al inventario.');

            redirect(url('facturacion/ver/' . $idFactura));
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());

        if ($accionPost === 'emitir') {
            $_SESSION['pos_old'] = [
                'items'       => (string) ($_POST['items'] ?? '[]'),
                'id_cliente'  => (int) ($_POST['id_cliente'] ?? 0),
                'metodo_pago' => (string) ($_POST['metodo_pago'] ?? 'EFECTIVO'),
                'igtf'        => isset($_POST['igtf']),
            ];
            redirect(url('facturacion/nueva'));
        }

        redirect(url('facturacion'));
    }
}

$accion = (string) ($_GET['accion'] ?? 'listar');

if ($accion === 'nuevo') {
    $tasa      = $modelo->tasaVigente();
    $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;
    $clientes  = $modelo->clientes();
    $productos = $modelo->productosVendibles();

    $posOld = $_SESSION['pos_old'] ?? null;
    unset($_SESSION['pos_old']);

    $titulo    = 'Nueva venta';
    $subtitulo = 'Punto de venta · Factura fiscal';
    $activo    = 'facturacion';

    require __DIR__ . '/../vistas/facturacion/pos.php';
    exit;
}

if ($accion === 'ver') {
    $idFactura = (int) ($_GET['id'] ?? 0);
    $factura   = $modelo->obtener($idFactura);

    if (!$factura) {
        flash('error', 'La factura solicitada no existe.');
        redirect(url('facturacion'));
    }

    $lineas = $modelo->lineas($idFactura);

    $titulo    = 'Factura ' . $factura['nro_factura_consecutivo'];
    $subtitulo = 'Detalle y comprobante fiscal';
    $activo    = 'facturacion';

    require __DIR__ . '/../vistas/facturacion/ver.php';
    exit;
}

$estado    = (string) ($_GET['estado'] ?? '');
$busqueda  = trim((string) ($_GET['q'] ?? ''));
$facturas  = $modelo->listar($estado, $busqueda);
$resumenHoy = $modelo->resumenHoy();
$tasa      = $modelo->tasaVigente();

$titulo    = 'Facturación';
$subtitulo = 'Ventas emitidas y comprobantes fiscales';
$activo    = 'facturacion';

require __DIR__ . '/../vistas/facturacion/index.php';
