<?php
/**
 * modules/caja/movimiento.php
 * -----------------------------------------------------------------
 * Atajo: registrar un movimiento de caja.
 *
 * La pantalla completa está en caja/abrir. Este endpoint existe
 * para que un POST sin datos no rompa nada y mande a la pantalla
 * correcta en vez de dar un error.
 * -----------------------------------------------------------------
*/

$caja = Caja::abierta();

if ($caja === null) {
    flash_error('No hay ninguna caja abierta.');
    redirigir('caja');
}

$errores = [];

$tipo    = post('tipo');
$importe = a_numero(post('importe', '0'));
$motivo  = post('motivo');

if (!array_key_exists($tipo, Caja::MOVIMIENTOS)) {
    $errores[] = 'Elegí si entró o salió plata.';
}

if ($importe <= 0) {
    $errores[] = 'El importe tiene que ser mayor a cero.';
}

if (mb_strlen(trim($motivo)) < 3) {
    $errores[] = 'Contá por qué, aunque sea una palabra.';
}

if (!empty($errores)) {
    guardar_entradas([
        'tipo'    => $tipo,
        'importe' => cantidad($importe, 2),
        'motivo'  => $motivo,
    ]);

    flash_error(implode(' ', $errores));
    redirigir('caja/abrir');
}

$resultado = Caja::movimiento((int) $caja['id'], $tipo, $importe, $motivo);

if (!$resultado['ok']) {
    guardar_entradas([
        'tipo'    => $tipo,
        'importe' => cantidad($importe, 2),
        'motivo'  => $motivo,
    ]);

    flash_error($resultado['mensaje']);
    redirigir('caja/abrir');
}

flash_exito(
    (Caja::MOVIMIENTOS[$tipo] ?? 'Movimiento') . ': '
    . money($tipo === 'ingreso' ? $importe : -$importe)
);

redirigir('caja');
