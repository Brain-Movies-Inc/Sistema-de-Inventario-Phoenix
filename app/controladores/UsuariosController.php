<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/UsuarioModel.php';

require_admin();

/**
 * Valida y normaliza los datos del formulario de usuarios.
 */
function usuarios_validar(): array
{
    $datos = [
        'cedula'          => strtoupper(trim((string) ($_POST['cedula'] ?? ''))),
        'nombre_completo' => trim((string) ($_POST['nombre_completo'] ?? '')),
        'usuario_login'   => strtolower(trim((string) ($_POST['usuario_login'] ?? ''))),
        'rol'             => strtoupper(trim((string) ($_POST['rol'] ?? 'CAJERO'))),
    ];

    if ($datos['cedula'] === '') {
        throw new InvalidArgumentException('La cédula es obligatoria.');
    }

    if (!preg_match('/^[VEJGP]-\d{6,9}(-\d)?$/', $datos['cedula'])) {
        throw new InvalidArgumentException('Cédula inválida. Usa el formato V-12345678.');
    }

    if ($datos['nombre_completo'] === '') {
        throw new InvalidArgumentException('El nombre completo es obligatorio.');
    }

    if (mb_strlen($datos['nombre_completo']) > 100) {
        throw new InvalidArgumentException('El nombre no puede superar los 100 caracteres.');
    }

    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $datos['usuario_login'])) {
        throw new InvalidArgumentException(
            'El usuario debe tener entre 3 y 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.'
        );
    }

    if (!in_array($datos['rol'], ['ADMIN', 'CAJERO'], true)) {
        throw new InvalidArgumentException('El rol seleccionado no es válido.');
    }

    return $datos;
}

/**
 * Valida una contraseña nueva.
 */
function usuarios_validar_clave(string $clave): string
{
    if (strlen($clave) < 6) {
        throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
    }

    if (strlen($clave) > 72) {
        throw new InvalidArgumentException('La contraseña no puede superar los 72 caracteres.');
    }

    return $clave;
}

$modelo = new UsuarioModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $accionPost = (string) ($_POST['accion'] ?? '');

    try {
        if ($accionPost === 'guardar') {
            $datos = usuarios_validar();
            $id    = (int) ($_POST['id_usuario'] ?? 0);
            $clave = (string) ($_POST['password'] ?? '');

            if ($modelo->existeCedula($datos['cedula'], $id)) {
                throw new RuntimeException('Ya existe otro usuario con esa cédula.');
            }

            if ($modelo->existeLogin($datos['usuario_login'], $id)) {
                throw new RuntimeException('Ese nombre de usuario ya está en uso.');
            }

            if ($id > 0) {
                $actual = $modelo->buscar($id);

                if (!$actual) {
                    throw new RuntimeException('El usuario que intentas editar no existe.');
                }

                if ($actual['rol'] === 'ADMIN'
                    && (int) $actual['activo'] === 1
                    && $datos['rol'] !== 'ADMIN'
                    && $modelo->contarAdminsActivos($id) === 0
                ) {
                    throw new RuntimeException(
                        'Debe quedar al menos un administrador activo. Asigna el rol ADMIN a otro usuario primero.'
                    );
                }

                $modelo->actualizar($id, $datos);

                if ($clave !== '') {
                    $modelo->cambiarPassword($id, usuarios_validar_clave($clave));
                    flash('ok', 'Usuario actualizado y contraseña cambiada.');
                } else {
                    flash('ok', 'Usuario actualizado correctamente.');
                }
            } else {
                if ($clave === '') {
                    throw new InvalidArgumentException('La contraseña es obligatoria para un usuario nuevo.');
                }

                $modelo->crear($datos, usuarios_validar_clave($clave));
                flash('ok', 'Usuario creado correctamente.');
            }
        } elseif ($accionPost === 'cambiar_estado') {
            $id     = (int) ($_POST['id_usuario'] ?? 0);
            $activo = (int) ($_POST['activo'] ?? 0) === 1;

            if ($id <= 0) {
                throw new InvalidArgumentException('Usuario inválido.');
            }

            if ($id === current_user_id()) {
                throw new RuntimeException('No puedes desactivar tu propia cuenta.');
            }

            $actual = $modelo->buscar($id);

            if (!$actual) {
                throw new RuntimeException('El usuario no existe.');
            }

            if (!$activo
                && $actual['rol'] === 'ADMIN'
                && (int) $actual['activo'] === 1
                && $modelo->contarAdminsActivos($id) === 0
            ) {
                throw new RuntimeException(
                    'Debe quedar al menos un administrador activo. Activa o crea otro ADMIN antes de desactivar este.'
                );
            }

            $modelo->cambiarActivo($id, $activo);
            flash('ok', $activo ? 'Usuario reactivado.' : 'Usuario desactivado.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        $_SESSION['old_usuario'] = $_POST;

        $id = (int) ($_POST['id_usuario'] ?? 0);
        redirect($id > 0 ? url('usuarios', ['editar' => $id]) : url('usuarios'));
    }

    redirect(url('usuarios'));
}

$busqueda = trim((string) ($_GET['q'] ?? ''));
$usuarios = $modelo->listar($busqueda);

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $modelo->buscar((int) $_GET['editar']);
}

$old = $_SESSION['old_usuario'] ?? null;
unset($_SESSION['old_usuario']);

$titulo    = 'Usuarios';
$subtitulo = 'Cuentas, roles y acceso al sistema';
$activo    = 'usuarios';

require __DIR__ . '/../vistas/usuarios/index.php';
