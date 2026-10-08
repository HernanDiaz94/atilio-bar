<?php
/**
 * modules/cobrar/index.php
 * -----------------------------------------------------------------
 * La pantalla de cobro de una mesa.
 *
 * Es la pantalla que el vendedor usa todo el día, así que está
 * pensada para el celular: el importe grande, los medios de pago
 * en botones, y el total a cobrar siempre a la vista.
 *
 * Variables que arma esta vista: $comanda, $metodos, $bruto,
 * $descuento, $total, $cajaAbierta, $esAdmin, $permiteDescuento
 * -----------------------------------------------------------------
*/

$comandaId = (int) get('id', get('comanda_id', '0'));

if ($comandaId <= 0) {
    // Sin comanda: se muestran las mesas que esperan cobro, para
    // no obligar a volver a la grilla.
    $pendientes = Venta::pendientesDeCobro();

    // Esta vista usa $esAdmin para decidir si esconde el boton de
    // la caja. Antes no se armaba en este camino: se definiaba mas
    // abajo, despues del return, asi que la vista recibia la
    // variable sin definir y PHP lareated vacía en silencio.
    $esAdmin = Auth::es_admin();

    $titulo    = 'Cobrar';
    $subtitulo = 'Mesas que pidieron la cuenta';
    $accionesCabecera = '<a href="' . e(url('mesas')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-grid-3x3-gap-fill"></i> Mesas</a>';

    require APP_ROOT . '/includes/header.php';
    require APP_ROOT . '/modules/cobrar/vistas/pendientes.php';
    exit;
}

$comanda = DB::one(
    'SELECT c.*, m.numero AS mesa_numero
     FROM comandas c
     LEFT JOIN mesas m ON m.id = c.mesa_id
     WHERE c.id = :id',
    ['id' => $comandaId]
);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

$volver = url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id'];

// Si el pedido no está listo para cobrar, se avisa y se vuelve.
if ($comanda['estado'] !== 'por_cobrar') {
    if ($comanda['estado'] === 'cerrada') {
        flash_info('Esta mesa ya fue cobrada.');

        $ventaId = (int) ($comanda['venta_id'] ?? 0);

        if ($ventaId > 0 && Auth::es_admin()) {
            redirigir('ventas/ver&id=' . $ventaId);
        }

        redirigir('mesas');
    }

    flash_error('Este pedido todavía no pidió la cuenta.');
    redirigir('mesas/ver&mesa=' . (int) $comanda['mesa_id']);
}

// --- Caja abierta ----------------------------------------------
$errorCaja = null;
$cajaAbierta = Caja::abierta();

if ($cajaAbierta === null) {
    $errorCaja = 'No hay ninguna caja abierta. Sin caja no se puede cobrar: '
        . 'la plata que entra tiene que saber a qué turno pertenece.';
}

// --- Datos ------------------------------------------------------
$items = DB::all(
    "SELECT * FROM comanda_items
     WHERE comanda_id = :comanda AND estado = 'pendiente'
     ORDER BY id ASC",
    ['comanda' => $comandaId]
);

$bruto = 0.0;

foreach ($items as $item) {
    $bruto += (float) $item['importe'];
}

$bruto = round($bruto, 2);

// El descuento lo aplica el administrador, salvo que se haya
// abierto el interruptor en la configuración.
$permiteDescuento = Auth::es_admin()
    || config_bool('venta_permitir_descuento_vendedor');

// --- Errores y datos del intento anterior -------------------------
$erroresGuardados = tomar_errores();
$entradas         = recuperar_entradas();
$errores          = $erroresGuardados['campos'];

// El descuento vive en su propia clave de la sesion, indexada por
// comanda: si este mismo mozo tiene dos mesas a medio cobrar, cada
// una lleva el suyo.
$aplicado = descuento_aplicado($comandaId);

$descuento = a_numero((string) ($aplicado['importe'] ?? '0'));
$motivoDescuento = (string) ($aplicado['motivo'] ?? '');

if ($descuento < 0) {
    $descuento = 0.0;
}

if ($descuento > $bruto) {
    $descuento = $bruto;
}

$total = round($bruto - $descuento, 2);

// --- Medios de pago ---------------------------------------------
$metodos = MetodoPago::activos();

if (empty($metodos)) {
    $errorCaja = $errorCaja ?? 'No hay medios de pago cargados. Revisá la base.';
}

// --- Lo que el vendedor ya había cargado -----------------------
// Los pagos se recargan de la entrada guardada para que, si se
// equivoca al confirmar, no tenga que escribirlos de nuevo.
//
// OJO: lo que se recarga es $entradas['pagos'], NO $entrados.
// La variable $entrados no existe en este archivo: se usaba una
// que nunca se definió, y PHP la creaba vacía sin avisar, así
// que los pagos del intento anterior NUNCA se recargaban. Lo que
// se veia era el boton de cobrar con los campos en blanco, sin
// error en pantalla y con un aviso en el log del servidor.
$pagosPrevios = [];

if (!empty($entradas['pagos']) && is_array($entradas['pagos'])) {
    $pagosPrevios = $entradas['pagos'];
}

$esAdmin = Auth::es_admin();

require APP_ROOT . '/modules/cobrar/vistas/index.php';
