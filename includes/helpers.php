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
 *
 *   url('usuarios')                -> /atilio/index.php?r=usuarios
 *   url('compras/ver&id=3')       -> /atilio/index.php?r=compras%2Fver&id=3
 *
 * Ojo con el segundo caso: lo que va después del & son parámetros
 * de la URL, NO parte del nombre de la ruta. Si se codificara
 * todo junto, el servidor buscaría una ruta llamada
 * "compras/ver&id=3" y daría 404. Por eso se separa antes de
 * codificar.
 *
 * @param string $ruta  Ruta del módulo, con parámetros opcionales
 *                      separados por & o por ?
 */
function url(string $ruta = ''): string
{
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';

    $ruta = ltrim($ruta, '/');

    // Separar los parámetros de la ruta.
    $separador = str_contains($ruta, '?') ? '?' : '&';
    $partes    = explode($separador, $ruta, 2);

    $nombreRuta = $partes[0];
    $parametros = isset($partes[1]) ? $separador . $partes[1] : '';

    return $base . '/index.php?r=' . rawurlencode($nombreRuta) . $parametros;
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
 * Toma un valor del POST que es un grupo de campos.
 *
 * El post() normal devuelve string, así que no sirve para cosas
 * como pagos[0][importe]. Esto devuelve el grupo ya limpio: si
 * no vino nada o vino algo que no es un grupo, devuelve un grupo
 * vacío.
 */
function post_array(string $clave): array
{
    $valor = $_POST[$clave] ?? [];

    return is_array($valor) ? $valor : [];
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
 * Convierte a float un número escrito en formato argentino.
 *
 * El problema real acá es que "1.800" es ambiguo: puede ser mil
 * ochocientos (formato argentino, punto de miles) o uno punto ocho
 * (formato inglés, punto decimal). Si se_choice mal, un precio de
 * $1.800 se guarda como $1,80 y no te das cuenta hasta ver la
 * cuenta.
 *
 * La regla que se aplica, para importes:
 *
 *   "1.800"     ->  1800    (punto + exactamente 3 dígitos: miles)
 *   "1.80"      ->  1.80    (punto + 1 o 2 dígitos: decimal)
 *   "1.8"       ->  1.8
 *   "1.234,56"  ->  1234.56 (tiene coma: la coma es el decimal)
 *   "1,50"      ->  1.50
 *   "12.500"    ->  12500
 *   "0.50"      ->  0.50
 *
 * En resumen: si hay coma, el punto es de miles. Si NO hay coma y
 * hay punto, se mira cuántos dígitos hay después: si son 3, es de
 * miles; si son 1 o 2, es decimal.
 */
function a_numero(string $valor): float
{
    $valor = trim(str_replace([' ', "\xc2\xa0"], '', $valor));

    if ($valor === '') {
        return 0.0;
    }

    // Fuera el signo y cualquier cosa que no sea dígito, coma o punto.
    $valor = preg_replace('/[^0-9.,\-]/', '', $valor) ?? '';

    if ($valor === '' || $valor === '-') {
        return 0.0;
    }

    $tieneComa = str_contains($valor, ',');
    $tienePunto = str_contains($valor, '.');

    if ($tieneComa && $tienePunto) {
        // Formato argentino completo: 1.234,56
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif ($tieneComa) {
        // Solo coma: el decimal es la coma. 1,50 -> 1.50
        $valor = str_replace(',', '.', $valor);
    } elseif ($tienePunto) {
        // Solo punto: hay que adivinar. Se mira la parte de atrás.
        $partes = explode('.', $valor);
        $decimales = strlen(end($partes));

        // Más de un punto: el primero era de miles (1.234.567).
        if (count($partes) > 2) {
            $decimales = strlen(end($partes));
            $valor = str_replace('.', '', $valor);
        } elseif ($decimales === 3) {
            // "1.800" -> miles. Pero ojo: si es solo el punto y
            // los dígitos de adelante son 3, es un miles.
            $valor = str_replace('.', '', $valor);
        }
        // Si son 1 o 2 dígitos, es decimal y se deja como está.
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
 * Convierte un estado interno en algo que se pueda usar como
 * clase de CSS.
 *
 * El estado en la base es "por_cobrar" (con guion bajo, porque así
 * lo usan los ENUM y el PHP), pero en la hoja de estilos las
 * clases llevan guiones: .mesa-por-cobrar. Sin esta traducción, la
 * mesa "por cobrar" saldría sin ningún color y nadie entendería por
 * qué.
 */
function slug_estado(string $estado): string
{
    $slug = str_replace('_', '-', strtolower(trim($estado)));

    // Solo se deja pasar lo que podría ser un nombre de clase:
    // letras, números y guiones. Así el valor no puede inyectar
    // atributos ni romper el HTML.
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';

    return $slug !== '' ? $slug : 'neutra';
}

/**
 * Lee un valor de la tabla de configuracion.
 *
 * Antes cada pantalla hacia su propio SELECT. Con la Etapa 4 ya son
 * varias lecturas del mismo tipo, asi que se centraliza aca.
 *
 * @param mixed $defecto Lo que se devuelve si la clave no existe.
 */
function config_valor(string $clave, $defecto = null)
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];

        foreach (DB::all('SELECT clave, valor FROM configuracion') as $fila) {
            $cache[(string) $fila['clave']] = (string) $fila['valor'];
        }
    }

    return $cache[$clave] ?? $defecto;
}

/**
 * Lee un valor de configuracion como booleano.
 *
 * La base guarda '1' y '0' como texto.
 */
function config_bool(string $clave, bool $defecto = false): bool
{
    $valor = config_valor($clave);

    return $valor === null ? $defecto : ((string) $valor === '1');
}

/**
 * Interpreta la columna `formas_venta` de un producto.
 *
 * La forma en que se escribe en la base es una lista separada por
 * comas, con cantidad y etiqueta:
 *
 *     1:Unidad,6:Media docena,12:Docena
 *
 * Se usa para que el mozo no tenga que escribir la cantidad: en la
 * pantalla del pedido aparecen tres botones, "Unidad", "Media
 * docena" y "Docena".
 *
 * Si el producto no tiene formas definidas, se devuelve la única
 * forma posible, la cantidad 1. Un producto vacío o con basura
 * guardada devuelve siempre eso, y nunca un error: que una venta
 * no se pueda cargar por un dato mal tipeado sería peor que vender
 * de a una.
 *
 * @return array<int, array{cantidad: float, etiqueta: string}>
 */
function formas_venta(?string $texto): array
{
    $formas = [];

    foreach (explode(',', (string) $texto) as $parte) {
        $parte = trim($parte);

        if ($parte === '') {
            continue;
        }

        // "6:Media docena" -> cantidad 6, etiqueta "Media docena".
        // Si no hay dos puntos, es solo la cantidad.
        $pos = mb_strpos($parte, ':');
        $cantidad = $pos === false ? $parte : mb_substr($parte, 0, $pos);
        $etiqueta = $pos === false ? '' : mb_substr($parte, $pos + 1);

        // Se descarta cualquier cosa que no sea un número: el dato
        // viene de un formulario y no se puede confiar en él.
        $numero = a_numero(str_replace(',', '.', trim($cantidad)));

        if ($numero <= 0) {
            continue;
        }

        // La etiqueta se recorta y se limita: es texto libre y va
        // directo a la pantalla.
        $etiqueta = mb_substr(trim($etiqueta), 0, 20);

        $formas[] = [
            'cantidad' => $numero,
            'etiqueta' => $etiqueta !== ''
                ? $etiqueta
                : cantidad($numero),
        ];
    }

    if (empty($formas)) {
        return [['cantidad' => 1.0, 'etiqueta' => '1']];
    }

    // De menor a mayor: el mozo va de una unidad a la docena, no al
    // revés.
    usort($formas, static fn ($a, $b) => $a['cantidad'] <=> $b['cantidad']);

    return $formas;
}

/**
 * Texto de ayuda para el campo de formas de venta del formulario de
 * productos, con un ejemplo que se entienda sin contexto.
 */
function ayuda_formas_venta(): string
{
    return 'Ej: 1:Unidad,6:Media docena,12:Docena — o vacío si se vende de a una.';
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

// ================================================================
// STOCK
// -----------------------------------------------------------------
// El estado de un producto se muestra SIEMPRE con texto e ícono,
// no solo con color: en un celular al sol, un verde y un rojo
// parecidos no se distinguen.
// ================================================================

/**
 * Clase CSS del badge según cómo esté el stock.
 *
 * @param float  $actual
 * @param float  $minimo
 * @param string $unidad
 */
function clase_stock(float $actual, float $minimo, string $unidad = 'unidad'): string
{
    if ($actual < 0) {
        return 'stock-negativo';
    }

    if ($actual === 0.0) {
        return 'stock-cero';
    }

    if ($minimo > 0 && $actual <= $minimo) {
        return 'stock-bajo';
    }

    return 'stock-ok';
}

/**
 * Texto del estado de stock.
 */
function texto_stock(float $actual, float $minimo, string $unidad = 'unidad'): string
{
    if ($actual < 0) {
        return 'Negativo, hay que contar';
    }

    if ($actual === 0.0) {
        return 'Sin stock';
    }

    if ($minimo > 0 && $actual <= $minimo) {
        $plural = plural($unidad, $actual);
        return 'Bajo (' . cantidad($actual) . ' ' . $plural . ')';
    }

    return cantidad($actual) . ' ' . plural($unidad, $actual);
}

/**
 * Concuerda la unidad: 1 botella / 3 botellas.
 */
function plural(string $unidad, float $cantidad): string
{
    // Las unidades que ya son plurales (bidón, caja) no se tocan.
    $yaPlurales = ['bidón', 'bidones', 'caja', 'cajas', 'docena', 'docenas'];

    if (in_array(mb_strtolower($unidad), $yaPlurales, true)) {
        return $unidad;
    }

    if ((float) $cantidad === 1.0) {
        return $unidad;
    }

    // "unidad" -> "unidades", "botella" -> "botellas"
    if (substr($unidad, -1) === 'a') {
        return mb_substr($unidad, 0, -1) . 'as';
    }

    return $unidad . 's';
}

/**
 * Lista de unidades para el desplegable de productos.
 */
function unidades_disponibles(): array
{
    return [
        'unidad'   => 'Unidad',
        'medida'   => 'Medida',
        'botella'  => 'Botella',
        'lata'     => 'Lata',
        'litro'    => 'Litro',
        'kg'       => 'Kilo',
        'docena'   => 'Docena',
        'caja'     => 'Caja',
        'bidón'    => 'Bidón',
        'paquete'  => 'Paquete',
    ];
}

/**
 * Texto del tipo de movimiento de stock.
 */
function texto_tipo_movimiento(string $tipo): string
{
    return match ($tipo) {
        'alta_inicial'     => 'Alta',
        'compra'           => 'Compra',
        'ajuste'           => 'Ajuste',
        'venta'            => 'Venta',
        'anulacion_venta'  => 'Anulación',
        'anulacion_item'   => 'Anulación',
        'mesa_consumo'     => 'Consumo en mesa',
        'devolucion'       => 'Devolución',
        'baja'             => 'Baja',
        default            => ucfirst($tipo),
    };
}

/** Clase CSS según el tipo de movimiento (verde entra, rojo sale). */
function clase_tipo_movimiento(string $tipo): string
{
    return match ($tipo) {
        'compra', 'alta_inicial', 'devolucion' => 'mov-suma',
        'ajuste'                                => 'mov-ajuste',
        default                                => 'mov-resta',
    };
}

/**
 * El ícono del movimiento: + para lo que entra, - para lo que sale.
 */
function icono_tipo_movimiento(string $tipo): string
{
    return match ($tipo) {
        'compra', 'alta_inicial' => '<i class="bi bi-plus-circle-fill"></i>',
        'devolucion'            => '<i class="bi bi-arrow-counterclockwise"></i>',
        'ajuste'                => '<i class="bi bi-sliders"></i>',
        'baja'                  => '<i class="bi bi-dash-circle-fill"></i>',
        default                 => '<i class="bi bi-dash-circle-fill"></i>',
    };
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