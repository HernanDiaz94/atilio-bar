<?php
/**
 * modules/mesas/reservas.php
 * -----------------------------------------------------------------
 * Reservas de mesas. Lo ven los dos roles: el vendedor tiene que
 * saber quién llega y a qué hora.
 * -----------------------------------------------------------------
*/

// --- Rango de fechas ---------------------------------------------
$desde = get('desde');
$hasta = get('hasta');

foreach (['desde', 'hasta'] as $campo) {
    if ($$campo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$campo)) {
        $$campo = '';
    }
}

// Por defecto: hoy y los próximos 7 días.
if ($desde === '' && $hasta === '') {
    $desde = date('Y-m-d');
    $hasta = date('Y-m-d', strtotime('+7 days'));
}

if ($desde !== '' && $hasta !== '') {
    // Si el usuario invirtió el rango, se da vuelta.
    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }
}

$reservas = ($desde !== '' && $hasta !== '')
    ? Reserva::listar($desde, $hasta)
    : [];

// --- Mesas para el formulario -------------------------------------
$mesas = DB::all(
    'SELECT id, numero, nombre, capacidad, zona
     FROM mesas
     WHERE activo = 1
     ORDER BY zona ASC, orden ASC'
);

// --- Errores del POST anterior ------------------------------------
$erroresGuardados = tomar_errores();
$entradas         = recuperar_entradas();

$valores = [
    'mesa_id'    => $entradas['mesa_id'] ?? '',
    'fecha'      => $entradas['fecha'] ?? date('Y-m-d'),
    'hora'       => $entradas['hora'] ?? date('H:i', strtotime('+1 hour')),
    'comensales' => $entradas['comensales'] ?? '2',
    'nombre'     => $entradas['nombre'] ?? '',
    'telefono'   => $entradas['telefono'] ?? '',
    'notas'      => $entradas['notas'] ?? '',
];

require APP_ROOT . '/modules/mesas/vistas/reservas.php';