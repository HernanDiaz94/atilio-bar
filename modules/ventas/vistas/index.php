<?php
/**
 * modules/ventas/vistas/index.php
 * -----------------------------------------------------------------
 * Listado de ventas.
 *
 * Variables: $ventas, $totales, $desde, $hasta, $rangoValido,
 *            $pagina, $totalPaginas, $porPagina
 * -----------------------------------------------------------------
*/

$titulo    = 'Ventas';
$subtitulo = 'Historial de cobros';

$accionesCabecera = '';

require APP_ROOT . '/includes/header.php';
?>

<!-- ============ FILTRO ============ -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cuerpo">
        <form method="get" action="<?= e(url_archivo('index.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="ventas">

            <div class="col-6 col-md-4">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>

            <div class="col-6 col-md-4">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>

            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-atilio flex-grow-1">
                    <i class="bi bi-search"></i> Ver
                </button>
            </div>
        </form>

        <div class="d-flex gap-1 flex-wrap mt-2">
            <a href="<?= e(url('ventas')) ?>" class="btn btn-atilio-amarillo btn-sm">Hoy</a>
            <a href="<?= e(url('ventas') . '&desde=' . date('Y-m-d', strtotime('-6 days')) . '&hasta=' . date('Y-m-d')) ?>"
               class="btn btn-atilio-amarillo btn-sm">7 días</a>
            <a href="<?= e(url('ventas') . '&desde=' . date('Y-m-d', strtotime('first day of this month')) . '&hasta=' . date('Y-m-d')) ?>"
               class="btn btn-atilio-amarillo btn-sm">Este mes</a>
        </div>
    </div>
</div>

<?php if (!$rangoValido): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-4">
            <span style="color: var(--texto-suave);">Elegí un rango de fechas.</span>
        </div>
    </div>
<?php else: ?>

    <!-- ============ TOTALES DEL RANGO ============ -->
    <div class="resumen-mesas mb-3">
        <span class="resumen-pill estado-neutra">
            <i class="bi bi-receipt"></i>
            <?= (int) $totales['ventas'] ?>
            <?= ((int) $totales['ventas'] === 1) ? 'venta' : 'ventas' ?>
        </span>
        <span class="resumen-pill estado-libre">
            <i class="bi bi-cash-coin"></i>
            <?= e(money($totales['neto'])) ?> cobrados
        </span>
        <?php if ((float) $totales['descuentos'] > 0): ?>
            <span class="resumen-pill estado-ocupada">
                <i class="bi bi-percent"></i>
                <?= e(money($totales['descuentos'])) ?> de descuentos
            </span>
        <?php endif; ?>
    </div>

    <!-- ============ LISTA ============ -->
    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <span>
                <i class="bi bi-list-ul"></i>
                Ventas del <?= e(fecha_corta($desde)) ?>
                <?= $desde !== $hasta ? 'al ' . e(fecha_corta($hasta)) : '' ?>
            </span>
        </div>

        <?php if (empty($ventas)): ?>
            <div class="tarjeta-cuerpo centrado py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p class="fw-bold mb-1">No hay ventas en esas fechas</p>
                <span style="color: var(--texto-suave);">
                    Cuando se cobre una mesa, va a aparecer acá.
                </span>
            </div>
        <?php else: ?>
            <div class="tabla-envoltura">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Venta</th>
                            <th>Mesa</th>
                            <th>Cobró</th>
                            <th>Medios</th>
                            <th class="text-end">Bruto</th>
                            <th class="text-end">Descuento</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <?php $anulada = $venta['estado'] === 'anulada'; ?>
                            <tr class="<?= $anulada ? 'producto-inactivo' : '' ?>">
                                <td><?= e(hora((string) $venta['cobrada_en'])) ?></td>
                                <td>
                                    <a href="<?= e(url('ventas/ver') . '&id=' . (int) $venta['id']) ?>">
                                        <?= e((string) $venta['numero']) ?>
                                    </a>
                                    <?php if ($anulada): ?>
                                        <br>
                                        <span class="estado-anulada">
                                            <i class="bi bi-x-octagon-fill"></i> Anulada
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $venta['mesa_numero']) ?></td>
                                <td style="color: var(--texto-suave); font-size: 0.9rem;">
                                    <?= e((string) $venta['cobrada_por']) ?>
                                </td>
                                <td style="color: var(--texto-suave); font-size: 0.9rem;">
                                    <?= e((string) ($venta['medios'] ?? '')) ?>
                                </td>
                                <td class="text-end"><?= e(money($venta['total_bruto'])) ?></td>
                                <td class="text-end">
                                    <?php if ((float) $venta['descuento_importe'] > 0): ?>
                                        <?= e(money(-$venta['descuento_importe'])) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong><?= e(money($venta['total_neto'])) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ============ PAGINACIÓN ============ -->
            <?php if ($totalPaginas > 1): ?>
                <div class="tarjeta-cuerpo">
                    <nav class="paginacion">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= e(url('ventas') . '&desde=' . $desde . '&hasta=' . $hasta . '&pagina=' . ($pagina - 1)) ?>">
                                <i class="bi bi-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <span>
                            Página <?= (int) $pagina ?> de <?= (int) $totalPaginas ?>
                        </span>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="<?= e(url('ventas') . '&desde=' . $desde . '&hasta=' . $hasta . '&pagina=' . ($pagina + 1)) ?>">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
