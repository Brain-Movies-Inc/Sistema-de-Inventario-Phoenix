<?php
declare(strict_types=1);

/**
 * Acciones POST sobre facturas: emitir, anular y eliminar.
 */
final class VentaController extends BaseController
{
    public function procesar(): void
    {
        $this->exigirLogin();

        $modelo = new FacturaModel();

        csrf_verify();

        $accion = (string) ($_POST['accion'] ?? '');

        if (in_array($accion, ['anular', 'eliminar'], true) && !is_admin()) {
            $this->aviso('error', 'Solo un administrador puede anular o eliminar facturas.');
            $this->redirigir('facturacion');
        }

        try {
            if ($accion === 'emitir') {
                TokenEmision::consumir();

                $items = json_decode((string) ($_POST['items'] ?? '[]'), true);

                if (!is_array($items) || !array_is_list($items)) {
                    throw new InvalidArgumentException('El carrito no tiene un formato válido.');
                }

                $idCliente = (int) ($_POST['id_cliente'] ?? 0);

                if ($idCliente <= 0) {
                    throw new InvalidArgumentException('Selecciona un cliente.');
                }

                $resultado = $modelo->emitir(
                    $items,
                    $idCliente,
                    (string) ($_POST['metodo_pago'] ?? 'EFECTIVO'),
                    isset($_POST['igtf']),
                    current_user_id(),
                    $this->tasaEsperadaSolicitada(),
                    $this->preciosEsperadosSolicitados(),
                    $this->montoSolicitado('pago_divisa_usd'),
                    $this->montoSolicitado('pago_bolivares_ves')
                );

                $this->aviso(
                    'ok',
                    'Factura ' . $resultado['nro_factura'] . ' emitida por Bs ' .
                    money($resultado['total_ves']) . ' ($ ' . money($resultado['total_usd']) . ').'
                );

                $this->redirigir('facturacion/ver/' . $resultado['id_factura']);
            }

            if ($accion === 'anular') {
                $idFactura = (int) ($_POST['id_factura'] ?? 0);

                if ($idFactura <= 0) {
                    throw new InvalidArgumentException('Factura inválida.');
                }

                $modelo->anular(
                    $idFactura,
                    current_user_id(),
                    (string) ($_POST['motivo_anulacion'] ?? '')
                );
                $this->aviso('ok', 'Factura anulada. El stock fue devuelto al inventario.');

                $this->redirigir('facturacion/ver/' . $idFactura);
            }

            if ($accion === 'eliminar') {
                $idFactura = (int) ($_POST['id_factura'] ?? 0);

                if ($idFactura <= 0) {
                    throw new InvalidArgumentException('Factura inválida.');
                }

                $modelo->eliminar($idFactura);
                $this->aviso('ok', 'Factura anulada eliminada. También se borraron sus líneas.');

                $this->redirigir('facturacion');
            }

            throw new InvalidArgumentException('La acción solicitada no es válida.');
        } catch (Throwable $e) {
            switch ($accion) {
                case 'emitir':
                    $contexto = 'emitir la factura';
                    break;
                case 'eliminar':
                    $contexto = 'eliminar la factura';
                    break;
                default:
                    $contexto = 'anular la factura';
                    break;
            }

            $this->aviso('error', error_usuario($e, $contexto));

            if ($accion === 'emitir') {
                $_SESSION['pos_old'] = [
                    'items'              => $this->itemsReintento((string) ($_POST['items'] ?? '[]')),
                    'id_cliente'         => (int) ($_POST['id_cliente'] ?? 0),
                    'metodo_pago'        => (string) ($_POST['metodo_pago'] ?? 'EFECTIVO'),
                    'igtf'               => isset($_POST['igtf']),
                    'pago_divisa_usd'    => (string) ($_POST['pago_divisa_usd'] ?? ''),
                    'pago_bolivares_ves' => (string) ($_POST['pago_bolivares_ves'] ?? ''),
                ];

                $this->redirigir('facturacion/nueva');
            }

            $this->redirigir('facturacion');
        }
    }

    /**
     * Tasa BCV con la que el POS calculó los totales mostrados.
     *
     * Es opcional para no romper formularios antiguos; cuando viene, el
     * modelo la compara con la tasa vigente antes de emitir.
     */
    private function tasaEsperadaSolicitada(): ?float
    {
        $valor = $_POST['tasa_aplicada'] ?? null;

        if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
            return null;
        }

        if (!is_numeric($valor)) {
            return null;
        }

        $tasa = (float) $valor;

        return $tasa > 0 ? $tasa : null;
    }

    /**
     * Precios en USD con los que el POS calculó cada línea del carrito.
     *
     * @return array<int, float>|null
     */
    private function preciosEsperadosSolicitados(): ?array
    {
        $json = $_POST['precios'] ?? null;

        if (!is_string($json) || $json === '') {
            return null;
        }

        $datos = json_decode($json, true);

        if (!is_array($datos)) {
            return null;
        }

        $precios = [];

        foreach ($datos as $id => $precio) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                continue;
            }

            if (!is_int($precio) && !is_float($precio)
                && !(is_string($precio) && is_numeric($precio))) {
                continue;
            }

            $idProducto = (int) $id;
            $valor      = (float) $precio;

            if ($idProducto > 0 && $valor >= 0) {
                $precios[$idProducto] = $valor;
            }
        }

        return $precios ?: null;
    }

    /**
     * Lee un monto de pago opcional (divisa o bolívares) enviado por el POS.
     */
    private function montoSolicitado(string $campo): ?float
    {
        $valor = $_POST[$campo] ?? null;

        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
            throw new InvalidArgumentException('Los montos de pago no tienen un formato válido.');
        }

        $texto = str_replace(',', '.', trim((string) $valor));

        if ($texto === '' || !is_numeric($texto)) {
            throw new InvalidArgumentException('Los montos de pago no tienen un formato válido.');
        }

        $monto = (float) $texto;

        if ($monto < 0) {
            throw new InvalidArgumentException('Los montos de pago no pueden ser negativos.');
        }

        return $monto;
    }

    /**
     * Guarda solo las líneas válidas del carrito para reconstruirlo cuando
     * falla la emisión. Evita reinyectar en la vista el JSON crudo del POST.
     */
    private function itemsReintento(string $json): string
    {
        $items = json_decode($json, true);

        if (!is_array($items)) {
            return '[]';
        }

        $limpios = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $idRaw   = $item['id_producto'] ?? null;
            $cantRaw = $item['cantidad'] ?? null;

            if (is_int($idRaw)) {
                $idProducto = $idRaw;
            } elseif (is_float($idRaw) && floor($idRaw) === $idRaw) {
                $idProducto = (int) $idRaw;
            } elseif (is_string($idRaw) && ctype_digit($idRaw)) {
                $idProducto = (int) $idRaw;
            } else {
                continue;
            }

            if (!is_int($cantRaw) && !is_float($cantRaw)
                && !(is_string($cantRaw) && is_numeric($cantRaw))) {
                continue;
            }

            $cantidad = round((float) $cantRaw, 3);

            if ($idProducto <= 0 || $cantidad <= 0 || $cantidad > 100000) {
                continue;
            }

            $limpios[] = [
                'id_producto' => $idProducto,
                'cantidad'    => $cantidad,
            ];

            if (count($limpios) >= 500) {
                break;
            }
        }

        return json_encode($limpios, JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
