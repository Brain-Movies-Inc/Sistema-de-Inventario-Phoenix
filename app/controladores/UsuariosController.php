<?php
declare(strict_types=1);

/**
 * Administración de cuentas, roles y estado de acceso.
 *
 * Solo un ADMIN puede entrar a este módulo.
 */
final class UsuariosController extends BaseController
{
    public function index(): void
    {
        $this->exigirAdmin();

        $modelo = new UsuarioModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        $busqueda = trim((string) ($_GET['q'] ?? ''));

        $editar = null;
        if (isset($_GET['editar'])) {
            $editar = $modelo->buscar((int) $_GET['editar']);
        }

        $old = $_SESSION['old_usuario'] ?? null;
        unset($_SESSION['old_usuario']);

        $this->render('usuarios/index', [
            'titulo'    => 'Usuarios',
            'subtitulo' => 'Cuentas, roles y acceso al sistema',
            'activo'    => 'usuarios',
            'busqueda'  => $busqueda,
            'usuarios'  => $modelo->listar($busqueda),
            'editar'    => $editar,
            'old'       => $old,
        ]);
    }

    private function procesarPost(UsuarioModel $modelo): void
    {
        csrf_verify();

        $accion = (string) ($_POST['accion'] ?? '');
        $id     = (int) ($_POST['id_usuario'] ?? 0);
        $destino = 'usuarios';

        try {
            if ($accion === 'guardar') {
                $datos = $this->validar();
                $clave = (string) ($_POST['password'] ?? '');

                // Se valida antes de escribir: si la clave es inválida, no se
                // debe guardar a medias el resto de los datos del usuario.
                $claveValidada = $clave !== '' ? $this->validarClave($clave) : '';

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

                    if ($id === current_user_id()) {
                        refresh_session_user($datos);

                        // Si el ADMIN se quitó el rol, ya no puede seguir en este módulo.
                        if ($datos['rol'] !== 'ADMIN') {
                            $destino = 'panel';
                        }
                    }

                    if ($clave !== '') {
                        $modelo->cambiarPassword($id, $claveValidada);
                        $this->aviso('ok', 'Usuario actualizado y contraseña cambiada.');
                    } else {
                        $this->aviso('ok', 'Usuario actualizado correctamente.');
                    }
                } else {
                    if ($clave === '') {
                        throw new InvalidArgumentException(
                            'La contraseña es obligatoria para un usuario nuevo.'
                        );
                    }

                    $modelo->crear($datos, $claveValidada);
                    $this->aviso('ok', 'Usuario creado correctamente.');
                }
            } elseif ($accion === 'cambiar_estado') {
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
                $this->aviso('ok', $activo ? 'Usuario reactivado.' : 'Usuario desactivado.');
            } elseif ($accion === 'eliminar') {
                if ($id <= 0) {
                    throw new InvalidArgumentException('Usuario inválido.');
                }

                if ($id === current_user_id()) {
                    throw new RuntimeException('No puedes eliminar tu propia cuenta.');
                }

                $actual = $modelo->buscar($id);

                if (!$actual) {
                    throw new RuntimeException('El usuario no existe.');
                }

                if ($actual['rol'] === 'ADMIN'
                    && (int) $actual['activo'] === 1
                    && $modelo->contarAdminsActivos($id) === 0
                ) {
                    throw new RuntimeException(
                        'Debe quedar al menos un administrador activo. Activa o crea otro ADMIN antes de eliminar este.'
                    );
                }

                $modelo->eliminar($id);
                $this->aviso('ok', 'Usuario eliminado correctamente.');
            } else {
                throw new InvalidArgumentException('La acción solicitada no es válida.');
            }
        } catch (Throwable $e) {
            switch ($accion) {
                case 'eliminar':
                    $contexto = 'eliminar el usuario';
                    break;
                case 'cambiar_estado':
                    $contexto = 'cambiar el estado del usuario';
                    break;
                default:
                    $contexto = 'guardar el usuario';
                    break;
            }

            $this->aviso('error', error_usuario($e, $contexto));

            if ($accion === 'guardar') {
                // Se conservan los datos del formulario, nunca la contraseña.
                $old = $_POST;
                unset($old['password'], $old['csrf']);
                $_SESSION['old_usuario'] = $old;

                if ($id > 0) {
                    $this->redirigir('usuarios', ['editar' => $id]);
                }
            }

            $this->redirigir('usuarios');
        }

        $this->redirigir($destino);
    }

    private function validar(): array
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

    private function validarClave(string $clave): string
    {
        if (strlen($clave) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
        }

        if (strlen($clave) > 72) {
            throw new InvalidArgumentException('La contraseña no puede superar los 72 caracteres.');
        }

        return $clave;
    }
}
