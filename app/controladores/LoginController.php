<?php
declare(strict_types=1);

/**
 * Acceso al sistema.
 *
 * El login siempre exige usuario y contraseña: si se llega con una sesión
 * abierta, se cierra antes de mostrar el formulario.
 */
final class LoginController extends BaseController
{
    public function index(): void
    {
        no_cache_headers();

        // Si se llega al login con una sesión abierta, se cierra: entrar al
        // sistema siempre exige escribir la contraseña en este punto.
        if (!$this->esPost() && is_logged_in()) {
            logout_user();
            redirect(url('login', ['reiniciado' => 1]));
        }

        $modelo = new UsuarioModel();
        $login  = '';

        if ($this->esPost()) {
            csrf_verify();

            $login = trim((string) ($_POST['usuario_login'] ?? ''));
            $clave = (string) ($_POST['password'] ?? '');

            try {
                if ($login === '' || $clave === '') {
                    throw new InvalidArgumentException('Ingresa tu usuario y contraseña.');
                }

                $usuario = $modelo->autenticar($login, $clave);

                if (!$usuario) {
                    throw new RuntimeException(
                        'Usuario o contraseña incorrectos, o el usuario está inactivo.'
                    );
                }

                login_user($usuario);
                $modelo->registrarBitacora((int) $usuario['id_usuario'], 'LOGIN', [
                    'usuario'   => $usuario['usuario_login'],
                    'resultado' => 'OK',
                ]);

                $this->aviso('ok', 'Bienvenido, ' . $usuario['nombre_completo'] . '.');
                $this->redirigir('panel');
            } catch (Throwable $e) {
                $this->aviso('error', error_usuario($e, 'iniciar sesión'));

                $modelo->registrarBitacora(null, 'LOGIN_FALLIDO', [
                    'usuario'   => $login,
                    'resultado' => 'ERROR',
                ]);
            }
        }

        $this->render('auth/login', [
            'titulo'     => 'Iniciar sesión',
            'login'      => $login,
            'cerrado'    => isset($_GET['cerrado']),
            'reiniciado' => isset($_GET['reiniciado']),
        ]);
    }
}
