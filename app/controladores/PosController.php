<?php
declare(strict_types=1);

/**
 * Punto de venta: formulario de nueva venta y catálogos remotos del POS.
 */
final class PosController extends BaseController
{
    /** Tamaño de página del catálogo del POS. */
    private const POS_PRODUCTOS_POR_PAGINA = 60;

    /** Clientes que se cargan de inicio en el POS; el resto se busca. */
    private const POS_CLIENTES_INICIALES = 100;

    public function nueva(): void
    {
        $this->exigirLogin();

        $posOld = $_SESSION['pos_old'] ?? null;
        unset($_SESSION['pos_old']);

        $idsOld     = $this->idsProductosReintento($posOld);
        $oldCliente = (int) ($posOld['id_cliente'] ?? 0);

        try {
            $modelo         = new FacturaConsultaModel();
            $tasa           = $modelo->tasaVigente();
            $clientes       = $modelo->clientes('', self::POS_CLIENTES_INICIALES);
            $productos      = $modelo->productosVendibles('', self::POS_PRODUCTOS_POR_PAGINA, 0);
            $totalProductos = $modelo->contarProductosVendibles('');

            // El carrito que se reintenta puede tener productos fuera de la
            // primera página: se agregan para que el JS pueda reconstruirlo.
            if ($idsOld) {
                $productos = $this->mezclarProductos(
                    $productos,
                    $modelo->productosPorIds($idsOld)
                );
            }

            if ($oldCliente > 0 && !in_array($oldCliente, array_map('intval', array_column($clientes, 'id_cliente')), true)) {
                $clienteOld = $modelo->clientePorId($oldCliente);

                if ($clienteOld) {
                    array_unshift($clientes, $clienteOld);
                }
            }
        } catch (Throwable $e) {
            $this->fallo($e, 'cargar el punto de venta', 'panel');
        }

        $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;

        $this->render('facturacion/pos', [
            'titulo'              => 'Nueva venta',
            'subtitulo'           => 'Punto de venta · Factura fiscal',
            'activo'              => 'facturacion',
            'tasa'                => $tasa,
            'tasaValor'           => $tasaValor,
            'tasaDesactualizada'  => tasa_desactualizada($tasa),
            'clientes'            => $clientes,
            'productos'           => $productos,
            'totalProductos'      => $totalProductos,
            'porPaginaProductos'  => self::POS_PRODUCTOS_POR_PAGINA,
            'posOld'              => $posOld,
            'tokenEmision'        => TokenEmision::generar(),
        ]);
    }

    /**
     * Catálogo paginado del POS para la búsqueda remota.
     */
    public function productos(): void
    {
        $this->exigirLogin();

        try {
            $busqueda = $this->busquedaSolicitada();
            $pagina   = max(1, (int) ($_GET['pagina'] ?? 1));
            $modelo   = new FacturaConsultaModel();
            $total    = $modelo->contarProductosVendibles($busqueda);
            $paginas  = max(1, (int) ceil($total / self::POS_PRODUCTOS_POR_PAGINA));
            $pagina   = min($pagina, $paginas);
            $offset   = ($pagina - 1) * self::POS_PRODUCTOS_POR_PAGINA;

            $productos = $modelo->productosVendibles(
                $busqueda,
                self::POS_PRODUCTOS_POR_PAGINA,
                $offset
            );

            $this->responderJson([
                'productos'  => array_map([$this, 'productoJson'], $productos),
                'total'      => $total,
                'pagina'     => $pagina,
                'paginas'    => $paginas,
                'por_pagina' => self::POS_PRODUCTOS_POR_PAGINA,
            ]);
        } catch (Throwable $e) {
            error_log('[De Todito Reyes] Error al buscar productos del POS: ' . $e->getMessage());
            $this->responderJson(['error' => 'No se pudo cargar el catálogo de productos.'], 500);
        }
    }

    /**
     * Búsqueda remota de clientes para el POS.
     */
    public function clientes(): void
    {
        $this->exigirLogin();

        try {
            $busqueda = $this->busquedaSolicitada();
            $modelo   = new FacturaConsultaModel();
            $clientes = $modelo->clientes($busqueda, 50);

            $this->responderJson([
                'clientes' => array_map(static function (array $c): array {
                    return [
                        'id_cliente'          => (int) $c['id_cliente'],
                        'rif_cedula'          => (string) $c['rif_cedula'],
                        'nombre_razon_social' => (string) $c['nombre_razon_social'],
                    ];
                }, $clientes),
            ]);
        } catch (Throwable $e) {
            error_log('[De Todito Reyes] Error al buscar clientes del POS: ' . $e->getMessage());
            $this->responderJson(['error' => 'No se pudo cargar la lista de clientes.'], 500);
        }
    }

    private function busquedaSolicitada(): string
    {
        $busqueda = trim((string) ($_GET['q'] ?? ''));

        if (mb_strlen($busqueda) > 100) {
            $busqueda = mb_substr($busqueda, 0, 100);
        }

        return $busqueda;
    }

    /**
     * @param array<string, mixed> $producto
     * @return array<string, mixed>
     */
    private function productoJson(array $producto): array
    {
        return [
            'id_producto'      => (int) $producto['id_producto'],
            'codigo_qr_barras' => (string) $producto['codigo_qr_barras'],
            'nombre_producto'  => (string) $producto['nombre_producto'],
            'unidad_medida'    => (string) $producto['unidad_medida'],
            'precio_venta_usd' => (float) $producto['precio_venta_usd'],
            'alicuota_iva'     => (float) $producto['alicuota_iva'],
            'stock_actual'     => (float) $producto['stock_actual'],
            'stock_minimo'     => (float) $producto['stock_minimo'],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function responderJson(array $datos, int $estado = 200): void
    {
        if (!headers_sent()) {
            http_response_code($estado);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Productos citados por el carrito que se reintenta.
     *
     * @return array<int, int>
     */
    private function idsProductosReintento($posOld): array
    {
        if (!is_array($posOld) || !isset($posOld['items']) || !is_string($posOld['items'])) {
            return [];
        }

        $items = json_decode($posOld['items'], true);

        if (!is_array($items)) {
            return [];
        }

        $ids = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id_producto'] ?? null;

            if (is_int($id)) {
                $ids[] = $id;
            } elseif (is_string($id) && ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0
        )));

        return array_slice($ids, 0, 500);
    }

    /**
     * Agrega a la página inicial los productos que faltan para el carrito.
     *
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $extra
     * @return array<int, array<string, mixed>>
     */
    private function mezclarProductos(array $base, array $extra): array
    {
        $vistos = [];

        foreach ($base as $producto) {
            $vistos[(int) $producto['id_producto']] = true;
        }

        foreach ($extra as $producto) {
            $id = (int) $producto['id_producto'];

            if (!isset($vistos[$id])) {
                $base[] = $producto;
                $vistos[$id] = true;
            }
        }

        return $base;
    }
}
