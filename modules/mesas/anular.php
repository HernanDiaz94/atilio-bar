<?php
/**
 * modules/mesas/anular.php
 * -----------------------------------------------------------------
 * Anula una mesa: da de baja el pedido SIN cobrar y devuelve todo
 * el stock.
 *
 * SOLO ADMINISTRADOR, y el router ya lo bloquea para el vendedor.
 *
 * Es una operación de peso: el pedido desaparece de la cuenta del
 * día y todo lo que se había descontado vuelve al depósito. Por eso
 * pide un motivo.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');
$motivo    = post('motivo');

if ($comandaId <= 0) {
    flash_error('No se indicó qué mesa anular.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$destino = url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id'];

$resultado = Comanda::anular($comandaId, $motivo);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);
} else {
    flash_exito('Mesa anulada. Todo el stock volvió al depósito.');
}

if (!headers_sent()) {
    header('Location: ' . $destino);
    exit;
}

exit;