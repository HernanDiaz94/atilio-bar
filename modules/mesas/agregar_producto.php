<?php
/**
 * modules/mesas/agregar_producto.php
 * -----------------------------------------------------------------
 * Agrega un producto al pedido de una mesa. Lo usan los dos roles.
 *
 * El precio se toma SIEMPRE del producto, nunca de lo que mande el
 * navegador. Si alguien altera el campo del precio en el formulario,
 * lo que se guarda es el precio real del producto.
 * -----------------------------------------------------------------
*/

$comandaId  = (int) post('comanda_id', '0');
$productoId = (int) post('producto_id', '0');
$cantidad   = a_numero(post('cantidad', '1'));

if ($comandaId <= 0 || $productoId <= 0) {
    flash_error('No se indicó qué agregar.');
    redirigir('mesas');
}

$comanda = Comanda::obtener($comandaId);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

// A dónde volver
$destino = 'mesas/ver&mesa=' . (int) $comanda['mesa_id'];

// --- Agregar ------------------------------------------------------
$resultado = Comanda::agregar_item($comandaId, $productoId, $cantidad);

if (!$resultado['ok']) {
    flash_error($resultado['mensaje']);

    if (!headers_sent()) {
        header('Location: ' . url($destino));
        exit;
    }

    exit;
}

// --- Avisos ------------------------------------------------------
$producto = DB::one('SELECT nombre FROM productos WHERE id = :id', ['id' => $productoId]);

flash_exito(
    ($producto['nombre'] ?? 'Producto') . ' agregado a la mesa.'
);

if (!empty($resultado['stock_bajo'])) {
    foreach ($resultado['stock_bajo'] as $aviso) {
        flash_info($aviso);
    }
}

if (!headers_sent()) {
    header('Location: ' . url($destino));
    exit;
}

exit;