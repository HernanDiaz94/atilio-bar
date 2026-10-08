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
// config/config.php NO está en el repositorio de git: contiene la
// contraseña de MySQL. En una máquina nueva hay que crearlo a
// partir de la plantilla.
//
// Si falta, en vez de un error fatal de PHP le mostramos al usuario
// qué hacer. Esta comprobación va antes de cargar nada, así que no
// puede usar los helpers todavía: el HTML va escrito a mano.
$rutaConfig = __DIR__ . '/../config/config.php';

if (!is_file($rutaConfig)) {
    http_response_code(500);
    $rutaEjemplo = str_replace('\\', '/', dirname(__DIR__) . '/config/config.example.php');
    ?>
    <!DOCTYPE html>
    <html lang="es-AR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php /* El nombre va escrito a mano y no sale de APP_NOMBRE a
                 propósito: esta pantalla aparece JUSTO cuando
                 config/config.php no existe, o sea cuando la
                 constante todavía no se pudo definir. Si se usara
                 acá, la propia pantalla que avisa del problema
                 rompería al tratar de mostrarlo. */ ?>
        <title>Falta la configuración &middot; Atilio Bar</title>
        <style>
            body { font-family: system-ui, sans-serif; background: #FFFADC; color: #2F4F2F;
                   margin: 0; padding: 2rem 1rem; display: flex; justify-content: center; }
            .caja { max-width: 620px; background: #FFFFFF; border: 3px solid #8CC98A;
                    border-radius: 16px; padding: 1.75rem; }
            h1 { font-size: 1.4rem; margin: 0 0 1rem; }
            pre { background: #F2F7EF; border: 2px solid #CFE3C6; border-radius: 8px;
                  padding: 0.75rem; overflow-x: auto; font-size: 0.85rem; }
            code { background: #F2F7EF; padding: 0.1rem 0.35rem; border-radius: 4px; }
            .pasos { padding-left: 1.2rem; }
            .pasos li { margin-bottom: 0.5rem; }
        </style>
    </head>
    <body>
        <div class="caja">
            <h1>Falta el archivo de configuración</h1>

            <p>
                No se encontró <code>config/config.php</code>. No te preocupes:
                es el archivo con los datos de conexión a la base de datos, y
                está fuera del repositorio a propósito para que la contraseña
                de MySQL no quede guardada en el historial de git.
            </p>

            <p>Se crea a partir de la plantilla en dos pasos:</p>

            <ol class="pasos">
                <li>
                    Copiá <code>config/config.example.php</code> como
                    <code>config/config.php</code>.<br>
                    En Windows: <code>copy config\config.example.php config\config.php</code><br>
                    En Linux/Mac: <code>cp config/config.example.php config/config.php</code>
                </li>
                <li>
                    Abrí <code>config/config.php</code> y completá los datos de MySQL
                    (<code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> y
                    <code>DB_PASS</code>).
                </li>
            </ol>

            <p class="small">
                La plantilla está en: <code><?= htmlspecialchars($rutaEjemplo, ENT_QUOTES) ?></code>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once $rutaConfig;

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
require_once __DIR__ . '/schema.php';

// Si falta alguna migracion, se avisa con todas las letras en vez
// de dejar que la primera pantalla que la consulta se caiga con un
// error 500 en blanco. install.php queda fuera: es justamente la
// pantalla que crea la base.
if (PHP_SAPI !== 'cli'
    && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'install.php'
    && Schema::puedeHaberTablas()) {
    $faltan = Schema::faltan();

    if ($faltan !== []) {
        Schema::mostrarAviso($faltan);
    }
}
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auditoria.php';
// Servicio de stock (Etapa 2): el único punto por donde pasa un
// cambio de existencias.
require_once __DIR__ . '/stock.php';
// Mesas, comandas y reservas (Etapa 3).
require_once __DIR__ . '/mesas.php';
// Ventas, cobros y caja (Etapa 4).
require_once __DIR__ . '/ventas.php';
// El descuento pendiente mientras se cobra (Etapa 4).
require_once __DIR__ . '/descuentos.php';
// Reportes (Etapa 5): las cuatro consultas del bar.
require_once __DIR__ . '/reportes.php';
// Horarios y días cerrados (Etapa 6).
require_once __DIR__ . '/horarios.php';
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

    // Un error de base de datos NO es siempre una conexión fallida.
    //
    // Antes, cualquier PDOException mostraba "no se pudo conectar",
    // y con eso una clave foránea violada mandaba al dueño a revisar
    // config.php, que era exactamente el archivo que estaba bien. Lo
    // que se muestra dice la verdad y reenvía al log.
    if ($e instanceof PDOException) {
        $titulo       = 'Error de la base de datos';
        $mensajeError = 'La base de datos no aceptó la operación. Si podés, volvé a probar; '
            . 'si sigue, el detalle quedó en logs/app.log en el servidor.';
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
