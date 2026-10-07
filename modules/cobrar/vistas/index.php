<?php
/**
 * modules/cobrar/vistas/index.php
 * -----------------------------------------------------------------
 * La pantalla de cobro de una mesa.
 *
 * Pensada para el celular y para ir rápido: el importe grande, los
 * medios de pago en botones, y el total siempre a la vista.
 *
 * Variables: $comanda, $items, $bruto, $descuento, $motivoDescuento,
 *            $total, $metodos, $cajaAbierta, $errorCaja, $errores,
 *            $permiteDescuento, $esAdmin, $pagosPrevios
 * -----------------------------------------------------------------
*/

$titulo    = 'Cobrar mesa ' . (string) $comanda['mesa_numero'];
$subtitulo = 'Pedido ' . (string) $comanda['numero']
    . ' · ' . (int) $comanda['comensales']
    . (((int) $comanda['comensales'] === 1) ? ' comensal' : ' comensales');

$accionesCabecera = '<a href="' . e($volver) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver al pedido</a>';

require APP_ROOT . '/includes/header.php';
?>

<?php if ($errorCaja !== null): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <strong>No se puede cobrar.</strong>
        <?= e($errorCaja) ?>
        <?php if ($esAdmin): ?>
            <div class="mt-2">
                <a href="<?= e(url('caja')) ?>" class="btn btn-atilio-amarillo btn-sm">
                    <i class="bi bi-safe-fill"></i> Ir a la caja
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) reset($errores)) ?>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ============ EL PEDIDO ============ -->
    <div class="col-12 col-lg-7">

        <div class="tarjeta mb-3">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-receipt"></i> Lo que pidió</span>
                <span class="small"><?= (int) $comanda['comensales'] ?> comensales</span>
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
                        <?php foreach ($items as $item): ?>
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
                    <strong><?= e(money($bruto)) ?></strong>
                </div>
            </div>
        </div>

        <!-- ============ DESCUENTO ============ -->
        <!-- Solo el administrador lo ve. El vendedor cobra el total
             tal cual: por eso el campo ni siquiera aparece, y el
             control real está en el servidor. -->
        <?php if ($permiteDescuento): ?>
            <div class="tarjeta mb-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-percent"></i> Descuento</span>
                    <?php if (!$esAdmin): ?>
                        <span class="small">te lo habilitó el administrador</span>
                    <?php endif; ?>
                </div>

                <div class="tarjeta-cuerpo">
                    <form method="post" action="<?= e(url('cobrar/descuento')) ?>" id="formDescuento">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                        <input type="hidden" name="volver" value="<?= e($volver) ?>">

                        <div class="form-rejilla">
                            <div class="campo mb-3">
                                <label for="descuento" class="form-label">Importe</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" id="descuento" name="descuento"
                                           class="form-control" inputmode="decimal"
                                           value="<?= e(cantidad($descuento, 2)) ?>"
                                           data-etiqueta="descuento">
                                </div>
                            </div>

                            <div class="campo mb-3">
                                <label for="descuento_motivo" class="form-label">Motivo</label>
                                <input type="text" id="descuento_motivo" name="descuento_motivo"
                                       class="form-control" maxlength="255"
                                       value="<?= e($motivoDescuento) ?>"
                                       data-etiqueta="motivo"
                                       placeholder="Cortesía de la casa">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-atilio-amarillo">
                                <i class="bi bi-check-lg"></i> Aplicar
                            </button>
                            <?php if ($descuento > 0): ?>
                                <a href="<?= e(url('cobrar') . '&id=' . (int) $comanda['id']) ?>"
                                   class="btn btn-atilio-amarillo">
                                    Quitar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============ EL COBRO ============ -->
    <div class="col-12 col-lg-5">

        <div class="tarjeta-caja">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-cash-coin"></i> A cobrar</span>
                <?php if ($cajaAbierta !== null): ?>
                    <span class="small">
                        caja desde <?= e(hora((string) $cajaAbierta['abierto_en'])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="tarjeta-cuerpo">
                <!-- El número grande: se lee de un vistazo -->
                <div class="monto-a-cobrar<?= $errorCaja !== null ? ' monto-bloqueado' : '' ?>">
                    <?= e(money($total)) ?>
                </div>

                <?php if ($descuento > 0): ?>
                    <p class="text-center small mb-3">
                        <?= e(money($bruto)) ?> menos
                        <?= e(money($descuento)) ?> de descuento
                        <?php if ($motivoDescuento !== ''): ?>
                            <br><span style="color: var(--texto-suave);"><?= e($motivoDescuento) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($errorCaja !== null || empty($metodos)): ?>
                    <p class="centrado" style="color: var(--texto-suave);">
                        <i class="bi bi-lock-fill"></i>
                        No se puede confirmar el cobro todavía.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('cobrar/confirmar')) ?>"
                          id="formCobro" data-validar novalidate>
                        <?= csrf_campo() ?>
                        <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                        <?php if ($permiteDescuento): ?>
                            <!-- Los campos viajan en el POST para que
                                 el descuento no dependa de la
                                 sesion. Para el resto no hace
                                 falta mandarlos. -->
                            <input type="hidden" name="descuento" value="<?= e(cantidad($descuento, 2)) ?>">
                            <input type="hidden" name="descuento_motivo" value="<?= e($motivoDescuento) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="vuelto" id="vuelto" value="0">

                        <!-- Medios de pago: un botón por medio -->
                        <div class="medios-pago">
                            <?php $primerMedio = true; ?>
                            <?php foreach ($metodos as $metodo): ?>
                                <?php $esEfectivo = (int) $metodo['afecta_caja'] === 1; ?>
                                <label class="medio-pago<?= $esEfectivo ? ' medio-efectivo' : '' ?>">
                                    <input type="radio"
                                           name="medio_principal"
                                           value="<?= (int) $metodo['id'] ?>"
                                           data-efectivo="<?= $esEfectivo ? '1' : '0' ?>"
                                           data-nombre="<?= e((string) $metodo['nombre']) ?>"
                                           <?= $primerMedio ? 'checked' : '' ?>
                                           <?php $primerMedio = false; ?>
                                           required>
                                    <i class="bi <?= $esEfectivo ? 'bi-cash-stack' : 'bi-credit-card' ?>"></i>
                                    <?= e((string) $metodo['nombre']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Cuánto recibe el cliente (solo tiene que
                             aparecer en efectivo, para calcular el
                             vuelto) -->
                        <div class="campo mb-3" id="bloqueRecibido">
                            <label for="recibido" class="form-label">
                                Recibió <span class="text-muted small">(para calcular el vuelto)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" id="recibido" name="recibido"
                                       class="form-control" inputmode="decimal"
                                       value="<?= e(cantidad($total, 2)) ?>"
                                       data-etiqueta="recibido">
                            </div>
                            <div class="vuelto-caja" id="vueltoCaja" hidden>
                                <span>Vuelto</span>
                                <strong id="vueltoTexto">$ 0,00</strong>
                            </div>
                        </div>

                        <!-- Botones rápidos: lo más común es dar
                             un billete redondo. -->
                        <div class="botones-rapidos mb-3">
                            <button type="button" class="btn-rapido" data-billete="1000">$ 1.000</button>
                            <button type="button" class="btn-rapido" data-billete="2000">$ 2.000</button>
                            <button type="button" class="btn-rapido" data-billete="5000">$ 5.000</button>
                            <button type="button" class="btn-rapido" data-billete="10000">$ 10.000</button>
                            <button type="button" class="btn-rapido" data-billete="20000">$ 20.000</button>
                            <button type="button" class="btn-rapido" data-billete="exacto">Exacto</button>
                        </div>

                        <!-- Pago partido: para cuando pagan una parte
                             en efectivo y otra con tarjeta. -->
                        <details class="pago-partido mb-3">
                            <summary>
                                <i class="bi bi-plus-circle"></i>
                                Pagar con más de un medio
                            </summary>

                            <div class="mt-2">
                                <div id="filasPagos">
                                    <?php
                                    // Se prepasa una fila con el total
                                    // completo: el caso normal es uno
                                    // solo pago.
                                    ?>
                                    <div class="fila-pago" data-fila="1">
                                        <select name="pagos[0][metodo_id]" class="form-select"
                                                aria-label="Medio de pago">
                                            <?php $idx = 0; ?>
                                            <?php foreach ($metodos as $metodo): ?>
                                                <option value="<?= (int) $metodo['id'] ?>"
                                                    <?= ($metodo === $metodos[0]) ? 'selected' : '' ?>>
                                                    <?= e((string) $metodo['nombre']) ?>
                                                </option>
                                                <?php $idx++; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" name="pagos[0][importe]"
                                                   class="form-control pago-importe"
                                                   inputmode="decimal" aria-label="Importe"
                                                   value="<?= e(cantidad($total, 2)) ?>">
                                        </div>
                                        <button type="button" class="btn-quitar-fila"
                                                aria-label="Sacar esta línea">&times;</button>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-atilio-amarillo btn-sm mt-2"
                                        id="agregarFila">
                                    <i class="bi bi-plus-lg"></i> Agregar otro medio
                                </button>

                                <div class="alerta-saldo" id="alertaSaldo" hidden></div>
                            </div>
                        </details>

                        <button type="submit" class="btn btn-atilio-oscuro btn-grande w-100"
                                data-confirmar="¿Confirmás el cobro por <?= e(money($total)) ?>?">
                            <i class="bi bi-check-circle-fill"></i>
                            Confirmar cobro
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Los datos que el JavaScript necesita para calcular el vuelto y
// avisar si el pago no cuadra.
$scriptsExtra = '<script>window.ATILIO_COBRO = '
    . json_encode([
        'total'   => (float) $total,
        'metodos' => array_map(
            static fn ($m) => [
                'id'       => (int) $m['id'],
                'nombre'   => (string) $m['nombre'],
                'efectivo' => (int) $m['afecta_caja'] === 1,
            ],
            $metodos
        ),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
    . ';</script>';
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
