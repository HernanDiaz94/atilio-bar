<?php
/**
 * modules/stock/vistas/ajuste.php
 * -----------------------------------------------------------------
 * Vista del ajuste manual de stock (solo administradores).
 *
 * Variables: $producto, $productosAjustables, $valores, $delta,
 *            $erroresGuardados
 * -----------------------------------------------------------------
*/

$errores         = $erroresGuardados['campos'];
$erroresGenerales = $erroresGuardados['generales'];

$titulo    = 'Ajuste de stock';
$subtitulo = 'Corregir el conteo después de contar el depósito';

$accionesCabecera = '<a href="' . e(url('stock')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver al historial</a>';

require APP_ROOT . '/includes/header.php';
?>

<?php if (!empty($erroresGenerales)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) $erroresGenerales[0]) ?>
    </div>
<?php endif; ?>

<?php if (empty($productosAjustables)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <p class="fw-bold">No hay productos con control de stock</p>
            <p style="color: var(--texto-suave);">
                Para poder ajustar el stock, primero creá productos que
                lleven control de stock.
            </p>
            <a href="<?= e(url('productos/formulario')) ?>" class="btn btn-atilio-oscuro mt-2">
                <i class="bi bi-plus-lg"></i> Crear producto
            </a>
        </div>
    </div>
<?php else: ?>

<div class="row g-3">

    <!-- ================= FORMULARIO ================= -->
    <div class="col-12 col-lg-7">
        <form method="post" action="<?= e(url('stock/ajuste-guardar')) ?>"
              class="tarjeta" data-validar novalidate>

            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-sliders"></i> Corregir el stock</span>
            </div>

            <div class="tarjeta-cuerpo">

                <!-- Producto -->
                <div class="campo mb-3 <?= isset($errores['producto_id']) ? 'campo-error' : '' ?>">
                    <label for="producto_id" class="form-label">Producto <span class="text-danger">*</span></label>
                    <select id="producto_id" name="producto_id" class="form-select"
                            required data-etiqueta="producto" onchange="this.form.submit()">
                        <option value="">Elegí un producto</option>
                        <?php foreach ($productosAjustables as $p): ?>
                            <?php $esElActual = ((string) $valores['producto_id'] === (string) $p['id']); ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $esElActual ? 'selected' : '' ?>>
                                <?= e((string) $p['nombre']) ?>
                                (<?= e(texto_stock((float) $p['stock_actual'], (float) $p['stock_minimo'], (string) $p['unidad'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['producto_id'])): ?>
                        <span class="error-campo"><?= e((string) $errores['producto_id']) ?></span>
                    <?php else: ?>
                        <span class="form-text">
                            Al elegir un producto se recarga la pantalla y se
                            muestra su stock actual.
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($producto): ?>
                    <!-- Estado actual -->
                    <div class="marco p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="small" style="color: var(--texto-suave);">Stock actual en el sistema</span>
                                <div style="font-size: 1.6rem; font-weight: 700;">
                                    <?= e(cantidad((float) $producto['stock_actual'])) ?>
                                    <span style="font-size: 0.95rem; font-weight: 400;">
                                        <?= e(plural((string) $producto['unidad'], (float) $producto['stock_actual'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="small" style="color: var(--texto-suave);">Costo unitario</span>
                                <div style="font-weight: 600;">
                                    <?php if ($producto['costo'] === null): ?>
                                        <span style="color: var(--texto-suave);">sin cargar</span>
                                    <?php else: ?>
                                        <?= e(money($producto['costo'])) ?>
                                    <?php endif; ?>
                                </div>
                                <small style="color: var(--texto-suave);">
                                    el ajuste no lo cambia
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Stock real -->
                    <div class="campo mb-3 <?= isset($errores['stock_real']) ? 'campo-error' : '' ?>">
                        <label for="stock_real" class="form-label">
                            Stock real contado <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" id="stock_real" name="stock_real" class="form-control"
                                   value="<?= e((string) $valores['stock_real']) ?>"
                                   inputmode="decimal" required autofocus
                                   data-etiqueta="stock real"
                                   placeholder="Cuántas unidades hay en el depósito">
                            <span class="input-group-text">
                                <?= e(plural((string) $producto['unidad'], 1)) ?>
                            </span>
                        </div>
                        <?php if (isset($errores['stock_real'])): ?>
                            <span class="error-campo"><?= e((string) $errores['stock_real']) ?></span>
                        <?php elseif ($delta !== null): ?>
                            <!-- Se muestra cuánto va a cambiar antes de confirmar -->
                            <span class="form-text d-block mt-1">
                                <?php if (abs($delta) < 0.001): ?>
                                    <i class="bi bi-equal"></i> No cambia nada.
                                <?php elseif ($delta > 0): ?>
                                    <strong class="texto-ganancia">
                                        <?= e('+' . cantidad($delta)) ?>
                                    </strong> va a sumar al stock.
                                <?php else: ?>
                                    <strong class="texto-perdida">
                                        <?= e(cantidad($delta)) ?>
                                    </strong> va a restar del stock.
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Motivo -->
                    <div class="campo mb-3 <?= isset($errores['motivo']) ? 'campo-error' : '' ?>">
                        <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                        <textarea id="motivo" name="motivo" class="form-control" rows="2"
                                  maxlength="255" required
                                  data-etiqueta="motivo"
                                  placeholder="Ej: se rompieron 3 botellas / conté el depósito y había menos"></textarea>
                        <?php if (isset($errores['motivo'])): ?>
                            <span class="error-campo"><?= e((string) $errores['motivo']) ?></span>
                        <?php else: ?>
                            <span class="form-text">
                                <i class="bi bi-info-circle-fill"></i>
                                Queda escrito en el historial para siempre. Un ajuste
                                sin motivo es indistinguible de un error.
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Motivo rápido -->
                    <div class="mb-3">
                        <span class="form-label d-block">Motivos frecuentes</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-atilio-amarillo btn-sm motivo-rapido"
                                    data-texto="Se rompió o se perdió mercadería">
                                Se rompió
                            </button>
                            <button type="button" class="btn btn-atilio-amarillo btn-sm motivo-rapido"
                                    data-texto="Error de tipeo al cargar una compra">
                                Error de tipeo
                            </button>
                            <button type="button" class="btn btn-atilio-amarillo btn-sm motivo-rapido"
                                    data-texto="Conté el depósito y la cantidad no coincidía">
                                Diferencia de conteo
                            </button>
                            <button type="button" class="btn btn-atilio-amarillo btn-sm motivo-rapido"
                                    data-texto="Mercadería devuelta por el proveedor">
                                Devuelto por proveedor
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= e(url('stock')) ?>" class="btn btn-atilio-amarillo">Cancelar</a>

                    <?php if ($producto): ?>
                        <button type="submit" class="btn btn-atilio-oscuro">
                            <i class="bi bi-check-lg"></i> Guardar ajuste
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- ================= AYUDA ================= -->
    <div class="col-12 col-lg-5">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-question-circle-fill"></i> Cuándo usar cada cosa</span>
            </div>
            <div class="tarjeta-cuerpo">
                <ul class="lista-movimientos">
                    <li>
                        <i class="bi bi-truck mt-1"></i>
                        <div>
                            <strong>Compras</strong><br>
                            <span style="color: var(--texto-suave); font-size: 0.88rem;">
                                Entró mercadería comprada. Suma stock y actualiza
                                el costo del producto.
                            </span>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-sliders mt-1"></i>
                        <div>
                            <strong>Ajuste</strong> <span style="color: var(--texto-suave);">(esta pantalla)</span><br>
                            <span style="color: var(--texto-suave); font-size: 0.88rem;">
                                El conteo físico no da. Solo corrige el número;
                                no cambia el costo.
                            </span>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-cart-check-fill mt-1"></i>
                        <div>
                            <strong>Venta</strong> <span style="color: var(--texto-suave);">(Etapa 4)</span><br>
                            <span style="color: var(--texto-suave); font-size: 0.88rem;">
                                Se descuenta solo al cobrar. No se toca a mano.
                            </span>
                        </div>
                    </li>
                </ul>

                <div class="marco-amarillo p-3 mt-3 small">
                    <strong><i class="bi bi-lightbulb-fill"></i> Importante:</strong>
                    <p class="mb-0 mt-1">
                        El stock se cambia solo por compras, ajustes y ventas.
                        Nada de eso se puede hacer desde la ficha del producto,
                        porque un cambio de número sin registro deja el historial
                        sin cuadrar.
                    </p>
                </div>
            </div>
        </div>

        <?php if ($producto): ?>
            <div class="tarjeta mt-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-clock-history"></i> Últimos movimientos de este producto</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <?php
                    $ultimos = DB::all(
                        'SELECT tipo, cantidad, stock_resultante, motivo, creado_en
                         FROM stock_movimientos
                         WHERE producto_id = :id
                         ORDER BY id DESC
                         LIMIT 8',
                        ['id' => (int) $producto['id']]
                    );
                    ?>

                    <?php if (empty($ultimos)): ?>
                        <p class="mb-0 centrado" style="color: var(--texto-suave);">
                            <i class="bi bi-inbox"></i> Este producto todavía no tuvo movimientos.
                        </p>
                    <?php else: ?>
                        <ul class="lista-movimientos">
                            <?php foreach ($ultimos as $mov): ?>
                                <li>
                                    <i class="bi <?= $mov === null ? '' : '' ?><?= (float) $mov['cantidad'] > 0 ? 'bi-plus-circle-fill' : 'bi-dash-circle-fill' ?> mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong>
                                            <?= e(texto_tipo_movimiento((string) $mov['tipo'])) ?>
                                            <?= e((float) $mov['cantidad'] > 0 ? '+' : '') ?>
                                            <?= e(cantidad((float) $mov['cantidad'])) ?>
                                        </strong>
                                        <span style="color: var(--texto-suave);">
                                            → <?= e(cantidad((float) $mov['stock_resultante'])) ?>
                                        </span>
                                        <br>
                                        <span class="momento">
                                            <?= e(fecha_hora((string) $mov['creado_en'])) ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php
$scriptsExtra = <<<'HTML'
<script>
(function () {
    // Los botones de "motivo rápido" completan el textarea.
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('.motivo-rapido');

        if (!boton) { return; }

        var campo = document.getElementById('motivo');

        if (campo) {
            campo.value = boton.getAttribute('data-texto') || '';
            campo.focus();
        }
    });
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>