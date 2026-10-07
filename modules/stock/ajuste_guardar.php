<?php
/**
 * modules/stock/ajuste_guardar.php
 * -----------------------------------------------------------------
 * Procesa el ajuste manual de stock. Solo administradores.
 *
 * El ajuste se guarda como un movimiento tipo 'ajuste' con la
 * diferencia entre el stock real y el que había. El motivo es
 * obligatorio.
 * -----------------------------------------------------------------
*/

$erroresGenerales = [];

$idProducto = (int) post('producto_id', '0');
$stockReal  = a_numero(post('stock_real'));
$motivo     = post('motivo');

// --- Producto ---------------------------------------------------
$producto = null;

if ($idProducto > 0) {
    $producto = DB::one(
        'SELECT id, nombre, stock_actual, unidad FROM productos
         WHERE id = :id AND controla_stock = 1',
        ['id' => $idProducto]
    );
}

if ($producto === null) {
    flash_error('Elegí un producto que lleve control de stock.');
    redirigir('stock/ajuste');
}

// --- Validación -------------------------------------------------
$errores = [];

if (post('stock_real') === '') {
    $errores['stock_real'] = 'Escribí cuántas unidades hay realmente.';
}

if ($stockReal < 0) {
    $errores['stock_real'] = 'El stock real no puede ser negativo.';
}

if ($motivo === '') {
    $errores['motivo'] = 'Contá el motivo del ajuste. Sin motivo no queda registrado.';
} elseif (mb_strlen($motivo) < 3) {
    $errores['motivo'] = 'El motivo es muy corto para que sirva de explicación.';
}

// Si no cambia nada, avisamos: probablemente fue un error de tipeo.
$stockAnterior = (float) $producto['stock_actual'];
$delta         = $stockReal - $stockAnterior;

if (empty($errores) && abs($delta) < 0.0001) {
    $errores['stock_real'] = 'El stock real es igual al que ya está cargado. No hay nada que ajustar.';
}

// --- Guardar ----------------------------------------------------
if (!empty($errores)) {
    guardar_entradas([
        'producto_id' => $idProducto,
        'stock_real'  => post('stock_real'),
        'motivo'      => $motivo,
        'tipo'        => 'faltante',
    ]);
    guardar_errores($errores, $erroresGenerales);
    flash_error('Revisá los campos marcados en rojo.');

    if (!headers_sent()) {
        header('Location: ' . url('stock/ajuste') . '&producto_id=' . $idProducto);
        exit;
    }

    exit;
}

try {
    $resultado = DB::enTransaccion(function () use ($idProducto, $stockReal, $delta, $motivo, $stockAnterior) {
        // El movimiento va con signo: si hay menos, resta stock.
        $tipoMovimiento = 'ajuste';

        return Stock::aplicar($idProducto, $tipoMovimiento, round($delta, 4), [
            'motivo'          => $motivo,
            'referencia_tipo' => 'producto',
            'referencia_id'   => $idProducto,
            // El ajuste NO toca el costo: el costo cambia con una
            // compra, no con un conteo.
            'actualizar_costo' => false,
        ]);
    });
} catch (Throwable $e) {
    error_log('Error guardando ajuste de stock: ' . $e->getMessage());

    guardar_entradas([
        'producto_id' => $idProducto,
        'stock_real'  => post('stock_real'),
        'motivo'      => $motivo,
    ]);
    guardar_errores([], ['No se pudo guardar el ajuste. Probá de nuevo.']);
    flash_error('No se pudo guardar el ajuste.');

    if (!headers_sent()) {
        header('Location: ' . url('stock/ajuste') . '&producto_id=' . $idProducto);
        exit;
    }

    exit;
}

if (!$resultado['ok']) {
    guardar_errores([], [$resultado['mensaje']]);
    flash_error($resultado['mensaje']);

    if (!headers_sent()) {
        header('Location: ' . url('stock/ajuste') . '&producto_id=' . $idProducto);
        exit;
    }

    exit;
}

// --- Auditoría y mensaje ----------------------------------------
Auditoria::registrar(
    'ajustar_stock',
    'productos',
    $idProducto,
    ['stock_actual' => $stockAnterior],
    ['stock_actual' => $resultado['stock_resultante'], 'motivo' => $motivo]
);

$signo = ($delta > 0) ? '+' : '';
$palabra = ($delta > 0) ? 'sumaron' : 'restaron';

flash_exito(
    'Ajuste guardado: ' . $palabra . ' ' . ltrim($signo . cantidad(abs($delta)))
    . ' ' . plural((string) $producto['unidad'], abs($delta))
    . ' de "' . $producto['nombre'] . '".'
);

if ($resultado['mensaje'] !== '') {
    flash_info($resultado['mensaje']);
}

redirigir('stock/ajuste&producto_id=' . $idProducto);