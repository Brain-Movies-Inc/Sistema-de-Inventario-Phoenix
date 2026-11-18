<?php
declare(strict_types=1);

/**
 * Facturación: historial de ventas, filtros y exportación CSV.
 */
final class FacturacionController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new FacturaConsultaModel();
        $idUsuarioAlcance = is_admin() ? null : current_user_id();

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

        try {
            $tasa       = $modelo->tasaVigente();
            $facturas   = $modelo->listar($estado, $busqueda, 100, $desde, $hasta, $idUsuarioAlcance);
            $resumenHoy = $modelo->resumenHoy($idUsuarioAlcance);
        } catch (Throwable $e) {
            $this->fallo($e, 'cargar el historial de ventas', 'panel');
        }

        $this->render('facturacion/index', [
            'titulo'             => 'Historial de ventas',
            'subtitulo'          => 'Facturas emitidas, filtros y descargas',
            'activo'             => 'facturacion',
            'estado'             => $estado,
            'busqueda'           => $busqueda,
            'desde'              => $desde,
            'hasta'              => $hasta,
            'facturas'           => $facturas,
            'resumenHoy'         => $resumenHoy,
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
        $this->exigirAdmin();

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

        $modelo = new FacturaExportModel();

        if ($tipo === 'detalle') {
            try {
                $filas = $modelo->exportarDetalle($estado, $busqueda, $desde, $hasta);
            } catch (Throwable $e) {
                $this->fallo($e, 'exportar el detalle de ventas', 'facturacion');
            }

            $this->descargarCsv(
                'historial_ventas_detalle',
                [
                    'Factura', 'Control fiscal', 'Fecha', 'Cliente', 'RIF/Cédula',
                    'Cajero', 'Código', 'Producto', 'Unidad', 'Cantidad/Peso',
                    'Precio USD', 'Precio Bs', 'Alícuota IVA %', 'IVA Bs',
                    'Subtotal Bs', 'Estado',
                ],
                $filas,
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

        try {
            $filas = $modelo->exportarResumen($estado, $busqueda, $desde, $hasta);
        } catch (Throwable $e) {
            $this->fallo($e, 'exportar el resumen de ventas', 'facturacion');
        }

        $this->descargarCsv(
            'historial_ventas_resumen',
            [
                'Factura', 'Control fiscal', 'Fecha', 'Cliente', 'RIF/Cédula',
                'Cajero', 'Método de pago', 'Estado', 'Subtotal exento Bs',
                'Base imponible Bs', 'IVA Bs', 'IGTF Bs', 'Total Bs',
                'Total USD', 'Tasa BCV aplicada',
            ],
            $filas,
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
     *
     * @param iterable<int, array<string, mixed>> $filas
     */
    private function descargarCsv(string $nombre, array $cabeceras, iterable $filas, callable $mapear): void
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

        // Se revisa el primer carácter visible: Excel/Calc pueden ignorar
        // espacios, tabuladores, saltos de línea o el BOM antes de una fórmula.
        $visible = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        $visible = ltrim((string) $visible, " \t\r\n\v\f\0");

        if ($visible !== '' && strpos("=+-@\t\r\n\v\f\0", $visible[0]) !== false) {
            return "'" . $texto;
        }

        return $texto;
    }

    private static function fechaCsv($fecha): string
    {
        $timestamp = strtotime((string) $fecha);

        return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : (string) $fecha;
    }
}
