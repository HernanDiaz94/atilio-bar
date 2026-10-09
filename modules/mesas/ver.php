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

// --- Es una mesa de pool? ----------------------------------------
//
// Las mesas de pool se cobran por ficha, y la ficha es un producto
// de la carta. Lo que cambia es la pantalla: en vez de que el mozo
// busque "Mesa de Pool" en la grilla de productos, hay un boton
// grande que dice "+ Ficha" y suma una.
//
// Por que importa: la ficha es lo UNICO que se cobra en una mesa de
// pool. Si el mozo tiene que buscarla entre las bebidas para
// encontrarla, hay chances de que se olvide. Y una ficha olvidada es
// plata que no entra.
//
// La ficha se busca por CODIGO, no por nombre: el dueno puede
// renombrarla desde la pantalla de Producto sin que el boton deje de
// encontrarla. Si el producto no esta, el boton no aparece y la mesa
// se comporta como una mesa normal.
$fichaPoolId = DB::valor(
    'SELECT id FROM productos WHERE codigo = :codigo AND activo = 1 LIMIT 1',
    ['codigo' => 'POOL']
);

$fichaPoolId = $fichaPoolId !== null ? (int) $fichaPoolId : 0;

$esPool = in_array((string) ($mesa['zona'] ?? ''), ['pool'], true);

// Cuantas fichas lleva esta mesa: se usa para el numero grande del
// boton, que es lo que el mozo mira para saber si ya cargo alguna.
$fichasCargadas = 0;

if ($esPool && $fichaPoolId > 0) {
    $fichasCargadas = (int) DB::valor(
        'SELECT COALESCE(SUM(cantidad), 0) FROM comanda_items
          WHERE comanda_id = :id AND producto_id = :producto AND estado = :estado',
        ['id' => (int) $comanda['id'], 'producto' => $fichaPoolId, 'estado' => 'pendiente']
    );
}

$fichaPoolPrecio = 0.0;

if ($esPool && $fichaPoolId > 0) {
    $fichaPoolPrecio = (float) DB::valor(
        'SELECT precio_venta FROM productos WHERE id = :id',
        ['id' => $fichaPoolId]
    );
}

// --- Botones de la cabecera de la vista ---
//
// Ya se arma mas abajo ($accionesCabecera), asi que primero se
// calculan los datos que necesita.
if ($esPool && $fichaPoolId > 0) {
    $volverFicha = url('mesas/ver') . '&mesa=' . $mesaId;
}

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