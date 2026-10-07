<?php
/**
 * modules/mesas/ver.php
 * -----------------------------------------------------------------
 * La pantalla de una mesa: ver el pedido y cargarle cosas.
 *
 * Es donde el vendedor pasa la mayor parte del turno, así que
 * los botones son grandes y las acciones principales están arriba,
 * al alcance del pulgar.
 * -----------------------------------------------------------------
*/

$mesaId = get_id('mesa');
$mesa   = Mesas::obtener_simple($mesaId);

if ($mesa === null) {
    flash_error('Esa mesa no existe.');
    redirigir('mesas');
}

// Si no está abierta, el vendedor ve el formulario para abrirla.
if ($mesa['comanda'] === null) {
    $mesasLibres = [0 => null];   // marcador para la vista
    require APP_ROOT . '/modules/mesas/vistas/mesa_libre.php';
    exit;
}

$comanda = Comanda::obtener((int) $mesa['comanda']['id']);

if ($comanda === null) {
    flash_error('No se encontró el pedido de esa mesa.');
    redirigir('mesas');
}

// --- Datos para la vista ----------------------------------------
// Los productos para cargar, agrupados por categoría.
$categorias = DB::all(
    'SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY orden ASC, nombre ASC'
);

$productos = DB::all(
    'SELECT p.id, p.nombre, p.precio_venta, p.controla_stock, p.stock_actual,
            p.stock_minimo, p.unidad, p.formas_venta, p.es_combo,
            c.nombre AS categoria, c.id AS categoria_id
     FROM productos p
     INNER JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1
     ORDER BY c.orden ASC, p.nombre ASC'
);

// Los productos con stock bajo o en cero se marcan para que el
// vendedor no prometa algo que no hay.
$stockAlerta = [];

foreach ($productos as $producto) {
    if ((int) $producto['controla_stock'] === 1
        && (float) $producto['stock_actual'] <= (float) $producto['stock_minimo']) {
        $stockAlerta[(int) $producto['id']] = (float) $producto['stock_actual'] < 0;
    }
}

// Mesas libres, para poder mover el pedido.
$mesasDestino = DB::all(
    "SELECT m.id, m.numero, m.capacidad, m.zona
     FROM mesas m
     WHERE m.activo = 1 AND m.id <> :mesa
     ORDER BY m.zona ASC, m.orden ASC",
    ['mesa' => $mesaId]
);

// Cuántos lleva abiertos este vendedor (para el resumen de arriba).
$misMesas = (int) DB::valor(
    "SELECT COUNT(*) FROM comandas
     WHERE vendedor_id = :usuario AND estado IN ('abierta','por_cobrar')",
    ['usuario' => Auth::id()]
);

$esAdmin = Auth::es_admin();
$estado  = (string) $comanda['estado'];

require APP_ROOT . '/modules/mesas/vistas/ver.php';