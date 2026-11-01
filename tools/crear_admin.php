<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Recuperación de acceso ADMIN (solo consola)
|--------------------------------------------------------------------------
| Este script permite crear un administrador nuevo o restablecer la
| contraseña de un administrador existente cuando se olvidó el acceso.
|
| Uso:
|   php tools/crear_admin.php
|   php tools/crear_admin.php --listar
|   php tools/crear_admin.php --crear --cedula=V-99999999 --nombre="Admin Respaldo" --usuario=admin2 --clave=ClaveSegura123 --si
|   php tools/crear_admin.php --resetear --usuario=admin --clave=NuevaClave123 --si
|   php tools/crear_admin.php --resetear --id=1 --clave=NuevaClave123 --si
|
| Nunca se puede ejecutar desde el navegador: si se abre por HTTP responde 403.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido. Este script solo se ejecuta desde la consola.');
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/modelos/UsuarioModel.php';

/**
 * Lee una línea de la consola.
 */
function cli_leer(string $etiqueta, string $defecto = ''): string
{
    $sufijo = $defecto !== '' ? ' [' . $defecto . ']' : '';
    fwrite(STDOUT, $etiqueta . $sufijo . ': ');
    $linea = fgets(STDIN);

    if ($linea === false) {
        return $defecto;
    }

    $linea = trim($linea);

    return $linea === '' ? $defecto : $linea;
}

/**
 * Pide una contraseña. Se muestra al escribir (Windows no permite ocultarla
 * de forma portable); usa --clave si prefieres no escribirla en pantalla.
 */
function cli_clave(string $etiqueta): string
{
    fwrite(STDOUT, $etiqueta . ' (se verá al escribir): ');
    $linea = fgets(STDIN);

    return $linea === false ? '' : trim($linea);
}

function cli_confirmar(string $pregunta): bool
{
    $respuesta = strtolower(cli_leer($pregunta . ' (s/n)', 'n'));

    return in_array($respuesta, ['s', 'si', 'sí', 'y', 'yes'], true);
}

function cli_titulo(string $texto): void
{
    fwrite(STDOUT, PHP_EOL . '=== ' . $texto . ' ===' . PHP_EOL);
}

function cli_error(string $texto): void
{
    fwrite(STDERR, '[ERROR] ' . $texto . PHP_EOL);
}

/**
 * Valida cédula, nombre, usuario y clave para un ADMIN.
 *
 * @return array{cedula:string,nombre_completo:string,usuario_login:string,clave:string}
 */
function cli_validar_admin(array $datos): array
{
    $datos['cedula']          = strtoupper(trim((string) ($datos['cedula'] ?? '')));
    $datos['nombre_completo'] = trim((string) ($datos['nombre_completo'] ?? ''));
    $datos['usuario_login']   = strtolower(trim((string) ($datos['usuario_login'] ?? '')));
    $datos['clave']           = (string) ($datos['clave'] ?? '');

    if (!preg_match('/^[VEJGP]-\d{6,9}(-\d)?$/', $datos['cedula'])) {
        throw new InvalidArgumentException('Cédula inválida. Usa el formato V-12345678.');
    }

    if ($datos['nombre_completo'] === '') {
        throw new InvalidArgumentException('El nombre completo es obligatorio.');
    }

    if (mb_strlen($datos['nombre_completo']) > 100) {
        throw new InvalidArgumentException('El nombre no puede superar los 100 caracteres.');
    }

    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $datos['usuario_login'])) {
        throw new InvalidArgumentException(
            'El usuario debe tener entre 3 y 40 caracteres: letras minúsculas, números, punto, guion o guion bajo.'
        );
    }

    if (strlen($datos['clave']) < 8) {
        throw new InvalidArgumentException('Para un ADMIN la contraseña debe tener al menos 8 caracteres.');
    }

    if (strlen($datos['clave']) > 72) {
        throw new InvalidArgumentException('La contraseña no puede superar los 72 caracteres.');
    }

    return $datos;
}

function cli_listar_admins(UsuarioModel $modelo): void
{
    $admins = array_values(array_filter(
        $modelo->listar(),
        static fn(array $u): bool => $u['rol'] === 'ADMIN'
    ));

    if ($admins === []) {
        fwrite(STDOUT, 'No hay administradores registrados.' . PHP_EOL);
        return;
    }

    fwrite(STDOUT, sprintf("%-4s %-22s %-28s %-10s %s" . PHP_EOL, 'ID', 'USUARIO', 'NOMBRE', 'ESTADO', 'CÉDULA'));

    foreach ($admins as $u) {
        fwrite(STDOUT, sprintf(
            "%-4d %-22s %-28s %-10s %s" . PHP_EOL,
            (int) $u['id_usuario'],
            $u['usuario_login'],
            mb_substr($u['nombre_completo'], 0, 27),
            (int) $u['activo'] === 1 ? 'Activo' : 'Inactivo',
            $u['cedula']
        ));
    }
}

/**
 * Crea un ADMIN nuevo.
 */
function cli_crear_admin(UsuarioModel $modelo, array $opciones): int
{
    cli_titulo('Crear administrador');

    $datos = [
        'cedula'          => (string) ($opciones['cedula'] ?? ''),
        'nombre_completo' => (string) ($opciones['nombre'] ?? ''),
        'usuario_login'   => (string) ($opciones['usuario'] ?? ''),
        'clave'           => (string) ($opciones['clave'] ?? ''),
    ];

    if ($datos['cedula'] === '') {
        $datos['cedula'] = cli_leer('Cédula (ej. V-12345678)');
    }
    if ($datos['nombre_completo'] === '') {
        $datos['nombre_completo'] = cli_leer('Nombre completo');
    }
    if ($datos['usuario_login'] === '') {
        $datos['usuario_login'] = cli_leer('Usuario de acceso');
    }
    if ($datos['clave'] === '') {
        $datos['clave'] = cli_clave('Contraseña');
        $repetida       = cli_clave('Repite la contraseña');

        if ($datos['clave'] !== $repetida) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }
    }

    $datos = cli_validar_admin($datos);

    if ($modelo->existeCedula($datos['cedula'])) {
        throw new RuntimeException('Ya existe un usuario con esa cédula.');
    }

    if ($modelo->existeLogin($datos['usuario_login'])) {
        throw new RuntimeException('Ese nombre de usuario ya está en uso.');
    }

    fwrite(STDOUT, PHP_EOL . 'Se creará este ADMIN:' . PHP_EOL);
    fwrite(STDOUT, '  Nombre : ' . $datos['nombre_completo'] . PHP_EOL);
    fwrite(STDOUT, '  Cédula : ' . $datos['cedula'] . PHP_EOL);
    fwrite(STDOUT, '  Usuario: ' . $datos['usuario_login'] . PHP_EOL);

    if (!isset($opciones['si']) && !cli_confirmar('¿Confirmas la creación?')) {
        fwrite(STDOUT, 'Cancelado.' . PHP_EOL);
        return 0;
    }

    $id = $modelo->crear([
        'cedula'          => $datos['cedula'],
        'nombre_completo' => $datos['nombre_completo'],
        'usuario_login'   => $datos['usuario_login'],
        'rol'             => 'ADMIN',
    ], $datos['clave']);

    $modelo->registrarBitacora(null, 'CREAR_ADMIN', [
        'id_usuario_creado' => $id,
        'usuario'           => $datos['usuario_login'],
        'origen'            => 'CLI',
    ]);

    fwrite(STDOUT, '[OK] ADMIN creado con id ' . $id . '.' . PHP_EOL);

    return 0;
}

/**
 * Restablece la contraseña de un ADMIN existente.
 */
function cli_resetear_admin(UsuarioModel $modelo, array $opciones): int
{
    cli_titulo('Restablecer contraseña de un ADMIN');

    $id     = (int) ($opciones['id'] ?? 0);
    $login  = strtolower(trim((string) ($opciones['usuario'] ?? '')));
    $clave  = (string) ($opciones['clave'] ?? '');

    if ($id <= 0 && $login === '') {
        cli_listar_admins($modelo);
        $id = (int) cli_leer('ID del ADMIN a restablecer');
    }

    $usuario = $id > 0 ? $modelo->buscar($id) : null;

    if (!$usuario && $login !== '') {
        foreach ($modelo->listar() as $fila) {
            if (strtolower((string) $fila['usuario_login']) === $login) {
                $usuario = $fila;
                break;
            }
        }
    }

    if (!$usuario) {
        throw new RuntimeException('No se encontró ese usuario.');
    }

    if ($usuario['rol'] !== 'ADMIN') {
        throw new RuntimeException('El usuario "' . $usuario['usuario_login'] . '" no es ADMIN. Usa la pantalla Usuarios para gestionarlo.');
    }

    if ($clave === '') {
        $clave    = cli_clave('Nueva contraseña');
        $repetida = cli_clave('Repite la nueva contraseña');

        if ($clave !== $repetida) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }
    }

    if (strlen($clave) < 8) {
        throw new RuntimeException('Para un ADMIN la contraseña debe tener al menos 8 caracteres.');
    }

    if (strlen($clave) > 72) {
        throw new RuntimeException('La contraseña no puede superar los 72 caracteres.');
    }

    fwrite(STDOUT, PHP_EOL . 'Se cambiará la contraseña de:' . PHP_EOL);
    fwrite(STDOUT, '  Usuario: ' . $usuario['usuario_login'] . PHP_EOL);
    fwrite(STDOUT, '  Nombre : ' . $usuario['nombre_completo'] . PHP_EOL);

    if (!isset($opciones['si']) && !cli_confirmar('¿Confirmas el cambio?')) {
        fwrite(STDOUT, 'Cancelado.' . PHP_EOL);
        return 0;
    }

    $modelo->cambiarPassword((int) $usuario['id_usuario'], $clave);
    $modelo->registrarBitacora(null, 'RESET_ADMIN', [
        'id_usuario_afectado' => (int) $usuario['id_usuario'],
        'usuario'             => $usuario['usuario_login'],
        'origen'              => 'CLI',
    ]);

    fwrite(STDOUT, '[OK] Contraseña actualizada. Ya puedes iniciar sesión con "' . $usuario['usuario_login'] . '".' . PHP_EOL);

    return 0;
}

function cli_menu(): string
{
    cli_titulo('Recuperación de acceso ADMIN');
    fwrite(STDOUT, '1) Crear un ADMIN nuevo' . PHP_EOL);
    fwrite(STDOUT, '2) Restablecer la contraseña de un ADMIN' . PHP_EOL);
    fwrite(STDOUT, '3) Listar administradores' . PHP_EOL);
    fwrite(STDOUT, '4) Salir' . PHP_EOL);

    return cli_leer('Opción', '4');
}

/* --------------------------------------------------------------------- */

$opciones = [];
foreach (array_slice($argv, 1) as $argumento) {
    if (strpos($argumento, '--') !== 0) {
        continue;
    }

    $partes = explode('=', substr($argumento, 2), 2);
    $clave  = $partes[0];
    $valor  = $partes[1] ?? true;
    $opciones[$clave] = $valor;
}

if (isset($opciones['ayuda']) || isset($opciones['help'])) {
    fwrite(STDOUT, 'Uso:' . PHP_EOL);
    fwrite(STDOUT, '  php tools/crear_admin.php                     (menú interactivo)' . PHP_EOL);
    fwrite(STDOUT, '  php tools/crear_admin.php --listar' . PHP_EOL);
    fwrite(STDOUT, '  php tools/crear_admin.php --crear --cedula=V-99999999 --nombre="Admin Respaldo" --usuario=admin2 --clave=ClaveSegura123 --si' . PHP_EOL);
    fwrite(STDOUT, '  php tools/crear_admin.php --resetear --usuario=admin --clave=NuevaClave123 --si' . PHP_EOL);
    fwrite(STDOUT, '  php tools/crear_admin.php --resetear --id=1 --clave=NuevaClave123 --si' . PHP_EOL);
    exit(0);
}

try {
    $modelo = new UsuarioModel();
} catch (Throwable $e) {
    cli_error('No se pudo conectar con la base de datos: ' . $e->getMessage());
    exit(1);
}

try {
    if (isset($opciones['listar'])) {
        cli_listar_admins($modelo);
        exit(0);
    }

    if (isset($opciones['crear'])) {
        exit(cli_crear_admin($modelo, $opciones));
    }

    if (isset($opciones['resetear'])) {
        exit(cli_resetear_admin($modelo, $opciones));
    }

    // Modo interactivo.
    $opcion = cli_menu();

    switch ($opcion) {
        case '1':
            exit(cli_crear_admin($modelo, []));
        case '2':
            exit(cli_resetear_admin($modelo, []));
        case '3':
            cli_listar_admins($modelo);
            exit(0);
        case '4':
        default:
            fwrite(STDOUT, 'Nada que hacer.' . PHP_EOL);
            exit(0);
    }
} catch (InvalidArgumentException | RuntimeException $e) {
    cli_error($e->getMessage());
    exit(1);
} catch (Throwable $e) {
    cli_error('Error inesperado: ' . $e->getMessage());
    exit(1);
}
