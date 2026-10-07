<?php
/**
 * modules/ventas/ver.php
 * -----------------------------------------------------------------
 * Detalle de una venta. Solo el administrador.
 *
 * Muestra los tres hechos por separado, que es como hay que
 * leerlos cuando algo no cierra:
 *
 *   - QUÉ se pidió (los ítems del pedido)
 *   - CUÁNTO se cobró y cómo (los medios de pago)
 *   - EN QUÉ CAJA entró (para poder anularla si algo salió mal)
 * -----------------------------------------------------------------
*/

$ventaId = get_id('id');

$venta = Venta::obtener($ventaId);

if ($venta === null) {
    flash_error('Esa venta no existe.');
    redirigir('ventas');
}

$anulada = $venta['estado'] === 'anulada';

// Solo se puede anular con la caja abierta: si el turno ya se
// cerró, la plata que había que sacar ya se contó.
$cajaAbierta = Caja::abierta();
$cajaDeLaVenta = DB::one(
    'SELECT * FROM cajas WHERE id = :id',
    ['id' => (int) $venta['caja_id']]
);

$sePuedeAnular = !$anulada
    && $cajaAbierta !== null
    && $cajaDeLaVenta !== null
    && $cajaDeLaVenta['estado'] === 'abierta';

$titulo    = 'Venta ' . (string) $venta['numero'];
$subtitulo = $anulada
    ? 'Anulada: ' . (string) $venta['motivo_anulacion']
    : fecha_hora((string) $venta['cobrada_en']) . ' · ' . (string) $venta['cobrada_por'];

$accionesCabecera = '<a href="' . e(url('ventas')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a ventas</a>';

require APP_ROOT . '/modules/ventas/vistas/ver.php';
