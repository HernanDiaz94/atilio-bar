<?php
/**
 * modules/caja/abrir.php
 * -----------------------------------------------------------------
 * Abrir el turno de caja, o registrar una entrada o salida de
 * plata.
 *
 * Son dos cosas en la misma pantalla porque se usan juntas: al
 * abrir el turno muchas veces hay que dejar asentado un gasto que
 * se pagó de la caja antes de abrirla (el hielo, el gas), y no
 * vale la pena hacer dos pantallas.
 *
 * GET  muestra la pantalla.
 * POST abre la caja, o guarda el movimiento si vino uno.
 *
 * Solo el administrador abre la caja. Registrar un movimiento lo
 * puede hacer cualquiera del turno.
 * -----------------------------------------------------------------
*/

$accion = post('accion', 'abrir');

// ------------------------------------------------------------
// Registrar una entrada o salida
// ------------------------------------------------------------
if ($accion === 'movimiento') {
    $caja = Caja::abierta();

    if ($caja === null) {
        flash_error('No hay ninguna caja abierta.');
        redirigir('caja/abrir');
    }

    $tipo     = post('tipo');
    $importe  = a_numero(post('importe', '0'));
    $motivo   = post('motivo');

    $resultado = Caja::movimiento((int) $caja['id'], $tipo, $importe, $motivo);

    if (!$resultado['ok']) {
        guardar_entradas([
            'tipo'     => $tipo,
            'importe'  => cantidad($importe, 2),
            'motivo'   => $motivo,
        ]);

        flash_error($resultado['mensaje']);
        redirigir('caja/abrir');
    }

    Auditoria::registrar('movimiento_caja', 'cajas', (int) $caja['id'], null, [
        'tipo'    => $tipo,
        'importe' => $importe,
        'motivo'  => $motivo,
    ]);

    flash_exito(
        (Caja::MOVIMIENTOS[$tipo] ?? 'Movimiento') . ': '
        . money($tipo === 'ingreso' ? $importe : -$importe)
    );

    redirigir('caja');
}

// ------------------------------------------------------------
// Abrir la caja
// ------------------------------------------------------------
$entradas = recuperar_entradas();
$erroresGuardados = tomar_errores();
$errores = $erroresGuardados['campos'];

if (es_post() && $accion === 'abrir') {
    $nombre    = post('nombre');
    $importe   = a_numero(post('apertura_importe', '0'));
    $observacion = post('apertura_observacion');

    if ($importe <= 0) {
        $errores['apertura_importe'] = 'Contá cuánta plata hay en el cajón.';
    }

    if (!empty($errores)) {
        guardar_entradas([
            'nombre'               => $nombre,
            'apertura_importe'     => cantidad($importe, 2),
            'apertura_observacion' => $observacion,
        ]);

        guardar_errores($errores);
        flash_error('Revisá los campos marcados.');
        redirigir('caja/abrir');
    }

    $resultado = Caja::abrir($nombre, $importe, $observacion);

    if (!$resultado['ok']) {
        guardar_entradas([
            'nombre'               => $nombre,
            'apertura_importe'     => cantidad($importe, 2),
            'apertura_observacion' => $observacion,
        ]);

        guardar_errores([], [$resultado['mensaje']]);
        flash_error($resultado['mensaje']);
        redirigir('caja/abrir');
    }

    flash_exito('Caja abierta con ' . money($importe) . '. Ya se puede cobrar.');
    redirigir('caja');
}

// ------------------------------------------------------------
// GET: la pantalla
// ------------------------------------------------------------
$caja = Caja::abierta();

if ($caja !== null) {
    // Si la caja ya está abierta, esta pantalla solo sirve para
    // registrar movimientos: se manda a la de caja.
    $titulo    = 'Movimiento de caja';
    $subtitulo = 'Entró o salió plata del cajón';

    $volver = url('caja');
    $soloMovimiento = true;
} else {
    $titulo    = 'Abrir caja';
    $subtitulo = 'Contá la plata del cajón para empezar el turno';
    $volver = url('caja');
    $soloMovimiento = false;
}

require APP_ROOT . '/modules/caja/vistas/abrir.php';
