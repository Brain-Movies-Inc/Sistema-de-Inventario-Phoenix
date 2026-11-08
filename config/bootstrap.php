<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/empresa.php';

/*
|--------------------------------------------------------------------------
| Autoload de la aplicación
|--------------------------------------------------------------------------
| Los controladores y modelos viven en app/core, app/modelos y
| app/controladores. El Front Controller solo instancia clases; ya no
| ejecuta scripts secuenciales.
*/
spl_autoload_register(static function (string $clase): void {
    foreach (['core', 'modelos', 'controladores'] as $carpeta) {
        $archivo = __DIR__ . '/../app/' . $carpeta . '/' . $clase . '.php';

        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});
