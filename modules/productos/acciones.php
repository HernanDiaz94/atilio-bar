<?php
/**
 * modules/productos/acciones.php
 * -----------------------------------------------------------------
 * Acciones puntuales sobre un producto (solo administradores):
 *
 *   activar        Vuelve a aparecer en la carta.
 *   desactivar     Se saca de la carta pero conserva su historial.
 *   eliminar       Solo si nunca tuvo movimientos de stock.
 *
 * Todas llegan por POST (el router ya validó el token CSRF) y
 * dejan rastro en la tabla auditoria.
 * -----------------------------------------------------------------
*/

$accion     = post('accion');
$idProducto = (int) post('id', '0');

if ($idProducto <= 0) {
    flash_error('No se indicó qué producto modificar.');
    redirigir('productos');
}

$producto = DB::one(
    'SELECT id, nombre, activo, controla_stock FROM productos WHERE id = :id',
    ['id' => $idProducto]
);

if ($producto === null) {
    flash_error('Ese producto no existe.');
    redirigir('productos');
}

$nombre = (string) $producto['nombre'];

switch ($accion) {
    // ------------------------------------------------------------
    case 'activar':
        DB::actualizar('productos', $idProducto, [
            'activo'         => 1,
            'actualizado_en' => ahora(),
        ]);

        Auditoria::registrar('activar_producto', 'productos', $idProducto,
            ['activo' => 0], ['activo' => 1]);

        flash_exito('"' . $nombre . '" volvió a la carta.');
        break;

    // ------------------------------------------------------------
    case 'desactivar':
        DB::actualizar('productos', $idProducto, [
            'activo'         => 0,
            'actualizado_en' => ahora(),
        ]);

        Auditoria::registrar('desactivar_producto', 'productos', $idProducto,
            ['activo' => 1], ['activo' => 0]);

        flash_exito('"' . $nombre . '" se sacó de la carta. Su historial queda intacto.');
        break;

    // ------------------------------------------------------------
    case 'eliminar':
        // Con historial de stock, nunca se borra: se desactiva.
        // Un producto que se vendió no puede desaparecer, o el
        // historial de los movimientos queda apuntando al vacío.
        $tieneMovimientos = (int) DB::valor(
            'SELECT COUNT(*) FROM stock_movimientos WHERE producto_id = :id',
            ['id' => $idProducto]
        ) > 0;

        if ($tieneMovimientos) {
            flash_error(
                'No se puede eliminar "' . $nombre . '" porque tiene movimientos de stock registrados. '
                . 'Desactivalo en su lugar: conserva el historial y deja de aparecer en la carta.'
            );
            redirigir('productos');
        }

        Auditoria::registrar('eliminar_producto', 'productos', $idProducto, $producto, null);

        DB::eliminar('productos', $idProducto);

        flash_exito('Producto "' . $nombre . '" eliminado.');
        break;

    // ------------------------------------------------------------
    default:
        flash_error('La acción solicitada no existe.');
        break;
}

redirigir('productos');