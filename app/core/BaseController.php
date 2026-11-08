<?php
declare(strict_types=1);

/**
 * Base de todos los controladores.
 *
 * Centraliza el renderizado de vistas, las redirecciones y el tratamiento
 * seguro de errores para no repetir esa lógica en cada controlador.
 */
abstract class BaseController
{
    /**
     * Renderiza una vista con las variables indicadas.
     */
    protected function render(string $vista, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);
        require __DIR__ . '/../vistas/' . $vista . '.php';
    }

    protected function redirigir(string $ruta, array $query = []): void
    {
        redirect(url($ruta, $query));
    }

    protected function aviso(string $tipo, string $mensaje): void
    {
        flash($tipo, $mensaje);
    }

    /**
     * Registra el error técnico y muestra un mensaje seguro al usuario.
     */
    protected function fallo(Throwable $e, string $contexto, string $ruta, array $query = []): void
    {
        $this->aviso('error', error_usuario($e, $contexto));
        $this->redirigir($ruta, $query);
    }

    /**
     * Indica si la petición actual es POST.
     */
    protected function esPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    protected function exigirLogin(): void
    {
        require_login();
    }

    protected function exigirAdmin(): void
    {
        require_admin();
    }
}
