<?php
declare(strict_types=1);

/**
 * Cierre de sesión por POST y con token CSRF.
 */
final class LogoutController extends BaseController
{
    public function logout(): void
    {
        if (!$this->esPost()) {
            $this->redirigir('login');
        }

        csrf_verify();

        if (is_logged_in()) {
            $modelo = new UsuarioModel();
            $modelo->registrarBitacora(current_user_id(), 'LOGOUT', [
                'usuario' => current_user()['usuario_login'],
            ]);
        }

        logout_user();
        $this->redirigir('login', ['cerrado' => 1]);
    }
}
