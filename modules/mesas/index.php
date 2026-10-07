<?php
/**
 * modules/mesas/index.php
 * -----------------------------------------------------------------
 * La grilla de mesas. Es la pantalla principal del bar.
 *
 * La ven los dos roles: el vendedor abre mesas y carga pedidos,
 * el administrador ve lo mismo más la opción de administrar.
 *
 * El estado de cada mesa lo calcula Mesas::grilla() a partir de las
 * comandas abiertas y las reservas de hoy. No hay una tabla de
 * estados guardada, justamente para que no pueda quedar
 * desactualizada.
 * -----------------------------------------------------------------
*/

$esAdmin = Auth::es_admin();

// --- Zona a mostrar (filtro) ------------------------------------
$zona = get('zona');

if (!array_key_exists($zona, Mesas::ZONAS)) {
    $zona = '';
}

// --- Grilla ------------------------------------------------------
$mesas = Mesas::grilla();

if ($zona !== '') {
    $mesas = array_values(array_filter($mesas, static fn (array $m) => $m['zona'] === $zona));
}

$grupos     = Mesas::agrupar_por_zona($mesas);
$resumen    = Mesas::resumen($mesas);

// --- Reservas de hoy --------------------------------------------
$reservasHoy = Reserva::hoy();

// --- Para el formulario de abrir mesa ---------------------------
$mesasLibres = array_values(array_filter($mesas, static fn (array $m) => $m['estado'] === 'libre'));

require APP_ROOT . '/modules/mesas/vistas/grilla.php';