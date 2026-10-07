<?php
/**
 * includes/helpers.php
 * -----------------------------------------------------------------
 * Funciones de ayuda reutilizables en toda la aplicación.
 * -----------------------------------------------------------------
 */

// ================================================================
// SALIDA SEGURA
// ================================================================

/**
 * Escapa texto para imprimirlo en HTML.
 *
 * Se usa en TODA salida hacia el navegador: <?= e($nombre) ?>
 * Sin esto, un nombre de producto con <script> ejecutaría JavaScript.
 */
function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escapa un valor para usarlo dentro de un atributo HTML entre comillas
 * y en el caso de que se coloque dentro de un JSON embebido.
 */
function e_json(mixed $valor): string
{
    return htmlspecialchars(
        json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// ================================================================
// RUTAS Y REDIRECCIONES
// ================================================================

/**
 * Arma una URL interna de la aplicación.
 *(url('usuarios') genera /atilio/index.php?r=usuarios)
 *
 * El prefijo se calcula solo a partir de APP_BASE_URL, así que la app
 * funciona igual en la raíz de htdocs o en una subcarpeta.
 */
function url(string $ruta = ''): string
{
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    $ruta = ltrim($ruta, '/');

    return $base . '/index.php?r=' . rawurlencode($ruta);
}

/** URL directa a un archivo físico (login.php, install.php...). */
function url_archivo(string $archivo): string
{
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';

    return $base . '/' . ltrim($archivo, '/');
}

/**
 * Redirige y corta la ejecución.
 * Va con exit para que nunca se siga ejecutando código después.
 */
function redirigir(string $ruta): never
{
    if (!headers_sent()) {
        header('Location: ' . (str_contains($ruta, '?') || str_starts_with($ruta, 'http') ? $ruta : url($ruta)));
        exit;
    }

    exit;
}

/**
 * Vuelve a la página anterior del navegador, con fallback al dashboard.
 */
function volver(): never
{
    $anterior = $_SERVER['HTTP_REFERER'] ?? '';
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';

    if ($anterior !== '' && str_contains($anterior, $base . '/index.php')) {
        if (!headers_sent()) {
            header('Location: ' . $anterior);
            exit;
        }
    }

    redirigir('dashboard');
}

// ================================================================
// FORMATO DE DINERO Y FECHAS
// ================================================================

/**
 * Formatea un importe como pesos argentinos: $ 1.234,50
 *
 * @param float|string|null $monto
 */
function money(float|string|null $monto, bool $conSimbolo = true): string
{
    $valor = round((float) $monto, 2);
    $texto = number_format($valor, 2, ',', '.');

    return $conSimbolo ? APP_MONEDA_SIMBOLO . ' ' . $texto : $texto;
}

/**
 * Formatea una cantidad de stock sin símbolo de moneda.
 */
function cantidad(float|string|null $numero, int $decimales = 2): string
{
    $numero = (float) $numero;

    // Si es entero se muestra sin decimales: "5" y no "5,00"
    if ($decimales === 0 || abs($numero - round($numero)) < 0.000001) {
        return number_format($numero, 0, ',', '.');
    }

    return number_format($numero, $decimales, ',', '.');
}

/**
 * Convierte a timestamp lo que venga de la base o de la sesión.
 *
 * Acepta:
 *   - string 'Y-m-d H:i:s'   (lo que devuelve MySQL)
 *   - null o ''              (sin dato)
 *   - '0000-00-00 00:00:00'  (MySQL con fecha vacía)
 *   - int                    (time(), como inicio_sesion)
 *
 * Devuelve null si no hay una fecha usable, así el llamador
 * decide qué mostrar.
 */
function a_timestamp(string|int|null $fecha): ?int
{
    if ($fecha === null || $fecha === '') {
        return null;
    }

    // Un entero ya es un timestamp de Unix: no hay que pasar por strtotime.
    if (is_int($fecha) || ctype_digit((string) $fecha)) {
        return (int) $fecha;
    }

    // Fecha vacía de MySQL.
    if (str_starts_with((string) $fecha, '0000-00-00')) {
        return null;
    }

    $timestamp = strtotime((string) $fecha);

    return $timestamp === false ? null : $timestamp;
}

/**
 * Formatea una fecha/hora a formato argentino: 07/10/2026 22:30
 */
function fecha_hora(string|int|null $fecha, bool $conHora = true): string
{
    $timestamp = a_timestamp($fecha);

    return $timestamp === null ? '-' : date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

/** Fecha corta para tablas: 07/10/26 */
function fecha_corta(string|int|null $fecha): string
{
    $timestamp = a_timestamp($fecha);

    return $timestamp === null ? '-' : date('d/m/y', $timestamp);
}

/** Hora suelta: 23:45 */
function hora(string|int|null $fecha): string
{
    $timestamp = a_timestamp($fecha);

    return $timestamp === null ? '-' : date('H:i', $timestamp);
}

/** Fecha y hora actual de Buenos Aires, lista para guardar en MySQL. */
function ahora(): string
{
    return date('Y-m-d H:i:s');
}

// ================================================================
// MENSAJES FLASH
// -----------------------------------------------------------------
// Guardan un mensaje que se muestra una sola vez en la pantalla
// siguiente. Así el usuario ve "Mesas guardada" después de un POST.
// ================================================================

function flash_set(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_exito(string $mensaje): void
{
    flash_set('exito', $mensaje);
}

function flash_error(string $mensaje): void
{
    flash_set('error', $mensaje);
}

function flash_info(string $mensaje): void
{
    flash_set('info', $mensaje);
}

/**
 * Devuelve y limpia los mensajes pendientes.
 */
function flash_todos(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($mensajes) ? $mensajes : [];
}

// ================================================================
// DATOS DEL REQUEST
// ================================================================

/**
 * Toma un valor del POST y lo limpia de espacios.
 */
function post(string $clave, string $porDefecto = ''): string
{
    $valor = $_POST[$clave] ?? $porDefecto;

    return is_string($valor) ? trim($valor) : $porDefecto;
}

/**
 * Toma un valor crudo del POST (sin trim) para contraseñas.
 */
function post_crudo(string $clave, string $porDefecto = ''): string
{
    $valor = $_POST[$clave] ?? $porDefecto;

    return is_string($valor) ? $valor : $porDefecto;
}

/**
 * Toma un valor del GET.
 */
function get(string $clave, string $porDefecto = ''): string
{
    $valor = $_GET[$clave] ?? $porDefecto;

    return is_string($valor) ? trim($valor) : $porDefecto;
}

/** Toma un entero del GET validado. */
function get_id(string $clave = 'id'): int
{
    return max(0, (int) get($clave, '0'));
}

/** Indica si el request actual es un POST. */
function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Guarda los datos del formulario para repoblarlo si hubo un error
 * de validación (así el usuario no escribe todo de nuevo).
 */
function guardar_entradas(array $datos): void
{
    unset($datos['_csrf'], $datos['password'], $datos['password_confirmacion'], $datos['password_actual']);
    $_SESSION['old_input'] = $datos;
}

/** Recupera las entradas guardadas (una sola vez). */
function recuperar_entradas(): array
{
    $datos = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);

    return is_array($datos) ? $datos : [];
}

// ================================================================
// ERRORES DE VALIDACIÓN ENTRE PANTALLAS
// -----------------------------------------------------------------
// Cuando un POST falla, se redirige al formulario en vez de volver
// a renderizarlo: así el usuario no puede recargar la página y
// reenviar el POST dos veces.
//
// Como la redirección borra las variables locales del script que
// validó, los errores por campo se dejan en la sesión y la vista
// los recupera con tomar_errores().
//
// Son dos cosas distintas y por eso hay dos funciones:
//   guardar_entradas() -> lo que el usuario escribió (para repoblar)
//   guardar_errores()   -> qué está mal (para marcar en rojo)
// ================================================================

function guardar_errores(array $errores, array $erroresGenerales = []): void
{
    $_SESSION['errores_formulario'] = [
        'campos'    => $errores,
        'generales' => $erroresGenerales,
    ];
}

/** Recupera los errores de validación (una sola vez). */
function tomar_errores(): array
{
    $guardado = $_SESSION['errores_formulario'] ?? null;
    unset($_SESSION['errores_formulario']);

    return [
        'campos'    => is_array($guardado['campos'] ?? null) ? $guardado['campos'] : [],
        'generales' => is_array($guardado['generales'] ?? null) ? $guardado['generales'] : [],
    ];
}

/** Valor a mostrar en un campo, priorizando lo que el usuario había tipeado. */
function valor_campo(string $campo, array $datos, string $porDefecto = ''): string
{
    return e((string) ($datos[$campo] ?? $porDefecto));
}

// ================================================================
// VALIDACIÓN
// ================================================================

/**
 * Valida una cadena con un conjunto de reglas.
 *
 * Devuelve un array de errores (vacío si todo está bien).
 * Reglas: requerido, min, max, email, usuario, entero, decimal
 */
function validar(array $datos, array $reglas): array
{
    $errores = [];

    foreach ($reglas as $campo => $regla) {
        $valor = $datos[$campo] ?? '';
        $valor = is_string($valor) ? trim($valor) : $valor;
        $etiqueta = $regla['etiqueta'] ?? ucfirst(str_replace('_', ' ', $campo));

        // Campo obligatorio
        if (!empty($regla['requerido']) && ($valor === '' || $valor === null)) {
            $errores[$campo] = "El campo {$etiqueta} es obligatorio.";
            continue;
        }

        // Si está vacío y no es requerido, listo
        if ($valor === '' || $valor === null) {
            continue;
        }

        $mensajes = $regla['mensaje'] ?? [];
        $prefijo  = $mensajes['prefijo'] ?? $etiqueta;

        if (isset($regla['email']) && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $errores[$campo] = $mensajes['email'] ?? "{$prefijo}: el formato del email no es válido.";
        }

        if (isset($regla['usuario']) && !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', (string) $valor)) {
            $errores[$campo] = $mensajes['usuario']
                ?? 'El usuario puede tener letras, números, punto, guion y guion bajo (mínimo 3 caracteres).';
        }

        if (isset($regla['min']) && mb_strlen((string) $valor) < $regla['min']) {
            $errores[$campo] = $mensajes['min'] ?? "{$prefijo}: debe tener al menos {$regla['min']} caracteres.";
        }

        if (isset($regla['max']) && mb_strlen((string) $valor) > $regla['max']) {
            $errores[$campo] = $mensajes['max'] ?? "{$prefijo}: no puede superar los {$regla['max']} caracteres.";
        }

        if (isset($regla['entero']) && filter_var($valor, FILTER_VALIDATE_INT) === false) {
            $errores[$campo] = $mensajes['entero'] ?? "{$prefijo}: tiene que ser un número entero.";
        }

        if (isset($regla['decimal']) && !is_numeric(str_replace(',', '.', (string) $valor))) {
            $errores[$campo] = $mensajes['decimal'] ?? "{$prefijo}: tiene que ser un número.";
        }
    }

    return $errores;
}

/**
 * Convierte un número escrito en formato argentino ("1.234,50") a float.
 * También acepta el formato con punto decimal ("1234.50").
 */
function a_numero(string $valor): float
{
    $valor = trim($valor);

    if ($valor === '') {
        return 0.0;
    }

    // Si tiene coma, se asume formato argentino: se sacan los puntos y la coma pasa a punto.
    if (str_contains($valor, ',')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float) $valor;
}

// ================================================================
// MISCELÁNEA
// ================================================================

/**
 * IP real del visitante, limpia de "unknown".
 */
function ip_cliente(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return substr($ip, 0, 45);
}

/** User agent recortado (la columna es VARCHAR(255)). */
function user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'desconocido', 0, 255);
}

/**
 * Construye una clase de Bootstrap a partir de un estado.
 * Se usa en la grilla de mesas (Etapa 3) y en los badges de estado.
 */
function clase_estado(string $estado): string
{
    return match ($estado) {
        'libre'      => 'estado-libre',
        'ocupada'    => 'estado-ocupada',
        'reservada'  => 'estado-reservada',
        'por_cobrar' => 'estado-por-cobrar',
        'abierta'    => 'estado-ocupada',
        'cerrada'    => 'estado-libre',
        'anulada'    => 'estado-anulada',
        default      => 'estado-neutra',
    };
}

/**
 * Texto legible de un estado de mesa.
 * El requisito es que los estados se entiendan SIN depender del color,
 * por eso siempre se acompaña de texto (y más adelante de un ícono).
 */
function texto_estado(string $estado): string
{
    return match ($estado) {
        'libre'      => 'Libre',
        'ocupada'    => 'Ocupada',
        'reservada'  => 'Reservada',
        'por_cobrar' => 'Por cobrar',
        'abierta'    => 'Abierta',
        'cerrada'    => 'Cerrada',
        'anulada'    => 'Anulada',
        default      => ucfirst($estado),
    };
}

/**
 * Ícono Bootstrap (solo SVG inline, sin descargas) para cada estado.
 */
function icono_estado(string $estado): string
{
    return match ($estado) {
        'libre'      => '<i class="bi bi-check-circle-fill"></i>',
        'ocupada'    => '<i class="bi bi-people-fill"></i>',
        'reservada'  => '<i class="bi bi-bookmark-fill"></i>',
        'por_cobrar' => '<i class="bi bi-cash-coin"></i>',
        'abierta'    => '<i class="bi bi-unlock-fill"></i>',
        'cerrada'    => '<i class="bi bi-lock-fill"></i>',
        'anulada'    => '<i class="bi bi-x-octagon-fill"></i>',
        default      => '<i class="bi bi-question-circle-fill"></i>',
    };
}

/**
 * Devuelve el nombre de la clase de rol, para pintar el badge.
 */
function clase_rol(string $rol): string
{
    return $rol === 'administrador' ? 'rol-admin' : 'rol-vendedor';
}

/** Etiqueta legible del rol. */
function texto_rol(string $rol): string
{
    return $rol === 'administrador' ? 'Administrador' : 'Vendedor';
}

/**
 * Rompe la línea de texto cortando por palabras.
 * Útil para mostrar descripciones largas en tarjetas sin romper el diseño.
 */
function recortar(string $texto, int $largo = 120): string
{
    $texto = trim($texto);

    if (mb_strlen($texto) <= $largo) {
        return $texto;
    }

    return mb_substr($texto, 0, $largo) . '...';
}