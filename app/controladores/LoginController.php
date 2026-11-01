<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/UsuarioModel.php';

$modelo = new UsuarioModel();
$error  = null;
$login  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $login = trim((string) ($_POST['usuario_login'] ?? ''));
    $clave = (string) ($_POST['password'] ?? '');

    try {
        if ($login === '' || $clave === '') {
            throw new InvalidArgumentException('Ingresa tu usuario y contraseña.');
        }

        $usuario = $modelo->autenticar($login, $clave);

        if (!$usuario) {
            throw new RuntimeException('Usuario o contraseña incorrectos, o el usuario está inactivo.');
        }

        login_user($usuario);
        $modelo->registrarBitacora((int) $usuario['id_usuario'], 'LOGIN', [
            'usuario'   => $usuario['usuario_login'],
            'resultado' => 'OK',
        ]);

        flash('ok', 'Bienvenido, ' . $usuario['nombre_completo'] . '.');
        redirect(url('panel'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $modelo->registrarBitacora(null, 'LOGIN_FALLIDO', [
            'usuario'   => $login,
            'resultado' => 'ERROR',
        ]);
    }
}

$titulo    = 'Iniciar sesión';
$subtitulo = 'Acceso al sistema';

require __DIR__ . '/../vistas/auth/login.php';
