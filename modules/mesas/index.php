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

// --- Grilla ------------------------------------------------------
//
// Trae TODAS las mesas, con o sin filtro. El filtro se aplica
// despues, sobre esta lista.
$mesas = Mesas::grilla();

// Los grupos se calculan SIEMPRE sobre todas las mesas, antes de
// aplicar el filtro.
//
// Es por el filtro de arriba: si se agrupara despues de filtrar, con
// un filtro puesto la grilla tendria un solo grupo y el filtro se
// quedaria con una sola opcion, sin forma de volver a ver las demas.
$grupos = Mesas::agrupar_por_zona($mesas);

// --- Zona a mostrar (filtro) ------------------------------------
//
// El filtro acepta cualquier zona que exista de verdad en la base,
// no solo las de Mesas::ZONAS. Si alguien escribe a mano una zona
// vieja en la URL (por ejemplo &zona=vereda, de una base a la que no
// se le corrio la etapa 7), el filtro la muestra en vez de ignorarla
// en silencio: asi el mozo ve esas mesas en vez de creer que nunca
// existieron.
$zona = get('zona');

if ($zona !== '' && !array_key_exists($zona, $grupos)) {
    $zona = '';
}

if ($zona !== '') {
    $mesas = array_values(array_filter($mesas, static fn (array $m) => $m['zona'] === $zona));
}

$resumen = Mesas::resumen($mesas);

// --- Reservas de hoy --------------------------------------------
$reservasHoy = Reserva::hoy();

// --- Para el formulario de abrir mesa ---------------------------
$mesasLibres = array_values(array_filter($mesas, static fn (array $m) => $m['estado'] === 'libre'));

require APP_ROOT . '/modules/mesas/vistas/grilla.php';