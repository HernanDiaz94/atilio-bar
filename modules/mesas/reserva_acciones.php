<?php
/**
 * modules/mesas/reserva_acciones.php
 * -----------------------------------------------------------------
 * Confirmar o cancelar reservas. Lo usan los dos roles.
 * -----------------------------------------------------------------
*/

$accion     = post('accion');
$idReserva  = (int) post('id', '0');

if ($idReserva <= 0) {
    flash_error('No se indicó qué reserva modificar.');
    redirigir('reservas');
}

$reserva = DB::one(
    'SELECT r.id, r.nombre, r.mesa_id, m.numero AS mesa_numero
     FROM reservas r
     INNER JOIN mesas m ON m.id = r.mesa_id
     WHERE r.id = :id',
    ['id' => $idReserva]
);

if ($reserva === null) {
    flash_error('Esa reserva no existe.');
    redirigir('reservas');
}

switch ($accion) {
    // ------------------------------------------------------------
    case 'confirmar':
        $resultado = Reserva::cambiar_estado($idReserva, 'confirmada');

        if ($resultado['ok']) {
            flash_exito(
                'Reserva de ' . $reserva['nombre'] . ' confirmada para la mesa '
                . $reserva['mesa_numero'] . '.'
            );
        } else {
            flash_error($resultado['mensaje']);
        }
        break;

    // ------------------------------------------------------------
    case 'cancelar':
        $resultado = Reserva::cambiar_estado($idReserva, 'cancelada');

        if ($resultado['ok']) {
            flash_exito('Reserva cancelada. La mesa vuelve a estar libre.');
        } else {
            flash_error($resultado['mensaje']);
        }
        break;

    // ------------------------------------------------------------
    case 'no_show':
        // Llegó y no vino. La mesa queda libre igual, pero queda
        // registrado, que sirve para ver si hay alguien que siempre
        // falla.
        $resultado = Reserva::cambiar_estado($idReserva, 'no_show');

        if ($resultado['ok']) {
            flash_info('Reserva marcada como "no vino".');
        } else {
            flash_error($resultado['mensaje']);
        }
        break;

    // ------------------------------------------------------------
    default:
        flash_error('La acción solicitada no existe.');
        break;
}

redirigir('reservas');