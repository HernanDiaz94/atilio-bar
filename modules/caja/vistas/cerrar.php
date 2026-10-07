<?php
/**
 * modules/caja/vistas/cerrar.php
 * -----------------------------------------------------------------
 * El conteo final del turno.
 *
 * El número esperado va arriba, grande, porque es contra ese número
 * que se está comparando. Si no da, el desglose de abajo dice de
 * dónde puede venir la diferencia.
 *
 * Variables: $caja, $esperado, $entradas, $errores, $contadoPropuesto
 * -----------------------------------------------------------------
*/

?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">

        <div class="tarjeta-caja mb-3">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-calculator"></i> Cuánto debería haber</span>
                <span class="small">
                    abierta <?= e(hora((string) $caja['abierto_en'])) ?>
                </span>
            </div>

            <div class="tarjeta-cuerpo">
                <div class="monto-caja">
                    <span>Debería haber</span>
                    <strong><?= e(money($esperado['esperado'])) ?></strong>
                </div>

                <div class="desglose-caja">
                    <div class="desglose-fila">
                        <span>Con lo que había al abrir</span>
                        <strong><?= e(money($esperado['apertura'])) ?></strong>
                    </div>
                    <div class="desglose-fila">
                        <span>Efectivo cobrado (<?= (int) $esperado['ventas'] ?>
                              <?= ((int) $esperado['ventas'] === 1) ? 'venta' : 'ventas' ?>)</span>
                        <strong><?= e(money($esperado['efectivo'])) ?></strong>
                    </div>
                    <?php if ($esperado['ingresos'] > 0): ?>
                        <div class="desglose-fila">
                            <span>Entradas sueltas</span>
                            <strong class="desglose-suma"><?= e(money($esperado['ingresos'])) ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($esperado['retiros'] > 0): ?>
                        <div class="desglose-fila">
                            <span>Retiros del dueño</span>
                            <strong class="desglose-resta"><?= e(money(-$esperado['retiros'])) ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($esperado['gastos'] > 0): ?>
                        <div class="desglose-fila">
                            <span>Gastos del bar</span>
                            <strong class="desglose-resta"><?= e(money(-$esperado['gastos'])) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="post" action="<?= e(url('caja/cerrar')) ?>"
              class="tarjeta" data-validar novalidate>
            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-hand-index-thumb"></i> Contá el cajón</span>
            </div>

            <div class="tarjeta-cuerpo">
                <div class="campo mb-3">
                    <label for="cierre_importe" class="form-label">
                        Cuánta plata hay <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">$</span>
                        <input type="text" id="cierre_importe" name="cierre_importe"
                               class="form-control" inputmode="decimal"
                               value="<?= e($contadoPropuesto) ?>"
                               required autofocus data-etiqueta="plata contada">
                    </div>
                    <span class="form-text">
                        Si da igual que lo de arriba, no hace falta cambiarlo.
                    </span>
                </div>

                <div class="alerta-saldo" id="alertaCierre" hidden></div>

                <div class="campo mb-3">
                    <label for="cierre_observacion" class="form-label">Observación</label>
                    <input type="text" id="cierre_observacion" name="cierre_observacion"
                           class="form-control"
                           value="<?= e((string) ($entradas['cierre_observacion'] ?? '')) ?>"
                           maxlength="255" data-etiqueta="observación"
                           placeholder="Si no cuadra, contá qué pasó">
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= e(url('caja')) ?>" class="btn btn-atilio-amarillo">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-atilio-oscuro"
                            data-confirmar="¿Cerrás el turno? Después no se pueden cargar ventas a esta caja.">
                        <i class="bi bi-lock-fill"></i> Cerrar la caja
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$scriptsExtra = '<script>window.ATILIO_COBRO = ' . json_encode([
    'esperado' => (float) $esperado['esperado'],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
