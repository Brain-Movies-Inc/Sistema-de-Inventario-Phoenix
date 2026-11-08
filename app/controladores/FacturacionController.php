<?php
declare(strict_types=1);

/**
 * Facturación: listado, punto de venta, comprobante, emisión y anulación.
 */
final class FacturacionController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new FacturaModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        // Compatibilidad con enlaces antiguos que usaban ?accion=...
        $accionGet = (string) ($_GET['accion'] ?? '');

        if ($accionGet === 'nuevo' || $accionGet === 'nueva') {
            $this->redirigir('facturacion/nueva');
        }

        if ($accionGet === 'ver') {
            $idFactura = (int) ($_GET['id'] ?? 0);

            if ($idFactura > 0) {
                $this->redirigir('facturacion/ver/' . $idFactura);
            }
        }

        try {
            [$estado, $busqueda, $desde, $hasta] = $this->filtrosSolicitados();
        } catch (Throwable $e) {
            $this->fallo($e, 'aplicar los filtros del historial de ventas', 'facturacion');
        }

        $tasa = $modelo->tasaVigente();

        $this->render('facturacion/index', [
            'titulo'             => 'Historial de ventas',
            'subtitulo'          => 'Facturas emitidas, filtros y descargas',
            'activo'             => 'facturacion',
            'estado'             => $estado,
            'busqueda'           => $busqueda,
            'desde'              => $desde,
            'hasta'              => $hasta,
            'facturas'           => $modelo->listar($estado, $busqueda, 100, $desde, $hasta),
            'resumenHoy'         => $modelo->resumenHoy(),
            'tasa'               => $tasa,
            'tasaDesactualizada' => tasa_desactualizada($tasa),
        ]);
    }

    /**
     * Descarga el historial de ventas en CSV (resumen de facturas o detalle
     * de líneas), respetando los mismos filtros del listado.
     */
    public function exportar(): void
    {
        $this->exigirLogin();

        $tipo = (string) ($_GET['tipo'] ?? 'resumen');

        if (!in_array($tipo, ['resumen', 'detalle'], true)) {
            $this->aviso('error', 'El tipo de exportación solicitado no es válido.');
            $this->redirigir('facturacion');
        }

        try {
            [$estado, $busqueda, $desde, $hasta] = $this->filtrosSolicitados();
        } catch (Throwable $e) {
            $this->fallo($e, 'exportar el historial de ventas', 'facturacion');
        }

        $modelo = new FacturaModel();

        if ($tipo === 'detalle') {
            $this->descargarCsv(
                'historial_ventas_detalle',
                [
                    'Factura', 'Control fiscal', 'Fecha', 'Cliente', 'RIF/Cédula',
                    'Cajero', 'Código', 'Producto', 'Unidad', 'Cantidad/Peso',
                    'Precio USD', 'Precio Bs', 'Alícuota IVA %', 'IVA Bs',
                    'Subtotal Bs', 'Estado',
                ],
                $modelo->exportarDetalle($estado, $busqueda, $desde, $hasta),
                static function (array $f): array {
                    return [
                        $f['nro_factura_consecutivo'],
                        $f['nro_control_fiscal'],
                        self::fechaCsv($f['fecha_emision']),
                        $f['cliente'],
                        $f['rif_cedula'],
                        $f['cajero'],
                        $f['codigo_qr_barras'],
                        $f['nombre_producto'],
                        $f['unidad_medida'],
                        cantidad((float) $f['cantidad_o_peso']),
                        number_format((float) $f['precio_unitario_usd'], 3, ',', '.'),
                        number_format((float) $f['precio_unitario_ves'], 2, ',', '.'),
                        number_format((float) $f['alicuota_iva_item'], 2, ',', '.'),
                        number_format((float) $f['monto_iva_item_ves'], 2, ',', '.'),
                        number_format((float) $f['subtotal_item_ves'], 2, ',', '.'),
                        $f['estado'],
                    ];
                }
            );

            return;
        }

        $this->descargarCsv(
            'historial_ventas_resumen',
            [
                'Factura', 'Control fiscal', 'Fecha', 'Cliente', 'RIF/Cédula',
                'Cajero', 'Método de pago', 'Estado', 'Subtotal exento Bs',
                'Base imponible Bs', 'IVA Bs', 'IGTF Bs', 'Total Bs',
                'Total USD', 'Tasa BCV aplicada',
            ],
            $modelo->exportarResumen($estado, $busqueda, $desde, $hasta),
            static function (array $f): array {
                return [
                    $f['nro_factura_consecutivo'],
                    $f['nro_control_fiscal'],
                    self::fechaCsv($f['fecha_emision']),
                    $f['cliente'],
                    $f['rif_cedula'],
                    $f['cajero'],
                    metodo_pago_label((string) $f['metodo_pago']),
                    $f['estado'],
                    number_format((float) $f['subtotal_exento_ves'], 2, ',', '.'),
                    number_format((float) $f['base_imponible_ves'], 2, ',', '.'),
                    number_format((float) $f['monto_iva_ves'], 2, ',', '.'),
                    number_format((float) $f['monto_igtf_ves'], 2, ',', '.'),
                    number_format((float) $f['total_ves'], 2, ',', '.'),
                    number_format((float) $f['total_usd'], 2, ',', '.'),
                    numero((float) $f['tasa_bcv_aplicada'], 4),
                ];
            }
        );
    }

    public function nueva(): void
    {
        $this->exigirLogin();

        $modelo    = new FacturaModel();
        $tasa      = $modelo->tasaVigente();
        $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;

        $posOld = $_SESSION['pos_old'] ?? null;
        unset($_SESSION['pos_old']);

        $this->render('facturacion/pos', [
            'titulo'             => 'Nueva venta',
            'subtitulo'          => 'Punto de venta · Factura fiscal',
            'activo'             => 'facturacion',
            'tasa'               => $tasa,
            'tasaValor'          => $tasaValor,
            'tasaDesactualizada' => tasa_desactualizada($tasa),
            'clientes'           => $modelo->clientes(),
            'productos'          => $modelo->productosVendibles(),
            'posOld'             => $posOld,
        ]);
    }

    public function ver(int $idFactura): void
    {
        $this->exigirLogin();

        $modelo  = new FacturaModel();
        $factura = $modelo->obtener($idFactura);

        if (!$factura) {
            $this->aviso('error', 'La factura solicitada no existe.');
            $this->redirigir('facturacion');
        }

        $this->render('facturacion/ver', [
            'titulo'    => 'Factura ' . $factura['nro_factura_consecutivo'],
            'subtitulo' => 'Detalle y comprobante fiscal',
            'activo'    => 'facturacion',
            'factura'   => $factura,
            'lineas'    => $modelo->lineas($idFactura),
        ]);
    }

    /**
     * Descarga el comprobante como PDF real, generado en el servidor.
     *
     * Se evita el diálogo de impresión del navegador porque Chrome agrega al
     * pie la URL de la página (http://127.0.0.1:8000/...). Este PDF no incluye
     * esa ruta ni depende de la configuración de encabezados del navegador.
     */
    public function pdf(int $idFactura): void
    {
        [$factura, $lineas] = $this->comprobante($idFactura);

        $pdf = (new PdfFactura())->generar($factura, $lineas);

        // ?ver=1 abre el PDF dentro del navegador para imprimirlo sin el pie
        // que Chrome agrega a las páginas HTML (URL y fecha).
        $inline = (string) ($_GET['ver'] ?? '') === '1';

        $this->descargar(
            $pdf,
            'application/pdf',
            $this->nombreArchivo($factura, 'pdf'),
            $inline
        );
    }

    /**
     * Descarga el comprobante como imagen PNG, también generada en el servidor.
     *
     * Sirve para enviar la factura por WhatsApp o correo sin depender de que el
     * destinatario abra un PDF.
     */
    public function imagen(int $idFactura): void
    {
        [$factura, $lineas] = $this->comprobante($idFactura);

        try {
            $png = (new ImagenFactura())->generar($factura, $lineas);
        } catch (Throwable $e) {
            $this->aviso('error', error_usuario($e, 'generar la imagen del comprobante'));
            $this->redirigir('facturacion/ver/' . (int) $factura['id_factura']);
        }

        $this->descargar(
            $png,
            'image/png',
            $this->nombreArchivo($factura, 'png')
        );
    }

    /**
     * Carga la factura y sus líneas exigiendo sesión iniciada.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function comprobante(int $idFactura): array
    {
        $this->exigirLogin();

        $modelo  = new FacturaModel();
        $factura = $modelo->obtener($idFactura);

        if (!$factura) {
            $this->aviso('error', 'La factura solicitada no existe.');
            $this->redirigir('facturacion');
        }

        return [$factura, $modelo->lineas($idFactura)];
    }

    /**
     * @param array<string, mixed> $factura
     */
    private function nombreArchivo(array $factura, string $extension): string
    {
        $numero = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $factura['nro_factura_consecutivo']);

        return 'Factura_' . $numero . '_' . date('Ymd') . '.' . $extension;
    }

    private function descargar(string $contenido, string $tipo, string $archivo, bool $inline = false): void
    {
        $disposicion = $inline ? 'inline' : 'attachment';

        header('Content-Type: ' . $tipo);
        header('Content-Disposition: ' . $disposicion . '; filename="' . $archivo . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $contenido;
        exit;
    }

    /**
     * Lee y valida los filtros del historial de ventas.
     *
     * @return array{0: string, 1: string, 2: ?string, 3: ?string}
     */
    private function filtrosSolicitados(): array
    {
        $estado = (string) ($_GET['estado'] ?? '');

        if (!in_array($estado, ['EMITIDA', 'ANULADA'], true)) {
            $estado = '';
        }

        $busqueda = trim((string) ($_GET['q'] ?? ''));

        if (mb_strlen($busqueda) > 100) {
            $busqueda = mb_substr($busqueda, 0, 100);
        }

        $desde = $this->fechaFiltro($_GET['desde'] ?? '');
        $hasta = $this->fechaFiltro($_GET['hasta'] ?? '');

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new InvalidArgumentException(
                'La fecha «desde» no puede ser posterior a la fecha «hasta».'
            );
        }

        return [$estado, $busqueda, $desde, $hasta];
    }

    private function fechaFiltro($valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('Una de las fechas del filtro no es válida.');
        }

        return $fecha->format('Y-m-d');
    }

    /**
     * Envía un CSV con BOM UTF-8 y separador «;» para que Excel lo abra bien.
     */
    private function descargarCsv(string $nombre, array $cabeceras, array $filas, callable $mapear): void
    {
        $archivo = $nombre . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $salida = fopen('php://output', 'w');

        if ($salida === false) {
            $this->aviso('error', 'No se pudo generar el archivo de descarga.');
            $this->redirigir('facturacion');
        }

        // BOM para que Excel reconozca el archivo como UTF-8.
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, array_map([$this, 'celdaCsv'], $cabeceras), ';', '"', '');

        foreach ($filas as $fila) {
            fputcsv(
                $salida,
                array_map([$this, 'celdaCsv'], $mapear($fila)),
                ';',
                '"',
                ''
            );
        }

        fclose($salida);
    }

    /**
     * Evita que un nombre, código o comentario se interprete como fórmula al
     * abrir el CSV en Excel/LibreOffice (inyección de fórmulas).
     */
    private static function celdaCsv($valor): string
    {
        $texto = (string) $valor;

        if ($texto === '' || is_numeric($texto)) {
            return $texto;
        }

        // Números con formato venezolano: 1.234,56 / 44,000 / -2,5.
        $normalizado = str_replace(['.', ','], ['', '.'], $texto);

        if (is_numeric($normalizado)) {
            return $texto;
        }

        if (strpos("=+-@\t\r", $texto[0]) !== false) {
            return "'" . $texto;
        }

        return $texto;
    }

    private static function fechaCsv($fecha): string
    {
        $timestamp = strtotime((string) $fecha);

        return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : (string) $fecha;
    }

    private function procesarPost(FacturaModel $modelo): void
    {
        csrf_verify();

        $accion = (string) ($_POST['accion'] ?? '');

        if (in_array($accion, ['anular', 'eliminar'], true) && !is_admin()) {
            $this->aviso('error', 'Solo un administrador puede anular o eliminar facturas.');
            $this->redirigir('facturacion');
        }

        try {
            if ($accion === 'emitir') {
                $items = json_decode((string) ($_POST['items'] ?? '[]'), true);

                if (!is_array($items)) {
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
                    current_user_id()
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

                $modelo->anular($idFactura, current_user_id());
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
            $contexto = match ($accion) {
                'emitir'   => 'emitir la factura',
                'eliminar' => 'eliminar la factura',
                default    => 'anular la factura',
            };

            $this->aviso('error', error_usuario($e, $contexto));

            if ($accion === 'emitir') {
                $_SESSION['pos_old'] = [
                    'items'       => (string) ($_POST['items'] ?? '[]'),
                    'id_cliente'  => (int) ($_POST['id_cliente'] ?? 0),
                    'metodo_pago' => (string) ($_POST['metodo_pago'] ?? 'EFECTIVO'),
                    'igtf'        => isset($_POST['igtf']),
                ];

                $this->redirigir('facturacion/nueva');
            }

            $this->redirigir('facturacion');
        }
    }
}
