<?php
/**
 * modules/cobrar/anular.php
 * -----------------------------------------------------------------
 * Anula una venta cobrada. Solo administrador.
 *
 * No es lo mismo que anular una mesa sin cobrar: acá la plata ya
 * entró al cajón y los productos ya salieron del depósito, así que
 * anular devuelve las dos cosas.
 *
 * Solo se puede anular con la caja del día abierta: si el turno ya
 * se cerró, la plata que había que sacar ya se contó, y "arreglar"
 * eso después sería inventar plata.
 * -----------------------------------------------------------------
*/

$ventaId = (int) post('venta_id', '0');
$motivo  = post('motivo');

if ($ventaId <= 0) {
    flash_error('No se indicó qué venta anular.');
    redirigir('ventas');
}

$venta = Venta::obtener($ventaId);

if ($venta === null) {
    flash_error('Esa venta no existe.');
    redirigir('ventas');
}

$resultado = Venta::anular($ventaId, $motivo);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);

    if (!headers_sent()) {
        header('Location: ' . url('ventas/ver') . '&id=' . $ventaId);
        exit;
    }

    exit;
}

flash_exito(
    'Venta ' . (string) $venta['numero'] . ' anulada. '
    . 'La plata sale de la caja y el stock vuelve al depósito.'
);

redirigir('ventas/ver&id=' . $ventaId);
