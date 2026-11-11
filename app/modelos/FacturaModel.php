<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

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

    /**
     * Bloqueo asesor de MySQL para serializar la generación del consecutivo
     * entre varias cajas conectadas a la misma base de datos.
     */
    private const BLOQUEO_CONSECUTIVO = 'de_todito_reyes_factura_consecutivo';

    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    public function tasaVigente(): ?array
    {
        // Una sola implementación de la consulta de la tasa vigente.
        return (new TasaModel())->vigente();
    }

    public function clientes(): array
    {
        return $this->db->query(
            "SELECT id_cliente, rif_cedula, nombre_razon_social
             FROM clientes
             ORDER BY nombre_razon_social ASC"
        )->fetchAll();
    }

    public function productosVendibles(): array
    {
        return $this->db->query(
            "SELECT id_producto, codigo_qr_barras, nombre_producto, unidad_medida,
                    precio_venta_usd, alicuota_iva, stock_actual, stock_minimo
             FROM productos
             WHERE activo = 1
             ORDER BY nombre_producto ASC"
        )->fetchAll();
    }

    public function listar(
        string $estado = '',
        string $busqueda = '',
        int $limite = 100,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        $sql = $this->selectFacturas();

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura DESC LIMIT :limite';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $marcador => $valor) {
            $stmt->bindValue($marcador, $valor);
        }

        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function exportarResumen(
        string $estado = '',
        string $busqueda = '',
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        $sql = $this->selectFacturas();

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function exportarDetalle(
        string $estado = '',
        string $busqueda = '',
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        [$where, $params] = $this->filtros($estado, $busqueda, $desde, $hasta);

        $sql = "SELECT f.nro_factura_consecutivo, f.nro_control_fiscal, f.fecha_emision,
                       f.estado, f.metodo_pago, f.tasa_bcv_aplicada,
                       cl.rif_cedula, cl.nombre_razon_social AS cliente,
                       u.usuario_login AS cajero,
                       p.codigo_qr_barras, p.nombre_producto, p.unidad_medida,
                       d.cantidad_o_peso, d.precio_unitario_usd, d.precio_unitario_ves,
                       d.alicuota_iva_item, d.monto_iva_item_ves, d.subtotal_item_ves
                FROM facturas f
                JOIN clientes cl ON cl.id_cliente = f.id_cliente
                JOIN usuarios u ON u.id_usuario = f.id_usuario
                JOIN detalle_factura d ON d.id_factura = f.id_factura
                JOIN productos p ON p.id_producto = d.id_producto";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.id_factura ASC, d.id_detalle_factura ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Arma el WHERE compartido por el listado y las exportaciones.
     *
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function filtros(
        string $estado,
        string $busqueda,
        ?string $desde,
        ?string $hasta
    ): array {
        $where  = [];
        $params = [];

        if ($estado === 'EMITIDA' || $estado === 'ANULADA') {
            $where[] = 'f.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($busqueda !== '') {
            $where[] = '(f.nro_factura_consecutivo LIKE :q1
                         OR f.nro_control_fiscal LIKE :q2
                         OR f.codigo_verificacion LIKE :q3
                         OR cl.nombre_razon_social LIKE :q4
                         OR cl.rif_cedula LIKE :q5)';
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }

        if ($desde !== null && $desde !== '') {
            $where[] = 'f.fecha_emision >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if ($hasta !== null && $hasta !== '') {
            $where[] = 'f.fecha_emision <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        return [$where, $params];
    }

    private function selectFacturas(): string
    {
        return "SELECT f.id_factura, f.nro_factura_consecutivo, f.nro_control_fiscal,
                       f.codigo_verificacion,
                       f.fecha_emision, f.estado, f.metodo_pago,
                       f.subtotal_exento_ves, f.base_imponible_ves,
                       f.monto_iva_ves, f.monto_igtf_ves,
                       f.total_ves, f.total_usd, f.tasa_bcv_aplicada,
                       cl.nombre_razon_social AS cliente, cl.rif_cedula,
                       u.usuario_login AS cajero,
                       (SELECT COUNT(*) FROM detalle_factura d WHERE d.id_factura = f.id_factura) AS lineas
                FROM facturas f
                JOIN clientes cl ON cl.id_cliente = f.id_cliente
                JOIN usuarios u ON u.id_usuario = f.id_usuario";
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT f.*,
                    cl.rif_cedula,
                    cl.nombre_razon_social AS cliente,
                    cl.direccion_fiscal,
                    cl.telefono AS cliente_telefono,
                    u.usuario_login AS cajero_login,
                    u.nombre_completo AS cajero_nombre
             FROM facturas f
             JOIN clientes cl ON cl.id_cliente = f.id_cliente
             JOIN usuarios u ON u.id_usuario = f.id_usuario
             WHERE f.id_factura = :id"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function lineas(int $idFactura): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.nombre_producto, p.codigo_qr_barras, p.unidad_medida
             FROM detalle_factura d
             JOIN productos p ON p.id_producto = d.id_producto
             WHERE d.id_factura = :id
             ORDER BY d.id_detalle_factura ASC"
        );
        $stmt->execute([':id' => $idFactura]);

        return $stmt->fetchAll();
    }

    public function resumenHoy(): array
    {
        $fila = $this->db->query(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_ves), 0) AS total_ves,
                    COALESCE(SUM(total_usd), 0) AS total_usd
             FROM facturas
             WHERE estado = 'EMITIDA' AND DATE(fecha_emision) = CURDATE()"
        )->fetch();

        return $fila ?: ['cantidad' => 0, 'total_ves' => 0, 'total_usd' => 0];
    }

    /**
     * Resumen del corte/cierre de caja de una fecha.
     *
     * Solo cuenta facturas EMITIDA: las anuladas se informan aparte para que
     * el arqueo no las mezcle con el efectivo y los demás medios de pago.
     *
     * @return array{
     *   fecha:string, cantidad:int, total_ves:float, total_usd:float,
     *   igtf_ves:float, primera:?string, ultima:?string,
     *   por_metodo:array<int, array<string, mixed>>,
     *   anuladas:array{cantidad:int, total_ves:float}
     * }
     */
    public function resumenCierre(?string $fecha = null, ?int $idUsuario = null): array
    {
        $fecha = $fecha !== null && $fecha !== '' ? $fecha : date('Y-m-d');

        $where  = "f.estado = 'EMITIDA' AND DATE(f.fecha_emision) = :fecha";
        $params = [':fecha' => $fecha];

        if ($idUsuario !== null) {
            $where .= ' AND f.id_usuario = :usuario';
            $params[':usuario'] = $idUsuario;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(f.total_ves), 0) AS total_ves,
                    COALESCE(SUM(f.total_usd), 0) AS total_usd,
                    COALESCE(SUM(f.monto_igtf_ves), 0) AS igtf_ves,
                    MIN(f.fecha_emision) AS primera,
                    MAX(f.fecha_emision) AS ultima
             FROM facturas f
             WHERE {$where}"
        );
        $stmt->execute($params);
        $totales = $stmt->fetch() ?: [];

        $stmt = $this->db->prepare(
            "SELECT f.metodo_pago,
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(f.total_ves), 0) AS total_ves,
                    COALESCE(SUM(f.total_usd), 0) AS total_usd
             FROM facturas f
             WHERE {$where}
             GROUP BY f.metodo_pago
             ORDER BY total_ves DESC, f.metodo_pago ASC"
        );
        $stmt->execute($params);
        $porMetodo = $stmt->fetchAll();

        $whereAnuladas  = "f.estado = 'ANULADA' AND DATE(f.fecha_emision) = :fecha";
        $paramsAnuladas = [':fecha' => $fecha];

        if ($idUsuario !== null) {
            $whereAnuladas .= ' AND f.id_usuario = :usuario';
            $paramsAnuladas[':usuario'] = $idUsuario;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(f.total_ves), 0) AS total_ves
             FROM facturas f
             WHERE {$whereAnuladas}"
        );
        $stmt->execute($paramsAnuladas);
        $anuladas = $stmt->fetch() ?: [];

        return [
            'fecha'      => $fecha,
            'cantidad'   => (int) ($totales['cantidad'] ?? 0),
            'total_ves'  => (float) ($totales['total_ves'] ?? 0),
            'total_usd'  => (float) ($totales['total_usd'] ?? 0),
            'igtf_ves'   => (float) ($totales['igtf_ves'] ?? 0),
            'primera'    => $totales['primera'] ?? null,
            'ultima'     => $totales['ultima'] ?? null,
            'por_metodo' => $porMetodo,
            'anuladas'   => [
                'cantidad'  => (int) ($anuladas['cantidad'] ?? 0),
                'total_ves' => (float) ($anuladas['total_ves'] ?? 0),
            ],
        ];
    }

    /**
     * Emite una factura completa en una sola transacción.
     *
     * Los precios, el IVA y el stock SIEMPRE se leen de la base de datos;
     * el navegador solo envía id_producto y cantidad.
     *
     * @param array<int, array{id_producto: int|string, cantidad: int|float|string}> $items
     * @return array{id_factura:int, nro_factura:string, total_ves:float, total_usd:float, tasa:float}
     */
    public function emitir(
        array $items,
        int $idCliente,
        string $metodoPago,
        bool $aplicaIgtf,
        int $idUsuario
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

        $tasa = $this->tasaVigente();
        $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;

        if ($tasaValor <= 0) {
            throw new RuntimeException(
                'No hay una tasa BCV vigente. Registra la tasa del día en tasas_cambio_bcv antes de facturar.'
            );
        }

        $this->adquirirBloqueoConsecutivo();

        try {
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
                $igtf            = $aplicaIgtf ? round(($subtotalGeneral + $iva) * 0.03, 3) : 0.0;
                $totalVes        = round($subtotalGeneral + $iva + $igtf, 3);
                $totalUsd        = round($totalVes / $tasaValor, 3);

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
        } finally {
            $this->liberarBloqueoConsecutivo();
        }
    }

    public function anular(int $idFactura, int $idUsuario): void
    {
        $this->db->beginTransaction();

        try {
            // El trigger trg_facturas_au usa esta variable de sesión para
            // registrar quién anuló (no el usuario que emitió la factura).
            $this->db->prepare('SET @auditoria_usuario = :id')
                ->execute([':id' => $idUsuario]);

            $stmt = $this->db->prepare(
                "SELECT estado FROM facturas WHERE id_factura = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $idFactura]);
            $factura = $stmt->fetch();

            if (!$factura) {
                throw new RuntimeException('La factura no existe.');
            }

            if ($factura['estado'] === 'ANULADA') {
                throw new RuntimeException('La factura ya está anulada.');
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
                "UPDATE facturas SET estado = 'ANULADA' WHERE id_factura = :id"
            );
            $stmt->execute([':id' => $idFactura]);

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
                "SELECT estado FROM facturas WHERE id_factura = :id FOR UPDATE"
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
        $consolidado = [];

        foreach ($items as $item) {
            $idProducto = (int) ($item['id_producto'] ?? 0);
            $cantidad   = round((float) ($item['cantidad'] ?? 0), 3);

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
     * @return array{nro:string, control:string, codigo:string}
     */
    private function siguienteConsecutivo(): array
    {
        $fila = $this->db->query(
            "SELECT COALESCE(MAX(numero), 0) AS ultimo
             FROM (
                 SELECT CAST(nro_factura_consecutivo AS UNSIGNED) AS numero
                 FROM facturas
                 UNION ALL
                 SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.nro_factura')) AS UNSIGNED)
                 FROM bitacora_auditoria
                 WHERE modulo = 'FACTURAS' AND accion = 'EMISION'
             ) AS numeracion"
        )->fetch();

        $siguiente = (int) ($fila['ultimo'] ?? 0) + 1;
        $nro = str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);

        return [
            'nro'     => $nro,
            'control' => '00-' . $nro,
            'codigo'  => $this->codigoVerificacion(),
        ];
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
     * Toma un bloqueo nombrado antes de leer el último consecutivo.
     *
     * El bloqueo se libera al terminar la emisión (éxito o error) para que
     * dos transacciones no lean el mismo número base al mismo tiempo.
     */
    private function adquirirBloqueoConsecutivo(): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:nombre, 10)');
        $stmt->execute([':nombre' => self::BLOQUEO_CONSECUTIVO]);

        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException(
                'El sistema está ocupado generando otra factura. Intenta de nuevo en unos segundos.'
            );
        }
    }

    private function liberarBloqueoConsecutivo(): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:nombre)');
            $stmt->execute([':nombre' => self::BLOQUEO_CONSECUTIVO]);
        } catch (Throwable $e) {
            // Si la conexión se cierra, MySQL libera el bloqueo automáticamente.
        }
    }

    private function dec($valor): string
    {
        return number_format((float) $valor, 3, '.', '');
    }
}
