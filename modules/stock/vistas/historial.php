<?php
/**
 * modules/stock/vistas/historial.php
 * -----------------------------------------------------------------
 * Vista del historial de movimientos de stock.
 *
 * Variables: $esAdmin, $movimientos, $filtros, $productos,
 *            $pagina, $totalPaginas, $totalMovimientos, $resumen
 * -----------------------------------------------------------------
*/

$titulo    = 'Stock';
$subtitulo = 'Todos los movimientos, uno por uno';

$accionesCabecera = '';

if ($esAdmin) {
    $accionesCabecera =
        '<a href="' . e(url('compras')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-truck"></i> Compras</a> '
        . '<a href="' . e(url('stock/ajuste')) . '" class="btn btn-atilio-oscuro">'
        . '<i class="bi bi-sliders"></i> Ajustar stock</a>';
}

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= RESUMEN (solo admin) ================= -->
<?php if ($esAdmin): ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Valor del stock</p>
                <p class="valor"><?= e(money($resumen['valor'] ?? 0)) ?></p>
                <span class="detalle">A costo de compra</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor <?= ((int) $resumen['total_bajo'] > 0) ? 'acento-amarillo' : '' ?>">
                <p class="etiqueta">Stock bajo</p>
                <p class="valor"><?= e((string) $resumen['total_bajo']) ?></p>
                <span class="detalle">Hay que reponer</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor <?= ((int) $resumen['total_cero'] > 0) ? 'acento-amarillo' : '' ?>">
                <p class="etiqueta">Sin stock</p>
                <p class="valor"><?= e((string) $resumen['total_cero']) ?></p>
                <span class="detalle">No se pueden vender</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Movimientos</p>
                <p class="valor"><?= e((string) $totalMovimientos) ?></p>
                <span class="detalle">En el filtro actual</span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ================= FILTROS ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cuerpo">
        <form method="get" action="<?= e(url_archivo('index.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="stock">

            <div class="col-12 col-md-4">
                <label for="producto_id" class="form-label">Producto</label>
                <select id="producto_id" name="producto_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filtros['producto_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e((string) $p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="tipo" class="form-label">Tipo</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (Stock::TIPOS as $tipoMovimiento): ?>
                        <option value="<?= e($tipoMovimiento) ?>" <?= $filtros['tipo'] === $tipoMovimiento ? 'selected' : '' ?>>
                            <?= e(texto_tipo_movimiento($tipoMovimiento)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control"
                       value="<?= e((string) $filtros['desde']) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control"
                       value="<?= e((string) $filtros['hasta']) ?>">
            </div>

            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-atilio flex-grow-1">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <?php if ($filtros['producto_id'] || $filtros['tipo'] || $filtros['desde'] || $filtros['hasta']): ?>
                    <a href="<?= e(url('stock')) ?>" class="btn btn-atilio-amarillo">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ================= MOVIMIENTOS ================= -->
<?php if (empty($movimientos)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <p class="mb-1 fw-bold">No hay movimientos con estos filtros</p>
            <p style="color: var(--texto-suave);">
                <?php if ($filtros['producto_id'] || $filtros['tipo'] || $filtros['desde'] || $filtros['hasta']): ?>
                    Probá sin filtros, o cambiá el rango de fechas.
                <?php else: ?>
                    Cuando se cree un producto, cargue una compra o se haga un
                    ajuste, los movimientos aparecen acá.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Stock</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-end">Costo</th>
                    <?php endif; ?>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $m): ?>
                    <?php
                    $cantidad    = (float) $m['cantidad'];
                    $esSuma      = $cantidad > 0;
                    $porProducto = (float) $m['stock_anterior'];
                    ?>
                    <tr>
                        <td>
                            <?= e(fecha_corta((string) $m['creado_en'])) ?>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e(hora((string) $m['creado_en'])) ?>
                            </small>
                        </td>

                        <td>
                            <?php if ($esAdmin): ?>
                                <a href="<?= e(url('stock') . '&producto_id=' . (int) $m['producto_id']) ?>">
                                    <?= e((string) $m['producto_nombre']) ?>
                                </a>
                            <?php else: ?>
                                <?= e((string) $m['producto_nombre']) ?>
                            <?php endif; ?>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e((string) $m['categoria']) ?>
                            </small>
                        </td>

                        <td>
                            <span class="<?= e(clase_tipo_movimiento((string) $m['tipo'])) ?>">
                                <?= icono_tipo_movimiento((string) $m['tipo']) ?>
                                <?= e(texto_tipo_movimiento((string) $m['tipo'])) ?>
                            </span>
                        </td>

                        <td class="text-end">
                            <strong class="<?= $esSuma ? 'texto-ganancia' : 'texto-perdida' ?>">
                                <?= $esSuma ? '+' : '' ?><?= e(cantidad($cantidad)) ?>
                            </strong>
                        </td>

                        <td class="text-end">
                            <?php if ($porProducto !== (float) $m['stock_resultante']): ?>
                                <small style="color: var(--texto-suave);"><?= e(cantidad($porProducto)) ?></small>
                                <i class="bi bi-arrow-right"></i>
                            <?php endif; ?>
                            <strong><?= e(cantidad((float) $m['stock_resultante'])) ?></strong>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e((string) $m['unidad']) ?>
                            </small>
                        </td>

                        <?php if ($esAdmin): ?>
                            <td class="text-end">
                                <?php if ($m['costo_unitario'] !== null): ?>
                                    <?= e(money($m['costo_unitario'])) ?>
                                <?php else: ?>
                                    <span style="color: var(--texto-suave);">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>

                        <td>
                            <?php if (!empty($m['motivo'])): ?>
                                <?= e(recortar((string) $m['motivo'], 60)) ?>
                            <?php else: ?>
                                <span style="color: var(--texto-suave);">-</span>
                            <?php endif; ?>
                            <?php if (!empty($m['usuario_nombre'])): ?>
                                <br>
                                <small style="color: var(--texto-suave);">
                                    por <?= e((string) $m['usuario_nombre']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINACIÓN ================= -->
    <?php if ($totalPaginas > 1): ?>
        <?php
        // Se conservan los filtros al cambiar de página.
        $basePagina = url('stock');

        if ($filtros['producto_id']) {
            $basePagina .= '&producto_id=' . (int) $filtros['producto_id'];
        }

        if ($filtros['tipo']) {
            $basePagina .= '&tipo=' . urlencode($filtros['tipo']);
        }

        if ($filtros['desde']) {
            $basePagina .= '&desde=' . urlencode($filtros['desde']);
        }

        if ($filtros['hasta']) {
            $basePagina .= '&hasta=' . urlencode($filtros['hasta']);
        }
        ?>
        <nav class="mt-3" aria-label="Paginación">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e($basePagina . '&pagina=' . max(1, $pagina - 1)) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                // Ventana de páginas alrededor de la actual
                $desde = max(1, $pagina - 2);
                $hasta = min($totalPaginas, $pagina + 2);

                if ($desde > 1) {
                    echo '<li class="page-item"><a class="page-link" href="'
                        . e($basePagina . '&pagina=1') . '">1</a></li>';

                    if ($desde > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($n = $desde; $n <= $hasta; $n++) {
                    $activa = ($n === $pagina) ? ' active' : '';
                    echo '<li class="page-item' . $activa . '"><a class="page-link" href="'
                        . e($basePagina . '&pagina=' . $n) . '">' . $n . '</a></li>';
                }

                if ($hasta < $totalPaginas) {
                    if ($hasta < $totalPaginas - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }

                    echo '<li class="page-item"><a class="page-link" href="'
                        . e($basePagina . '&pagina=' . $totalPaginas) . '">' . $totalPaginas . '</a></li>';
                }
                ?>

                <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= e($basePagina . '&pagina=' . min($totalPaginas, $pagina + 1)) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>

            <p class="text-center form-text">
                Página <?= e((string) $pagina) ?> de <?= e((string) $totalPaginas) ?>
                &middot; <?= e((string) $totalMovimientos) ?> movimiento(s)
            </p>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>