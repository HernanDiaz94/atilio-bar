<?php
/**
 * modules/stock/compra_guardar.php
 * -----------------------------------------------------------------
 * Procesa la carga de una compra. Solo administradores.
 *
 * Todo lo que hace está en Stock::guardar_compra(): cabecera,
 * detalle, movimientos de stock y actualización de costos, en una
 * sola transacción. Si algo falla a mitad de camino, no queda una
 * compra con stock a medias.
 * -----------------------------------------------------------------
*/

$erroresGenerales = [];

$datosCabecera = [
    'proveedor'          => post('proveedor'),
    'numero_comprobante' => post('numero_comprobante'),
    'fecha'              => post('fecha'),
    'observaciones'      => post('observaciones'),
];

// --- Validación de la cabecera -----------------------------------
$errores = validar(
    $datosCabecera,
    [
        'proveedor' => [
            'max'      => 120,
            'etiqueta' => 'proveedor',
        ],
        'numero_comprobante' => [
            'max'      => 60,
            'etiqueta' => 'número de comprobante',
        ],
        'observaciones' => [
            'max'      => 255,
            'etiqueta' => 'observaciones',
        ],
        'fecha' => [
            'requerido' => true,
            'etiqueta'  => 'fecha',
        ],
    ]
);

// La fecha tiene que tener el formato Y-m-d y no estar en el futuro.
if ($errores['fecha'] ?? null) {
    // Ok
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datosCabecera['fecha'])) {
    $errores['fecha'] = 'La fecha no tiene el formato correcto.';
} elseif ($datosCabecera['fecha'] > date('Y-m-d')) {
    $errores['fecha'] = 'La fecha no puede ser futura.';
}

// --- Lectura de las líneas ---------------------------------------
// Llegan como lineas[0][producto_id], lineas[0][cantidad], etc.
$lineas = [];

if (is_array($_POST['lineas'] ?? null)) {
    foreach ($_POST['lineas'] as $indice => $linea) {
        if (!is_array($linea)) {
            continue;
        }

        $lineas[] = [
            'producto_id' => (int) ($linea['producto_id'] ?? 0),
            // El costo no viene por el número que escribió el
            // navegador, sino por el que se reserializa en el
            // hidden. Igual se relee y se valida en el servidor.
            'cantidad'    => a_numero((string) ($linea['cantidad'] ?? '0')),
            'costo'       => a_numero((string) ($linea['costo'] ?? '0')),
        ];
    }
}

// Quitar las líneas totalmente vacías (el agregar y sacar filas
// puede dejar huecos).
$lineas = array_values(array_filter($lineas, static fn (array $l) => $l['producto_id'] > 0));

if (empty($lineas)) {
    $erroresGenerales[] = 'Agregá al menos un producto a la compra.';
}

// --- Guardar ----------------------------------------------------
if (!empty($errores) || !empty($erroresGenerales)) {
    // Se guardan las líneas para que el usuario no las pierda.
    guardar_entradas([
        'proveedor'          => $datosCabecera['proveedor'],
        'numero_comprobante' => $datosCabecera['numero_comprobante'],
        'fecha'              => $datosCabecera['fecha'],
        'observaciones'      => $datosCabecera['observaciones'],
        'lineas'             => $lineas,
    ]);
    guardar_errores($errores, $erroresGenerales);
    flash_error('Revisá la compra antes de guardarla.');

    if (!headers_sent()) {
        header('Location: ' . url('compras/nueva'));
        exit;
    }

    exit;
}

$resultado = Stock::guardar_compra($datosCabecera, $lineas);

if (!$resultado['ok']) {
    guardar_entradas([
        'proveedor'          => $datosCabecera['proveedor'],
        'numero_comprobante' => $datosCabecera['numero_comprobante'],
        'fecha'              => $datosCabecera['fecha'],
        'observaciones'      => $datosCabecera['observaciones'],
        'lineas'             => $lineas,
    ]);

    $mensajes = !empty($resultado['errores'])
        ? $resultado['errores']
        : [$resultado['mensaje']];

    guardar_errores([], $mensajes);
    flash_error('No se pudo guardar la compra.');

    if (!headers_sent()) {
        header('Location: ' . url('compras/nueva'));
        exit;
    }

    exit;
}

// --- Mensaje ----------------------------------------------------
$cantidadItems = count($lineas);

flash_exito(
    'Compra #' . $resultado['compra_id'] . ' guardada: '
    . $cantidadItems . ' producto(s), '
    . money(DB::valor('SELECT total FROM compras WHERE id = :id', ['id' => $resultado['compra_id']]))
    . '. El stock y los costos quedaron actualizados.'
);

// Aviso si algún producto quedó por debajo del mínimo.
$bajos = Stock::stock_bajo(3);

if (!empty($bajos)) {
    $nombres = array_map(static fn (array $p) => (string) $p['nombre'], $bajos);
    flash_info('Quedaron con stock bajo: ' . implode(', ', $nombres) . '.');
}

redirigir('compras/ver&id=' . $resultado['compra_id']);