<?php
/**
 * modules/stock/ajuste.php
 * -----------------------------------------------------------------
 * Ajuste manual de stock. Solo administradores.
 *
 * Sirve para corregir el conteo físico: se contó el depósito, el
 * número no da y hay que dejarloRight. El motivo es OBLIGATORIO:
 * un ajuste sin explicación es indistinguible de un error.
 *
 * Un ajuste no cambia el costo del producto. Si el costo cambió,
 * eso es otra cosa (y se carga con una compra o editando el
 * producto).
 * -----------------------------------------------------------------
*/

$idProducto = get_id('producto_id');
$erroresGuardados = tomar_errores();
$entradas = recuperar_entradas();

// --- Producto elegido -------------------------------------------
$producto = null;

if ($idProducto > 0) {
    $producto = DB::one(
        'SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad, p.costo,
                c.nombre AS categoria
         FROM productos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id = :id AND p.controla_stock = 1',
        ['id' => $idProducto]
    );

    if ($producto === null) {
        flash_error('Ese producto no lleva control de stock, no se puede ajustar.');
        redirigir('stock/ajuste');
    }
}

// --- Todos los productos ajustables -----------------------------
$productosAjustables = DB::all(
    'SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad, p.costo
     FROM productos p
     WHERE p.activo = 1 AND p.controla_stock = 1
     ORDER BY p.nombre ASC'
);

// --- Valores precargados del formulario --------------------------
$valores = [
    'producto_id' => $entradas['producto_id'] ?? ($producto['id'] ?? ''),
    'stock_real'  => $entradas['stock_real']  ?? '',
    'motivo'      => $entradas['motivo']      ?? '',
    'tipo'        => $entradas['tipo']        ?? 'faltante',
];

// Si viene un producto, el stock real arranca con el actual: lo
// habitual es corregir un número, no arrancar de cero.
if ($producto && ($valores['stock_real'] === '' || $valores['stock_real'] === null)) {
    $valores['stock_real'] = cantidad((float) $producto['stock_actual'], 2);
}

// Cuánto va a cambiar, solo como ayuda visual antes de guardar.
$delta = null;

if ($producto && $valores['stock_real'] !== '') {
    $delta = a_numero((string) $valores['stock_real']) - (float) $producto['stock_actual'];
}

require APP_ROOT . '/modules/stock/vistas/ajuste.php';