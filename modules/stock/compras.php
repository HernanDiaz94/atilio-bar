<?php
/**
 * modules/stock/compras.php
 * -----------------------------------------------------------------
 * Listado de compras (ingresos de mercadería). Solo administradores.
 * -----------------------------------------------------------------
*/

// --- Filtros ---------------------------------------------------
$desde = get('desde');
$hasta = get('hasta');
$buscar = get('q');

foreach (['desde', 'hasta'] as $campo) {
    if ($$campo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$campo)) {
        $$campo = '';
    }
}

// --- Consulta --------------------------------------------------
$sql = 'SELECT c.id, c.proveedor, c.numero_comprobante, c.fecha, c.total,
               c.observaciones, c.cantidad_items, c.creado_en,
               u.usuario AS usuario_nombre,
               (SELECT COUNT(*) FROM compras_detalle cd WHERE cd.compra_id = c.id) AS lineas
        FROM compras c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        WHERE 1 = 1';

$parametros = [];

if ($desde !== '') {
    $sql .= ' AND c.fecha >= :desde';
    $parametros['desde'] = $desde;
}

if ($hasta !== '') {
    $sql .= ' AND c.fecha <= :hasta';
    $parametros['hasta'] = $hasta;
}

if ($buscar !== '') {
    $sql .= ' AND (c.proveedor          LIKE :b_proveedor
                   OR c.numero_comprobante LIKE :b_comprobante)';
    $patron = '%' . $buscar . '%';
    $parametros['b_proveedor']   = $patron;
    $parametros['b_comprobante'] = $patron;
}

$sql .= ' ORDER BY c.fecha DESC, c.id DESC LIMIT 200';

$compras = DB::all($sql, $parametros);

// --- Totales del filtro ------------------------------------------
$totalCompras = (int) DB::valor('SELECT COUNT(*) FROM compras');
$totalGastado = (float) DB::valor('SELECT COALESCE(SUM(total), 0) FROM compras');

require APP_ROOT . '/modules/stock/vistas/compras.php';