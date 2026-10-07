<?php
/**
 * modules/mesas/reabrir.php
 * -----------------------------------------------------------------
 * Vuelve a abrir un pedido que ya habia pedido la cuenta.
 *
 * Los dos roles. En un bar esto pasa todos los días: el cliente
 * pide la cuenta, y al ver la carta se acuerda de un postre.
 *
 * El stock NO se toca. Los productos ya estaban descontados del
 * depósito cuando se cargaron, y siguen consumiéndose: lo que
 * cambia es que ahora se puede volver a cargar.
 *
 * Pide un motivo, que queda en la auditoría. No es desconfianza
 * del mozo: es para saber después por qué una mesa que ya habia
 * pedido la cuenta volvió a abrirse.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');
$motivo    = post('motivo');

if ($comandaId <= 0) {
    flash_error('No se indicó qué pedido reabrir.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$destino = url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id'];

$resultado = Comanda::reabrir($comandaId, $motivo);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);
} else {
    flash_exito('Pedido reabierto. Ya se le pueden agregar productos.');
}

if (!headers_sent()) {
    header('Location: ' . $destino);
    exit;
}

exit;
