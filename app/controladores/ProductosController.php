<?php
declare(strict_types=1);

/**
 * Gestión del inventario y sus precios.
 *
 * El ADMIN administra el catálogo. El CAJERO puede consultarlo en modo solo
 * lectura para conocer precios y disponibilidad, sin crear ni modificar.
 */
final class ProductosController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new ProductoModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        $busqueda         = trim((string) ($_GET['q'] ?? ''));
        $idCategoria      = (int) ($_GET['categoria'] ?? 0);
        $soloBajo         = isset($_GET['bajo']);
        $incluirInactivos = isset($_GET['inactivos']);

        $editar = null;
        if (isset($_GET['editar'])) {
            $editar = $modelo->buscar((int) $_GET['editar']);
        }

        $old = $_SESSION['old_producto'] ?? null;
        unset($_SESSION['old_producto']);

        // La tasa se usa solo como referencia visual: el precio oficial del
        // producto se guarda en USD y el POS aplica la tasa de cada venta.
        $tasa     = (new TasaModel())->vigente();
        $tasaValor = $tasa ? (float) $tasa['tasa_bcv_ves'] : 0.0;

        $this->render('productos/index', [
            'titulo'             => 'Productos',
            'subtitulo'          => 'Inventario, precios e IVA',
            'activo'             => 'productos',
            'busqueda'           => $busqueda,
            'idCategoria'        => $idCategoria,
            'soloBajo'           => $soloBajo,
            'incluirInactivos'   => $incluirInactivos,
            'productos'          => $modelo->listar($busqueda, $idCategoria, $soloBajo, $incluirInactivos),
            'categorias'         => $modelo->categorias(),
            'editar'             => $editar,
            'old'                => $old,
            'tasa'               => $tasa,
            'tasaValor'          => $tasaValor,
            'tasaDesactualizada' => tasa_desactualizada($tasa),
        ]);
    }

    private function procesarPost(ProductoModel $modelo): void
    {
        csrf_verify();

        if (!is_admin()) {
            $this->aviso('error', 'Solo un administrador puede crear o modificar productos.');
            $this->redirigir('productos');
        }

        $accion = (string) ($_POST['accion'] ?? '');
        $id     = (int) ($_POST['id_producto'] ?? 0);

        try {
            if ($accion === 'guardar') {
                $datos = $this->validar();

                if ($modelo->existeCodigo($datos['codigo_qr_barras'], $id)) {
                    throw new RuntimeException('Ya existe otro producto con ese código de barras o QR.');
                }

                if ($id > 0) {
                    if (!$modelo->buscar($id)) {
                        throw new RuntimeException('El producto que intentas editar no existe.');
                    }

                    $modelo->actualizar($id, $datos);
                    $this->aviso('ok', 'Producto actualizado correctamente.');
                } else {
                    $modelo->crear($datos);
                    $this->aviso('ok', 'Producto creado correctamente.');
                }
            } elseif ($accion === 'cambiar_estado') {
                $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;

                if ($id <= 0) {
                    throw new InvalidArgumentException('Producto inválido.');
                }

                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('El producto no existe.');
                }

                $modelo->cambiarActivo($id, $activo);
                $this->aviso('ok', $activo ? 'Producto reactivado.' : 'Producto desactivado.');
            } elseif ($accion === 'eliminar') {
                if ($id <= 0) {
                    throw new InvalidArgumentException('Producto inválido.');
                }

                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('El producto no existe.');
                }

                $modelo->eliminar($id);
                $this->aviso('ok', 'Producto eliminado correctamente.');
            } else {
                throw new InvalidArgumentException('La acción solicitada no es válida.');
            }
        } catch (Throwable $e) {
            $this->aviso('error', error_usuario($e, 'guardar el producto'));

            if ($accion === 'guardar') {
                $_SESSION['old_producto'] = $_POST;

                if ($id > 0) {
                    $this->redirigir('productos', ['editar' => $id]);
                }
            }

            $this->redirigir('productos');
        }

        $this->redirigir('productos');
    }

    private function validar(): array
    {
        $unidades = ['UND', 'KG', 'LT', 'GR', 'PAQ'];
        $unidad   = strtoupper(trim((string) ($_POST['unidad_medida'] ?? 'UND')));

        if (!in_array($unidad, $unidades, true)) {
            $unidad = 'UND';
        }

        $datos = [
            'codigo_qr_barras' => trim((string) ($_POST['codigo_qr_barras'] ?? '')),
            'nombre_producto'  => trim((string) ($_POST['nombre_producto'] ?? '')),
            'unidad_medida'    => $unidad,
            'costo_usd'        => (float) ($_POST['costo_usd'] ?? 0),
            'precio_venta_usd' => (float) ($_POST['precio_venta_usd'] ?? 0),
            'alicuota_iva'     => (float) ($_POST['alicuota_iva'] ?? 0),
            'stock_actual'     => (float) ($_POST['stock_actual'] ?? 0),
            'stock_minimo'     => (float) ($_POST['stock_minimo'] ?? 0),
            'stock_maximo'     => ($_POST['stock_maximo'] ?? '') === ''
                ? null
                : (float) $_POST['stock_maximo'],
            'id_categoria'     => (int) ($_POST['id_categoria'] ?? 0),
        ];

        if ($datos['codigo_qr_barras'] === '') {
            throw new InvalidArgumentException('El código de barras o QR es obligatorio.');
        }

        if (mb_strlen($datos['codigo_qr_barras']) > 64) {
            throw new InvalidArgumentException('El código no puede superar los 64 caracteres.');
        }

        if ($datos['nombre_producto'] === '') {
            throw new InvalidArgumentException('El nombre del producto es obligatorio.');
        }

        if (mb_strlen($datos['nombre_producto']) > 120) {
            throw new InvalidArgumentException('El nombre no puede superar los 120 caracteres.');
        }

        if ($datos['id_categoria'] <= 0) {
            throw new InvalidArgumentException('Selecciona una categoría.');
        }

        if ($datos['costo_usd'] < 0 || $datos['precio_venta_usd'] < 0) {
            throw new InvalidArgumentException('El costo y el precio no pueden ser negativos.');
        }

        if ($datos['alicuota_iva'] < 0 || $datos['alicuota_iva'] > 100) {
            throw new InvalidArgumentException('La alícuota de IVA debe estar entre 0 y 100.');
        }

        if ($datos['stock_actual'] < 0 || $datos['stock_minimo'] < 0) {
            throw new InvalidArgumentException('El stock no puede ser negativo.');
        }

        if ($datos['stock_maximo'] !== null && $datos['stock_maximo'] < 0) {
            throw new InvalidArgumentException('El stock máximo no puede ser negativo.');
        }

        return $datos;
    }
}
