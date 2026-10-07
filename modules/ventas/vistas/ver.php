<?php
/**
 * modules/ventas/vistas/ver.php
 * -----------------------------------------------------------------
 * Detalle de una venta.
 *
 * Variables: $venta, $anulada, $sePuedeAnular, $cajaAbierta,
 *            $cajaDeLaVenta
 * -----------------------------------------------------------------
*/

require APP_ROOT . '/includes/header.php';
?>

<?php if ($anulada): ?>
    <div class="alert alert-danger">
        <i class="bi bi-x-octagon-fill"></i>
        <strong>Esta venta está anulada.</strong>
        <?= e((string) $venta['motivo_anulacion']) ?>
        <br>
        <?= e(fecha_hora((string) $venta['anulada_en'])) ?>
        — la plata salió de la caja y el stock volvió al depósito,
        pero el registro queda: se cobró y se devolvió.
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ============ LO QUE SE PIDIO ============ -->
    <div class="col-12 col-lg-7">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-receipt"></i> Lo que pidió</span>
                <span class="small">
                    mesa <?= e((string) $venta['mesa_numero']) ?>
                    · <?= (int) $venta['comensales'] ?> comensales
                </span>
            </div>

            <div class="tabla-envoltura">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($venta['items'] as $item): ?>
                            <tr>
                                <td><?= e((string) $item['producto_nombre']) ?></td>
                                <td class="text-end"><?= e(money($item['precio_unitario'])) ?></td>
                                <td class="text-center"><?= e(cantidad($item['cantidad'])) ?></td>
                                <td class="text-end"><?= e(money($item['importe'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tarjeta-cuerpo">
                <div class="linea-total">
                    <span>Subtotal</span>
                    <strong><?= e(money($venta['total_bruto'])) ?></strong>
                </div>

                <?php if ((float) $venta['descuento_importe'] > 0): ?>
                    <div class="linea-total">
                        <span>
                            Descuento
                            <?php if (!empty($venta['descuento_motivo'])): ?>
                                <br>
                                <small style="color: var(--texto-suave); font-weight: 400;">
                                    <?= e((string) $venta['descuento_motivo']) ?>
                                    <?php if (!empty($venta['descuento_por'])): ?>
                                        — <?= e((string) $venta['descuento_por']) ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </span>
                        <strong class="desglose-resta">
                            <?= e(money(-$venta['descuento_importe'])) ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <div class="linea-total linea-total-final">
                    <span>Total</span>
                    <strong><?= e(money($venta['total_neto'])) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ COMO SE COBRO ============ -->
    <div class="col-12 col-lg-5">

        <div class="tarjeta mb-3">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-credit-card"></i> Cómo se cobró</span>
            </div>

            <div class="tarjeta-cuerpo">
                <?php foreach ($venta['pagos'] as $pago): ?>
                    <?php
                    $esEfectivo = (int) $pago['afecta_caja'] === 1;
                    $vuelto = round((float) $pago['recibido'] - (float) $pago['importe'], 2);
                    ?>
                    <div class="pago-detalle">
                        <i class="bi <?= $esEfectivo ? 'bi-cash-stack' : 'bi-credit-card' ?>"></i>
                        <div class="flex-grow-1">
                            <strong><?= e((string) $pago['metodo_nombre']) ?></strong>
                            <?php if (!$esEfectivo): ?>
                                <br>
                                <small style="color: var(--texto-suave);">
                                    no entra al cajón
                                </small>
                            <?php endif; ?>
                            <?php if ($vuelto > 0): ?>
                                <br>
                                <small style="color: var(--texto-suave);">
                                    recibió <?= e(money($pago['recibido'])) ?>,
                                    vuelto <?= e(money($vuelto)) ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($pago['referencia'])): ?>
                                <br>
                                <small style="color: var(--texto-suave);">
                                    <?= e((string) $pago['referencia']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <strong><?= e(money($pago['importe'])) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tarjeta mb-3">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-info-circle"></i> Datos</span>
            </div>
            <div class="tarjeta-cuerpo">
                <dl class="lista-datos">
                    <dt>Cobrada</dt>
                    <dd><?= e(fecha_hora((string) $venta['cobrada_en'])) ?></dd>

                    <dt>Por</dt>
                    <dd><?= e((string) $venta['cobrada_por']) ?></dd>

                    <dt>Caja</dt>
                    <dd>
                        <?= e((string) ($venta['caja_nombre'] !== '' ? $venta['caja_nombre'] : 'Sin nombre')) ?>
                        <?php if ($cajaDeLaVenta !== null): ?>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= $cajaDeLaVenta['estado'] === 'abierta'
                                    ? 'sigue abierta'
                                    : 'cerrada el ' . e(fecha_hora((string) $cajaDeLaVenta['cerrado_en'])) ?>
                            </small>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- ============ ANULAR ============ -->
        <?php if ($anulada): ?>
            <!-- Nada: ya está anulada y se explica arriba. -->
        <?php elseif (!$sePuedeAnular): ?>
            <div class="tarjeta">
                <div class="tarjeta-cuerpo">
                    <p class="mb-0 small" style="color: var(--texto-suave);">
                        <i class="bi bi-lock-fill"></i>
                        No se puede anular: la caja de esta venta ya está
                        cerrada. Anularla ahora significaría cambiar la
                        plata de un turno que ya fue contado.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('cobrar/anular')) ?>"
                  class="tarjeta"
                  data-confirmar="Se anula la venta: la plata sale de la caja y el stock vuelve al depósito. ¿Seguro?">
                <?= csrf_campo() ?>
                <input type="hidden" name="venta_id" value="<?= (int) $venta['id'] ?>">

                <div class="tarjeta-cabecera">
                    <span style="color: #8A1B1B;">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        Anular esta venta
                    </span>
                </div>

                <div class="tarjeta-cuerpo">
                    <p class="small">
                        Devuelve <strong><?= e(money($venta['total_neto'])) ?></strong>
                        a la caja y devuelve el stock al depósito.
                        El registro queda: se ve que se cobró y se anuló.
                    </p>

                    <input type="text" name="motivo" class="form-control mb-2"
                           maxlength="255" required
                           placeholder="Motivo (obligatorio)"
                           aria-label="Motivo por el que se anula la venta">

                    <button type="submit" class="btn btn-peligro w-100">
                        <i class="bi bi-x-octagon-fill"></i> Anular la venta
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
