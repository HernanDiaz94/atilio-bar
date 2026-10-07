<?php
/**
 * modules/stock/vistas/compra_nueva.php
 * -----------------------------------------------------------------
 * Vista del formulario de carga de compra (solo administradores).
 * -----------------------------------------------------------------
*/

$errores         = $erroresGuardados['campos'];
$erroresGenerales = $erroresGuardados['generales'];

$titulo    = 'Nueva compra';
$subtitulo = 'Cargar mercadería que entró al depósito';

$accionesCabecera = '<a href="' . e(url('compras')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a las compras</a>';

require APP_ROOT . '/includes/header.php';
?>

<?php if (!empty($erroresGenerales)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php foreach ($erroresGenerales as $mensaje): ?>
            <?= e($mensaje) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($productos)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-truck fs-1 d-block mb-3"></i>
            <p class="fw-bold mb-1">No hay productos para comprar</p>
            <p style="color: var(--texto-suave);">
                Para cargar una compra tienen que existir productos que
                lleven control de stock.
            </p>
            <a href="<?= e(url('productos/formulario')) ?>" class="btn btn-atilio-oscuro mt-2">
                <i class="bi bi-plus-lg"></i> Crear producto
            </a>
        </div>
    </div>
<?php else: ?>

<form method="post" action="<?= e(url('compras/guardar')) ?>" class="tarjeta" data-validar novalidate>

    <?= csrf_campo() ?>

    <div class="tarjeta-cabecera">
        <span><i class="bi bi-truck"></i> Datos de la compra</span>
    </div>

    <div class="tarjeta-cuerpo">

        <div class="form-rejilla mb-4">
            <!-- Proveedor -->
            <div class="campo mb-3 <?= isset($errores['proveedor']) ? 'campo-error' : '' ?>">
                <label for="proveedor" class="form-label">Proveedor</label>
                <input type="text" id="proveedor" name="proveedor" class="form-control"
                       value="<?= e((string) $valores['proveedor']) ?>"
                       maxlength="120" list="listaProveedores"
                       data-etiqueta="proveedor"
                       placeholder="Opcional">
                <datalist id="listaProveedores">
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= e((string) $prov['proveedor']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if (isset($errores['proveedor'])): ?>
                    <span class="error-campo"><?= e((string) $errores['proveedor']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Comprobante -->
            <div class="campo mb-3 <?= isset($errores['numero_comprobante']) ? 'campo-error' : '' ?>">
                <label for="numero_comprobante" class="form-label">Nº de comprobante</label>
                <input type="text" id="numero_comprobante" name="numero_comprobante"
                       class="form-control"
                       value="<?= e((string) $valores['numero_comprobante']) ?>"
                       maxlength="60" data-etiqueta="número de comprobante"
                       placeholder="Opcional">
                <?php if (isset($errores['numero_comprobante'])): ?>
                    <span class="error-campo"><?= e((string) $errores['numero_comprobante']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Fecha -->
            <div class="campo mb-3 <?= isset($errores['fecha']) ? 'campo-error' : '' ?>">
                <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                <input type="date" id="fecha" name="fecha" class="form-control"
                       value="<?= e((string) $valores['fecha']) ?>"
                       required data-etiqueta="fecha">
                <?php if (isset($errores['fecha'])): ?>
                    <span class="error-campo"><?= e((string) $errores['fecha']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Observaciones -->
            <div class="campo mb-3 <?= isset($errores['observaciones']) ? 'campo-error' : '' ?>">
                <label for="observaciones" class="form-label">Observaciones</label>
                <input type="text" id="observaciones" name="observaciones" class="form-control"
                       value="<?= e((string) $valores['observaciones']) ?>"
                       maxlength="255" data-etiqueta="observaciones"
                       placeholder="Opcional">
                <?php if (isset($errores['observaciones'])): ?>
                    <span class="error-campo"><?= e((string) $errores['observaciones']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= LÍNEAS ================= -->
        <hr class="my-3 sin-borde-abajo">
        <p class="fw-bold mb-2">
            <i class="bi bi-list-ul"></i> Qué entró
        </p>

        <!-- Agregar una línea -->
        <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-6">
                <label for="nuevoProducto" class="form-label">Producto</label>
                <select id="nuevoProducto" class="form-select">
                    <option value="">Elegí un producto</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"
                                data-nombre="<?= e((string) $p['nombre']) ?>"
                                data-unidad="<?= e((string) $p['unidad']) ?>"
                                data-stock="<?= e((string) $p['stock_actual']) ?>"
                                data-costo="<?= e((string) ($p['costo'] ?? '0')) ?>">
                            <?= e((string) $p['categoria']) ?> &middot;
                            <?= e((string) $p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="nuevaCantidad" class="form-label">Cantidad</label>
                <input type="text" id="nuevaCantidad" class="form-control"
                       value="1" inputmode="decimal">
            </div>

            <div class="col-6 col-md-2">
                <label for="nuevoCosto" class="form-label">Costo unitario</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" id="nuevoCosto" class="form-control"
                           value="" inputmode="decimal" placeholder="0,00">
                </div>
            </div>

            <div class="col-12 col-md-2">
                <button type="button" class="btn btn-atilio w-100" id="agregarLinea">
                    <i class="bi bi-plus-lg"></i> Agregar
                </button>
            </div>
        </div>

        <div id="avisoLinea" class="alert alert-info d-none" role="alert"></div>

        <!-- Tabla de líneas -->
        <div class="tabla-envoltura">
            <table class="tabla" id="tablaLineas">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Stock actual</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Costo unit.</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Quitar</th>
                    </tr>
                </thead>
                <tbody id="cuerpoLineas">
                    <!-- Las filas las arma JavaScript -->
                </tbody>
                <tfoot id="pieLineas" class="d-none">
                    <tr>
                        <th colspan="4" class="text-end">Total de la compra</th>
                        <th class="text-end" id="totalLineas">$ 0,00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p id="sinLineas" class="centrado my-4" style="color: var(--texto-suave);">
            <i class="bi bi-info-circle"></i>
            Todavía no agregaste ningún producto.
        </p>

        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="<?= e(url('compras')) ?>" class="btn btn-atilio-amarillo">Cancelar</a>
            <button type="submit" class="btn btn-atilio-oscuro" id="botonGuardar" disabled>
                <i class="bi bi-check-lg"></i> Guardar compra
            </button>
        </div>
    </div>
</form>

<?php
// Los datos de los productos van al JavaScript para que pueda
// mostrar nombre, unidad y costo al agregar una línea.
$scriptsDatos = [
    'productos' => array_map(static fn (array $p) => [
        'id'     => (int) $p['id'],
        'nombre' => (string) $p['nombre'],
        'unidad' => (string) $p['unidad'],
        'stock'  => (float) $p['stock_actual'],
        'costo'  => $p['costo'] === null ? null : (float) $p['costo'],
        'categoria' => (string) $p['categoria'],
    ], $productos),
    // Líneas que el usuario ya había agregado antes de un error
    'lineas' => $lineasPrecargadas,
];

$scriptsExtra = '<script>window.ATILIO_COMPRA = ' . json_encode($scriptsDatos, JSON_UNESCAPED_UNICODE) . ';</script>';
?>

<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>