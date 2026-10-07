<?php
/**
 * modules/mesas/por_cobrar.php
 * -----------------------------------------------------------------
 * Marca el pedido como "por cobrar".
 *
 * Lo usan los dos roles. A partir de acá el pedido queda
 * congelado: no se le pueden agregar productos ni cambiar
 * cantidades. El cobro llega en la Etapa 4.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');

if ($comandaId <= 0) {
    flash_error('No se indicó qué pedido cerrar.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$destino = 'mesas/ver&mesa=' . (int) $comanda['mesa_id'];

$resultado = Comanda::marcar_por_cobrar($comandaId);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);
} else {
    flash_exito('Pedido cerrado. La mesa quedó esperando el cobro.');
}

if (!headers_sent()) {
    header('Location: ' . url($destino));
    exit;
}

exit;