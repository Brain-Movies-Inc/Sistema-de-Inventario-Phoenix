<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Punto de entrada único
|--------------------------------------------------------------------------
| Todas las peticiones pasan por este archivo. El servidor embebido de PHP
| devuelve `false` para los archivos estáticos (CSS/JS); Apache los sirve
| directamente gracias a public/.htaccess.
|
| El Front Controller solo resuelve la ruta e instancia la clase del
| controlador correspondiente. Cada controlador maneja su propia petición.
*/

if (PHP_SAPI === 'cli-server') {
    $rutaArchivo = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $archivo     = __DIR__ . $rutaArchivo;

    // Solo los archivos estáticos se sirven directamente; los .php pasan por el router.
    if (is_file($archivo) && strtolower(substr($archivo, -4)) !== '.php') {
        return false;
    }
}

require __DIR__ . '/../config/bootstrap.php';

$ruta = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$base = rtrim(base_url(), '/');

if ($base !== '' && strncasecmp($ruta, $base, strlen($base)) === 0) {
    $resto = substr($ruta, strlen($base));

    if ($resto === '' || $resto[0] === '/') {
        $ruta = $resto;
    }
}

$ruta = '/' . trim($ruta, '/');

/*
|--------------------------------------------------------------------------
| Compatibilidad con las URLs antiguas (.php)
|--------------------------------------------------------------------------
*/
$legacy = [
    '/index.php'       => 'panel',
    '/login.php'       => 'login',
    '/logout.php'      => 'logout',
    '/productos.php'   => 'productos',
    '/clientes.php'    => 'clientes',
    '/facturacion.php' => 'facturacion',
    '/usuarios.php'    => 'usuarios',
    '/tasas.php'       => 'tasas',
];

if (isset($legacy[$ruta])) {
    $destino = $legacy[$ruta];
    $query   = $_GET;

    if ($ruta === '/facturacion.php') {
        $accion = (string) ($_GET['accion'] ?? '');

        if ($accion === 'nuevo' || $accion === 'nueva') {
            $destino = 'facturacion/nueva';
            unset($query['accion']);
        } elseif ($accion === 'ver' && (int) ($_GET['id'] ?? 0) > 0) {
            $destino = 'facturacion/ver/' . (int) $_GET['id'];
            unset($query['accion'], $query['id']);
        }
    }

    redirect(url($destino, $query));
}

/*
|--------------------------------------------------------------------------
| Rutas
|--------------------------------------------------------------------------
| El router no ejecuta lógica de negocio ni imprime vistas: solo despacha
| la petición al método del controlador.
*/
try {
    if ($ruta === '/' || $ruta === '/login') {
        (new LoginController())->index();
    } elseif ($ruta === '/panel') {
        (new DashboardController())->index();
    } elseif ($ruta === '/logout') {
        (new LogoutController())->logout();
    } elseif ($ruta === '/productos') {
        (new ProductosController())->index();
    } elseif ($ruta === '/clientes') {
        (new ClientesController())->index();
    } elseif ($ruta === '/facturacion') {
        (new FacturacionController())->index();
    } elseif ($ruta === '/mermas') {
        (new MermasController())->index();
    } elseif ($ruta === '/facturacion/nueva') {
        (new FacturacionController())->nueva();
    } elseif ($ruta === '/facturacion/exportar') {
        (new FacturacionController())->exportar();
    } elseif (preg_match('#^/facturacion/ver/(\d+)$#', $ruta, $coincidencia) === 1) {
        (new FacturacionController())->ver((int) $coincidencia[1]);
    } elseif (preg_match('#^/facturacion/pdf/(\d+)$#', $ruta, $coincidencia) === 1) {
        (new FacturacionController())->pdf((int) $coincidencia[1]);
    } elseif (preg_match('#^/facturacion/imagen/(\d+)$#', $ruta, $coincidencia) === 1) {
        (new FacturacionController())->imagen((int) $coincidencia[1]);
    } elseif ($ruta === '/usuarios') {
        (new UsuariosController())->index();
    } elseif ($ruta === '/tasas') {
        (new TasasController())->index();
    } else {
        http_response_code(404);
        require __DIR__ . '/../app/vistas/errores/404.php';
    }
} catch (Throwable $e) {
    error_log(sprintf(
        '[De Todito Reyes] Error no controlado en %s: %s en %s:%d',
        $ruta,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    http_response_code(500);
    require __DIR__ . '/../app/vistas/errores/500.php';
}
