<?php
/**
 * modules/stock/vistas/compras.php
 * -----------------------------------------------------------------
 * Vista del listado de compras (solo administradores).
 * -----------------------------------------------------------------
*/

$titulo    = 'Compras';
$subtitulo = 'Ingresos de mercadería';

$accionesCabecera = '<a href="' . e(url('compras/nueva')) . '" class="btn btn-atilio-oscuro">'
    . '<i class="bi bi-plus-lg"></i> Nueva compra</a>';

require APP_ROOT . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-6">
        <div class="tarjeta-valor">
            <p class="etiqueta">Compras registradas</p>
            <p class="valor"><?= e((string) $totalCompras) ?></p>
            <span class="detalle">Desde que arrancó el sistema</span>
        </div>
    </div>
    <div class="col-6 col-lg-6">
        <div class="tarjeta-valor">
            <p class="etiqueta">Total comprado</p>
            <p class="valor"><?= e(money($totalGastado)) ?></p>
            <span class="detalle">Suma de todas las compras</span>
        </div>
    </div>
</div>

<!-- ================= FILTROS ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cuerpo">
        <form method="get" action="<?= e(url_archivo('index.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="r" value="compras">

            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Buscar</label>
                <input type="text" id="q" name="q" class="form-control"
                       value="<?= e($buscar) ?>" placeholder="Proveedor o comprobante">
            </div>

            <div class="col-6 col-md-3">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>

            <div class="col-6 col-md-3">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-atilio flex-grow-1">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <?php if ($buscar !== '' || $desde !== '' || $hasta !== ''): ?>
                    <a href="<?= e(url('compras')) ?>" class="btn btn-atilio-amarillo">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ================= LISTA ================= -->
<?php if (empty($compras)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-truck fs-1 d-block mb-3"></i>
            <p class="fw-bold mb-1">No hay compras registradas</p>
            <p style="color: var(--texto-suave);">
                <?php if ($buscar !== '' || $desde !== '' || $hasta !== ''): ?>
                    Probá sin filtros.
                <?php else: ?>
                    Cuando mercaderías, cargá la primera compra para
                    sumar stock y actualizar los costos.
                <?php endif; ?>
            </p>
            <a href="<?= e(url('compras/nueva')) ?>" class="btn btn-atilio-oscuro mt-2">
                <i class="bi bi-plus-lg"></i> Cargar una compra
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Comprobante</th>
                    <th class="text-end">Ítems</th>
                    <th class="text-end">Total</th>
                    <th>Cargada por</th>
                    <th class="text-end">Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($compras as $compra): ?>
                    <tr>
                        <td><?= e(fecha_corta((string) $compra['fecha'])) ?></td>
                        <td>
                            <strong><?= e($compra['proveedor'] !== '' ? (string) $compra['proveedor'] : 'Sin proveedor') ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($compra['numero_comprobante'])): ?>
                                <span class="mono"><?= e((string) $compra['numero_comprobante']) ?></span>
                            <?php else: ?>
                                <span style="color: var(--texto-suave);">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= (int) $compra['lineas'] ?></td>
                        <td class="text-end">
                            <strong><?= e(money($compra['total'])) ?></strong>
                        </td>
                        <td>
                            <?= e((string) ($compra['usuario_nombre'] ?? 'sistema')) ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('compras/ver') . '&id=' . (int) $compra['id']) ?>"
                               class="btn btn-atilio-amarillo btn-sm">
                                <i class="bi bi-eye-fill"></i>
                                <span class="d-none d-md-inline">Detalle</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="form-text mt-2">
        <i class="bi bi-info-circle-fill"></i>
        Se muestran las últimas 200 compras. Para llegar más atrás, usá los
        filtros de fecha.
    </p>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>