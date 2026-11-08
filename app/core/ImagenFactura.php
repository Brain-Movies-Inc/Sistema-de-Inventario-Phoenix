<?php
declare(strict_types=1);

/**
 * Generador de imagen PNG para el comprobante de factura.
 *
 * Usa la extensión GD (incluida en XAMPP) y las fuentes Roboto incluidas en
 * app/core/fuentes, sin CDN ni librerías de terceros. El resultado es una sola
 * imagen vertical con el mismo contenido del PDF, útil para enviarla por
 * WhatsApp o correo.
 */
final class ImagenFactura
{
    private const ANCHO   = 900.0;
    private const MARGEN  = 48.0;
    private const DERECHA = 852.0;

    /** @var array<int, array<string, mixed>> */
    private array $ops = [];
    private float $y = 0.0;
    /** @var array<string, mixed> */
    private array $factura = [];
    private string $fuenteRegular;
    private string $fuenteNegrita;

    private const TINTA   = [15, 23, 42];
    private const SUAVE   = [100, 116, 139];
    private const LINEA   = [226, 232, 240];
    private const BANDA   = [241, 245, 249];
    private const PRIMARIO = [15, 118, 110];
    private const EXITO   = [22, 163, 74];
    private const PELIGRO = [220, 38, 38];

    public function __construct()
    {
        $this->fuenteRegular = __DIR__ . '/fuentes/Roboto-Regular.ttf';
        $this->fuenteNegrita = __DIR__ . '/fuentes/Roboto-Bold.ttf';

        if (!function_exists('imagettftext') || !function_exists('imagepng')) {
            throw new RuntimeException(
                'La extensión GD de PHP no está habilitada; no se puede generar la imagen.'
            );
        }

        foreach ([$this->fuenteRegular, $this->fuenteNegrita] as $fuente) {
            if (!is_file($fuente)) {
                throw new RuntimeException('No se encontró la fuente del comprobante: ' . $fuente);
            }
        }
    }

    /**
     * @param array<string, mixed> $factura
     * @param array<int, array<string, mixed>> $lineas
     */
    public function generar(array $factura, array $lineas): string
    {
        $this->factura = $factura;
        $this->ops     = [];
        $this->y       = self::MARGEN;

        $this->encabezado();
        $this->datosCliente();
        $this->tabla($lineas);
        $this->totales();
        $this->pie();

        $alto = (int) ceil($this->y + self::MARGEN);

        return $this->renderizar($alto);
    }

    private function encabezado(): void
    {
        $f = $this->factura;

        $this->y += 34;
        $this->texto(self::MARGEN, (string) EMPRESA_NOMBRE, 32, true, 'left', 0, self::TINTA);
        $this->texto(self::DERECHA, 'FACTURA', 28, true, 'right', 0, self::PRIMARIO);

        $this->y += 22;
        $this->texto(self::MARGEN, 'RIF: ' . EMPRESA_RIF, 14, false, 'left', 0, self::SUAVE);
        $this->texto(
            self::DERECHA,
            'N° ' . (string) $f['nro_factura_consecutivo'],
            20,
            true,
            'right',
            0,
            self::TINTA
        );

        $this->y += 20;
        $this->texto(self::MARGEN, (string) EMPRESA_DIRECCION, 14, false, 'left', 0, self::SUAVE);
        $this->texto(
            self::DERECHA,
            'Control: ' . (string) $f['nro_control_fiscal'],
            14,
            false,
            'right',
            0,
            self::SUAVE
        );

        $this->y += 16;
        $this->texto(
            self::DERECHA,
            'Código: ' . (string) $f['codigo_verificacion'],
            13,
            false,
            'right',
            0,
            self::SUAVE
        );

        $this->y += 20;
        if (EMPRESA_TELEFONO !== '') {
            $this->texto(self::MARGEN, 'Teléfono: ' . EMPRESA_TELEFONO, 14, false, 'left', 0, self::SUAVE);
        }
        $this->texto(
            self::DERECHA,
            date('d/m/Y H:i', strtotime((string) $f['fecha_emision'])),
            14,
            false,
            'right',
            0,
            self::SUAVE
        );

        $this->y += 22;
        $estado  = (string) $f['estado'];
        $color   = $estado === 'ANULADA' ? self::PELIGRO : self::EXITO;
        $this->texto(self::DERECHA, $estado, 15, true, 'right', 0, $color);

        $this->y += 14;
        $this->separador();
        $this->y += 36;
    }

    private function datosCliente(): void
    {
        $f = $this->factura;

        $this->texto(self::MARGEN, 'Cliente', 13, false, 'left', 0, self::SUAVE);
        $this->texto(470, 'RIF / Cédula', 13, false, 'left', 0, self::SUAVE);
        $this->y += 22;
        $this->texto(
            self::MARGEN,
            $this->recortar((string) $f['cliente'], 380, 18, true),
            18,
            true,
            'left',
            0,
            self::TINTA
        );
        $this->texto(self::DERECHA, (string) $f['rif_cedula'], 18, true, 'right', 0, self::TINTA);

        $this->y += 30;
        $this->texto(self::MARGEN, 'Dirección fiscal', 13, false, 'left', 0, self::SUAVE);
        $this->texto(470, 'Método de pago', 13, false, 'left', 0, self::SUAVE);
        $this->y += 22;
        $this->texto(
            self::MARGEN,
            $this->recortar((string) ($f['direccion_fiscal'] ?? '—'), 380, 17),
            17,
            false,
            'left',
            0,
            self::TINTA
        );
        $this->texto(470, metodo_pago_label((string) $f['metodo_pago']), 17, true, 'left', 0, self::TINTA);

        $this->y += 30;
        $this->texto(self::MARGEN, 'Tasa BCV aplicada', 13, false, 'left', 0, self::SUAVE);
        $this->texto(470, 'Cajero', 13, false, 'left', 0, self::SUAVE);
        $this->y += 22;
        $this->texto(
            self::MARGEN,
            'Bs ' . numero($f['tasa_bcv_aplicada'], 4),
            17,
            true,
            'left',
            0,
            self::TINTA
        );
        $this->texto(470, $this->recortar((string) $f['cajero_nombre'], 340, 17, true), 17, true, 'left', 0, self::TINTA);

        $this->y += 18;
        $this->separador();
        $this->y += 34;
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     */
    private function tabla(array $lineas): void
    {
        $this->cabeceraTabla();

        foreach ($lineas as $indice => $linea) {
            $this->rectangulo(
                self::MARGEN - 10,
                $this->y - 21,
                self::DERECHA - self::MARGEN + 20,
                30,
                $indice % 2 === 1 ? [249, 250, 251] : [255, 255, 255]
            );

            $this->texto(self::MARGEN, (string) ($indice + 1), 15, false, 'left', 0, self::SUAVE);
            $this->texto(
                78,
                $this->recortar((string) ($linea['nombre_producto'] ?? ''), 310, 15),
                15,
                false,
                'left',
                0,
                self::TINTA
            );
            $this->texto(
                470,
                cantidad($linea['cantidad_o_peso']) . ' ' . $linea['unidad_medida'],
                15,
                false,
                'right',
                0,
                self::TINTA
            );
            $this->texto(550, '$ ' . money($linea['precio_unitario_usd']), 15, false, 'right', 0, self::TINTA);
            $this->texto(650, money($linea['precio_unitario_ves']), 15, false, 'right', 0, self::TINTA);
            $this->texto(710, money($linea['alicuota_iva_item'], 2) . '%', 15, false, 'right', 0, self::SUAVE);
            $this->texto(
                self::DERECHA,
                money($linea['subtotal_item_ves']),
                15,
                true,
                'right',
                0,
                self::TINTA
            );

            $this->y += 30;
        }

        $this->y -= 12;
        $this->separador();
        $this->y += 32;
    }

    private function cabeceraTabla(): void
    {
        $this->rectangulo(
            self::MARGEN - 10,
            $this->y - 21,
            self::DERECHA - self::MARGEN + 20,
            30,
            self::BANDA
        );

        $this->texto(self::MARGEN, '#', 13, true, 'left', 0, self::SUAVE);
        $this->texto(78, 'Producto', 13, true, 'left', 0, self::SUAVE);
        $this->texto(470, 'Cant.', 13, true, 'right', 0, self::SUAVE);
        $this->texto(550, 'P. USD', 13, true, 'right', 0, self::SUAVE);
        $this->texto(650, 'P. Bs', 13, true, 'right', 0, self::SUAVE);
        $this->texto(710, 'IVA', 13, true, 'right', 0, self::SUAVE);
        $this->texto(self::DERECHA, 'Subtotal Bs', 13, true, 'right', 0, self::SUAVE);

        $this->y += 34;
    }

    private function totales(): void
    {
        $f = $this->factura;

        $this->filaTotal('Subtotal exento', money($f['subtotal_exento_ves']), 16, false);
        $this->filaTotal('Base imponible', money($f['base_imponible_ves']), 16, false);
        $this->filaTotal('IVA', money($f['monto_iva_ves']), 16, false);

        if ((float) $f['monto_igtf_ves'] > 0) {
            $this->filaTotal('IGTF 3%', money($f['monto_igtf_ves']), 16, false);
        }

        $this->y += 6;
        $this->separador();
        $this->y += 30;

        $this->texto(560, 'Total Bs', 21, true, 'right', 140, self::TINTA);
        $this->texto(self::DERECHA, money($f['total_ves']), 21, true, 'right', 0, self::PRIMARIO);
        $this->y += 30;

        $this->texto(560, 'Total USD', 21, true, 'right', 140, self::TINTA);
        $this->texto(self::DERECHA, '$ ' . money($f['total_usd']), 21, true, 'right', 0, self::PRIMARIO);

        $this->y += 34;
    }

    private function filaTotal(string $etiqueta, string $valor, float $tamano, bool $negrita): void
    {
        $this->texto(560, $etiqueta, $tamano, $negrita, 'right', 140, self::SUAVE);
        $this->texto(self::DERECHA, $valor, $tamano, $negrita, 'right', 0, self::TINTA);
        $this->y += 26;
    }

    private function pie(): void
    {
        $this->separador();
        $this->y += 26;

        $this->texto(
            self::MARGEN,
            'Gracias por su compra. Documento generado por ' . EMPRESA_NOMBRE . '.',
            15,
            false,
            'left',
            0,
            self::SUAVE
        );

        if ((string) $this->factura['estado'] === 'ANULADA') {
            $this->y += 28;
            $this->texto(self::MARGEN, 'FACTURA ANULADA', 20, true, 'left', 0, self::PELIGRO);
        }

        $this->y += 26;
        $this->texto(
            self::MARGEN,
            'Emisión: ' . (string) $this->factura['tipo_emision']
                . ' · Cajero: ' . (string) $this->factura['cajero_login'],
            13,
            false,
            'left',
            0,
            self::SUAVE
        );
    }

    private function texto(
        float $x,
        string $texto,
        float $tamano,
        bool $negrita = false,
        string $alineacion = 'left',
        float $ancho = 0,
        array $color = self::TINTA
    ): void {
        $this->ops[] = [
            'tipo'       => 'texto',
            'x'          => $x,
            'y'          => $this->y,
            'texto'      => $texto,
            'tamano'     => $tamano,
            'negrita'    => $negrita,
            'alineacion' => $alineacion,
            'ancho'      => $ancho,
            'color'      => $color,
        ];
    }

    private function separador(): void
    {
        $this->ops[] = [
            'tipo'  => 'linea',
            'x1'    => self::MARGEN,
            'y1'    => $this->y,
            'x2'    => self::DERECHA,
            'y2'    => $this->y,
            'color' => self::LINEA,
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $color
     */
    private function rectangulo(float $x, float $y, float $ancho, float $alto, array $color): void
    {
        $this->ops[] = [
            'tipo'   => 'rect',
            'x'      => $x,
            'y'      => $y,
            'ancho'  => $ancho,
            'alto'   => $alto,
            'color'  => $color,
        ];
    }

    private function anchoTexto(string $texto, float $tamano, bool $negrita): float
    {
        $caja = imagettfbbox($tamano, 0, $negrita ? $this->fuenteNegrita : $this->fuenteRegular, $texto);

        return abs((float) $caja[2] - (float) $caja[0]);
    }

    private function recortar(string $texto, float $anchoMaximo, float $tamano, bool $negrita = false): string
    {
        if ($this->anchoTexto($texto, $tamano, $negrita) <= $anchoMaximo) {
            return $texto;
        }

        $recorte = $texto;

        while ($recorte !== '' && $this->anchoTexto($recorte . '...', $tamano, $negrita) > $anchoMaximo) {
            $recorte = mb_substr($recorte, 0, mb_strlen($recorte) - 1);
        }

        return rtrim($recorte) . '...';
    }

    private function renderizar(int $alto): string
    {
        $imagen = imagecreatetruecolor((int) self::ANCHO, $alto);

        if ($imagen === false) {
            throw new RuntimeException('No se pudo crear la imagen del comprobante.');
        }

        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        imagefilledrectangle($imagen, 0, 0, (int) self::ANCHO, $alto, $blanco);

        foreach ($this->ops as $op) {
            $color = imagecolorallocate($imagen, $op['color'][0], $op['color'][1], $op['color'][2]);

            if ($op['tipo'] === 'linea') {
                imageline(
                    $imagen,
                    (int) round($op['x1']),
                    (int) round($op['y1']),
                    (int) round($op['x2']),
                    (int) round($op['y2']),
                    $color
                );
                continue;
            }

            if ($op['tipo'] === 'rect') {
                imagefilledrectangle(
                    $imagen,
                    (int) round($op['x']),
                    (int) round($op['y']),
                    (int) round($op['x'] + $op['ancho']),
                    (int) round($op['y'] + $op['alto']),
                    $color
                );
                continue;
            }

            $fuente = $op['negrita'] ? $this->fuenteNegrita : $this->fuenteRegular;
            $x      = (float) $op['x'];

            if ($op['alineacion'] === 'right') {
                $x -= $this->anchoTexto((string) $op['texto'], (float) $op['tamano'], (bool) $op['negrita']);
            } elseif ($op['alineacion'] === 'center' && (float) $op['ancho'] > 0) {
                $x += (
                    (float) $op['ancho']
                    - $this->anchoTexto((string) $op['texto'], (float) $op['tamano'], (bool) $op['negrita'])
                ) / 2;
            }

            imagettftext(
                $imagen,
                (float) $op['tamano'],
                0,
                (int) round($x),
                (int) round($op['y']),
                $color,
                $fuente,
                (string) $op['texto']
            );
        }

        // Marco exterior tenue.
        $borde = imagecolorallocate($imagen, self::LINEA[0], self::LINEA[1], self::LINEA[2]);
        imagerectangle($imagen, 0, 0, (int) self::ANCHO - 1, $alto - 1, $borde);

        ob_start();
        imagepng($imagen, null, 9);
        $png = (string) ob_get_clean();

        imagedestroy($imagen);

        return $png;
    }
}
