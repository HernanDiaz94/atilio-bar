<?php
/**
 * modules/productos/formulario.php
 * -----------------------------------------------------------------
 * Alta y edición de productos. Solo administradores.
 *
 * Si viene ?id= edita ese producto; si no, crea uno nuevo.
 * -----------------------------------------------------------------
*/

$idProducto = get_id('id');
$esNuevo    = ($idProducto === 0);
$producto   = null;

if (!$esNuevo) {
    $producto = DB::one(
        'SELECT id, codigo, categoria_id, nombre, descripcion, precio_venta,
                costo, stock_actual, stock_minimo, unidad, controla_stock,
                es_combo, activo, creado_en
         FROM productos
         WHERE id = :id',
        ['id' => $idProducto]
    );

    if ($producto === null) {
        flash_error('Ese producto no existe.');
        redirigir('productos');
    }
}

// --- Datos precargados -----------------------------------------
$entradas = recuperar_entradas();
$erroresGuardados = tomar_errores();

// Líneas de la receta que el usuario había cargado antes de un
// error de validación, para que no las pierda al volver.
$componentesPrecargadas = $entradas['lineas'] ?? [];

$valores = [
    'codigo'         => $entradas['codigo']         ?? ($producto['codigo']         ?? ''),
    'categoria_id'   => $entradas['categoria_id']   ?? ($producto['categoria_id']   ?? ''),
    'nombre'         => $entradas['nombre']         ?? ($producto['nombre']         ?? ''),
    'descripcion'    => $entradas['descripcion']    ?? ($producto['descripcion']    ?? ''),
    'precio_venta'   => $entradas['precio_venta']   ?? ($producto['precio_venta']   ?? ''),
    'costo'          => $entradas['costo']          ?? ($producto['costo']          ?? ''),
    'stock_actual'   => $entradas['stock_actual']   ?? ($producto['stock_actual']   ?? '0'),
    'stock_minimo'   => $entradas['stock_minimo']   ?? ($producto['stock_minimo']   ?? '0'),
    'unidad'         => $entradas['unidad']         ?? ($producto['unidad']         ?? 'unidad'),
    'controla_stock' => $entradas['controla_stock'] ?? ($producto['controla_stock'] ?? '1'),
    'es_combo'       => $entradas['es_combo']       ?? ($producto['es_combo']       ?? '0'),
    'activo'         => $entradas['activo']         ?? ($producto['activo']         ?? '1'),
];

// --- Categorías disponibles ------------------------------------
// Solo las activas: una categoría desactivada no debería recibir
// productos nuevos. Si el producto ya está en una desactivada,
// igual se la ofrecemos para poder sacarlo de ahí.
$categorias = DB::all(
    'SELECT id, nombre, activo FROM categorias ORDER BY orden ASC, nombre ASC'
);

// --- Receta del combo (si es combo) -----------------------------
$receta     = [];
$componentes = [];

if (!$esNuevo && (int) $producto['es_combo'] === 1) {
    $receta = Stock::receta($idProducto);

    foreach ($receta as $insumo) {
        $componentes[(int) $insumo['componente_id']] = (float) $insumo['cantidad'];
    }
}

// Si viene de un rechazo del servidor, tienen prioridad las líneas
// que el usuario había tipeado en ese intento.
if (!empty($componentesPrecargadas)) {
    $componentes = $componentesPrecargadas;
}

// Candidatos a insumo: los que llevan control de stock, más los
// ya usados en la receta (aunque estén inactivos).
$candidatosInsumo = DB::all(
    "SELECT id, nombre, unidad, stock_actual, controla_stock, activo
     FROM productos
     WHERE controla_stock = 1
        OR id IN (SELECT componente_id FROM combo_componentes WHERE combo_id = :combo)
     ORDER BY nombre ASC",
    ['combo' => $idProducto]
);

$avisosReceta = (!$esNuevo && (int) $producto['es_combo'] === 1)
    ? Stock::problemas_receta($idProducto)
    : [];

// ¿Se puede borrar? Solo si no tiene movimientos de stock.
$tieneMovimientos = false;

if (!$esNuevo) {
    $tieneMovimientos = (int) DB::valor(
        'SELECT COUNT(*) FROM stock_movimientos WHERE producto_id = :id',
        ['id' => $idProducto]
    ) > 0;
}

require APP_ROOT . '/modules/productos/vistas/formulario.php';