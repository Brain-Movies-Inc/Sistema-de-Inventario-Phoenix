<?php
declare(strict_types=1);

/**
 * Generador de PDF para el comprobante de factura.
 *
 * No usa librerías externas ni el diálogo de impresión del navegador. Así el
 * archivo descargado no incluye la URL ni los encabezados que Chrome agrega al
 * imprimir. Usa las fuentes base Helvetica/Helvetica-Bold con codificación
 * Windows-1252, suficiente para el español.
 */
final class PdfFactura
{
    private const ANCHO  = 595.28; // A4 en puntos
    private const ALTO   = 841.89;
    private const MARGEN = 40.0;

    /** @var array<int, string> */
    private array $paginas = [];
    private string $actual = '';
    private float $y = 0.0;
    private array $factura = [];

    /**
     * @param array<string, mixed> $factura
     * @param array<int, array<string, mixed>> $lineas
     */
    public function generar(array $factura, array $lineas): string
    {
        $this->factura = $factura;
        $this->paginas = [];
        $this->actual  = '';
        $this->y       = self::ALTO - self::MARGEN;

        $this->encabezado();
        $this->datosCliente();
        $this->tabla($lineas);
        $this->totales();
        $this->pie();

        $this->paginas[] = $this->actual;

        return $this->construirPdf();
    }

    private function encabezado(): void
    {
        $f = $this->factura;

        $this->texto(self::MARGEN, $this->y, (string) EMPRESA_NOMBRE, 16, true);
        $this->texto(555, $this->y, 'FACTURA', 16, true, 'right');

        $this->y -= 14;
        $this->texto(self::MARGEN, $this->y, 'RIF: ' . EMPRESA_RIF, 8);
        $this->texto(555, $this->y, 'N° ' . (string) $f['nro_factura_consecutivo'], 11, true, 'right');

        $this->y -= 11;
        $this->texto(self::MARGEN, $this->y, (string) EMPRESA_DIRECCION, 8);
        $this->texto(555, $this->y, 'Control: ' . (string) $f['nro_control_fiscal'], 8, false, 'right');

        $this->y -= 9;
        $this->texto(555, $this->y, 'Código: ' . (string) $f['codigo_verificacion'], 7, false, 'right');

        $this->y -= 11;
        if (EMPRESA_TELEFONO !== '') {
            $this->texto(self::MARGEN, $this->y, 'Teléfono: ' . EMPRESA_TELEFONO, 8);
        }
        $this->texto(
            555,
            $this->y,
            date('d/m/Y H:i', strtotime((string) $f['fecha_emision'])),
            8,
            false,
            'right'
        );

        $this->y -= 11;
        $this->texto(555, $this->y, (string) $f['estado'], 8, true, 'right');

        $this->y -= 12;
        $this->linea(self::MARGEN, $this->y, 555, $this->y);
        $this->y -= 20;
    }

    private function datosCliente(): void
    {
        $f = $this->factura;

        $this->texto(self::MARGEN, $this->y, 'Cliente', 7);
        $this->texto(300, $this->y, 'RIF / Cédula', 7);
        $this->y -= 11;
        $this->texto(self::MARGEN, $this->y, (string) $f['cliente'], 9, true);
        $this->texto(300, $this->y, (string) $f['rif_cedula'], 9, true);

        $this->y -= 17;
        $this->texto(self::MARGEN, $this->y, 'Dirección fiscal', 7);
        $this->texto(300, $this->y, 'Método de pago', 7);
        $this->y -= 11;
        $this->texto(self::MARGEN, $this->y, $this->recortar((string) ($f['direccion_fiscal'] ?? '—'), 240, 9), 9);
        $this->texto(300, $this->y, metodo_pago_label((string) $f['metodo_pago']), 9, true);

        $this->y -= 17;
        $this->texto(self::MARGEN, $this->y, 'Tasa BCV aplicada', 7);
        $this->texto(300, $this->y, 'Cajero', 7);
        $this->y -= 11;
        $this->texto(self::MARGEN, $this->y, 'Bs ' . numero($f['tasa_bcv_aplicada'], 4), 9, true);
        $this->texto(300, $this->y, (string) $f['cajero_nombre'], 9, true);

        $this->y -= 15;
        $this->linea(self::MARGEN, $this->y, 555, $this->y);
        $this->y -= 18;
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     */
    private function tabla(array $lineas): void
    {
        $this->cabeceraTabla();

        foreach ($lineas as $indice => $linea) {
            $this->asegurar(16);

            $this->texto(40, $this->y, (string) ($indice + 1), 8);
            $this->texto(
                58,
                $this->y,
                $this->recortar((string) ($linea['nombre_producto'] ?? ''), 232, 8),
                8
            );
            $this->texto(344, $this->y, cantidad($linea['cantidad_o_peso']) . ' ' . $linea['unidad_medida'], 8, false, 'right');
            $this->texto(391, $this->y, '$ ' . money($linea['precio_unitario_usd']), 8, false, 'right');
            $this->texto(445, $this->y, money($linea['precio_unitario_ves']), 8, false, 'right');
            $this->texto(475, $this->y, money($linea['alicuota_iva_item'], 2) . '%', 8, false, 'right');
            $this->texto(555, $this->y, money($linea['subtotal_item_ves']), 8, false, 'right');

            $this->y -= 14;
        }

        $this->linea(self::MARGEN, $this->y, 555, $this->y);
        $this->y -= 16;
    }

    private function cabeceraTabla(): void
    {
        // Fondo gris claro de la cabecera de la tabla.
        $this->rectangulo(self::MARGEN, $this->y - 3, 515, 13, 0.92);

        $this->texto(40, $this->y, '#', 7.5, true);
        $this->texto(58, $this->y, 'Producto', 7.5, true);
        $this->texto(344, $this->y, 'Cant.', 7.5, true, 'right');
        $this->texto(391, $this->y, 'P. USD', 7.5, true, 'right');
        $this->texto(445, $this->y, 'P. Bs', 7.5, true, 'right');
        $this->texto(475, $this->y, 'IVA', 7.5, true, 'right');
        $this->texto(555, $this->y, 'Subtotal Bs', 7.5, true, 'right');

        $this->y -= 14;
    }

    private function totales(): void
    {
        $f = $this->factura;

        $this->asegurar(110);
        $this->linea(300, $this->y, 555, $this->y);
        $this->y -= 15;

        $this->filaTotal('Subtotal exento', money($f['subtotal_exento_ves']));
        $this->filaTotal('Base imponible', money($f['base_imponible_ves']));
        $this->filaTotal('IVA', money($f['monto_iva_ves']));

        if ((float) $f['monto_igtf_ves'] > 0) {
            $this->filaTotal('IGTF 3%', money($f['monto_igtf_ves']));
        }

        $this->y -= 3;
        $this->linea(300, $this->y, 555, $this->y);
        $this->y -= 15;

        $this->texto(500, $this->y, 'Total Bs', 10, true, 'right');
        $this->texto(555, $this->y, money($f['total_ves']), 10, true, 'right');
        $this->y -= 15;

        $this->texto(500, $this->y, 'Total USD', 10, true, 'right');
        $this->texto(555, $this->y, '$ ' . money($f['total_usd']), 10, true, 'right');
        $this->y -= 20;
    }

    private function filaTotal(string $etiqueta, string $valor): void
    {
        $this->texto(500, $this->y, $etiqueta, 9, false, 'right');
        $this->texto(555, $this->y, $valor, 9, false, 'right');
        $this->y -= 14;
    }

    private function pie(): void
    {
        $this->asegurar(45);
        $this->linea(self::MARGEN, $this->y, 555, $this->y);
        $this->y -= 13;

        $this->texto(
            self::MARGEN,
            $this->y,
            'Gracias por su compra. Documento generado por ' . EMPRESA_NOMBRE . '.',
            8
        );

        if ((string) $this->factura['estado'] === 'ANULADA') {
            $this->y -= 14;
            $this->texto(self::MARGEN, $this->y, 'FACTURA ANULADA', 10, true);
        }

        $this->y -= 12;
        $this->texto(
            self::MARGEN,
            $this->y,
            'Emisión: ' . (string) $this->factura['tipo_emision']
                . ' · Cajero: ' . (string) $this->factura['cajero_login'],
            7
        );
    }

    private function asegurar(float $altura): void
    {
        if ($this->y - $altura >= 55) {
            return;
        }

        $this->paginas[] = $this->actual;
        $this->actual    = '';
        $this->y         = self::ALTO - self::MARGEN;

        $this->texto(
            self::MARGEN,
            $this->y,
            'Factura N° ' . (string) $this->factura['nro_factura_consecutivo'] . ' (continuación)',
            10,
            true
        );
        $this->y -= 18;
        $this->cabeceraTabla();
    }

    /**
     * Dibuja texto. Con alineación «right» la coordenada $x es el borde derecho
     * del texto; con «center» es el centro. Así ninguna cadena se sale de la
     * hoja cuando se alinea a la derecha.
     */
    private function texto(
        float $x,
        float $y,
        string $texto,
        float $tamano = 9,
        bool $negrita = false,
        string $alineacion = 'left'
    ): void {
        $texto = $this->codificar($texto);
        $ancho = $this->anchoTexto($texto, $tamano, $negrita);

        if ($alineacion === 'right') {
            $x -= $ancho;
        } elseif ($alineacion === 'center') {
            $x -= $ancho / 2;
        }

        $this->actual .= sprintf(
            "BT /%s %s Tf %s %s Td (%s) Tj ET\n",
            $negrita ? 'F2' : 'F1',
            $this->n($tamano),
            $this->n($x),
            $this->n($y),
            $this->escapar($texto)
        );
    }

    private function linea(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->actual .= sprintf(
            "0.6 w 0.55 g %s %s m %s %s l S 0 g\n",
            $this->n($x1),
            $this->n($y1),
            $this->n($x2),
            $this->n($y2)
        );
    }

    private function rectangulo(float $x, float $y, float $ancho, float $alto, float $gris): void
    {
        $this->actual .= sprintf(
            "%s g %s %s %s %s re f 0 g\n",
            $this->n($gris),
            $this->n($x),
            $this->n($y),
            $this->n($ancho),
            $this->n($alto)
        );
    }

    private function anchoTexto(string $texto, float $tamano, bool $negrita): float
    {
        return strlen($texto) * $tamano * ($negrita ? 0.55 : 0.50);
    }

    private function recortar(string $texto, float $ancho, float $tamano): string
    {
        $maximo = max(4, (int) floor($ancho / ($tamano * 0.50)));

        if (mb_strlen($texto) <= $maximo) {
            return $texto;
        }

        return mb_substr($texto, 0, $maximo - 3) . '...';
    }

    private function codificar(string $texto): string
    {
        $texto = strtr($texto, [
            '—' => '-',
            '–' => '-',
            '…' => '...',
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
            '·' => '-',
            '•' => '-',
        ]);

        $convertido = @mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

        if (!is_string($convertido)) {
            $convertido = preg_replace('/[^\x20-\x7E]/', '?', $texto) ?? '';
        }

        // Ningún salto de línea o carácter de control puede romper el flujo PDF.
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $convertido) ?? '';
    }

    private function escapar(string $texto): string
    {
        return str_replace(
            ["\\", '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $texto
        );
    }

    private function n(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private function construirPdf(): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $kids  = [];
        $numero = 5;

        foreach ($this->paginas as $contenido) {
            $objPagina   = $numero++;
            $objContenido = $numero++;

            $kids[] = $objPagina . ' 0 R';

            $objetos[$objPagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->n(self::ANCHO),
                $this->n(self::ALTO),
                $objContenido
            );

            $objetos[$objContenido] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($contenido),
                $contenido
            );
        }

        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count '
            . count($this->paginas) . ' >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objetos as $indice => $objeto) {
            $offsets[$indice] = strlen($pdf);
            $pdf .= $indice . " 0 obj\n" . $objeto . "\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $total      = count($objetos) + 1;

        $pdf .= "xref\n0 " . $total . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . $total . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $inicioXref . "\n%%EOF";

        return $pdf;
    }
}
