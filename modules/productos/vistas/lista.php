<?php
/**
 * modules/productos/vistas/lista.php
 * -----------------------------------------------------------------
 * Vista de la carta de productos.
 *
 * Variables: $esAdmin, $productos, $categorias, $buscar, $categoria,
 *            $estado, $verTodos, $totalProductos, $totalConStock,
 *            $totalStockBajo, $totalStockCero, $valorStock
 * -----------------------------------------------------------------
*/

$titulo    = 'Productos';
$subtitulo = $esAdmin
    ? 'Carta del bar, precios, costos y stock'
    : 'Carta del bar: productos y precios';

$accionesCabecera = '';

if ($esAdmin) {
    $accionesCabecera =
        '<a href="' . e(url('categorias')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-tags-fill"></i> Categorías</a> '
        . '<a href="' . e(url('productos/formulario')) . '" class="btn btn-atilio-oscuro">'
        . '<i class="bi bi-plus-lg"></i> Nuevo producto</a>';
}

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= RESUMEN ================= -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor">
            <p class="etiqueta">En la carta</p>
            <p class="valor"><?= e((string) $totalProductos) ?></p>
            <span class="detalle">
                <i class="bi bi-cup-straw"></i>
                <?= e((string) $totalConStock) ?> con stock
            </span>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor <?= ((int) $totalStockBajo > 0) ? 'acento-amarillo' : '' ?>">
            <p class="etiqueta">Stock bajo</p>
            <p class="valor"><?= e((string) $totalStockBajo) ?></p>
            <span class="detalle">
                <i class="bi bi-exclamation-triangle-fill"></i> Para reponer
            </span>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor <?= ((int) $totalStockCero > 0) ? 'acento-amarillo' : '' ?>">
            <p class="etiqueta">Sin stock</p>
            <p class="valor"><?= e((string) $totalStockCero) ?></p>
            <span class="detalle">
                <i class="bi bi-x-circle-fill"></i> No se pueden vender
            </span>
        </div>
    </div>

    <?php if ($esAdmin): ?>
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Valor del stock</p>
                <p class="valor"><?= e(money($valorStock)) ?></p>
                <span class="detalle">
                    <i class="bi bi-cash-coin"></i> A costo de compra
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Tu rol</p>
                <p class="valor" style="font-size: 1.2rem;"><?= e(texto_rol(Auth::rol())) ?></p>
                <span class="detalle">Solo lectura de la carta</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= FILTROS ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cuerpo">
        <form method="get" action="<?= e(url_archivo('index.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="productos">

            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Buscar</label>
                <input type="text" id="q" name="q" class="form-control"
                       value="<?= e($buscar) ?>" placeholder="Nombre o código">
            </div>

            <div class="col-8 col-md-3">
                <label for="categoria" class="form-label">Categoría</label>
                <select id="categoria" name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoria === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['nombre']) ?> (<?= (int) $cat['productos'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-4 col-md-2">
                <label for="estado" class="form-label">Stock</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Normal</option>
                    <option value="bajo" <?= $estado === 'bajo' ? 'selected' : '' ?>>Bajo</option>
                    <option value="sin" <?= $estado === 'sin' ? 'selected' : '' ?>>Sin stock</option>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-atilio flex-grow-1">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <?php if ($verTodos): ?>
                    <a href="<?= e(url('productos')) ?>" class="btn btn-atilio-amarillo">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($esAdmin): ?>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="ver_inactivos" value="1"
                               <?= $verTodos ? 'checked' : '' ?>>
                        <span class="form-check-label small">
                            Incluir productos desactivados
                        </span>
                    </label>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ================= CARTA ================= -->
<?php if (empty($productos)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <p class="mb-1 fw-bold">No hay productos para mostrar</p>
            <p style="color: var(--texto-suave);">
                <?php if ($esAdmin): ?>
                    Cambiá los filtros, o creá el primer producto de la carta.
                <?php else: ?>
                    Todavía no se cargó la carta. Hablá con el administrador.
                <?php endif; ?>
            </p>
            <?php if ($esAdmin && !$verTodos): ?>
                <a href="<?= e(url('productos/formulario')) ?>" class="btn btn-atilio-oscuro mt-2">
                    <i class="bi bi-plus-lg"></i> Crear el primero
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-end">Precio</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Margen</th>
                    <?php endif; ?>
                    <th>Stock</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-end">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <?php
                    $conStock  = (int) $p['controla_stock'] === 1;
                    $precio    = (float) $p['precio_venta'];
                    $stock     = (float) $p['stock_actual'];
                    $minimo    = (float) $p['stock_minimo'];
                    $activo    = (int) $p['activo'] === 1;
                    $costo     = isset($p['costo']) && $p['costo'] !== null ? (float) $p['costo'] : null;

                    // Margen solo si hay costo cargado
                    $margen = null;
                    $margenPct = null;
                    if ($esAdmin && $costo !== null && $costo > 0 && $precio > 0) {
                        $margen = $precio - $costo;
                        $margenPct = round(($margen / $precio) * 100, 1);
                    }
                    ?>
                    <tr class="<?= $activo ? '' : 'producto-inactivo' ?>">
                        <td>
                            <strong><?= e((string) $p['nombre']) ?></strong>
                            <?php if (!$activo): ?>
                                <span class="estado-anulada">
                                    <i class="bi bi-slash-circle-fill"></i> Desactivado
                                </span>
                            <?php endif; ?>
                            <?php if ((int) $p['es_combo'] === 1): ?>
                                <span class="estado-reservada" title="Descuenta insumos de stock">
                                    <i class="bi bi-boxes"></i> Combo
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($p['descripcion'])): ?>
                                <br>
                                <small style="color: var(--texto-suave);">
                                    <?= e(recortar((string) $p['descripcion'], 60)) ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($p['codigo'])): ?>
                                <br>
                                <span class="mono"><?= e((string) $p['codigo']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td><?= e((string) $p['categoria_nombre']) ?></td>

                        <td class="text-end">
                            <strong><?= e(money($precio)) ?></strong>
                        </td>

                        <?php if ($esAdmin): ?>
                            <td class="text-end">
                                <?php if ($costo === null): ?>
                                    <span style="color: var(--texto-suave);" title="Todavía no se cargó el costo">
                                        sin costo
                                    </span>
                                <?php else: ?>
                                    <?= e(money($costo)) ?>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <?php if ($margen === null): ?>
                                    <span style="color: var(--texto-suave);">-</span>
                                <?php else: ?>
                                    <span class="<?= $margen > 0 ? 'texto-ganancia' : 'texto-perdida' ?>">
                                        <?= e(money($margen)) ?>
                                    </span>
                                    <br>
                                    <small style="color: var(--texto-suave);">
                                        <?= e((string) $margenPct) ?>%
                                    </small>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>

                        <td>
                            <?php if ($conStock): ?>
                                <span class="<?= e(clase_stock($stock, $minimo, (string) $p['unidad'])) ?>">
                                    <?php if ($stock < 0): ?>
                                        <i class="bi bi-exclamation-octagon-fill"></i>
                                    <?php elseif ($minimo > 0 && $stock <= $minimo): ?>
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    <?else: ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                    <?php endif; ?>
                                    <?= e(texto_stock($stock, $minimo, (string) $p['unidad'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--texto-suave);" title="Preparación: no lleva control de stock">
                                    <i class="bi bi-dash-circle"></i> No lleva stock
                                </span>
                            <?php endif; ?>
                        </td>

                        <?php if ($esAdmin): ?>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 justify-content-end">
                                    <a href="<?= e(url('productos/formulario') . '&id=' . (int) $p['id']) ?>"
                                       class="btn btn-atilio-amarillo btn-sm" title="Editar producto">
                                        <i class="bi bi-pencil-fill"></i>
                                        <span class="d-none d-xl-inline">Editar</span>
                                    </a>

                                    <form method="post" action="<?= e(url('productos/acciones')) ?>" class="d-inline">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit"
                                                class="btn <?= $activo ? 'btn-peligro' : 'btn-atilio' ?> btn-sm"
                                                data-confirmar="<?= $activo
                                                    ? '¿Desactivar "' . e((string) $p['nombre']) . '"? Deja de aparecer en la carta.'
                                                    : '¿Activar "' . e((string) $p['nombre']) . '"?' ?>">
                                            <i class="bi bi-<?= $activo ? 'slash-circle-fill' : 'check-circle-fill' ?>"></i>
                                            <span class="d-none d-xl-inline"><?= $activo ? 'Desactivar' : 'Activar' ?></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($esAdmin): ?>
        <p class="form-text mt-2">
            <i class="bi bi-info-circle-fill"></i>
            Desactivar es mejor que borrar: el producto conserva su historial de
            stock y, más adelante, sus ventas. Para sacar algo de la carta de
            forma permanente conviene desactivarlo.
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>