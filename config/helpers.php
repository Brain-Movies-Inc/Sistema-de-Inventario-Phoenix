<?php
declare(strict_types=1);

define('SISTEMA_DR', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('America/Caracas');

/**
 * Escapa cualquier valor antes de imprimirlo en HTML.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Formato de moneda venezolano: 1.234,56
 */
function money($value, int $decimals = 2): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

function money3($value): string
{
    return money($value, 3);
}

/**
 * Formato apto para inputs y atributos data-*: 1234.560
 */
function input_number($value, int $decimals = 3): string
{
    return number_format((float) $value, $decimals, '.', '');
}

/**
 * Ruta base pública del sistema.
 *
 * Funciona tanto si el DocumentRoot es `public/` (devuelve "") como si el
 * sistema vive en una subcarpeta de Apache (devuelve "/sistema/public").
 */
function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $base    = '';
        $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $public  = realpath(__DIR__ . '/../public');

        if ($docRoot !== false && $public !== false) {
            $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
            $public  = str_replace('\\', '/', $public);

            if ($docRoot !== '' && strncasecmp($public, $docRoot, strlen($docRoot)) === 0) {
                $base = rtrim(substr($public, strlen($docRoot)), '/');
            }
        }
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * URL interna con query opcional: url('facturacion', ['estado' => 'EMITIDA']).
 */
function url(string $path = '', array $query = []): string
{
    $url = base_url($path);

    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('La sesión expiró. Recarga la página e inténtalo de nuevo.');
    }
}

/**
 * Usuario autenticado en la sesión actual.
 */
function current_user(): array
{
    if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }

    return [
        'id_usuario'      => 0,
        'nombre_completo' => 'Invitado',
        'usuario_login'   => '',
        'rol'             => '',
    ];
}

function current_user_id(): int
{
    return (int) current_user()['id_usuario'];
}

function is_logged_in(): bool
{
    return current_user_id() > 0;
}

function is_admin(): bool
{
    return (current_user()['rol'] ?? '') === 'ADMIN';
}

/**
 * Guarda al usuario en la sesión y renueva el id de sesión (evita fijación).
 */
function login_user(array $usuario): void
{
    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id_usuario'      => (int) $usuario['id_usuario'],
        'nombre_completo' => (string) $usuario['nombre_completo'],
        'usuario_login'   => (string) $usuario['usuario_login'],
        'rol'             => (string) $usuario['rol'],
    ];
    $_SESSION['ultima_actividad'] = time();

    // Token CSRF nuevo para la sesión autenticada.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Exige sesión iniciada y controla la inactividad (30 minutos).
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect(url('login'));
    }

    $limite = 30 * 60;
    $ultima = (int) ($_SESSION['ultima_actividad'] ?? time());

    if (time() - $ultima > $limite) {
        unset($_SESSION['usuario'], $_SESSION['ultima_actividad']);
        session_regenerate_id(true);
        flash('warn', 'Tu sesión expiró por inactividad. Inicia sesión de nuevo.');
        redirect(url('login'));
    }

    $_SESSION['ultima_actividad'] = time();
}

/**
 * Exige rol ADMIN.
 */
function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        flash('error', 'Solo un administrador puede realizar esa acción.');
        redirect(url('panel'));
    }
}

function metodo_pago_label(string $metodo): string
{
    $etiquetas = [
        'EFECTIVO'      => 'Efectivo',
        'PAGO_MOVIL'    => 'Pago móvil',
        'PUNTO_VENTA'   => 'Punto de venta',
        'TRANSFERENCIA' => 'Transferencia',
        'ZELLE'         => 'Zelle',
        'MIXTO'         => 'Mixto',
    ];

    return $etiquetas[$metodo] ?? $metodo;
}

function estado_badge(string $estado): string
{
    return $estado === 'ANULADA' ? 'badge-danger' : 'badge-ok';
}
