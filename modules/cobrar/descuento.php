<?php
/**
 * modules/cobrar/descuento.php
 * -----------------------------------------------------------------
 * Guarda el descuento de un pedido mientras se está cobrando.
 *
 * El descuento NO se guarda en la base en este paso, y a propósito:
 * en este momento el pedido todavia puede no cobrarse nunca (el
 * cliente se puede arrepenti, la mesa se puede anular). Si se
 * escribiera en la comanda, quedaría un descuento flotando en un
 * pedido que quizás nunca se cobre.
 *
 * Se guarda en la sesión y recién al confirmar el cobro se copia a
 * la venta, que es el lugar donde un descuento tiene sentido: en
 * el hecho de "esto se cobró por tal cantidad".
 *
 * El control real de quien puede descontar esta en
 * Venta::cobrar(). Acá solo se evita que el vendedor ni siquiera
 * llegue a la pantalla.
 * -----------------------------------------------------------------
*/

$comandaId = (int) post('comanda_id', '0');
$descuento = a_numero(post('descuento', '0'));
$motivo    = post('descuento_motivo');
$volver    = post('volver');

$comanda = DB::one('SELECT * FROM comandas WHERE id = :id', ['id' => $comandaId]);

if ($comanda === null) {
    flash_error('Ese pedido no existe.');
    redirigir('mesas');
}

// La pantalla ya no se muestra al vendedor, pero un POST directo
// tiene que frenarse acá también: el control de rol no puede
// depender de que el botón esté oculto.
if (!Auth::es_admin() && !config_bool('venta_permitir_descuento_vendedor')) {
    flash_error('Los descuentos los aplica el administrador.');
    redirigir('cobrar&id=' . $comandaId);
}

if ($comanda['estado'] !== 'por_cobrar') {
    flash_error('Este pedido no se puede modificar: ya pidió la cuenta y está cerrado.');
    redirigir('mesas/ver&mesa=' . (int) $comanda['mesa_id']);
}

if ($descuento < 0) {
    flash_error('El descuento no puede ser negativo.');
    redirigir('cobrar&id=' . $comandaId);
}

$bruto = (float) DB::valor(
    "SELECT COALESCE(SUM(importe), 0) FROM comanda_items
     WHERE comanda_id = :comanda AND estado = 'pendiente'",
    ['comanda' => $comandaId]
);

if ($descuento > $bruto) {
    flash_error('El descuento no puede ser más grande que el total de ' . money($bruto) . '.');
    redirigir('cobrar&id=' . $comandaId);
}

if ($descuento > 0 && mb_strlen(trim($motivo)) < 3) {
    flash_error('Contá por qué se hace el descuento.');
    redirigir('cobrar&id=' . $comandaId);
}

// El descuento queda en su propia clave de sesion, indexada por
// comanda: el old_input se consume en cada formulario y el
// descuento se perdia al volver a la pantalla de cobro.
descuento_aplicar($comandaId, $descuento, $motivo);

flash_exito($descuento > 0
    ? 'Descuento de ' . money($descuento) . ' aplicado.'
    : 'Descuento quitado.');

redirigir('cobrar&id=' . $comandaId);
