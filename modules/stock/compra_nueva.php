<?php
/**
 * modules/stock/compra_nueva.php
 * -----------------------------------------------------------------
 * Formulario para cargar una compra. Solo administradores.
 *
 * Las líneas se arman en el navegador: se elige un producto y la
 * cantidad, y se agrega a la tabla. El total se calcula solo.
 * -----------------------------------------------------------------
*/

$erroresGuardados = tomar_errores();
$entradas = recuperar_entradas();

// Productos que se pueden comprar: los que llevan control de stock
$productos = DB::all(
    'SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad, p.costo,
            c.nombre AS categoria
     FROM productos p
     INNER JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1 AND p.controla_stock = 1
     ORDER BY c.orden ASC, p.nombre ASC'
);

// Valores precargados
$valores = [
    'proveedor'          => $entradas['proveedor'] ?? '',
    'numero_comprobante' => $entradas['numero_comprobante'] ?? '',
    'fecha'              => $entradas['fecha'] ?? date('Y-m-d'),
    'observaciones'      => $entradas['observaciones'] ?? '',
];

// Líneas que el usuario ya había cargado antes de un error
$lineasPrecargadas = $entradas['lineas'] ?? [];

// Proveedores que ya se usaron: es cómodo no tener que escribirlos
$proveedores = DB::all(
    'SELECT DISTINCT proveedor FROM compras
     WHERE proveedor <> \'\'
     ORDER BY proveedor ASC
     LIMIT 50'
);

require APP_ROOT . '/modules/stock/vistas/compra_nueva.php';