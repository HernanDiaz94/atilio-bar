<?php
/**
 * modules/caja/cerrar.php
 * -----------------------------------------------------------------
 * Cierra el turno con la plata contada.
 *
 * La pantalla muestra cuánto DEBERÍA haber con el desglose, para
 * que el que cuenta sepa contra qué está comparando. Si el número
 * no da, el desglose dice si el problema son las ventas, los gastos
 * o un error de tipeo.
 *
 * Solo el administrador.
 * -----------------------------------------------------------------
*/

$caja = Caja::abierta();

if ($caja === null) {
    flash_error('No hay ninguna caja abierta.');
    redirigir('caja');
}

$cajaId  = (int) $caja['id'];
$esperado = Caja::esperado($cajaId);

// ------------------------------------------------------------
// POST: cerrar de verdad
// ------------------------------------------------------------
if (es_post()) {
    $contado     = a_numero(post('cierre_importe', '0'));
    $observacion = post('cierre_observacion');

    $resultado = Caja::cerrar($cajaId, $contado, $observacion);

    if (!$resultado['ok']) {
        guardar_entradas([
            'cierre_importe'     => cantidad($contado, 2),
            'cierre_observacion' => $observacion,
        ]);

        flash_error($resultado['mensaje']);
        redirigir('caja/cerrar');
    }

    $dif = (float) $resultado['diferencia'];

    if ($dif == 0.0) {
        flash_exito('Caja cerrada. Cuadra exacto.');
    } elseif ($dif > 0) {
        flash_info('Caja cerrada. Sobraron ' . money($dif) . '.');
    } else {
        flash_error('Caja cerrada. Faltaron ' . money(-$dif) . '. Revisá si hubo algún gasto sin cargar.');
    }

    redirigir('caja');
}

// ------------------------------------------------------------
// GET: la pantalla de conteo
// ------------------------------------------------------------
$entradas = recuperar_entradas();
$erroresGuardados = tomar_errores();
$errores = $erroresGuardados['campos'];

// Lo más común es que cuadre: se propone el número esperado como
// valor inicial para no obligar a escribirlo si ya está contado.
$contadoPropuesto = $entradas['cierre_importe']
    ?? cantidad($esperado['esperado'], 2);

$titulo    = 'Cerrar caja';
$subtitulo = 'Contá la plata y compará con lo que debería haber';

$accionesCabecera = '<a href="' . e(url('caja')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a la caja</a>';

require APP_ROOT . '/modules/caja/vistas/cerrar.php';
