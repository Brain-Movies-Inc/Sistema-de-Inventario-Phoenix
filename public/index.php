<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Punto de entrada único
|--------------------------------------------------------------------------
| Todas las peticiones pasan por este archivo. El servidor embebido de PHP
| devuelve `false` para los archivos estáticos (CSS/JS); Apache los sirve
| directamente gracias a public/.htaccess.
*/

if (PHP_SAPI === 'cli-server') {
    $rutaArchivo = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $archivo     = __DIR__ . $rutaArchivo;

    // Solo los archivos estáticos se sirven directamente; los .php pasan por el router.
    if (is_file($archivo) && !str_ends_with(strtolower($archivo), '.php')) {
        return false;
    }
}

require __DIR__ . '/../config/bootstrap.php';

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

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
    '/index.php'      => 'panel',
    '/login.php'      => 'login',
    '/logout.php'     => 'logout',
    '/productos.php'  => 'productos',
    '/clientes.php'   => 'clientes',
    '/facturacion.php' => 'facturacion',
    '/usuarios.php'   => 'usuarios',
];

if (isset($legacy[$ruta])) {
    $destino = $legacy[$ruta];
    $query   = $_GET;

    if ($ruta === '/facturacion.php') {
        $accion = (string) ($_GET['accion'] ?? '');

        if ($accion === 'nuevo') {
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
*/
$controladores = __DIR__ . '/../app/controladores';

switch (true) {
    case $ruta === '/' || $ruta === '/login':
        require $controladores . '/LoginController.php';
        break;

    case $ruta === '/panel':
        require_login();
        require $controladores . '/DashboardController.php';
        break;

    case $ruta === '/logout':
        require $controladores . '/LogoutController.php';
        break;

    case $ruta === '/productos':
        require_login();
        require $controladores . '/ProductosController.php';
        break;

    case $ruta === '/clientes':
        require_login();
        require $controladores . '/ClientesController.php';
        break;

    case $ruta === '/facturacion':
        require_login();
        require $controladores . '/FacturacionController.php';
        break;

    case $ruta === '/facturacion/nueva':
        require_login();
        $_GET['accion'] = 'nuevo';
        require $controladores . '/FacturacionController.php';
        break;

    case preg_match('#^/facturacion/ver/(\d+)$#', $ruta, $coincidencia) === 1:
        require_login();
        $_GET['accion'] = 'ver';
        $_GET['id']     = (int) $coincidencia[1];
        require $controladores . '/FacturacionController.php';
        break;

    case $ruta === '/usuarios':
        require_login();
        require $controladores . '/UsuariosController.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/../app/vistas/errores/404.php';
        break;
}
