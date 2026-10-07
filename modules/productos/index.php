<?php
/**
 * modules/productos/index.php
 * -----------------------------------------------------------------
 * Carta de productos.
 *
 * Visible para los dos roles, pero muy distinto según quién mire:
 *
 *   - Administrador: ve costo, margen y alertas de stock. Puede
 *     modificar todo.
 *   - Vendedor: ve nombre, categoría, precio y stock. NO ve el
 *     costo, y los botones de edición no aparecen.
 *
 * El costo no está en la consulta que hace el vendedor, no es que
 * se lo escondamos con CSS: la consulta que se ejecuta para ese rol
 * no lo trae. Un vendedor que mire el código fuente tampoco lo
 * encuentra.
 * -----------------------------------------------------------------
*/

$esAdmin = Auth::es_admin();

// --- Filtros ---------------------------------------------------
$buscar     = get('q');
$categoria  = get('categoria');
$estado     = get('estado');   // bajo | sin | todos
$verTodos   = ($buscar !== '' || $categoria !== '' || $estado !== '');

// --- Validar el filtro de categoría ----------------------------
if ($categoria !== '' && !ctype_digit($categoria)) {
    $categoria = '';
}

// --- Consulta --------------------------------------------------
// Los placeholders con nombre NO se pueden repetir en la misma
// consulta con EMULATE_PREPARES apagado, así que cada uno tiene su
// nombre. Ver la nota en includes/db.php.
$sql = 'SELECT p.id, p.codigo, p.nombre, p.descripcion, p.precio_venta,
               p.stock_actual, p.stock_minimo, p.unidad,
               p.controla_stock, p.es_combo, p.activo,
               c.nombre AS categoria_nombre';

$parametros = [];

// La columna de costo y margen SOLO se pide si quien mira es admin.
if ($esAdmin) {
    $sql .= ', p.costo';
}

$sql .= ' FROM productos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         WHERE 1 = 1';

if (!$verTodos) {
    // Por defecto se ven solo los activos: la carta del bar.
    $sql .= ' AND p.activo = 1';
}

if ($buscar !== '') {
    $sql .= ' AND (p.nombre     LIKE :b_nombre
                   OR p.descripcion LIKE :b_descripcion
                   OR p.codigo      LIKE :b_codigo)';
    $patron = '%' . $buscar . '%';
    $parametros['b_nombre']      = $patron;
    $parametros['b_descripcion'] = $patron;
    $parametros['b_codigo']      = $patron;
}

if ($categoria !== '') {
    $sql .= ' AND p.categoria_id = :categoria';
    $parametros['categoria'] = (int) $categoria;
}

if ($estado === 'bajo') {
    $sql .= ' AND p.controla_stock = 1 AND p.stock_actual <= p.stock_minimo';
}

if ($estado === 'sin') {
    $sql .= ' AND p.controla_stock = 1 AND p.stock_actual <= 0';
}

$sql .= ' ORDER BY c.orden ASC, p.nombre ASC';

$productos = DB::all($sql, $parametros);

// --- Datos auxiliares -------------------------------------------
$categorias = DB::all(
    'SELECT c.id, c.nombre,
            (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS productos
     FROM categorias c
     ORDER BY c.orden ASC, c.nombre ASC'
);

// Totales para las tarjetas de arriba
$totalProductos   = (int) DB::valor('SELECT COUNT(*) FROM productos WHERE activo = 1');
$totalConStock    = (int) DB::valor('SELECT COUNT(*) FROM productos WHERE activo = 1 AND controla_stock = 1');
$totalStockBajo   = Stock::cantidad_stock_bajo();
$totalStockCero   = (int) DB::valor('SELECT COUNT(*) FROM productos WHERE activo = 1 AND controla_stock = 1 AND stock_actual <= 0');

// Valorización del stock: solo tiene sentido si hay costos cargados.
// Si ninguno tiene costo, el valor es 0 y no se muestra.
$valorStock = 0.0;
if ($esAdmin) {
    $valorStock = (float) DB::valor(
        'SELECT COALESCE(SUM(stock_actual * costo), 0) FROM productos
         WHERE activo = 1 AND controla_stock = 1'
    );
}

// Productos que se pueden usar en un ajuste o una compra
$productosConStock = DB::all(
    'SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad, p.costo
     FROM productos p
     WHERE p.activo = 1 AND p.controla_stock = 1
     ORDER BY p.nombre ASC'
);

require APP_ROOT . '/modules/productos/vistas/lista.php';