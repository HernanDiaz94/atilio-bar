<?php
/**
 * modules/stock/compra_ver.php
 * -----------------------------------------------------------------
 * Detalle de una compra. Solo administradores.
 *
 * Muestra la cabecera, las líneas y el efecto que tuvo en el
 * stock y en el costo de cada producto. Sirve, entre otras cosas,
 * para entender por qué un costo quedó como quedó.
 * -----------------------------------------------------------------
*/

$idCompra = get_id('id');

if ($idCompra <= 0) {
    flash_error('No se indicó qué compra ver.');
    redirigir('compras');
}

$compra = DB::one(
    'SELECT c.*, u.usuario AS usuario_nombre, u.rol AS usuario_rol
     FROM compras c
     LEFT JOIN usuarios u ON u.id = c.usuario_id
     WHERE c.id = :id',
    ['id' => $idCompra]
);

if ($compra === null) {
    flash_error('Esa compra no existe.');
    redirigir('compras');
}

// Detalle con el estado actual del producto, para ver el efecto
$lineas = DB::all(
    'SELECT cd.*, p.nombre AS producto_nombre, p.unidad, p.stock_actual AS stock_hoy,
            p.costo AS costo_hoy, c.nombre AS categoria
     FROM compras_detalle cd
     INNER JOIN productos p ON p.id = cd.producto_id
     INNER JOIN categorias c ON c.id = p.categoria_id
     WHERE cd.compra_id = :compra
     ORDER BY p.nombre ASC',
    ['compra' => $idCompra]
);

require APP_ROOT . '/modules/stock/vistas/compra_ver.php';