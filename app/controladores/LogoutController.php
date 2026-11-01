<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/UsuarioModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('login'));
}

csrf_verify();

if (is_logged_in()) {
    $modelo = new UsuarioModel();
    $modelo->registrarBitacora(current_user_id(), 'LOGOUT', [
        'usuario' => current_user()['usuario_login'],
    ]);
}

logout_user();
redirect(url('login', ['cerrado' => 1]));
