<?php
/**
 * modules/mesas/generar_mesas.php
 * -----------------------------------------------------------------
 * Genera varias mesas de un tirón. Solo administradores.
 *
 * El bar tiene diez mesas hoy, pero mañana puede tener veinte o
 * treinta. Cargar una por una es un engorro, así que acá se pide
 * cuántas y de qué zona, y se crean todas juntas.
 *
 * Por qué NO se genera con un número y nada más: porque las mesas
 * de un bar tienen números que el mozo reconoce de memoria ("la 7
 * es la del fondo"). Si el sistema inventara los números, el mozo
 * terminaría diciendo "mesa tres" yendo a otra. Por eso se escribe
 * el rango: "de la 6 a la 12".
 * -----------------------------------------------------------------
*/

$desde    = strtoupper(trim(post('desde')));
$hasta    = strtoupper(trim(post('hasta')));
$prefijo  = mb_strtoupper(trim(post('prefijo')));
$zona     = post('zona', 'salon');
$capacidad = (int) post('capacidad', '4');
$nombre   = post('nombre');

$errores = [];

// --- Validación ---------------------------------------------------
if ($desde === '' || $hasta === '') {
    $errores['rango'] = 'Escribí desde qué mesa hasta cuál, por ejemplo de la 6 a la 12.';
}

if (!array_key_exists($zona, Mesas::ZONAS)) {
    $errores['zona'] = 'Elegí una zona válida.';
}

if ($capacidad < 1 || $capacidad > 50) {
    $errores['capacidad'] = 'La capacidad tiene que estar entre 1 y 50.';
}

// El prefijo solo puede ser texto corto: es parte del número de la
// mesa y va directo a la grilla.
if ($prefijo !== '' && !preg_match('/^[A-Z0-9]{1,5}$/', $prefijo)) {
    $errores['prefijo'] = 'El prefijo puede tener hasta 5 letras o números, sin espacios.';
}

// "6" y "06" son la misma mesa. Se comparan como enteros.
$numeroDesde = (int) $desde;
$numeroHasta = (int) $hasta;

if ($desde !== '' && $hasta !== '' && (!ctype_digit($desde) || !ctype_digit($hasta))) {
    $errores['rango'] = 'Los números de mesa tienen que ser dígitos: 6, 12, 30.';
} elseif ($numeroDesde < 1 || $numeroHasta < 1) {
    $errores['rango'] = 'Los números de mesa arrancan en 1.';
} elseif ($numeroHasta < $numeroDesde) {
    $errores['rango'] = 'La mesa final tiene que ser más grande que la inicial.';
} elseif (($numeroHasta - $numeroDesde + 1) > 100) {
    $errores['rango'] = 'Son demasiadas mesas de una vez. Hacélo de a 50.';
}

// --- Generar ------------------------------------------------------
if (empty($errores)) {
    $creadas = [];
    $omitidas = [];

    try {
        DB::enTransaccion(function () use (
            $numeroDesde, $numeroHasta, $prefijo, $zona,
            $capacidad, $nombre, &$creadas, &$omitidas
        ) {
            // El orden arranca después de la última mesa de esa
            // zona, así las nuevas quedan al final en la grilla.
            $siguienteOrden = (int) DB::valor(
                'SELECT COALESCE(MAX(orden), 0) FROM mesas WHERE zona = :zona',
                ['zona' => $zona]
            );

            for ($n = $numeroDesde; $n <= $numeroHasta; $n++) {
                $numero = $prefijo . $n;

                // Si el número ya existe no se pisa: podría tener
                // pedidos viejos.
                $existe = DB::valor(
                    'SELECT id FROM mesas WHERE numero = :numero',
                    ['numero' => $numero]
                );

                if ($existe) {
                    $omitidas[] = $numero;
                    continue;
                }

                DB::insertar('mesas', [
                    'numero'    => $numero,
                    'nombre'    => mb_substr($nombre, 0, 60),
                    'capacidad' => $capacidad,
                    'zona'      => $zona,
                    'orden'     => $siguienteOrden + 1,
                    'activo'    => 1,
                    'creado_en' => ahora(),
                ]);

                $siguienteOrden++;
                $creadas[] = $numero;
            }
        });
    } catch (Throwable $e) {
        error_log('Error generando mesas: ' . $e->getMessage());

        flash_error('No se pudieron generar las mesas. Probá de nuevo.');
        redirigir('mesas/administrar');
    }

    if (!empty($creadas)) {
        Auditoria::registrar('generar_mesas', 'mesas', 0, null, [
            'creadas' => count($creadas),
            'primera' => $creadas[0],
            'ultima'  => $creadas[count($creadas) - 1],
            'zona'    => $zona,
        ]);

        flash_exito(
            count($creadas) . ($creadas === 1 ? ' mesa creada' : ' mesas creadas')
            . ' (' . $creadas[0] . ' a ' . $creadas[count($creadas) - 1] . ').'
        );
    }

    if (!empty($omitidas)) {
        flash_info(
            'Se omitieron ' . count($omitidas) . ' porque esos números ya existen: '
            . implode(', ', $omitidas)
        );
    }

    if (empty($creadas) && empty($omitidas)) {
        flash_info('No se creó ninguna mesa.');
    }

    redirigir('mesas/administrar');
}

guardar_entradas([
    'desde'     => $desde,
    'hasta'     => $hasta,
    'prefijo'   => $prefijo,
    'zona'      => $zona,
    'capacidad' => (string) $capacidad,
    'nombre'    => $nombre,
]);

guardar_errores($errores);
flash_error('Revisá los campos marcados en rojo.');

redirigir('mesas/administrar');
