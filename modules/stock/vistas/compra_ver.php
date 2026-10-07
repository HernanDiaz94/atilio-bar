<?php
/**
 * modules/stock/vistas/compra_ver.php
 * -----------------------------------------------------------------
 * Vista del detalle de una compra (solo administradores).
 * -----------------------------------------------------------------
*/

$titulo    = 'Compra #' . (int) $compra['id'];
$subtitulo = ($compra['proveedor'] !== '' ? (string) $compra['proveedor'] : 'Sin proveedor')
    . ' · ' . fecha_corta((string) $compra['fecha']);

$accionesCabecera = '<a href="' . e(url('compras')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a las compras</a>';

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= CABECERA ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-truck"></i>
            <?= e((string) ($compra['proveedor'] !== '' ? $compra['proveedor'] : 'Compra sin proveedor')) ?>
        </span>
        <strong><?= e(money($compra['total'])) ?></strong>
    </div>

    <div class="tarjeta-cuerpo">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <span class="form-text d-block">Fecha</span>
                <strong><?= e(fecha_corta((string) $compra['fecha'])) ?></strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="form-text d-block">Comprobante</span>
                <strong>
                    <?php if (!empty($compra['numero_comprobante'])): ?>
                        <span class="mono"><?= e((string) $compra['numero_comprobante']) ?></span>
                    <?php else: ?>
                        <span style="color: var(--texto-suave);">No informado</span>
                    <?php endif; ?>
                </strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="form-text d-block">Cargada por</span>
                <strong>
                    <?= e((string) ($compra['usuario_nombre'] ?? 'sistema')) ?>
                </strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="form-text d-block">Ítems</span>
                <strong><?= e((string) count($lineas)) ?></strong>
            </div>

            <?php if (!empty($compra['observaciones'])): ?>
                <div class="col-12">
                    <span class="form-text d-block">Observaciones</span>
                    <?= e((string) $compra['observaciones']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ================= LÍNEAS ================= -->
<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <span><i class="bi bi-list-ul"></i> Qué entró</span>
        <strong><?= e(money($compra['total'])) ?></strong>
    </div>

    <div class="tabla-envoltura">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Costo unit.</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Costo antes</th>
                    <th class="text-end">Costo ahora</th>
                    <th class="text-end">Stock hoy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineas as $linea): ?>
                    <?php
                    $subtotal = (float) $linea['subtotal'];
                    $cantidad = (float) $linea['cantidad'];
                    $costoAnt = $linea['costo_anterior'] === null ? null : (float) $linea['costo_anterior'];
                    $costoNue = $linea['costo_nuevo'] === null ? null : (float) $linea['costo_nuevo'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $linea['producto_nombre']) ?></strong>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e((string) $linea['categoria']) ?>
                            </small>
                        </td>

                        <td class="text-end">
                            <strong class="texto-ganancia">
                                +<?= e(cantidad($cantidad)) ?>
                            </strong>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e(plural((string) $linea['unidad'], $cantidad)) ?>
                            </small>
                        </td>

                        <td class="text-end"><?= e(money($linea['costo_unitario'])) ?></td>

                        <td class="text-end">
                            <strong><?= e(money($subtotal)) ?></strong>
                        </td>

                        <td class="text-end">
                            <?php if ($costoAnt === null): ?>
                                <span style="color: var(--texto-suave);" title="No se conocía el costo antes">
                                    sin dato
                                </span>
                            <?php else: ?>
                                <?= e(money($costoAnt)) ?>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <?php if ($costoNue === null): ?>
                                <span style="color: var(--texto-suave);">-</span>
                            <?php else: ?>
                                <strong><?= e(money($costoNue)) ?></strong>
                                <?php if ($costoAnt !== null && abs($costoNue - $costoAnt) >= 0.01): ?>
                                    <br>
                                    <small style="color: var(--texto-suave);" title="Costo promedio ponderado">
                                        promedio
                                    </small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <?= e(cantidad((float) $linea['stock_hoy'])) ?>
                            <br>
                            <small style="color: var(--texto-suave);">
                                <?= e((string) $linea['unidad']) ?>
                            </small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!--
    Aclaración sobre el costo promedio:
    cuando ya había costo y llega mercadería a un precio distinto,
    el costo NO se reemplaza: se promedia. Con 10 botellas a $100 y
    10 nuevas a $120, el costo queda en $110, no en $120. Así el
    margen que se calcula después es real.
-->
<div class="marco-amarillo p-3 mt-3 small">
    <strong><i class="bi bi-calculator-fill"></i> Sobre el costo:</strong>
    <p class="mb-0 mt-1">
        Si un producto ya tenía costo cargado, esta compra actualizó el
        <strong>costo promedio ponderado</strong>: se promedia el valor que
        había en el depósito con el de lo que entró. Si no tenía costo,
        se adoptó directamente el de esta compra. Por eso el costo puede
        quedar entre los dos valores y no ser igual al de la factura.
    </p>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>