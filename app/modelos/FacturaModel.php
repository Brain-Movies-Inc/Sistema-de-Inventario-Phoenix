<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

/**
 * Escritura de facturas: emisión, anulación, eliminación y numeración fiscal.
 *
 * Las consultas de lectura viven en FacturaConsultaModel y la exportación CSV
 * en FacturaExportModel.
 */
final class FacturaModel
{
    private const METODOS_PAGO = [
        'EFECTIVO',
        'PAGO_MOVIL',
        'PUNTO_VENTA',
        'TRANSFERENCIA',
        'ZELLE',
        'MIXTO',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * Emite una factura completa en una sola transacción.
     *
     * Los precios, el IVA y el stock SIEMPRE se leen de la base de datos;
     * el navegador solo envía id_producto y cantidad. Opcionalmente envía la
     * tasa y los precios con los que calculó los totales en pantalla: si ya
     * no coinciden con los vigentes, se rechaza la emisión para que el cajero
     * recargue y vea los importes reales (F-09).
     *
     * En un pago MIXTO el navegador envía la porción pagada en divisa (USD)
     * y la porción pagada en bolívares. El IGTF se calcula solo sobre la
     * porción en divisa y la suma de ambos pagos debe cubrir el total (F-21).
     *
     * @param array<int, array{id_producto: int|string, cantidad: int|float|string}> $items
     * @param array<int, float>|null $preciosEsperados
     * @return array{id_factura:int, nro_factura:string, total_ves:float, total_usd:float, tasa:float}
     */
    public function emitir(
        array $items,
        int $idCliente,
        string $metodoPago,
        bool $aplicaIgtf,
        int $idUsuario,
        ?float $tasaEsperada = null,
        ?array $preciosEsperados = null,
        ?float $pagoDivisaUsd = null,
        ?float $pagoBolivaresVes = null
    ): array {
        if (!$items) {
            throw new InvalidArgumentException('Agrega al menos un producto al carrito.');
        }

        if (!in_array($metodoPago, self::METODOS_PAGO, true)) {
            throw new InvalidArgumentException('El método de pago no es válido.');
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clientes WHERE id_cliente = :id");
        $stmt->execute([':id' => $idCliente]);
        if (!(int) $stmt->fetchColumn()) {
            throw new RuntimeException('El cliente seleccionado no existe.');
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE id_usuario = :id AND activo = 1"
        );
        $stmt->execute([':id' => $idUsuario]);
        if (!(int) $stmt->fetchColumn()) {
            throw new RuntimeException(
                'No hay un usuario activo para emitir la factura. Importa los datos de ejemplo o crea un usuario.'
            );
        }

        $tasa = (new TasaModel())->vigente();
        $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;

        if ($tasaValor <= 0) {
            throw new RuntimeException(
                'No hay una tasa BCV vigente. Registra la tasa del día en tasas_cambio_bcv antes de facturar.'
            );
        }

        if ($tasaEsperada !== null
            && abs(round($tasaValor, 4) - round($tasaEsperada, 4)) > 0.00001) {
            throw new InvalidArgumentException(
                'La tasa BCV cambió mientras la venta estaba abierta. ' .
                'Recarga el punto de venta e intenta de nuevo.'
            );
        }

        // Se intenta hasta 3 veces por si dos cajas generan el mismo consecutivo.
        for ($intento = 1; $intento <= 3; $intento++) {
            try {
                $this->db->beginTransaction();

                $consolidado = $this->consolidarItems($items);
                $productos   = $this->bloquearProductos(array_keys($consolidado));

                $exento = 0.0;
                $base   = 0.0;
                $iva    = 0.0;
                $lineas = [];

                foreach ($consolidado as $idProducto => $cantidad) {
                    $producto = $productos[$idProducto] ?? null;

                    if (!$producto) {
                        throw new RuntimeException('Uno de los productos ya no está disponible.');
                    }

                    $stock = (float) $producto['stock_actual'];
                    if ($cantidad > $stock + 0.0001) {
                        throw new RuntimeException(
                            'Stock insuficiente de "' . $producto['nombre_producto'] . '". ' .
                            'Disponible: ' . cantidad($stock) . ' ' . $producto['unidad_medida'] . '.'
                        );
                    }

                    $precioUsd  = (float) $producto['precio_venta_usd'];

                    if ($preciosEsperados !== null
                        && array_key_exists($idProducto, $preciosEsperados)
                        && abs((float) $preciosEsperados[$idProducto] - $precioUsd) > 0.0005) {
                        throw new InvalidArgumentException(
                            'El precio de "' . $producto['nombre_producto'] . '" cambió mientras ' .
                            'la venta estaba abierta. Recarga el punto de venta e intenta de nuevo.'
                        );
                    }

                    $precioVes  = round($precioUsd * $tasaValor, 3);
                    $subtotal   = round($cantidad * $precioVes, 3);
                    $alicuota   = (float) $producto['alicuota_iva'];
                    $ivaItem    = $alicuota > 0 ? round($subtotal * $alicuota / 100, 3) : 0.0;

                    if ($alicuota > 0) {
                        $base += $subtotal;
                        $iva  += $ivaItem;
                    } else {
                        $exento += $subtotal;
                    }

                    $lineas[] = [
                        'id_producto'         => $idProducto,
                        'cantidad'            => $cantidad,
                        'precio_unitario_usd' => $precioUsd,
                        'precio_unitario_ves' => $precioVes,
                        'alicuota_iva_item'   => $alicuota,
                        'monto_iva_item_ves'  => $ivaItem,
                        'subtotal_item_ves'   => $subtotal,
                    ];
                }

                $exento = round($exento, 3);
                $base   = round($base, 3);
                $iva    = round($iva, 3);

                $subtotalGeneral = round($exento + $base, 3);
                $bienes          = round($subtotalGeneral + $iva, 3);

                if (($pagoDivisaUsd ?? 0.0) < 0 || ($pagoBolivaresVes ?? 0.0) < 0) {
                    throw new InvalidArgumentException('Los montos de pago no pueden ser negativos.');
                }

                if ($metodoPago === 'MIXTO') {
                    $divisaUsd    = round((float) ($pagoDivisaUsd ?? 0.0), 3);
                    $bolivaresVes = round((float) ($pagoBolivaresVes ?? 0.0), 3);
                    $divisaVes    = round($divisaUsd * $tasaValor, 3);

                    // La porción en divisa puede superar el subtotal porque
                    // también cubre su propio IGTF. El máximo es el total que
                    // resultaría si todo se pagara en divisa: bienes / 0,97.
                    $maximoDivisa = round($bienes / 0.97, 3);

                    if ($divisaVes > $maximoDivisa + 0.01) {
                        throw new InvalidArgumentException(
                            'La porción en divisa no puede superar el total de la venta.'
                        );
                    }

                    // El IGTF solo grava la porción pagada en divisa (F-21).
                    $igtf     = round($divisaVes * 0.03, 3);
                    $totalVes = round($bienes + $igtf, 3);

                    if (abs(round($divisaVes + $bolivaresVes, 3) - $totalVes) > 0.011) {
                        throw new InvalidArgumentException(
                            'Los pagos no coinciden con el total. ' .
                            'Divisa: Bs ' . number_format($divisaVes, 2, ',', '.') .
                            ', bolívares: Bs ' . number_format($bolivaresVes, 2, ',', '.') .
                            ', total: Bs ' . number_format($totalVes, 2, ',', '.') . '.'
                        );
                    }

                    $pagos = [];

                    if ($divisaUsd > 0) {
                        $pagos[] = [
                            'metodo'    => 'MIXTO',
                            'moneda'    => 'USD',
                            'monto'     => $divisaUsd,
                            'monto_ves' => $divisaVes,
                        ];
                    }

                    if ($bolivaresVes > 0) {
                        $pagos[] = [
                            'metodo'    => 'MIXTO',
                            'moneda'    => 'VES',
                            'monto'     => $bolivaresVes,
                            'monto_ves' => $bolivaresVes,
                        ];
                    }
                } else {
                    $enDivisa = $aplicaIgtf || $metodoPago === 'ZELLE';
                    $igtf     = $enDivisa ? round($bienes * 0.03, 3) : 0.0;
                    $totalVes = round($bienes + $igtf, 3);

                    $pagos = [[
                        'metodo'    => $metodoPago,
                        'moneda'    => $enDivisa ? 'USD' : 'VES',
                        'monto'     => $enDivisa ? round($totalVes / $tasaValor, 3) : $totalVes,
                        'monto_ves' => $totalVes,
                    ]];
                }

                $totalUsd = round($totalVes / $tasaValor, 3);

                $consecutivo = $this->siguienteConsecutivo();

                $stmt = $this->db->prepare(
                    "INSERT INTO facturas
                        (nro_factura_consecutivo, nro_control_fiscal, codigo_verificacion,
                         tipo_emision, fecha_emision,
                         subtotal_exento_ves, base_imponible_ves, monto_iva_ves, monto_igtf_ves,
                         total_ves, total_usd, tasa_bcv_aplicada, metodo_pago, estado,
                         id_cliente, id_usuario)
                     VALUES
                        (:nro, :control, :codigo, 'SISTEMA', NOW(),
                         :exento, :base, :iva, :igtf,
                         :total_ves, :total_usd, :tasa, :metodo, 'EMITIDA',
                         :cliente, :usuario)"
                );
                $stmt->execute([
                    ':nro'       => $consecutivo['nro'],
                    ':control'   => $consecutivo['control'],
                    ':codigo'    => $consecutivo['codigo'],
                    ':exento'    => $this->dec($exento),
                    ':base'      => $this->dec($base),
                    ':iva'       => $this->dec($iva),
                    ':igtf'      => $this->dec($igtf),
                    ':total_ves' => $this->dec($totalVes),
                    ':total_usd' => $this->dec($totalUsd),
                    ':tasa'      => number_format($tasaValor, 4, '.', ''),
                    ':metodo'    => $metodoPago,
                    ':cliente'   => $idCliente,
                    ':usuario'   => $idUsuario,
                ]);

                $idFactura = (int) $this->db->lastInsertId();

                $insertarPago = $this->db->prepare(
                    "INSERT INTO pagos_factura (id_factura, metodo, moneda, monto, monto_ves)
                     VALUES (:factura, :metodo, :moneda, :monto, :monto_ves)"
                );

                foreach ($pagos as $pago) {
                    $insertarPago->execute([
                        ':factura'  => $idFactura,
                        ':metodo'   => $pago['metodo'],
                        ':moneda'   => $pago['moneda'],
                        ':monto'    => $this->dec($pago['monto']),
                        ':monto_ves' => $this->dec($pago['monto_ves']),
                    ]);
                }

                $insertarLinea = $this->db->prepare(
                    "INSERT INTO detalle_factura
                        (id_factura, id_producto, cantidad_o_peso, precio_unitario_usd,
                         precio_unitario_ves, alicuota_iva_item, monto_iva_item_ves, subtotal_item_ves)
                     VALUES
                        (:factura, :producto, :cantidad, :precio_usd,
                         :precio_ves, :iva_item, :monto_iva, :subtotal)"
                );

                $descontarStock = $this->db->prepare(
                    "UPDATE productos
                     SET stock_actual = stock_actual - :cantidad_descuento
                     WHERE id_producto = :producto AND stock_actual >= :cantidad_minima"
                );

                foreach ($lineas as $linea) {
                    $insertarLinea->execute([
                        ':factura'    => $idFactura,
                        ':producto'   => $linea['id_producto'],
                        ':cantidad'   => $this->dec($linea['cantidad']),
                        ':precio_usd' => $this->dec($linea['precio_unitario_usd']),
                        ':precio_ves' => $this->dec($linea['precio_unitario_ves']),
                        ':iva_item'   => number_format($linea['alicuota_iva_item'], 2, '.', ''),
                        ':monto_iva'  => $this->dec($linea['monto_iva_item_ves']),
                        ':subtotal'   => $this->dec($linea['subtotal_item_ves']),
                    ]);

                    $descontarStock->execute([
                        ':cantidad_descuento' => $this->dec($linea['cantidad']),
                        ':cantidad_minima'    => $this->dec($linea['cantidad']),
                        ':producto'           => $linea['id_producto'],
                    ]);

                    if ($descontarStock->rowCount() !== 1) {
                        throw new RuntimeException(
                            'No se pudo descontar el stock del producto "' .
                            $productos[$linea['id_producto']]['nombre_producto'] .
                            '". Intenta de nuevo.'
                        );
                    }
                }

                $this->db->commit();

                return [
                    'id_factura'          => $idFactura,
                    'nro_factura'         => $consecutivo['nro'],
                    'codigo_verificacion' => $consecutivo['codigo'],
                    'total_ves'           => $totalVes,
                    'total_usd'           => $totalUsd,
                    'tasa'                => $tasaValor,
                ];
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                // Choque de consecutivo: se reintenta con el siguiente número.
                if ($e->getCode() === '23000' && stripos($e->getMessage(), 'uq_facturas') !== false) {
                    // El rollback revirtió el incremento de secuencias_fiscales,
                    // así que sin resincronizar el reintento pediría el mismo
                    // número y volvería a chocar. Se avanza la secuencia hasta
                    // el máximo ya emitido antes de reintentar.
                    $this->sincronizarSecuencia();
                    continue;
                }

                throw $e;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                throw $e;
            }
        }

        throw new RuntimeException(
            'No se pudo generar un número de factura único. Intenta emitir de nuevo.'
        );
    }

    /**
     * Anula una factura y devuelve su stock.
     *
     * El motivo es obligatorio y queda guardado en la propia factura, junto
     * con la fecha/hora y el usuario que ejecutó la anulación (F-16). No se
     * permite anular una factura de un período fiscal cerrado (F-17).
     */
    public function anular(int $idFactura, int $idUsuario, string $motivo): void
    {
        $motivo = trim($motivo);
        $largo  = function_exists('mb_strlen') ? mb_strlen($motivo, 'UTF-8') : strlen($motivo);

        if ($largo < 5 || $largo > 200) {
            throw new InvalidArgumentException(
                'Indica el motivo de la anulación (entre 5 y 200 caracteres).'
            );
        }

        $this->db->beginTransaction();

        try {
            // El trigger trg_facturas_au usa esta variable de sesión para
            // registrar quién anuló (no el usuario que emitió la factura).
            $this->db->prepare('SET @auditoria_usuario = :id')
                ->execute([':id' => $idUsuario]);

            $stmt = $this->db->prepare(
                "SELECT estado, fecha_emision
                 FROM facturas
                 WHERE id_factura = :id
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $idFactura]);
            $factura = $stmt->fetch();

            if (!$factura) {
                throw new RuntimeException('La factura no existe.');
            }

            if ($factura['estado'] === 'ANULADA') {
                throw new RuntimeException('La factura ya está anulada.');
            }

            if ($this->periodoCerrado((string) $factura['fecha_emision'])) {
                throw new RuntimeException(
                    'El período fiscal de esta factura está cerrado. ' .
                    'Un administrador debe reabrirlo antes de anular.'
                );
            }

            $stmt = $this->db->prepare(
                "SELECT id_producto, cantidad_o_peso
                 FROM detalle_factura
                 WHERE id_factura = :id"
            );
            $stmt->execute([':id' => $idFactura]);
            $lineas = $stmt->fetchAll();

            $devolverStock = $this->db->prepare(
                "UPDATE productos
                 SET stock_actual = stock_actual + :cantidad
                 WHERE id_producto = :producto"
            );

            foreach ($lineas as $linea) {
                $devolverStock->execute([
                    ':cantidad' => $this->dec($linea['cantidad_o_peso']),
                    ':producto' => $linea['id_producto'],
                ]);
            }

            $stmt = $this->db->prepare(
                "UPDATE facturas
                 SET estado = 'ANULADA',
                     motivo_anulacion = :motivo,
                     fecha_anulacion = NOW(),
                     id_usuario_anulacion = :usuario
                 WHERE id_factura = :id"
            );
            $stmt->execute([
                ':motivo'  => $motivo,
                ':usuario' => $idUsuario,
                ':id'      => $idFactura,
            ]);

            // El trigger trg_facturas_au registra el cambio en la bitácora.
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        } finally {
            try {
                $this->db->exec('SET @auditoria_usuario = NULL');
            } catch (Throwable $e) {
                // La conexión se cierra al terminar la petición.
            }
        }
    }

    /**
     * Elimina una factura ANULADA y sus líneas.
     *
     * Solo se permite sobre facturas ya anuladas: una factura emitida es un
     * registro fiscal que debe conservarse. El stock ya fue devuelto al
     * anular, así que borrar no vuelve a tocar el inventario.
     */
    public function eliminar(int $idFactura): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT estado, fecha_emision
                 FROM facturas
                 WHERE id_factura = :id
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $idFactura]);
            $factura = $stmt->fetch();

            if (!$factura) {
                throw new RuntimeException('La factura no existe.');
            }

            if ($factura['estado'] !== 'ANULADA') {
                throw new RuntimeException(
                    'Solo se pueden eliminar facturas anuladas. Anula la factura primero.'
                );
            }

            if ($this->periodoCerrado((string) $factura['fecha_emision'])) {
                throw new RuntimeException(
                    'El período fiscal de esta factura está cerrado. ' .
                    'Un administrador debe reabrirlo antes de eliminarla.'
                );
            }

            $this->db->prepare("DELETE FROM detalle_factura WHERE id_factura = :id")
                ->execute([':id' => $idFactura]);

            $this->db->prepare("DELETE FROM facturas WHERE id_factura = :id")
                ->execute([':id' => $idFactura]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Agrupa líneas repetidas y valida ids/cantidades.
     *
     * @return array<int, float>
     */
    private function consolidarItems(array $items): array
    {
        if (count($items) > 500) {
            throw new InvalidArgumentException('El carrito tiene demasiadas líneas.');
        }

        $consolidado = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Hay una línea del carrito con un formato inválido.');
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
                throw new InvalidArgumentException('Hay una línea con un producto inválido.');
            }

            if (!is_int($cantRaw) && !is_float($cantRaw)
                && !(is_string($cantRaw) && is_numeric($cantRaw))) {
                throw new InvalidArgumentException('Hay una línea con una cantidad inválida.');
            }

            $cantidad = round((float) $cantRaw, 3);

            if ($idProducto <= 0 || $cantidad <= 0) {
                throw new InvalidArgumentException('Hay una línea con producto o cantidad inválida.');
            }

            if ($cantidad > 100000) {
                throw new InvalidArgumentException('La cantidad de una línea es demasiado alta.');
            }

            $consolidado[$idProducto] = round(($consolidado[$idProducto] ?? 0) + $cantidad, 3);
        }

        if (count($consolidado) > 100) {
            throw new InvalidArgumentException('Una factura no puede tener más de 100 productos distintos.');
        }

        return $consolidado;
    }

    /**
     * Bloquea los productos para que dos cajas no vendan el mismo stock.
     */
    private function bloquearProductos(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

        if (!$ids) {
            throw new InvalidArgumentException('El carrito no contiene productos válidos.');
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->db->prepare(
            "SELECT id_producto, nombre_producto, unidad_medida, precio_venta_usd,
                    alicuota_iva, stock_actual
             FROM productos
             WHERE id_producto IN ({$marcadores}) AND activo = 1
             FOR UPDATE"
        );
        $stmt->execute($ids);

        $productos = [];
        foreach ($stmt->fetchAll() as $producto) {
            $productos[(int) $producto['id_producto']] = $producto;
        }

        return $productos;
    }

    /**
     * Reserva el siguiente número fiscal de forma atómica.
     *
     * El UPDATE sobre secuencias_fiscales deja la fila bloqueada hasta el
     * commit de la factura, así que dos cajas nunca obtienen el mismo número
     * sin necesidad de un lock global (F-14). LAST_INSERT_ID(expr) guarda el
     * valor reservado en la sesión para recuperarlo con lastInsertId().
     *
     * @return array{nro:string, control:string, codigo:string}
     */
    private function siguienteConsecutivo(): array
    {
        $this->db->exec(
            "INSERT IGNORE INTO secuencias_fiscales (nombre, ultimo_numero)
             VALUES ('FACTURA', 0)"
        );

        $stmt = $this->db->prepare(
            "UPDATE secuencias_fiscales
             SET ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1)
             WHERE nombre = 'FACTURA'"
        );
        $stmt->execute();

        $siguiente = (int) $this->db->lastInsertId();

        if ($siguiente <= 0) {
            $stmt = $this->db->query(
                "SELECT ultimo_numero FROM secuencias_fiscales WHERE nombre = 'FACTURA'"
            );
            $siguiente = (int) $stmt->fetchColumn();
        }

        if ($siguiente <= 0) {
            throw new RuntimeException(
                'No se pudo reservar el número de factura. Intenta emitir de nuevo.'
            );
        }

        $nro = str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);

        return [
            'nro'     => $nro,
            'control' => '00-' . $nro,
            'codigo'  => $this->codigoVerificacion(),
        ];
    }

    /**
     * Avanza la secuencia fiscal hasta el mayor consecutivo ya emitido.
     *
     * Se usa tras un choque de clave única: el rollback de la transacción
     * revierte el incremento hecho por siguienteConsecutivo(), y sin esta
     * resincronización el reintento volvería a pedir el mismo número.
     */
    private function sincronizarSecuencia(): void
    {
        $this->db->exec(
            "INSERT IGNORE INTO secuencias_fiscales (nombre, ultimo_numero)
             VALUES ('FACTURA', 0)"
        );

        $this->db->exec(
            "UPDATE secuencias_fiscales s
             SET s.ultimo_numero = GREATEST(
                 s.ultimo_numero,
                 (SELECT COALESCE(MAX(CAST(f.nro_factura_consecutivo AS UNSIGNED)), 0)
                  FROM facturas f)
             )
             WHERE s.nombre = 'FACTURA'"
        );
    }

    /**
     * Código aleatorio de verificación que acompaña al consecutivo fiscal.
     *
     * Se excluyen letras y números que se confunden al leerlos o dictarlos
     * (O/0, I/1, L, S/5). No sustituye la numeración consecutiva: solo permite
     * comprobar que un comprobante fue emitido por el sistema.
     */
    private function codigoVerificacion(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM facturas WHERE codigo_verificacion = :codigo'
        );

        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = '';

            for ($i = 0; $i < 8; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            $stmt->execute([':codigo' => $codigo]);

            if ((int) $stmt->fetchColumn() === 0) {
                return $codigo;
            }
        }

        throw new RuntimeException(
            'No se pudo generar un código de verificación único. Intenta emitir de nuevo.'
        );
    }

    /**
     * Indica si el período fiscal (año/mes) de una fecha está cerrado.
     */
    private function periodoCerrado(string $fecha): bool
    {
        $marca = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $fecha)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($marca === false) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT cerrado
             FROM periodos_fiscales
             WHERE anio = :anio AND mes = :mes
             LIMIT 1"
        );
        $stmt->execute([
            ':anio' => (int) $marca->format('Y'),
            ':mes'  => (int) $marca->format('n'),
        ]);
        $valor = $stmt->fetchColumn();

        return $valor !== false && (int) $valor === 1;
    }

    private function dec($valor): string
    {
        return number_format((float) $valor, 3, '.', '');
    }
}
