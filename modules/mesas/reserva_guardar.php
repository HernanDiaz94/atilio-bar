<?php
/**
 * modules/mesas/reserva_guardar.php
 * -----------------------------------------------------------------
 * Guarda una reserva. Lo usan los dos roles.
 * -----------------------------------------------------------------
*/

$resultado = Reserva::crear([
    'mesa_id'    => (int) post('mesa_id', '0'),
    'fecha'      => post('fecha'),
    'hora'       => post('hora'),
    'comensales' => (int) post('comensales', '2'),
    'nombre'     => post('nombre'),
    'telefono'   => post('telefono'),
    'notas'      => post('notas'),
    'estado'     => 'pendiente',
]);

if ($resultado['ok']) {
    flash_exito('Reserva guardada.');
} else {
    // Se guardan los datos para no hacer escribir todo de nuevo.
    guardar_entradas([
        'mesa_id'    => post('mesa_id'),
        'fecha'      => post('fecha'),
        'hora'       => post('hora'),
        'comensales' => post('comensales'),
        'nombre'     => post('nombre'),
        'telefono'   => post('telefono'),
        'notas'      => post('notas'),
    ]);

    guardar_errores(['reserva' => $resultado['mensaje']]);
    flash_error($resultado['mensaje']);
}

redirigir('reservas');