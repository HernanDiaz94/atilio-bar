<?php
/**
 * modules/caja/index.php
 * -----------------------------------------------------------------
 * La caja: cuánto hay, cómo viene el turno, y el botón para abrir
 * o cerrar.
 *
 * La ven los dos roles. El vendedor necesita saber si puede
 * cobrar; abrir y cerrar es del administrador.
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
$historial = $esAdmin ? Caja::historial(10) : [];

require APP_ROOT . '/modules/caja/vistas/index.php';
