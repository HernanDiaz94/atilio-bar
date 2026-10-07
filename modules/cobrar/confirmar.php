<?php
/**
 * modules/cobrar/confirmar.php
 * -----------------------------------------------------------------
 * Confirma el cobro de una mesa.
 *
 * Acepta los dos caminos de la pantalla:
 *
 *   - uno solo, que es el normal: el medio principal y lo que
 *     recibió el cliente;
 *   - partido, con varias líneas en pagos[0], pagos[1], etc.
 *
 * Todo lo que llega del navegador se vuelve a validar contra el
 * pedido y el precio real. El navegador puede mandar cualquier
 * número: la autoridad la tiene la base, y Venta::cobrar() la
 * vuelve a comprobar.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');

if ($comandaId <= 0) {
    flash_error('No se indicó qué cobro confirmar.');
    redirigir('cobrar');
}

$comanda = DB::one(
    'SELECT c.*, m.numero AS mesa_numero FROM comandas c
     LEFT JOIN mesas m ON m.id = c.mesa_id
     WHERE c.id = :id',
    ['id' => $comandaId]
);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$volver = url('cobrar') . '&id=' . $comandaId;

// --- El total real del pedido ------------------------------------
// Sale de la base, no de lo que dice el formulario. Es el número
// contra el que después se validan los pagos.
$bruto = (float) DB::valor(
    "SELECT COALESCE(SUM(importe), 0) FROM comanda_items
     WHERE comanda_id = :comanda AND estado = 'pendiente'",
    ['comanda' => $comandaId]
);

// --- El descuento ------------------------------------------------
// Puede venir en el POST o haber quedado guardado en la sesión
// cuando el administrador lo aplicó con "Aplicar". Nunca se suman:
// si viene en el POST, el POST manda.
$entradas = recuperar_entradas();

$descuentoPost = post('descuento', '');
$aplicado = descuento_aplicado($comandaId);
$descuento = a_numero($descuentoPost === '' ? (string) ($aplicado['importe'] ?? '0') : $descuentoPost);

if ($descuento < 0) {
    $descuento = 0.0;
}

if ($descuento > $bruto) {
    $descuento = $bruto;
}

$motivoDescuento = post('descuento_motivo');

if ($motivoDescuento === '' && isset($aplicado['motivo'])) {
    $motivoDescuento = (string) $aplicado['motivo'];
}

$neto = round($bruto - $descuento, 2);

// --- Armar la lista de pagos -------------------------------------
// Camino partido: el formulario mandó varias líneas.
$pagos = post_array('pagos');
$pagosLimpios = [];

if (is_array($pagos)) {
    foreach ($pagos as $fila) {
        if (!is_array($fila)) {
            continue;
        }

        $importe = a_numero((string) ($fila['importe'] ?? '0'));

        if ($importe <= 0) {
            continue;
        }

        $pagosLimpios[] = [
            'metodo_id'  => (int) ($fila['metodo_id'] ?? 0),
            'importe'    => $importe,
            'referencia' => mb_substr((string) ($fila['referencia'] ?? ''), 0, 60),
            'recibido'   => a_numero((string) ($fila['recibido'] ?? $fila['importe'] ?? '0')),
        ];
    }
}

// Camino normal: un solo medio y lo que recibió el cliente.
if (empty($pagosLimpios)) {
    $medioPrincipal = (int) post('medio_principal', '0');

    if ($medioPrincipal <= 0) {
        flash_error('Elegí con qué se paga.');
        redirigir($volver);
    }

    $metodo = MetodoPago::porId($medioPrincipal);

    if ($metodo === null || (int) $metodo['activo'] !== 1) {
        flash_error('Ese medio de pago no está disponible.');
        redirigir($volver);
    }

    $recibido = a_numero(post('recibido', (string) $neto));

    // Si no dice cuánto recibió, se asume que entregó el exacto:
    // un campo vacío no debería frenar un cobro.
    if ($recibido <= 0) {
        $recibido = $neto;
    }

    $pagosLimpios[] = [
        'metodo_id'  => $medioPrincipal,
        'importe'    => $neto,
        'referencia' => '',
        'recibido'   => $recibido,
    ];
}

// --- Cobrar ------------------------------------------------------
$resultado = Venta::cobrar($comandaId, $pagosLimpios, $descuento, $motivoDescuento);

if (!$resultado['ok']) {
    // Se guarda lo que se había cargado para no hacerlo de nuevo
    // en el próximo intento.
    guardar_entradas([
        'descuento'       => cantidad($descuento, 2),
        'descuento_motivo' => $motivoDescuento,
        'pagos'           => $pagosLimpios,
    ]);

    flash_error($resultado['mensaje']);
    redirigir($volver);
}

// --- Salida ------------------------------------------------------
$ventaId = (int) $resultado['venta_id'];
$vuelto  = (float) $resultado['vuelto'];

// Se limpia lo que había en la sesión: el descuento y los pagos ya
// quedaron asentados en la venta.
$_SESSION['old_input'] = [];
descuento_olvidar($comandaId);

$venta = Venta::obtener($ventaId);

flash_exito(
    'Venta ' . (string) ($venta['numero'] ?? '') . ' registrada: '
    . money((float) ($venta['total_neto'] ?? $neto)) . '.'
);

if ($vuelto > 0) {
    flash_info('Vuelto: ' . money($vuelto) . '.');
}

// El vendedor vuelve a la grilla: ya tiene otra mesa que atender.
redirigir('mesas');
