<?php
/**
 * includes/bootstrap.php
 * -----------------------------------------------------------------
 * Arranque de la aplicación.
 *
 * Todo lo que entra por index.php o login.php pasa primero por acá.
 * El orden importa: primero configuración, después manejo de errores,
 * después base de datos, helpers, seguridad y por último la sesión.
 *
 * Con este orden, aunque haya un error en un controlador, la pantalla
 * de error ya está armada y el error queda en el log, no expuesto.
 * -----------------------------------------------------------------
 */

// ------------------------------------------------------------
// 1) Configuración
// ------------------------------------------------------------
require_once __DIR__ . '/../config/config.php';

// ------------------------------------------------------------
// 2) Manejo de errores
// ------------------------------------------------------------
// En producción no se muestra nada de PHP al usuario: todo se
// guarda en logs/app.log y se muestra una pantalla genérica.
if (!APP_DEBUG) {
    // No mostrar errores en pantalla
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    // Crear la carpeta de logs si no existe (puede fallar por permisos)
    if (!is_dir(APP_LOG_DIR)) {
        @mkdir(APP_LOG_DIR, 0775, true);
    }

    if (is_dir(APP_LOG_DIR) && is_writable(APP_LOG_DIR)) {
        ini_set('error_log', APP_LOG_DIR . '/app.log');
    } else {
        // Si no se puede escribir, al menos que quede en el error_log del servidor.
        ini_set('error_log', '');
    }

    error_reporting(E_ALL);
} else {
    // Modo desarrollo: se ve todo en pantalla (solo en tu PC).
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    ini_set('error_log', '');
}

// ------------------------------------------------------------
// 3) Fecha y hora: siempre Buenos Aires
// ------------------------------------------------------------
date_default_timezone_set(APP_TIMEZONE);

// ------------------------------------------------------------
// 4) Núcleo de la aplicación
// ------------------------------------------------------------
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auditoria.php';
// El router cierra el arranque: es el que despacha a los módulos.
require_once __DIR__ . '/router.php';

// ------------------------------------------------------------
// 5) Sesión segura
// ------------------------------------------------------------
// Los parámetros de la cookie se fijan ANTES de iniciar la sesión,
// porque después ya no se pueden cambiar.
if (session_status() === PHP_SESSION_NONE) {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

    session_set_cookie_params([
        'lifetime' => 0,       // Cookie de sesión: se borra al cerrar el navegador
        'path'     => APP_BASE_URL !== '' ? APP_BASE_URL . '/' : '/',
        'domain'   => '',
        'secure'   => $esHttps, // Solo por HTTPS si el sitio tiene HTTPS
        'httponly' => true,     // JavaScript no puede leer la cookie (anti XSS)
        'samesite' => 'Lax',    // Evita envío de cookies en requests de terceros
    ]);

    // Nombre de la cookie con prefijo para no choque con otras apps del hosting.
    session_name('ATILIO_SID');

    // Usar cookies estrictas: si el hosting no tiene sesiones en archivos,
    // la app sigue funcionando (el hosting de ByetHost sí las tiene).
    ini_set('session.use_strict_mode', '1');

    session_start();
}

// ------------------------------------------------------------
// 6) Errores no capturados y excepciones
// ------------------------------------------------------------
set_exception_handler(function (Throwable $e): void {
    // Loguear el detalle real (nunca se muestra al usuario).
    error_log(sprintf(
        '[%s] %s en %s:%d%sTraza: %s',
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    // Los errores de base de datos casi siempre son credenciales malas.
    // En ese caso tiene más sentido mandar al instalador.
    if ($e instanceof PDOException) {
        $mensajeError = 'No se pudo conectar con la base de datos. Revisá los datos en config/config.php y que la base exista en el hosting.';
        $titulo       = 'Error de conexión';
        require __DIR__ . '/vistas/500.php';
        exit;
    }

    // Se mira la sesión sin pasar por Auth::usuario(), que consulta la
    // base y podría fallar justo ahora. Con ver el ID alcanza.
    $titulo = !empty($_SESSION['usuario_id']) ? 'Algo salió mal' : 'Error';
    require __DIR__ . '/vistas/500.php';
    exit;
});

// Errores de PHP que no son excepciones (avisos, deprecated)
set_error_handler(function (int $severidad, string $mensaje, string $archivo = '', int $linea = 0): bool {
    // Si el error no está permitido por error_reporting, se ignora.
    if (!(error_reporting() & $severidad)) {
        return false;
    }

    // Los avisos y notices no cortan la ejecución: se loguean y se sigue.
    error_log(sprintf('[%s] %s en %s:%d', date('Y-m-d H:i:s'), $mensaje, $archivo, $linea));

    return true;
});