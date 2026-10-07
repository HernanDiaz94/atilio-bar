<?php
/**
 * modules/mesas/anular_producto.php
 * -----------------------------------------------------------------
 * Saca un producto del pedido y devuelve el stock.
 *
 * Lo usan los dos roles: el vendedor se equivoca al cargar y
 * necesita corregirlo. Anular un producto no es lo mismo que
 * cancelar la mesa: cancelar la mesa es otra cosa y sí es solo
 * del administrador.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');
$itemId    = (int) post('item_id', '0');
$motivo    = post('motivo');

if ($comandaId <= 0 || $itemId <= 0) {
    flash_error('No se indicó qué producto anular.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$destino = 'mesas/ver&mesa=' . (int) $comanda['mesa_id'];

$resultado = Comanda::anular_item($comandaId, $itemId, $motivo);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);
} else {
    flash_exito('Producto sacado del pedido. El stock volvió.');
}

if (!headers_sent()) {
    header('Location: ' . url($destino));
    exit;
}

exit;