<?php
/**
 * modules/caja/index.php
 * -----------------------------------------------------------------
 * La caja: cuánto hay, cómo viene el turno, y el botón para abrir
 * o cerrar.
 *
 * La ven los dos roles. El vendedor necesita saber si puede
 * cobrar, registrar entradas y salidas de plata, y cerrar el turno.
 * Abrir la caja sigue siendo del administrador, porque es donde se
 * declara cuanta plata hay en el cajon al empezar.
 * -----------------------------------------------------------------
*/

$caja = Caja::abierta();
$esAdmin = Auth::es_admin();

// --- Caja abierta -------------------------------------------------
$esperado    = null;
$porMetodo   = [];
$ventas      = [];
$movimientos = [];

if ($caja !== null) {
    $cajaId     = (int) $caja['id'];
    $esperado    = Caja::esperado($cajaId);
    $porMetodo   = Caja::resumenPorMetodo($cajaId);
    $ventas      = Caja::ventas($cajaId);
    $movimientos = Caja::movimientos($cajaId);
}

// --- Mesas esperando cobro ---------------------------------------
$pendientes = Venta::pendientesDeCobro();

// --- Historial de cajas -------------------------------------------
// Se carga para los dos roles. La vista ya no filtra por $esAdmin,
// pero mientras acá venia vacia para el vendedor, el cierre quedaba
// sin forma de comprobar: uno cierra el turno y no puede ver ni que
// lo cerro ni cuanto le falto.
//
// No es un dato sensible: es el turno que acaba de cerrar, con los
// numeros que el sistema le mostro en pantalla antes de cerrarlo.
$historial = Caja::historial(10);

require APP_ROOT . '/modules/caja/vistas/index.php';
