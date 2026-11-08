<?php
declare(strict_types=1);

define('SISTEMA_DR', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Rechaza IDs de sesión no generados por el servidor (fijación de sesión).
    ini_set('session.use_strict_mode', '1');

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
 * Formato de cantidades: 44 → 44, 1,5 → 1,5, 1,234 → 1,234.
 *
 * Evita mostrar «44,000» (que en español se lee como cuarenta y cuatro mil)
 * cuando la cantidad es un número entero. Las cantidades se guardan con hasta
 * tres decimales (peso, litros, gramos).
 */
function cantidad($value): string
{
    return numero($value, 3);
}

/**
 * Formato numérico general sin ceros decimales innecesarios.
 *
 * Se usa, por ejemplo, para la tasa BCV: 36,5000 → 36,5 y 900,0000 → 900.
 * El máximo de decimales permite conservar la precisión de la tasa.
 */
function numero($value, int $decimales = 4): string
{
    $numero = (float) $value;

    if ($numero == 0.0) {
        return '0';
    }

    $decimales = max(0, min(6, $decimales));
    $texto = number_format($numero, $decimales, ',', '.');
    $texto = rtrim(rtrim($texto, '0'), ',');

    return $texto === '' || $texto === '-' ? '0' : $texto;
}

/**
 * Número para un input type="number": sin ceros sobrantes y con punto decimal.
 */
function input_cantidad($value, int $decimales = 4): string
{
    $numero = (float) $value;

    if ($numero == 0.0) {
        return '0';
    }

    $decimales = max(0, min(6, $decimales));
    $texto = number_format($numero, $decimales, '.', '');
    $texto = rtrim(rtrim($texto, '0'), '.');

    return $texto === '' || $texto === '-' ? '0' : $texto;
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
    $token    = $_POST['csrf'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';

    // Un token vacío nunca es válido: sin esta comprobación, una petición sin
    // sesión compararía "" con "" y pasaría la validación (CSRF).
    if (!is_string($token)
        || !is_string($esperado)
        || $token === ''
        || $esperado === ''
        || !hash_equals($esperado, $token)
    ) {
        http_response_code(419);
        exit('La sesión expiró o la solicitud no es válida. Recarga la página e inténtalo de nuevo.');
    }
}

/**
 * Cabeceras de seguridad comunes a todas las respuestas.
 *
 * La CSP bloquea scripts inline y recursos externos: aunque una salida se
 * escapara mal, el navegador no ejecutaría un <script> inyectado.
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; " .
        "object-src 'none'; img-src 'self' data:; " .
        "style-src 'self' 'unsafe-inline'; script-src 'self'"
    );
}

security_headers();

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

function is_cajero(): bool
{
    return (current_user()['rol'] ?? '') === 'CAJERO';
}

/**
 * Impide que el navegador guarde en caché páginas con datos de sesión.
 *
 * Sin esto, el botón «Atrás» puede mostrar el panel de un usuario que ya
 * cerró sesión, dando la impresión de que se entró sin contraseña.
 */
function no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
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

/**
 * Sincroniza los datos del usuario en la sesión después de que un ADMIN
 * edita su propia cuenta (nombre, login o rol), para no conservar valores
 * obsoletos hasta el próximo inicio de sesión.
 */
function refresh_session_user(array $usuario): void
{
    if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
        return;
    }

    $_SESSION['usuario']['nombre_completo'] = (string) ($usuario['nombre_completo'] ?? '');
    $_SESSION['usuario']['usuario_login']   = (string) ($usuario['usuario_login'] ?? '');
    $_SESSION['usuario']['rol']             = (string) ($usuario['rol'] ?? '');
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
 * Revalida la cuenta contra la base de datos en cada petición autenticada.
 *
 * Así una desactivación o un cambio de rol surte efecto de inmediato, sin
 * esperar a que el usuario cierre sesión. También sincroniza nombre y login
 * por si un ADMIN editó la cuenta mientras estaba conectada.
 */
function validar_sesion_actual(): void
{
    $id = current_user_id();

    if ($id <= 0) {
        return;
    }

    $stmt = db()->prepare(
        "SELECT nombre_completo, usuario_login, rol, activo
         FROM usuarios
         WHERE id_usuario = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();

    if (!$usuario || (int) $usuario['activo'] !== 1) {
        unset($_SESSION['usuario'], $_SESSION['ultima_actividad']);
        session_regenerate_id(true);
        flash('warn', 'Tu cuenta ya no está activa o fue eliminada. Inicia sesión de nuevo.');
        redirect(url('login'));
    }

    refresh_session_user($usuario);
}

/**
 * Exige sesión iniciada y controla la inactividad (30 minutos).
 */
function require_login(): void
{
    no_cache_headers();

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

    validar_sesion_actual();

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

/**
 * Indica si la tasa vigente no corresponde al día de hoy.
 */
function tasa_desactualizada(?array $tasa): bool
{
    if (!$tasa || empty($tasa['fecha_vigencia'])) {
        return true;
    }

    $fecha = strtotime((string) $tasa['fecha_vigencia']);

    if ($fecha === false) {
        return true;
    }

    return date('Y-m-d', $fecha) < date('Y-m-d');
}

/**
 * Devuelve un mensaje seguro para el usuario y deja el detalle técnico
 * únicamente en el registro del servidor.
 *
 * Las excepciones de validación y las reglas de negocio de la aplicación
 * contienen mensajes escritos para el usuario; PDOException y los errores
 * internos de PHP nunca se exponen en pantalla.
 */
function error_usuario(Throwable $e, string $contexto = 'operación'): string
{
    error_log(sprintf(
        '[De Todito Reyes] Error al %s: %s en %s:%d',
        $contexto,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if ($e instanceof PDOException || $e instanceof Error) {
        return 'No se pudo completar la ' . $contexto . '. Intenta de nuevo o contacta al administrador.';
    }

    if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
        return $e->getMessage();
    }

    return 'No se pudo completar la ' . $contexto . '. Intenta de nuevo o contacta al administrador.';
}
