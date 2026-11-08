<?php
declare(strict_types=1);

/**
 * Gestión de la tasa BCV (Bs por 1 USD).
 *
 * Todos los usuarios autenticados pueden consultar la tasa y su historial;
 * solo un ADMIN puede registrar o corregir valores.
 */
final class TasasController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new TasaModel();

        if ($this->esPost()) {
            $this->procesarPost($modelo);
        }

        $editar = null;
        if (isset($_GET['editar'])) {
            $editar = $modelo->buscar((int) $_GET['editar']);
        }

        $old = $_SESSION['old_tasa'] ?? null;
        unset($_SESSION['old_tasa']);

        $tasaActual = $modelo->vigente();

        $this->render('tasas/index', [
            'titulo'             => 'Tasas BCV',
            'subtitulo'          => 'Valor del dólar y historial de cambios',
            'activo'             => 'tasas',
            'tasaActual'         => $tasaActual,
            'tasaDesactualizada' => tasa_desactualizada($tasaActual),
            'tasas'              => $modelo->listar(100),
            'editar'             => $editar,
            'old'                => $old,
        ]);
    }

    private function procesarPost(TasaModel $modelo): void
    {
        csrf_verify();

        if (!is_admin()) {
            $this->aviso('error', 'Solo un administrador puede registrar o modificar la tasa BCV.');
            $this->redirigir('tasas');
        }

        $accion = (string) ($_POST['accion'] ?? '');
        $id     = (int) ($_POST['id_tasa'] ?? 0);

        try {
            if ($accion !== 'guardar') {
                throw new InvalidArgumentException('La acción solicitada no es válida.');
            }

            $datos = $this->validar();

            if ($modelo->existeFecha($datos['fecha_vigencia'], $id)) {
                throw new RuntimeException(
                    'Ya existe una tasa registrada con esa fecha y hora. Revisa el historial antes de guardar.'
                );
            }

            if ($id > 0) {
                if (!$modelo->buscar($id)) {
                    throw new RuntimeException('La tasa que intentas corregir no existe.');
                }

                $modelo->actualizar($id, $datos);
                $this->aviso('ok', 'Tasa actualizada correctamente.');
            } else {
                $modelo->crear($datos, current_user_id());
                $this->aviso('ok', 'Tasa registrada correctamente.');
            }
        } catch (Throwable $e) {
            $this->aviso('error', error_usuario($e, 'guardar la tasa BCV'));
            $_SESSION['old_tasa'] = $_POST;

            if ($id > 0) {
                $this->redirigir('tasas', ['editar' => $id]);
            }

            $this->redirigir('tasas');
        }

        $this->redirigir('tasas');
    }

    private function validar(): array
    {
        $tasaRaw = trim((string) ($_POST['tasa_bcv_ves'] ?? ''));
        $tasaRaw = str_replace(',', '.', $tasaRaw);

        if ($tasaRaw === '' || !is_numeric($tasaRaw)) {
            throw new InvalidArgumentException('Ingresa un valor numérico para la tasa.');
        }

        $tasa = round((float) $tasaRaw, 4);

        if ($tasa <= 0) {
            throw new InvalidArgumentException('La tasa debe ser mayor que cero.');
        }

        if ($tasa > 99999999.9999) {
            throw new InvalidArgumentException('La tasa ingresada es demasiado alta.');
        }

        $fechaRaw = trim((string) ($_POST['fecha_vigencia'] ?? ''));

        if ($fechaRaw === '') {
            throw new InvalidArgumentException('Indica la fecha y hora de vigencia de la tasa.');
        }

        $fecha = date_create_immutable($fechaRaw);

        if ($fecha === false) {
            throw new InvalidArgumentException('La fecha de vigencia no es válida.');
        }

        if ($fecha > new DateTimeImmutable('now +5 minutes')) {
            throw new InvalidArgumentException('La fecha de vigencia no puede estar en el futuro.');
        }

        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if (mb_strlen($observacion) > 100) {
            throw new InvalidArgumentException('La observación no puede superar los 100 caracteres.');
        }

        return [
            'tasa_bcv_ves'   => $tasa,
            'fecha_vigencia' => $fecha->format('Y-m-d H:i:s'),
            'observacion'    => $observacion,
        ];
    }
}
