<?php
declare(strict_types=1);

/**
 * Registro y consulta de mermas.
 *
 * Tanto el ADMIN como el CAJERO pueden reportar una merma: es una salida de
 * inventario que debe quedar documentada con motivo y observación.
 */
final class MermasController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new MermaModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        $old = $_SESSION['old_merma'] ?? null;
        unset($_SESSION['old_merma']);

        $this->render('mermas/index', [
            'titulo'    => 'Merma',
            'subtitulo' => 'Bajas de inventario por vencimiento, daño o pérdida',
            'activo'    => 'mermas',
            'productos' => $modelo->productos(),
            'mermas'    => $modelo->listar(),
            'motivos'   => MermaModel::MOTIVOS,
            'old'       => $old,
        ]);
    }

    private function procesarPost(MermaModel $modelo): void
    {
        csrf_verify();

        $accion = (string) ($_POST['accion'] ?? '');

        if ($accion !== 'registrar') {
            $this->aviso('error', 'La acción solicitada no es válida.');
            $this->redirigir('mermas');
        }

        try {
            $resultado = $modelo->registrar(
                (int) ($_POST['id_producto'] ?? 0),
                round((float) ($_POST['cantidad_baja'] ?? 0), 3),
                (string) ($_POST['tipo_motivo'] ?? 'OTRO'),
                trim((string) ($_POST['observacion'] ?? '')),
                current_user_id()
            );

            $this->aviso(
                'ok',
                'Merma registrada: ' . cantidad($resultado['cantidad']) . ' de ' .
                $resultado['producto'] . '. Stock restante: ' .
                cantidad($resultado['stock']) . '.'
            );
        } catch (Throwable $e) {
            $this->aviso('error', error_usuario($e, 'registrar la merma'));

            // Se conserva lo escrito, nunca datos sensibles.
            $_SESSION['old_merma'] = [
                'id_producto'   => (int) ($_POST['id_producto'] ?? 0),
                'cantidad_baja' => (string) ($_POST['cantidad_baja'] ?? ''),
                'tipo_motivo'   => (string) ($_POST['tipo_motivo'] ?? 'OTRO'),
                'observacion'   => (string) ($_POST['observacion'] ?? ''),
            ];
        }

        $this->redirigir('mermas');
    }
}
