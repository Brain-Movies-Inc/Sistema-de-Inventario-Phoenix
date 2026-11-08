<?php
declare(strict_types=1);

/**
 * Gestión de clientes: listado, alta, edición y baja.
 */
final class ClientesController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new ClienteModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $clientes = $modelo->listar($busqueda);

        $editar = null;
        if (is_admin() && isset($_GET['editar'])) {
            $editar = $modelo->buscar((int) $_GET['editar']);
        }

        $old = $_SESSION['old_cliente'] ?? null;
        unset($_SESSION['old_cliente']);

        $this->render('clientes/index', [
            'titulo'    => 'Clientes',
            'subtitulo' => 'Cédulas, RIF y datos fiscales',
            'activo'    => 'clientes',
            'busqueda'  => $busqueda,
            'clientes'  => $clientes,
            'editar'    => $editar,
            'old'       => $old,
        ]);
    }

    private function procesarPost(ClienteModel $modelo): void
    {
        csrf_verify();

        $accion = (string) ($_POST['accion'] ?? '');
        $id     = (int) ($_POST['id_cliente'] ?? 0);

        // El CAJERO puede registrar clientes nuevos, pero editar o eliminar
        // sigue reservado al ADMIN para no alterar datos fiscales existentes.
        if (!is_admin() && ($accion !== 'guardar' || $id > 0)) {
            $this->aviso('error', 'Solo un administrador puede editar o eliminar clientes.');
            $this->redirigir('clientes');
        }

        try {
            if ($accion === 'guardar') {
                $datos = $this->validar();

                if ($modelo->existeRif($datos['rif_cedula'], $id)) {
                    throw new RuntimeException('Ya existe otro cliente con esa cédula o RIF.');
                }

                if ($id > 0) {
                    if (!$modelo->buscar($id)) {
                        throw new RuntimeException('El cliente que intentas editar no existe.');
                    }

                    $modelo->actualizar($id, $datos);
                    $this->aviso('ok', 'Cliente actualizado correctamente.');
                } else {
                    $modelo->crear($datos);
                    $this->aviso('ok', 'Cliente registrado correctamente.');
                }
            } elseif ($accion === 'eliminar') {
                if ($id <= 0) {
                    throw new InvalidArgumentException('Cliente inválido.');
                }

                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('El cliente que intentas eliminar no existe.');
                }

                $modelo->eliminar($id);
                $this->aviso('ok', 'Cliente eliminado correctamente.');
            } else {
                throw new InvalidArgumentException('La acción solicitada no es válida.');
            }
        } catch (Throwable $e) {
            $this->aviso(
                'error',
                error_usuario($e, $accion === 'eliminar' ? 'eliminar el cliente' : 'guardar el cliente')
            );

            if ($accion === 'guardar') {
                $_SESSION['old_cliente'] = $_POST;

                if ($id > 0) {
                    $this->redirigir('clientes', ['editar' => $id]);
                }
            }

            $this->redirigir('clientes');
        }

        $this->redirigir('clientes');
    }

    private function validar(): array
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
}
