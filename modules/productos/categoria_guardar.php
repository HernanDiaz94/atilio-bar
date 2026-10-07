<?php
/**
 * modules/productos/categoria_guardar.php
 * -----------------------------------------------------------------
 * Alta de categorías de la carta. Solo administradores.
 * -----------------------------------------------------------------
*/

$erroresGenerales = [];

$datos = [
    'nombre'      => post('nombre'),
    'descripcion' => post('descripcion'),
    'orden'       => (int) post('orden', '0'),
];

// --- Validación ------------------------------------------------
$errores = validar(
    $datos,
    [
        'nombre' => [
            'requerido' => true,
            'min'       => 2,
            'max'       => 60,
            'etiqueta'  => 'nombre',
        ],
        'descripcion' => [
            'max'      => 255,
            'etiqueta' => 'descripción',
        ],
    ]
);

if ($errores === [] && $datos['orden'] < 0) {
    $errores['orden'] = 'El orden no puede ser negativo.';
}

// El nombre no puede repetirse: dos categorías con el mismo nombre
// hacen la carta confusa y rompen los filtros.
// Solo se busca si el nombre ya pasó la validación de largo.
if (!isset($errores['nombre'])) {
    $repetida = DB::valor(
        'SELECT id FROM categorias WHERE nombre = :nombre',
        ['nombre' => $datos['nombre']]
    );

    if ($repetida) {
        $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
    }
}

// --- Guardar ---------------------------------------------------
if (!empty($errores)) {
    guardar_entradas($datos);
    guardar_errores($errores, $erroresGenerales);
    flash_error('Revisá los campos marcados en rojo.');

    if (!headers_sent()) {
        header('Location: ' . url('categorias'));
        exit;
    }

    exit;
}

try {
    $nuevoId = DB::insertar('categorias', [
        'nombre'      => $datos['nombre'],
        'descripcion' => $datos['descripcion'],
        'orden'       => $datos['orden'],
        'activo'      => 1,
        'creado_en'   => ahora(),
    ]);

    Auditoria::registrar(
        'crear_categoria',
        'categorias',
        $nuevoId,
        null,
        ['nombre' => $datos['nombre'], 'orden' => $datos['orden']]
    );

    flash_exito('Categoría "' . $datos['nombre'] . '" creada.');
} catch (Throwable $e) {
    error_log('Error creando categoría: ' . $e->getMessage());

    guardar_entradas($datos);
    guardar_errores([], ['No se pudo crear la categoría. Probá de nuevo.']);
    flash_error('No se pudo crear la categoría.');
}

redirigir('categorias');