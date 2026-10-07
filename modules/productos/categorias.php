<?php
/**
 * modules/productos/categorias.php
 * -----------------------------------------------------------------
 * Gestión de categorías de la carta. Solo administradores.
 * -----------------------------------------------------------------
*/

// Errores del POST anterior, si categoria_guardar.php lo rebotó.
// Sin esto, un rechazo se guarda en la sesión y nunca se muestra:
// el usuario ve la pantalla como si nada, y no entiende por qué no
// se creó la categoría.
$erroresGuardados = tomar_errores();
$entradas         = recuperar_entradas();

$categorias = DB::all(
    "SELECT c.id, c.nombre, c.descripcion, c.orden, c.activo,
            (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS productos_total,
            (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS productos_activos,
            (SELECT COALESCE(SUM(p.stock_actual * p.costo), 0) FROM productos p
              WHERE p.categoria_id = c.id AND p.activo = 1 AND p.controla_stock = 1) AS valor_stock
     FROM categorias c
     ORDER BY c.orden ASC, c.nombre ASC"
);

$totalCategorias = count($categorias);

// Valores para repoblar el formulario de alta si quedó a medias.
$valores = [
    'nombre'      => $entradas['nombre'] ?? '',
    'descripcion' => $entradas['descripcion'] ?? '',
    'orden'       => $entradas['orden'] ?? ($totalCategorias + 1),
];

require APP_ROOT . '/modules/productos/vistas/categorias.php';