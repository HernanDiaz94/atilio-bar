<?php
/**
 * modules/caja/vistas/abrir.php
 * -----------------------------------------------------------------
 * Abrir el turno, o registrar una entrada o salida de plata.
 *
 * Variables: $errores, $entradas, $caja, $soloMovimiento, $volver
 * -----------------------------------------------------------------
*/

$accionesCabecera = '<a href="' . e($volver) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a la caja</a>';

require APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">

        <?php if ($soloMovimiento): ?>
            <!-- ============ MOVIMIENTO ============ -->
            <form method="post" action="<?= e(url('caja/movimiento')) ?>"
                  class="tarjeta" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="movimiento">

                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-arrow-left-right"></i> Entrada o salida de plata</span>
                </div>

                <div class="tarjeta-cuerpo">
                    <p class="small mb-3">
                        Anotá cuando el dueño se lleva plata, o cuando se
                        paga algo del cajón (gas, hielo, delivery). Queda
                        asentado con tu nombre.
                    </p>

                    <div class="tipos-movimiento">
                        <?php foreach (Caja::MOVIMIENTOS as $clave => $etiqueta): ?>
                            <label class="tipo-movimiento">
                                <input type="radio"
                                       name="tipo"
                                       value="<?= e($clave) ?>"
                                       <?= ((string) ($entradas['tipo'] ?? '') === $clave) ? 'checked' : '' ?>
                                       required>
                                <i class="bi <?= $clave === 'ingreso' ? 'bi-plus-circle' : 'bi-dash-circle' ?>"></i>
                                <?= e($etiqueta) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="campo mb-3">
                        <label for="importe" class="form-label">
                            Importe <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" id="importe" name="importe" class="form-control"
                                   value="<?= e((string) ($entradas['importe'] ?? '')) ?>"
                                   inputmode="decimal" required autofocus data-etiqueta="importe">
                        </div>
                    </div>

                    <div class="campo mb-3">
                        <label for="motivo" class="form-label">
                            Motivo <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="motivo" name="motivo" class="form-control"
                               value="<?= e((string) ($entradas['motivo'] ?? '')) ?>"
                               maxlength="255" required data-etiqueta="motivo"
                               placeholder="Ej: se llevó el dueño $20.000">
                    </div>

                    <button type="submit" class="btn btn-atilio-oscuro w-100">
                        <i class="bi bi-check-lg"></i> Guardar el movimiento
                    </button>
                </div>
            </form>
        <?php else: ?>
            <!-- ============ ABRIR CAJA ============ -->
            <form method="post" action="<?= e(url('caja/abrir')) ?>"
                  class="tarjeta" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="abrir">

                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-unlock-fill"></i> Abrir la caja</span>
                </div>

                <div class="tarjeta-cuerpo">
                    <div class="marco-amarillo p-3 mb-3">
                        <strong>Contá la plata antes de abrir.</strong>
                        <p class="mb-0 mt-1">
                            Con esa cantidad el sistema va a ir sumando lo
                            que se cobre. Cuando cierres el turno te va a
                            decir cuánto debería haber: si no coincide,
                            vas a saber que falta plata.
                        </p>
                    </div>

                    <div class="campo mb-3 <?= isset($errores['apertura_importe']) ? 'campo-error' : '' ?>">
                        <label for="apertura_importe" class="form-label">
                            Cuánta plata hay en el cajón <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" id="apertura_importe" name="apertura_importe"
                                   class="form-control" inputmode="decimal"
                                   value="<?= e((string) ($entradas['apertura_importe'] ?? '0')) ?>"
                                   required autofocus data-etiqueta="plata del cajón">
                        </div>
                        <span class="form-text">Billetes y monedas, todo junto.</span>
                        <?php if (isset($errores['apertura_importe'])): ?>
                            <span class="error-campo"><?= e((string) $errores['apertura_importe']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="campo mb-3">
                        <label for="nombre" class="form-label">Nombre del turno</label>
                        <input type="text" id="nombre" name="nombre" class="form-control"
                               value="<?= e((string) ($entradas['nombre'] ?? '')) ?>"
                               maxlength="80" data-etiqueta="nombre"
                               placeholder="Opcional. Ej: turno de la noche">
                    </div>

                    <div class="campo mb-3">
                        <label for="apertura_observacion" class="form-label">Observación</label>
                        <input type="text" id="apertura_observacion" name="apertura_observacion"
                               class="form-control"
                               value="<?= e((string) ($entradas['apertura_observacion'] ?? '')) ?>"
                               maxlength="255" data-etiqueta="observación"
                               placeholder="Opcional">
                    </div>

                    <button type="submit" class="btn btn-atilio-oscuro btn-grande w-100">
                        <i class="bi bi-unlock-fill"></i> Abrir la caja
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
