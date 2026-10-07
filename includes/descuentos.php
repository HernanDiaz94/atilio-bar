<?php
/**
 * includes/descuentos.php
 * -----------------------------------------------------------------
 * El descuento pendiente de una mesa, mientras se está cobrando.
 *
 * POR QUÉ ESTÁ EN LA SESIÓN Y NO EN LA BASE
 *
 * Un descuento se aplica al cobrar, no al cargar el pedido. Si se
 * escribiera en la comanda en este momento, quedaría un descuento
 * flotando en un pedido que quizás nunca se cobre (el cliente se
 * arrepiente, la mesa se levanta, el mozo se equivoca de mesa).
 *
 * En la base recién queda cuando hay una venta, que es el lugar
 * donde "esto se cobró por tal cantidad" es un hecho.
 *
 * POR QUÉ NO EN EL old_input
 *
 * old_input se consume cada vez que se dibuja un formulario. El
 * flujo es aplicar el descuento, volver a la pantalla de cobro
 * (que la consume) y recién ahí confirmar. Con old_input el
 * descuento se perdía siempre. Por eso tiene su propia clave,
 * indexada por comanda: si el mozo tiene dos mesas abiertas, cada
 * una lleva su descuento.
 *
 * Se limpia al cobrar, al anular la mesa y al reabrir el pedido.
 * -----------------------------------------------------------------
*/

/**
 * Guarda (o cambia) el descuento pendiente de una comanda.
 */
function descuento_aplicar(int $comandaId, float $importe, string $motivo = ''): void
{
    if ($importe <= 0) {
        descuento_olvidar($comandaId);
        return;
    }

    $_SESSION['atilio_descuentos'][$comandaId] = [
        'importe' => cantidad($importe, 2),
        'motivo'  => mb_substr($motivo, 0, 255),
    ];
}

/**
 * El descuento pendiente de una comanda, o null si no tiene.
 *
 * @return array{importe: string, motivo: string}|null
 */
function descuento_aplicado(int $comandaId): ?array
{
    $guardado = $_SESSION['atilio_descuentos'][$comandaId] ?? null;

    return is_array($guardado) ? $guardado : null;
}

/**
 * Olvida el descuento pendiente de una comanda.
 */
function descuento_olvidar(int $comandaId): void
{
    unset($_SESSION['atilio_descuentos'][$comandaId]);
}

/**
 * Vacía todos los descuentos pendientes de la sesión.
 *
 * Se usa al cerrar sesión: si el mozo se va a mitad de un cobro y
 * vuelve al día siguiente, no tiene que encontrarse un descuento
 * de ayer.
 */
function descuentos_vaciar(): void
{
    $_SESSION['atilio_descuentos'] = [];
}
