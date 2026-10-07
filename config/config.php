<?php
/**
 * config/config.php
 * -----------------------------------------------------------------
 * ARCHIVO ÚNICO DE CONFIGURACIÓN DEL BAR "ATILIO"
 *
 * Este es el ÚNICO archivo que tenés que editar al subir la aplicación
 * al hosting. Todos los datos de conexión, los parámetros de seguridad
 * y la ruta de la aplicación viven acá.
 *
 * IMPORTANTE PARA ByetHost:
 *   - El host de MySQL NO es "localhost" (es tipo sqlXXX.byethost.com).
 *   - El usuario y el nombre de la base llevan prefijo
 *     (ejemplo: b12_12345678_atilio).
 * -----------------------------------------------------------------
 */

// ================================================================
// 1) BASE DE DATOS (MySQL / MariaDB)
// ================================================================

// Host del servidor MySQL.
// ByetHost -> algo como: sql258.byethost.com
// Local/XAMPP -> localhost
define('DB_HOST', 'localhost');

// Puerto de MySQL (casi siempre 3306)
define('DB_PORT', 3306);

// Nombre de la base de datos.
// En ByetHost VA CON PREFIJO: b12_12345678_atilio
define('DB_NAME', 'atilio');

// Usuario de MySQL.
// En ByetHost VA CON PREFIJO: b12_12345678_atilio
define('DB_USER', 'root');

// Contraseña de MySQL (la del panel de ByetHost)
define('DB_PASS', '');

// Juego de caracteres de la conexión
define('DB_CHARSET', 'utf8mb4');


// ================================================================
// 2) DATOS GENERALES DE LA APLICACIÓN
// ================================================================

// Nombre del negocio (se usa en la navbar, el login y los tickets)
define('APP_NOMBRE', 'Atilio');

// Entorno de ejecución.
//   'produccion'  -> no muestra errores, los guarda en logs/app.log  (USAR ESTE)
//   'desarrollo'  -> muestra los errores en pantalla (solo en tu PC)
define('APP_ENV', 'produccion');

// Modo depuración. Tiene que ser false en el hosting.
define('APP_DEBUG', false);

// Zona horaria deArgentina
define('APP_TIMEZONE', 'America/Argentina/Buenos_Aires');

// Símbolo de moneda con el que se formatean los importes
define('APP_MONEDA_SIMBOLO', '$');

// Configuración regional (formato de fechas y números)
define('APP_LOCALE', 'es_AR');


// ================================================================
// 3) SEGURIDAD
// ================================================================

// Máximo de intentos de login fallidos por usuario antes de bloquearlo
define('LOGIN_MAX_INTENTOS', 5);

// Minutos de bloqueo del usuario cuando supera los intentos
define('LOGIN_MINUTOS_BLOQUEO', 15);

// Máximo de intentos fallidos desde una misma IP en la ventana de bloqueo.
// Protege contra ataques que prueban muchos usuarios desde el mismo equipo.
define('LOGIN_MAX_INTENTOS_IP', 20);

// Minutos de inactividad antes de cerrar la sesión automáticamente
define('SESION_MINUTOS_INACTIVIDAD', 120);


// ================================================================
// 4) RUTAS
// -----------------------------------------------------------------
// NO hace falta tocar nada acá: la ruta base se detecta sola
// comparando la URL del script con la carpeta real de la aplicación.
// Sirve tanto en la raíz de htdocs como dentro de una subcarpeta.
// ================================================================

// Carpeta física donde vive la aplicación en el servidor.
// __DIR__ de este archivo es /config, así que se sube un nivel.
define('APP_ROOT', dirname(__DIR__));

// Ruta base de la aplicación dentro del dominio, calculada automáticamente.
//   raíz de htdocs  -> APP_BASE_URL = ''
//   subcarpeta      -> APP_BASE_URL = '/atilio'
// El helper url() la usa para armar todos los enlaces.
define('APP_BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

// Carpeta donde se guardan los logs de errores
define('APP_LOG_DIR', APP_ROOT . '/logs');


// ================================================================
// 5) PARÁMETROS QUE PUEDEN AJUSTARSE DESDE LA PANTALLA DE CONFIGURACIÓN
//    (se guardan en la tabla "configuracion" y tienen prioridad sobre
//     los valores de acá cuando están definidos)
// ================================================================

// Ancho del ticket en milímetros (impresora térmica común: 80 mm)
define('TICKET_ANCHO_MM', 80);

// Redondeo de importes al cobrar
define('CAJA_REDONDEO', 2);