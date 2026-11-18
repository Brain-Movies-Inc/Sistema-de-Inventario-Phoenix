<?php
declare(strict_types=1);

/**
 * Cierre y reapertura de períodos fiscales mensuales (F-17).
 *
 * Solo el ADMIN puede cerrar o reabrir un período. Cada cambio queda en la
 * bitácora de auditoría. Mientras un período está cerrado, FacturaModel
 * rechaza la anulación y la eliminación de sus facturas.
 */
final class PeriodosController extends BaseController
{
    public function index(): void
    {
        $this->exigirAdmin();

        $modelo = new PeriodoModel();

        if ($this->esPost()) {
            csrf_verify();

            $accion = (string) ($_POST['accion'] ?? '');
            $anio   = (int) ($_POST['anio'] ?? 0);
            $mes    = (int) ($_POST['mes'] ?? 0);

            try {
                if ($accion === 'cerrar') {
                    $modelo->cerrar($anio, $mes, current_user_id());
                    $this->aviso(
                        'ok',
                        'Período ' . $this->etiqueta($anio, $mes) . ' cerrado. ' .
                        'Ya no se pueden anular ni eliminar sus facturas.'
                    );
                } elseif ($accion === 'reabrir') {
                    $modelo->reabrir($anio, $mes, current_user_id());
                    $this->aviso(
                        'ok',
                        'Período ' . $this->etiqueta($anio, $mes) . ' reabierto.'
                    );
                } else {
                    throw new InvalidArgumentException('La acción solicitada no es válida.');
                }
            } catch (Throwable $e) {
                $this->aviso('error', error_usuario($e, 'actualizar el período fiscal'));
            }

            $this->redirigir('periodos', $anio > 0 ? ['anio' => $anio] : []);
        }

        $anio = (int) ($_GET['anio'] ?? date('Y'));

        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }

        $anios = $modelo->aniosDisponibles();

        if (!in_array($anio, $anios, true)) {
            $anios[] = $anio;
            rsort($anios);
        }

        $this->render('periodos/index', [
            'titulo'    => 'Períodos fiscales',
            'subtitulo' => 'Cierre mensual y bloqueo de anulaciones',
            'activo'    => 'periodos',
            'anio'      => $anio,
            'anios'     => $anios,
            'periodos'  => $modelo->listar($anio),
        ]);
    }

    private function etiqueta(int $anio, int $mes): string
    {
        $meses = [
            1  => 'enero',
            2  => 'febrero',
            3  => 'marzo',
            4  => 'abril',
            5  => 'mayo',
            6  => 'junio',
            7  => 'julio',
            8  => 'agosto',
            9  => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return ($meses[$mes] ?? (string) $mes) . ' de ' . $anio;
    }
}
