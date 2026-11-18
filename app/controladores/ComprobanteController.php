<?php
declare(strict_types=1);

/**
 * Comprobantes de factura: detalle, PDF e imagen PNG.
 */
final class ComprobanteController extends BaseController
{
    public function ver(int $idFactura): void
    {
        $this->exigirLogin();

        try {
            [$factura, $lineas, $pagos] = $this->comprobante($idFactura);
        } catch (Throwable $e) {
            $this->fallo($e, 'cargar la factura', 'facturacion');
        }

        $this->render('facturacion/ver', [
            'titulo'    => 'Factura ' . $factura['nro_factura_consecutivo'],
            'subtitulo' => 'Detalle y comprobante fiscal',
            'activo'    => 'facturacion',
            'factura'   => $factura,
            'lineas'    => $lineas,
            'pagos'     => $pagos,
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
        try {
            [$factura, $lineas, $pagos] = $this->comprobante($idFactura);
            $pdf = (new PdfFactura())->generar($factura, $lineas, $pagos);
        } catch (Throwable $e) {
            $this->fallo($e, 'generar el PDF del comprobante', 'facturacion');
        }

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
        try {
            [$factura, $lineas, $pagos] = $this->comprobante($idFactura);
            $png = (new ImagenFactura())->generar($factura, $lineas, $pagos);
        } catch (Throwable $e) {
            $this->fallo($e, 'generar la imagen del comprobante', 'facturacion/ver/' . $idFactura);
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
     * @return array{
     *   0: array<string, mixed>,
     *   1: array<int, array<string, mixed>>,
     *   2: array<int, array<string, mixed>>
     * }
     */
    private function comprobante(int $idFactura): array
    {
        $this->exigirLogin();

        $modelo  = new FacturaConsultaModel();
        $factura = $modelo->obtener($idFactura);

        if (!$factura) {
            $this->aviso('error', 'La factura solicitada no existe.');
            $this->redirigir('facturacion');
        }

        if (!is_admin() && (int) ($factura['id_usuario'] ?? 0) !== current_user_id()) {
            $this->aviso('error', 'La factura solicitada no existe.');
            $this->redirigir('facturacion');
        }

        return [
            $factura,
            $modelo->lineas($idFactura),
            $modelo->pagos($idFactura),
        ];
    }

    /**
     * @param array<string, mixed> $factura
     */
    private function nombreArchivo(array $factura, string $extension): string
    {
        $numero = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $factura['nro_factura_consecutivo']);
        $fecha  = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) ($factura['fecha_emision'] ?? '')
        );
        $sufijo = $fecha !== false ? $fecha->format('Ymd') : date('Ymd');

        return 'Factura_' . $numero . '_' . $sufijo . '.' . $extension;
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
}
