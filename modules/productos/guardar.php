<?php
/**
 * modules/productos/guardar.php
 * -----------------------------------------------------------------
 * Procesa el alta y la edición de productos. Solo administradores.
 *
 * Este archivo no muestra nada: valida, guarda y redirige.
 *
 * Detalle importante sobre el stock: en un producto YA existente,
 * el stock no se cambia desde el formulario. Cambiarlo por ahí
 * dejaría el movimiento fuera del libro de stock_movimientos, y el
 * historial dejaría de cuadrar. Para mover stock hay dos caminos
 * que sí quedan registrados: cargar una compra o hacer un ajuste
 * con motivo.
 * -----------------------------------------------------------------
*/

$idProducto = (int) post('id', '0');
$esNuevo    = ($idProducto === 0);

$erroresGenerales = [];

// --- Datos del formulario --------------------------------------
$datos = [
    'codigo'         => post('codigo'),
    'categoria_id'   => (int) post('categoria_id', '0'),
    'nombre'         => post('nombre'),
    'descripcion'    => post('descripcion'),
    'precio_venta'   => a_numero(post('precio_venta')),
    'costo'          => post('costo'),
    'stock_minimo'   => a_numero(post('stock_minimo', '0')),
    'unidad'         => post('unidad', 'unidad'),
    'controla_stock' => post('controla_stock') === '1' ? 1 : 0,
    'es_combo'       => post('es_combo') === '1' ? 1 : 0,
    'activo'         => post('activo') === '1' ? 1 : 0,
];

// El costo vacío significa "todavía no lo sé": se guarda NULL,
// no cero. Con cero, el margen daría un número falso.
$costoValor = ($datos['costo'] === '') ? null : a_numero($datos['costo']);

$stockInicial = a_numero(post('stock_actual', '0'));

// --- Producto existente ----------------------------------------
$productoExistente = null;

if (!$esNuevo) {
    $productoExistente = DB::one(
        'SELECT id, nombre, precio_venta, costo, stock_actual, stock_minimo,
                unidad, controla_stock, es_combo, activo, categoria_id, codigo
         FROM productos WHERE id = :id',
        ['id' => $idProducto]
    );

    if ($productoExistente === null) {
        flash_error('Ese producto no existe.');
        redirigir('productos');
    }

    // En edición, el stock del formulario se ignora a propósito.
    // Ver la nota de la cabecera.
    $stockInicial = (float) $productoExistente['stock_actual'];
}

// --- Validación ------------------------------------------------
$reglas = [
    'nombre' => [
        'requerido' => true,
        'min'       => 2,
        'max'       => 120,
        'etiqueta'  => 'nombre',
    ],
    'descripcion' => [
        'max'      => 255,
        'etiqueta' => 'descripción',
    ],
    'codigo' => [
        'max'      => 30,
        'etiqueta' => 'código',
    ],
    'unidad' => [
        'requerido' => true,
        'etiqueta'  => 'unidad',
    ],
];

$errores = validar([
    'nombre'      => $datos['nombre'],
    'descripcion' => $datos['descripcion'],
    'codigo'      => $datos['codigo'],
    'unidad'      => $datos['unidad'],
], $reglas);

// Categoría: tiene que existir y estar activa
if ($datos['categoria_id'] <= 0) {
    $errores['categoria_id'] = 'Elegí una categoría.';
} else {
    $categoriaValida = DB::valor(
        'SELECT id FROM categorias WHERE id = :id AND activo = 1',
        ['id' => $datos['categoria_id']]
    );

    if (!$categoriaValida) {
        $errores['categoria_id'] = 'Esa categoría no existe o está desactivada.';
    }
}

// Nombre duplicado en la misma categoría: es confuso en la carta.
$nombreRepetido = DB::valor(
    'SELECT id FROM productos
     WHERE nombre = :nombre AND categoria_id = :categoria AND id <> :id',
    [
        'nombre'     => $datos['nombre'],
        'categoria'  => $datos['categoria_id'],
        'id'         => $idProducto,
    ]
);

if ($nombreRepetido) {
    $errores['nombre'] = 'Ya hay un producto con ese nombre en la misma categoría.';
}

// Código duplicado (solo si se usó)
if ($datos['codigo'] !== '') {
    $codigoRepetido = DB::valor(
        'SELECT id FROM productos WHERE codigo = :codigo AND id <> :id',
        ['codigo' => $datos['codigo'], 'id' => $idProducto]
    );

    if ($codigoRepetido) {
        $errores['codigo'] = 'Ese código ya lo está usando otro producto.';
    }
}

// Precio
if ($datos['precio_venta'] < 0) {
    $errores['precio_venta'] = 'El precio no puede ser negativo.';
}

// Costo. Si viene vacío se guarda como NULL ("todavía no lo sé").
if ($costoValor !== null && $costoValor < 0) {
    $errores['costo'] = 'El costo no puede ser negativo.';
}

// Stock: solo se valida al crear (en edición viene del producto)
if ($esNuevo && $datos['controla_stock'] === 1) {
    if ($stockInicial < 0) {
        $errores['stock_actual'] = 'El stock inicial no puede ser negativo.';
    }

    if ($datos['stock_minimo'] < 0) {
        $errores['stock_minimo'] = 'El stock mínimo no puede ser negativo.';
    }
}

// --- Si hay errores, volver al formulario -----------------------
if (!empty($errores) || !empty($erroresGenerales)) {
    // Guardamos lo tipeado para repoblar el formulario. La
    // contraseña nunca va, pero acá tampoco hay contraseñas.
    guardar_entradas([
        'codigo'         => $datos['codigo'],
        'categoria_id'   => $datos['categoria_id'],
        'nombre'         => $datos['nombre'],
        'descripcion'    => $datos['descripcion'],
        'precio_venta'   => cantidad($datos['precio_venta'], 2),
        'costo'          => $costoValor === null ? '' : cantidad($costoValor, 2),
        'stock_actual'   => cantidad($stockInicial, 2),
        'stock_minimo'   => cantidad($datos['stock_minimo'], 2),
        'unidad'         => $datos['unidad'],
        'controla_stock' => (string) $datos['controla_stock'],
        'es_combo'       => (string) $datos['es_combo'],
        'activo'         => (string) $datos['activo'],
        'lineas'         => leer_componentes(),
    ]);

    // Los errores van aparte de las entradas: uno es "qué
    // escribió" y el otro "qué está mal". La redirección borra
    // las variables locales, así que viajan por la sesión.
    guardar_errores($errores, $erroresGenerales);

    flash_error('Revisá los campos marcados en rojo.');

    $destino = $esNuevo
        ? url('productos/formulario')
        : url('productos/formulario') . '&id=' . $idProducto;

    if (!headers_sent()) {
        header('Location: ' . $destino);
        exit;
    }

    exit;
}

// --- Guardar ---------------------------------------------------
try {
    $resultado = DB::enTransaccion(function () use (
        $datos, $costoValor, $esNuevo, $idProducto,
        $stockInicial, $productoExistente
    ) {
        $aGuardar = [
            'codigo'         => $datos['codigo'] !== '' ? $datos['codigo'] : null,
            'categoria_id'   => $datos['categoria_id'],
            'nombre'         => $datos['nombre'],
            'descripcion'    => $datos['descripcion'],
            'precio_venta'   => $datos['precio_venta'],
            'costo'          => $costoValor,
            'stock_minimo'   => $datos['controla_stock'] === 1 ? $datos['stock_minimo'] : 0.0,
            'unidad'         => $datos['unidad'],
            'controla_stock' => $datos['controla_stock'],
            'es_combo'       => $datos['es_combo'],
            'activo'         => $datos['activo'],
            'actualizado_en' => ahora(),
        ];

        if ($esNuevo) {
            $aGuardar['stock_actual'] = $datos['controla_stock'] === 1 ? $stockInicial : 0.0;
            $aGuardar['creado_en']     = ahora();

            $nuevoId = DB::insertar('productos', $aGuardar);

            // El stock inicial tiene que quedar asentado en el
            // libro, o el historial no cierra.
            if ($datos['controla_stock'] === 1) {
                Stock::registrar_alta_inicial($nuevoId, $stockInicial);
            }

            // Receta del combo
            $componentes = leer_componentes();
            if ($datos['es_combo'] === 1 && !empty($componentes)) {
                Stock::guardar_receta($nuevoId, $componentes);
            }

            Auditoria::registrar(
                'crear_producto',
                'productos',
                $nuevoId,
                null,
                [
                    'nombre'   => $aGuardar['nombre'],
                    'categoria' => $datos['categoria_id'],
                    'precio'   => $aGuardar['precio_venta'],
                    'stock'    => $aGuardar['stock_actual'],
                    'controla_stock' => $datos['controla_stock'],
                ]
            );

            return ['id' => $nuevoId, 'nuevo' => true];
        }

        // --- Edición ---
        DB::actualizar('productos', $idProducto, $aGuardar);

        // Si cambió el precio de venta, en las etapas siguientes
        // hay que revisar los pedidos abiertos que ya lo tenían
        // cargado. Por ahora solo queda registrado.
        Auditoria::registrar(
            'editar_producto',
            'productos',
            $idProducto,
            [
                'nombre'         => $productoExistente['nombre'],
                'precio_venta'   => (float) $productoExistente['precio_venta'],
                'costo'          => $productoExistente['costo'],
                'stock_minimo'   => (float) $productoExistente['stock_minimo'],
                'activo'         => (int) $productoExistente['activo'],
            ],
            [
                'nombre'         => $aGuardar['nombre'],
                'precio_venta'   => $aGuardar['precio_venta'],
                'costo'          => $aGuardar['costo'],
                'stock_minimo'   => $aGuardar['stock_minimo'],
                'activo'         => $aGuardar['activo'],
            ]
        );

        // Receta: solo si el producto está marcado como combo
        if ($datos['es_combo'] === 1) {
            $componentes = leer_componentes();
            Stock::guardar_receta($idProducto, $componentes);
        } elseif ((int) $productoExistente['es_combo'] === 1) {
            // Dejó de ser combo: se borra la receta vieja.
            Stock::guardar_receta($idProducto, []);
        }

        return ['id' => $idProducto, 'nuevo' => false];
    });
} catch (Throwable $e) {
    error_log('Error guardando producto: ' . $e->getMessage());

    guardar_entradas($_POST);
    guardar_errores([], ['No se pudo guardar el producto. Probá de nuevo.']);
    flash_error('No se pudo guardar el producto.');

    $destino = $esNuevo ? url('productos/formulario') : url('productos/formulario') . '&id=' . $idProducto;

    if (!headers_sent()) {
        header('Location: ' . $destino);
        exit;
    }

    exit;
}

// --- Mensaje y vuelta ------------------------------------------
if ($resultado['nuevo']) {
    flash_exito('Producto "' . $datos['nombre'] . '" creado.');

    if ($datos['controla_stock'] === 1 && $stockInicial > 0) {
        flash_info('Se registraron ' . cantidad($stockInicial) . ' ' . plural($datos['unidad'], $stockInicial) . ' de stock inicial.');
    }
} else {
    flash_exito('Producto "' . $datos['nombre'] . '" actualizado.');
}

redirigir('productos');


/**
 * Lee la receta del combo del POST.
 *
 * Los dos arrays llegan en paralelo: componentes[] con los ids y
 * componentes_cantidad[] con las cantidades. Se arman en un mapa
 * id => cantidad.
 *
 * @return array<int, float>
 */
function leer_componentes(): array
{
    $ids        = $_POST['componentes'] ?? [];
    $cantidades = $_POST['componentes_cantidad'] ?? [];

    if (!is_array($ids) || !is_array($cantidades)) {
        return [];
    }

    $componentes = [];

    foreach ($ids as $indice => $id) {
        $insumoId = (int) $id;

        if ($insumoId <= 0) {
            continue;
        }

        $cantidad = a_numero((string) ($cantidades[$indice] ?? '0'));

        // Si el mismo insumo aparece dos veces, se suman las cantidades.
        $componentes[$insumoId] = ($componentes[$insumoId] ?? 0.0) + $cantidad;
    }

    // Se descartaron los que quedaron en cero o negativo.
    return array_filter($componentes, static fn (float $c) => $c > 0);
}