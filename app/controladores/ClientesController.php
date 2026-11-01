<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/ClienteModel.php';

function clientes_validar(): array
{
    $datos = [
        'rif_cedula'          => strtoupper(trim((string) ($_POST['rif_cedula'] ?? ''))),
        'nombre_razon_social' => trim((string) ($_POST['nombre_razon_social'] ?? '')),
        'direccion_fiscal'    => trim((string) ($_POST['direccion_fiscal'] ?? '')),
        'telefono'            => trim((string) ($_POST['telefono'] ?? '')),
    ];

    if ($datos['rif_cedula'] === '') {
        throw new InvalidArgumentException('La cédula o RIF es obligatorio.');
    }

    if (!preg_match('/^[VEJGP]-\d{6,9}(-\d)?$/', $datos['rif_cedula'])) {
        throw new InvalidArgumentException(
            'Formato inválido. Usa V-12345678 o J-12345678-9 (letra, guion y números).'
        );
    }

    if ($datos['nombre_razon_social'] === '') {
        throw new InvalidArgumentException('El nombre o razón social es obligatorio.');
    }

    if (mb_strlen($datos['nombre_razon_social']) > 120) {
        throw new InvalidArgumentException('El nombre no puede superar los 120 caracteres.');
    }

    if (mb_strlen($datos['direccion_fiscal']) > 180) {
        throw new InvalidArgumentException('La dirección no puede superar los 180 caracteres.');
    }

    if (mb_strlen($datos['telefono']) > 25) {
        throw new InvalidArgumentException('El teléfono no puede superar los 25 caracteres.');
    }

    return $datos;
}

$modelo = new ClienteModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_admin()) {
    csrf_verify();
    flash('error', 'Solo un administrador puede crear o modificar clientes.');
    redirect(url('clientes'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $accionPost = (string) ($_POST['accion'] ?? '');

    try {
        if ($accionPost === 'guardar') {
            $datos = clientes_validar();
            $id    = (int) ($_POST['id_cliente'] ?? 0);

            if ($modelo->existeRif($datos['rif_cedula'], $id)) {
                throw new RuntimeException('Ya existe otro cliente con esa cédula o RIF.');
            }

            if ($id > 0) {
                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('El cliente que intentas editar no existe.');
                }
                $modelo->actualizar($id, $datos);
                flash('ok', 'Cliente actualizado correctamente.');
            } else {
                $modelo->crear($datos);
                flash('ok', 'Cliente registrado correctamente.');
            }
        } elseif ($accionPost === 'eliminar') {
            $id = (int) ($_POST['id_cliente'] ?? 0);

            if ($id <= 0) {
                throw new InvalidArgumentException('Cliente inválido.');
            }

            $modelo->eliminar($id);
            flash('ok', 'Cliente eliminado correctamente.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        $_SESSION['old_cliente'] = $_POST;

        $id = (int) ($_POST['id_cliente'] ?? 0);
        redirect($id > 0 ? url('clientes', ['editar' => $id]) : url('clientes'));
    }

    redirect(url('clientes'));
}

$busqueda = trim((string) ($_GET['q'] ?? ''));
$clientes = $modelo->listar($busqueda);

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $modelo->buscar((int) $_GET['editar']);
}

$old = $_SESSION['old_cliente'] ?? null;
unset($_SESSION['old_cliente']);

$titulo    = 'Clientes';
$subtitulo = 'Cédulas, RIF y datos fiscales';
$activo    = 'clientes';

require __DIR__ . '/../vistas/clientes/index.php';
