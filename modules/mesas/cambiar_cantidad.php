<?php
/**
 * modules/mesas/cambiar_cantidad.php
 * -----------------------------------------------------------------
 * Cambia la cantidad de un producto del pedido.
 *
 * El stock sigue a la cantidad: si sube, se descuenta la
 * diferencia; si baja, se repone. Así la línea y el stock no
 * quedan desalineados.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');
$itemId    = (int) post('item_id', '0');
$cantidad  = a_numero(post('cantidad', '0'));

if ($comandaId <= 0 || $itemId <= 0) {
    flash_error('No se indicó qué producto cambiar.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$destino = 'mesas/ver&mesa=' . (int) $comanda['mesa_id'];

$resultado = Comanda::cambiar_cantidad($comandaId, $itemId, $cantidad);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);
} else {
    flash_exito('Cantidad actualizada a ' . cantidad($cantidad) . '.');
}

if (!headers_sent()) {
    header('Location: ' . url($destino));
    exit;
}

exit;