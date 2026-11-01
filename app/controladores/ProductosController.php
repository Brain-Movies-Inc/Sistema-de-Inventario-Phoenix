<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/ProductoModel.php';

/**
 * Valida y normaliza los datos del formulario de productos.
 */
function productos_validar(): array
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

$modelo = new ProductoModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_admin()) {
    csrf_verify();
    flash('error', 'Solo un administrador puede crear o modificar productos.');
    redirect(url('productos'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $accionPost = (string) ($_POST['accion'] ?? '');

    try {
        if ($accionPost === 'guardar') {
            $datos = productos_validar();
            $id    = (int) ($_POST['id_producto'] ?? 0);

            if ($modelo->existeCodigo($datos['codigo_qr_barras'], $id)) {
                throw new RuntimeException('Ya existe otro producto con ese código de barras o QR.');
            }

            if ($id > 0) {
                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('El producto que intentas editar no existe.');
                }
                $modelo->actualizar($id, $datos);
                flash('ok', 'Producto actualizado correctamente.');
            } else {
                $modelo->crear($datos);
                flash('ok', 'Producto creado correctamente.');
            }
        } elseif ($accionPost === 'cambiar_estado') {
            $id     = (int) ($_POST['id_producto'] ?? 0);
            $activo = (int) ($_POST['activo'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Producto inválido.');
            }

            $modelo->cambiarActivo($id, $activo);
            flash('ok', $activo ? 'Producto reactivado.' : 'Producto desactivado.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        $_SESSION['old_producto'] = $_POST;

        $id = (int) ($_POST['id_producto'] ?? 0);
        redirect($id > 0 ? url('productos', ['editar' => $id]) : url('productos'));
    }

    redirect(url('productos'));
}

$busqueda         = trim((string) ($_GET['q'] ?? ''));
$idCategoria      = (int) ($_GET['categoria'] ?? 0);
$soloBajo         = isset($_GET['bajo']);
$incluirInactivos = isset($_GET['inactivos']);

$productos  = $modelo->listar($busqueda, $idCategoria, $soloBajo, $incluirInactivos);
$categorias = $modelo->categorias();

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $modelo->buscar((int) $_GET['editar']);
}

$old = $_SESSION['old_producto'] ?? null;
unset($_SESSION['old_producto']);

$titulo    = 'Productos';
$subtitulo = 'Inventario, precios e IVA';
$activo    = 'productos';

require __DIR__ . '/../vistas/productos/index.php';
