<?php
/**
 * modules/stock/historial.php
 * -----------------------------------------------------------------
 * Historial de movimientos de stock.
 *
 * Lo pueden ver los dos roles: entender por qué cambió un número
 * es información, y le sirve tanto al administrador para auditar
 * como al vendedor para saber si hay que reponer mercadería.
 * Lo que el vendedor NO puede es modificar el stock, y eso lo
 * garantizan las rutas de ajuste y compra, no esta pantalla.
 * -----------------------------------------------------------------
*/

$esAdmin = Auth::es_admin();

// --- Filtros ---------------------------------------------------
$filtros = [
    'producto_id' => get_id('producto_id'),
    'tipo'        => get('tipo'),
    'desde'       => get('desde'),
    'hasta'       => get('hasta'),
];

// El tipo tiene que ser uno de los conocidos.
if (!in_array($filtros['tipo'], Stock::TIPOS, true)) {
    $filtros['tipo'] = '';
}

// Las fechas tienen que tener el formato Y-m-d.
foreach (['desde', 'hasta'] as $campo) {
    if ($filtros[$campo] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$campo])) {
        $filtros[$campo] = '';
    }
}

// --- Paginación ------------------------------------------------
// 40 por página: en una pantalla de celular se leen sin scroll
// infinito, y en una de escritorio tampoco hay que scrollear mucho.
$pagina  = max(1, (int) get('pagina', '1'));
$porPagina = 40;

$totalMovimientos = Stock::total_movimientos($filtros);
$totalPaginas     = max(1, (int) ceil($totalMovimientos / $porPagina));

// Si alguien pide una página que ya no existe, vuelve a la última.
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$movimientos = Stock::movimientos($filtros, $porPagina, $pagina);

// --- Datos auxiliares -------------------------------------------
$productos = DB::all(
    'SELECT id, nombre, unidad FROM productos
     WHERE controla_stock = 1
     ORDER BY nombre ASC'
);

// --- Totales del período filtrado --------------------------------
$resumen = [
    'total'   => 0.0,
    'total_bajo' => 0,
    'total_cero' => 0,
];

if ($esAdmin) {
    // Valorización del stock actual, no del período: sirve para
    // saber cuánto dinero está parado en el depósito.
    $resumen['total_bajo'] = Stock::cantidad_stock_bajo();
    $resumen['total_cero'] = (int) DB::valor(
        'SELECT COUNT(*) FROM productos
         WHERE activo = 1 AND controla_stock = 1 AND stock_actual <= 0'
    );
    $resumen['valor'] = (float) DB::valor(
        'SELECT COALESCE(SUM(stock_actual * costo), 0) FROM productos
         WHERE activo = 1 AND controla_stock = 1'
    );
}

require APP_ROOT . '/modules/stock/vistas/historial.php';