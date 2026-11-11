<?php
declare(strict_types=1);

/**
 * Corte / cierre de caja diario.
 *
 * El ADMIN ve todas las ventas del día. El CAJERO ve únicamente sus propias
 * ventas, para que pueda cuadrar su caja sin acceder a la operación global.
 */
final class CierreController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $fecha = trim((string) ($_GET['fecha'] ?? ''));

        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $valida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($valida === false || $valida->format('Y-m-d') !== $fecha) {
            $this->aviso('error', 'La fecha del cierre no es válida.');
            $this->redirigir('cierre');
        }

        $esAdmin   = is_admin();
        $idUsuario = $esAdmin ? null : current_user_id();
        $modelo    = new FacturaModel();

        $this->render('cierre/index', [
            'titulo'    => 'Cierre de caja',
            'subtitulo' => $esAdmin
                ? 'Corte diario de todas las ventas'
                : 'Corte diario de tus ventas',
            'activo'    => 'cierre',
            'fecha'     => $fecha,
            'esAdmin'   => $esAdmin,
            'resumen'   => $modelo->resumenCierre($fecha, $idUsuario),
        ]);
    }
}
