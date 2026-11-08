<?php
declare(strict_types=1);

/**
 * Panel principal.
 *
 * ADMIN ve el resumen operativo completo del negocio. El CAJERO tiene un
 * panel propio, limitado a sus ventas, sin inventario, costos ni usuarios.
 */
final class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->exigirLogin();

        $modelo = new DashboardModel();
        $tasa   = $modelo->tasaVigente();

        if (!is_admin()) {
            $this->render('dashboard/cajero', [
                'titulo'             => 'Mi panel',
                'subtitulo'          => 'Resumen de tus ventas y accesos rápidos',
                'activo'             => 'inicio',
                'resumen'            => $modelo->resumenCajero(current_user_id()),
                'ultimasFacturas'    => $modelo->ultimasFacturasCajero(current_user_id(), 8),
                'mermas'             => $modelo->resumenMermas(4),
                'tasa'               => $tasa,
                'tasaDesactualizada' => tasa_desactualizada($tasa),
            ]);

            return;
        }

        $this->render('dashboard/index', [
            'titulo'             => 'Panel principal',
            'subtitulo'          => 'Resumen operativo del negocio',
            'activo'             => 'inicio',
            'resumen'            => $modelo->resumen(),
            'tasa'               => $tasa,
            'tasaDesactualizada' => tasa_desactualizada($tasa),
            'stockBajo'          => $modelo->stockBajo(8),
            'ultimasFacturas'    => $modelo->ultimasFacturas(6),
            'topProductos'       => $modelo->topProductos(5),
            'mermas'             => $modelo->resumenMermas(5),
        ]);
    }
}
