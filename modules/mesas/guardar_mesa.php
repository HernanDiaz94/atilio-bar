<?php
/**
 * modules/mesas/guardar_mesa.php
 * -----------------------------------------------------------------
 * Alta de mesas. Solo administradores.
 * -----------------------------------------------------------------
*/

$erroresGenerales = [];

$datos = [
    'numero'    => post('numero'),
    'nombre'    => post('nombre'),
    'capacidad' => (int) post('capacidad', '4'),
    'zona'      => post('zona', 'salon'),
];

// --- Validación ---------------------------------------------------
$errores = validar(
    $datos,
    [
        'numero' => [
            'requerido' => true,
            'min'       => 1,
            'max'       => 10,
            'etiqueta'  => 'número de mesa',
        ],
        'nombre' => [
            'max'      => 60,
            'etiqueta' => 'nombre',
        ],
    ]
);

if ($errores['numero'] ?? null) {
    // OK
} else {
    // El número tiene que ser único: dos mesas con el mismo número
    // en la grilla son indistinguibles.
    $repetido = DB::valor(
        'SELECT id FROM mesas WHERE numero = :numero',
        ['numero' => $datos['numero']]
    );

    if ($repetido) {
        $errores['numero'] = 'Ya existe una mesa con ese número.';
    }
}

if ($datos['capacidad'] < 1) {
    $errores['capacidad'] = 'La mesa tiene que banca al menos a una persona.';
}

if ($datos['capacidad'] > 50) {
    $errores['capacidad'] = 'Eso es demasiado para una mesa.';
}

if (!array_key_exists($datos['zona'], Mesas::ZONAS)) {
    $errores['zona'] = 'Elegí una zona válida.';
}

// --- Guardar -----------------------------------------------------
if (!empty($errores) || !empty($erroresGenerales)) {
    guardar_entradas($datos);
    guardar_errores($errores, $erroresGenerales);
    flash_error('Revisá los campos marcados en rojo.');

    if (!headers_sent()) {
        header('Location: ' . url('mesas/administrar'));
        exit;
    }

    exit;
}

// El orden es correlativo dentro de la zona: la nueva mesa queda
// al final de las de su zona.
$maximo = (int) DB::valor(
    'SELECT COALESCE(MAX(orden), 0) FROM mesas WHERE zona = :zona',
    ['zona' => $datos['zona']]
);

try {
    $id = DB::insertar('mesas', [
        'numero'    => $datos['numero'],
        'nombre'    => $datos['nombre'],
        'capacidad' => $datos['capacidad'],
        'zona'      => $datos['zona'],
        'orden'     => $maximo + 1,
        'activo'    => 1,
        'creado_en' => ahora(),
    ]);

    Auditoria::registrar('crear_mesa', 'mesas', $id, null, [
        'numero'    => $datos['numero'],
        'zona'      => $datos['zona'],
        'capacidad' => $datos['capacidad'],
    ]);

    flash_exito('Mesa ' . $datos['numero'] . ' creada.');
} catch (Throwable $e) {
    error_log('Error creando mesa: ' . $e->getMessage());

    guardar_entradas($datos);
    guardar_errores([], ['No se pudo crear la mesa. Probá de nuevo.']);
    flash_error('No se pudo crear la mesa.');
}

redirigir('mesas/administrar');