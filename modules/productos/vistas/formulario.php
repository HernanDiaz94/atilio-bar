<?php
/**
 * modules/productos/vistas/formulario.php
 * -----------------------------------------------------------------
 * Formulario de alta / edición de producto (solo administradores).
 *
 * Variables: $idProducto, $esNuevo, $producto, $valores, $categorias,
 *            $componentes, $candidatosInsumo, $avisosReceta,
 *            $tieneMovimientos, $erroresGuardados
 * -----------------------------------------------------------------
*/

$errores         = $erroresGuardados['campos'];
$erroresGenerales = $erroresGuardados['generales'];

$titulo    = $esNuevo ? 'Nuevo producto' : 'Editar producto';
$subtitulo = $esNuevo
    ? 'Sumar un producto a la carta'
    : (string) ($producto['nombre'] ?? '');

$accionesCabecera = '<a href="' . e(url('productos')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a la carta</a>';

require APP_ROOT . '/includes/header.php';
?>

<?php if (!empty($erroresGenerales)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) $erroresGenerales[0]) ?>
    </div>
<?php endif; ?>

<?php foreach ($avisosReceta as $aviso): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= e($aviso) ?>
    </div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('productos/guardar')) ?>" class="tarjeta" data-validar novalidate>

    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $idProducto ?>">

    <div class="tarjeta-cabecera">
        <span><i class="bi bi-cup-straw"></i> Datos del producto</span>
    </div>

    <div class="tarjeta-cuerpo">
        <div class="form-rejilla">

            <!-- ================= NOMBRE ================= -->
            <div class="campo mb-3 <?= isset($errores['nombre']) ? 'campo-error' : '' ?>">
                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" id="nombre" name="nombre" class="form-control"
                       value="<?= e((string) $valores['nombre']) ?>"
                       maxlength="120" required autofocus data-etiqueta="nombre">
                <?php if (isset($errores['nombre'])): ?>
                    <span class="error-campo"><?= e((string) $errores['nombre']) ?></span>
                <?php endif; ?>
            </div>

            <!-- ================= CATEGORÍA ================= -->
            <div class="campo mb-3 <?= isset($errores['categoria_id']) ? 'campo-error' : '' ?>">
                <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                <select id="categoria_id" name="categoria_id" class="form-select"
                        required data-etiqueta="categoría">
                    <option value="">Elegí una</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                                <?= ((string) $valores['categoria_id'] === (string) $cat['id']) ? 'selected' : '' ?>
                                <?= (int) $cat['activo'] === 0 ? ' disabled' : '' ?>>
                            <?= e($cat['nombre']) ?><?= (int) $cat['activo'] === 0 ? ' (desactivada)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['categoria_id'])): ?>
                    <span class="error-campo"><?= e((string) $errores['categoria_id']) ?></span>
                <?php else: ?>
                    <span class="form-text">
                        Si no existe la que buscás, creala desde
                        <a href="<?= e(url('categorias')) ?>">Categorías</a>.
                    </span>
                <?php endif; ?>
            </div>

            <!-- ================= DESCRIPCIÓN ================= -->
            <div class="campo mb-3 ancho-total <?= isset($errores['descripcion']) ? 'campo-error' : '' ?>">
                <label for="descripcion" class="form-label">Descripción</label>
                <input type="text" id="descripcion" name="descripcion" class="form-control"
                       value="<?= e((string) $valores['descripcion']) ?>"
                       maxlength="255" data-etiqueta="descripción"
                       placeholder="Opcional. Por ejemplo: con limón y hielo">
                <?php if (isset($errores['descripcion'])): ?>
                    <span class="error-campo"><?= e((string) $errores['descripcion']) ?></span>
                <?php endif; ?>
            </div>

            <!-- ================= CÓDIGO ================= -->
            <div class="campo mb-3 <?= isset($errores['codigo']) ? 'campo-error' : '' ?>">
                <label for="codigo" class="form-label">Código</label>
                <input type="text" id="codigo" name="codigo" class="form-control"
                       value="<?= e((string) $valores['codigo']) ?>"
                       maxlength="30" autocapitalize="characters" data-etiqueta="código">
                <?php if (isset($errores['codigo'])): ?>
                    <span class="error-campo"><?= e((string) $errores['codigo']) ?></span>
                <?php else: ?>
                    <span class="form-text">Opcional. Útil para buscar rápido.</span>
                <?php endif; ?>
            </div>

            <!-- ================= UNIDAD ================= -->
            <div class="campo mb-3 <?= isset($errores['unidad']) ? 'campo-error' : '' ?>">
                <label for="unidad" class="form-label">Unidad</label>
                <select id="unidad" name="unidad" class="form-select">
                    <?php foreach (unidades_disponibles() as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>" <?= ((string) $valores['unidad'] === $valor) ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['unidad'])): ?>
                    <span class="error-campo"><?= e((string) $errores['unidad']) ?></span>
                <?php endif; ?>
            </div>

            <!-- ============ FORMAS DE VENTA ============ -->
            <!-- Ejemplo: 1:Unidad,6:Media docena,12:Docena
                 Con esto el mozo ve tres botones en vez de
                 tener que escribir la cantidad. -->
            <div class="campo mb-3 <?= isset($errores['formas_venta']) ? 'campo-error' : '' ?>">
                <label for="formas_venta" class="form-label">Formas de venta</label>
                <input type="text" id="formas_venta" name="formas_venta"
                       class="form-control"
                       value="<?= e((string) $valores['formas_venta']) ?>"
                       maxlength="160" data-etiqueta="formas de venta"
                       placeholder="1:Unidad,6:Media docena,12:Docena">
                <?php if (isset($errores['formas_venta'])): ?>
                    <span class="error-campo"><?= e((string) $errores['formas_venta']) ?></span>
                <?php else: ?>
                    <span class="form-text"><?= e(ayuda_formas_venta()) ?></span>

                    <?php if ((string) $valores['formas_venta'] !== ''): ?>
                        <div class="vista-previa-formas">
                            En el pedido va a aparecer así:
                            <?php foreach (formas_venta((string) $valores['formas_venta']) as $forma): ?>
                                <span class="forma-previa">
                                    <?= e($forma['etiqueta']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ================= PRECIO ================= -->
            <div class="campo mb-3 <?= isset($errores['precio_venta']) ? 'campo-error' : '' ?>">
                <label for="precio_venta" class="form-label">Precio de venta <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" id="precio_venta" name="precio_venta" class="form-control"
                           value="<?= e(cantidad(a_numero((string) $valores['precio_venta']), 2)) ?>"
                           inputmode="decimal" required data-etiqueta="precio de venta">
                </div>
                <?php if (isset($errores['precio_venta'])): ?>
                    <span class="error-campo"><?= e((string) $errores['precio_venta']) ?></span>
                <?php endif; ?>
            </div>

            <!-- ================= COSTO ================= -->
            <div class="campo mb-3 <?= isset($errores['costo']) ? 'campo-error' : '' ?>">
                <label for="costo" class="form-label">Costo de compra</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" id="costo" name="costo" class="form-control"
                           value="<?= $valores['costo'] !== '' && $valores['costo'] !== null ? e(cantidad(a_numero((string) $valores['costo']), 2)) : '' ?>"
                           inputmode="decimal" data-etiqueta="costo">
                </div>
                <?php if (isset($errores['costo'])): ?>
                    <span class="error-campo"><?= e((string) $errores['costo']) ?></span>
                <?php else: ?>
                    <span class="form-text">
                        <i class="bi bi-eye-slash-fill"></i>
                        Solo el administrador lo ve. Si lo dejás vacío, se
                        completa solo con las compras que hagas.
                    </span>
                <?php endif; ?>
            </div>

            <!-- ================= TIPO ================= -->
            <div class="ancho-total mb-3">
                <label class="form-label">Tipo de producto</label>

                <div class="form-check border rounded p-3 mb-2">
                    <input type="radio" class="form-check-input" name="controla_stock" value="1"
                           id="cs_si" <?= ((string) $valores['controla_stock'] === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="cs_si">
                        <strong>Lleva control de stock</strong><br>
                        <span class="small" style="color: var(--texto-suave);">
                            Se lleva cuenta y se descuenta cuando se vende.
                            Cerveza, gaseosas, agua, insumos.
                        </span>
                    </label>
                </div>

                <div class="form-check border rounded p-3">
                    <input type="radio" class="form-check-input" name="controla_stock" value="0"
                           id="cs_no" <?= ((string) $valores['controla_stock'] === '0') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="cs_no">
                        <strong>Preparación, no lleva stock</strong><br>
                        <span class="small" style="color: var(--texto-suave);">
                            Se arma en el momento y no se descuenta nada.
                            Tragos, pizza, empanadas.
                        </span>
                    </label>
                </div>
            </div>

            <!-- ================= STOCK ================= -->
            <!-- Se muestra y oculta con JavaScript según el tipo elegido -->
            <div id="bloqueStock" class="<?= ((string) $valores['controla_stock'] === '1') ? '' : 'oculto' ?>">
                <div class="form-rejilla">
                    <div class="campo mb-3 <?= isset($errores['stock_actual']) ? 'campo-error' : '' ?>">
                        <label for="stock_actual" class="form-label">Stock actual</label>
                        <input type="text" id="stock_actual" name="stock_actual" class="form-control"
                               value="<?= e(cantidad(a_numero((string) $valores['stock_actual']), 2)) ?>"
                               inputmode="decimal" data-etiqueta="stock actual">
                        <?php if (isset($errores['stock_actual'])): ?>
                            <span class="error-campo"><?= e((string) $errores['stock_actual']) ?></span>
                        <?php elseif ($esNuevo): ?>
                            <span class="form-text">
                                Se asienta en el historial como "alta inicial".
                            </span>
                        <?php else: ?>
                            <span class="form-text">
                                <i class="bi bi-lock-fill"></i>
                                El stock no se cambia desde acá: se cambia con
                                compras o con un ajuste, para que quede registrado.
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="campo mb-3 <?= isset($errores['stock_minimo']) ? 'campo-error' : '' ?>">
                        <label for="stock_minimo" class="form-label">Stock mínimo</label>
                        <input type="text" id="stock_minimo" name="stock_minimo" class="form-control"
                               value="<?= e(cantidad(a_numero((string) $valores['stock_minimo']), 2)) ?>"
                               inputmode="decimal" data-etiqueta="stock mínimo">
                        <?php if (isset($errores['stock_minimo'])): ?>
                            <span class="error-campo"><?= e((string) $errores['stock_minimo']) ?></span>
                        <?php else: ?>
                            <span class="form-text">
                                Debajo de este número sale la alerta de stock bajo.
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ================= COMBO ================= -->
            <div class="ancho-total mb-3">
                <div class="form-check border rounded p-3">
                    <input type="checkbox" class="form-check-input" name="es_combo" value="1"
                           id="es_combo" <?= ((string) $valores['es_combo'] === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="es_combo">
                        <strong>Es un combo: descuenta insumos</strong><br>
                        <span class="small" style="color: var(--texto-suave);">
                            Al venderlo, resta del stock de los insumos que
                            lleva. Ej: un gin tonic descuenta tónica y gin.
                        </span>
                    </label>
                </div>

                <!-- Solo visible si el combo está marcado -->
                <div id="bloqueReceta" class="<?= ((string) $valores['es_combo'] === '1') ? '' : 'oculto' ?>">
                    <p class="form-text mt-2 mb-2">
                        Agregá los insumos y cuánto se descuenta por cada unidad:
                    </p>

                    <div id="listaComponentes" class="mb-2">
                        <!-- Las filas las arma app.js a partir de lo guardado -->
                        <?php foreach ($componentes as $insumoId => $cantidadInsumo): ?>
                            <?php
                            $info = null;
                            foreach ($candidatosInsumo as $cand) {
                                if ((int) $cand['id'] === (int) $insumoId) {
                                    $info = $cand;
                                    break;
                                }
                            }
                            if ($info === null) {
                                continue;
                            }
                            ?>
                            <div class="input-group mb-2 fila-componente">
                                <span class="input-group-text">
                                    <i class="bi bi-box"></i>
                                </span>
                                <select name="componentes[]" class="form-select componente-insumo" aria-label="Insumo">
                                    <?php foreach ($candidatosInsumo as $cand): ?>
                                        <option value="<?= (int) $cand['id'] ?>"
                                                data-unidad="<?= e((string) $cand['unidad']) ?>"
                                                <?= ((int) $cand['id'] === (int) $insumoId) ? 'selected' : '' ?>>
                                            <?= e((string) $cand['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="componentes_cantidad[]" class="form-control componente-cantidad"
                                       value="<?= e(cantidad((float) $cantidadInsumo, 2)) ?>"
                                       inputmode="decimal" aria-label="Cantidad">
                                <button type="button" class="btn btn-peligro quitar-componente">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn btn-atilio-amarillo btn-sm" id="agregarComponente">
                        <i class="bi bi-plus-lg"></i> Agregar insumo
                    </button>
                </div>
            </div>

            <!-- ================= ESTADO ================= -->
            <div class="ancho-total">
                <label class="form-label">Estado</label>
                <select id="activo" name="activo" class="form-select">
                    <option value="1" <?= ((string) $valores['activo'] === '1') ? 'selected' : '' ?>>
                        Activo (aparece en la carta)
                    </option>
                    <option value="0" <?= ((string) $valores['activo'] === '0') ? 'selected' : '' ?>>
                        Desactivado (oculto, pero conserva el historial)
                    </option>
                </select>
            </div>
        </div>

        <!-- ================= BOTONES ================= -->
        <div class="d-flex gap-2 flex-wrap justify-content-end mt-4">
            <?php if (!$esNuevo && !$tieneMovimientos): ?>
                <button type="submit" form="formBorrar" class="btn btn-peligro me-auto">
                    <i class="bi bi-trash-fill"></i> Eliminar producto
                </button>
            <?php elseif (!$esNuevo): ?>
                <span class="me-auto form-text" style="align-self: center;">
                    <i class="bi bi-info-circle-fill"></i>
                    No se puede eliminar: tiene movimientos de stock registrados.
                </span>
            <?php endif; ?>

            <a href="<?= e(url('productos')) ?>" class="btn btn-atilio-amarillo">Cancelar</a>

            <button type="submit" class="btn btn-atilio-oscuro">
                <i class="bi bi-check-lg"></i>
                <?= $esNuevo ? 'Crear producto' : 'Guardar cambios' ?>
            </button>
        </div>
    </div>
</form>

<?php if (!$esNuevo && !$tieneMovimientos): ?>
    <form id="formBorrar" method="post" action="<?= e(url('productos/acciones')) ?>" class="d-none"
          data-confirmar="Se va a eliminar &quot;<?= e((string) $producto['nombre']) ?>&quot; de forma permanente. Esta acción no se puede deshacer. ¿Seguro?">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= (int) $idProducto ?>">
    </form>
<?php endif; ?>

<?php
// Se le pasa a JavaScript la lista de insumos, para que pueda
// agregar filas nuevas al combo sin recargar la página.
$scriptsDatos = [
    'candidatos' => array_map(static fn (array $c) => [
        'id'     => (int) $c['id'],
        'nombre' => (string) $c['nombre'],
        'unidad' => (string) $c['unidad'],
        'stock'  => (float) $c['stock_actual'],
    ], $candidatosInsumo),
];

$scriptsExtra = '<script>window.ATILIO_FORM_PRODUCTO = ' . json_encode($scriptsDatos, JSON_UNESCAPED_UNICODE) . ';</script>';
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>