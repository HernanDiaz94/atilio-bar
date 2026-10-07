<?php
/**
 * modules/dashboard/vistas/caja.php
 * -----------------------------------------------------------------
 * El panel de caja del inicio.
 *
 * Va arriba de todo cuando hay algo que hacer: si la caja está
 * cerrada no se puede cobrar nada, y eso bloquea el bar entero.
 *
 * Variables: $cajaAbierta, $esperadoCaja, $porCobrar, $ventasHoy,
 *            $esAdmin
 * -----------------------------------------------------------------
*/

?>

<!-- ============ CAJA CERRADA: BLOQUEA EL BAR ============ -->
<?php if ($cajaAbierta === null): ?>
    <div class="tarjeta mb-3">
        <div class="tarjeta-cabecera" style="background-color: #8A1B1B; color: #fff;">
            <span>
                <i class="bi bi-exclamation-octagon-fill"></i>
                La caja está cerrada
            </span>
        </div>
        <div class="tarjeta-cuerpo">
            <p class="fw-bold mb-1">No se puede cobrar ninguna mesa</p>
            <p style="color: var(--texto-suave);">
                <?php if ($esAdmin): ?>
                    Abrí un turno contando la plata del cajón.
                    Con esa cantidad el sistema va a ir sumando lo que
                    se cobre, y al cerrar te va a decir cuánto
                    debería haber.
                <?php else: ?>
                    Avisale al administrador. Mientras tanto no se
                    pueden cobrar mesas.
                <?php endif; ?>
            </p>
            <?php if ($esAdmin): ?>
                <a href="<?= e(url('caja/abrir')) ?>" class="btn btn-atilio-oscuro">
                    <i class="bi bi-unlock-fill"></i> Abrir la caja
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>

    <!-- ============ RESUMEN DEL TURNO ============ -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-7">
            <div class="tarjeta-caja h-100">
                <div class="tarjeta-cabecera">
                    <span>
                        <i class="bi bi-safe-fill"></i>
                        <?= e((string) ($cajaAbierta['nombre'] !== ''
                            ? $cajaAbierta['nombre'] : 'Caja abierta')) ?>
                    </span>
                    <span class="small">
                        desde <?= e(hora((string) $cajaAbierta['abierto_en'])) ?>
                    </span>
                </div>

                <div class="tarjeta-cuerpo">
                    <div class="monto-caja">
                        <span>Hay en el cajón ahora</span>
                        <strong><?= e(money($esperadoCaja['esperado'])) ?></strong>
                    </div>

                    <div class="desglose-fila">
                        <span>Cobrado hoy (<?= (int) $ventasHoy['ventas'] ?>
                              <?= ((int) $ventasHoy['ventas'] === 1) ? 'venta' : 'ventas' ?>)</span>
                        <strong><?= e(money($ventasHoy['neto'])) ?></strong>
                    </div>

                    <a href="<?= e(url('caja')) ?>" class="btn btn-atilio-amarillo w-100 mt-3">
                        Ver la caja
                        <?php if ($esAdmin): ?>
                            <i class="bi bi-arrow-right"></i>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- ============ MESAS POR COBRAR ============ -->
        <div class="col-12 col-lg-5">
            <div class="tarjeta h-100">
                <div class="tarjeta-cabecera">
                    <span>
                        <i class="bi bi-hourglass-split"></i>
                        Esperando el cobro
                    </span>
                    <span class="small"><?= (int) count($porCobrar) ?></span>
                </div>

                <?php if (empty($porCobrar)): ?>
                    <div class="tarjeta-cuerpo centrado py-4">
                        <i class="bi bi-check2-circle fs-2 d-block mb-2"></i>
                        <span style="color: var(--texto-suave);">
                            Ninguna mesa está esperando.
                        </span>
                    </div>
                <?php else: ?>
                    <div class="tarjeta-cuerpo">
                        <?php foreach (array_slice($porCobrar, 0, 4) as $pedido): ?>
                            <a href="<?= e(url('cobrar') . '&id=' . (int) $pedido['id']) ?>"
                               class="fila-cobrar fila-cobrar-compacta">
                                <span class="fila-cobrar-mesa">
                                    <?= e((string) $pedido['mesa_numero']) ?>
                                </span>
                                <span class="fila-cobrar-datos">
                                    abierta <?= e(hora((string) $pedido['abierta_en'])) ?>
                                </span>
                                <span class="fila-cobrar-total">
                                    <?= e(money($pedido['total'])) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>

                        <?php if (count($porCobrar) > 4): ?>
                            <a href="<?= e(url('cobrar')) ?>"
                               class="btn btn-atilio-amarillo w-100 mt-2">
                                Ver las <?= (int) count($porCobrar) ?> mesas
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
