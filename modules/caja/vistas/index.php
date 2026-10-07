<?php
/**
 * modules/caja/vistas/index.php
 * -----------------------------------------------------------------
 * La caja del día.
 *
 * Variables: $caja, $esperado, $porMetodo, $ventas, $movimientos,
 *            $pendientes, $historial, $esAdmin
 * -----------------------------------------------------------------
*/

$titulo    = 'Caja';
$subtitulo = $caja !== null
    ? 'Abierta ' . fecha_hora((string) $caja['abierto_en']) . ' por ' . (string) $caja['abierta_por']
    : 'No hay ninguna caja abierta';

$accionesCabecera = '';

if ($caja !== null) {
    $accionesCabecera = '<a href="' . e(url('caja/abrir')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-plus-circle"></i> Registrar movimiento</a>';

    if ($esAdmin) {
        $accionesCabecera .= ' <a href="' . e(url('caja/cerrar')) . '" class="btn btn-atilio-amarillo">'
            . '<i class="bi bi-lock"></i> Cerrar caja</a>';
    }
} elseif ($esAdmin) {
    $accionesCabecera = '<a href="' . e(url('caja/abrir')) . '" class="btn btn-atilio-oscuro">'
        . '<i class="bi bi-unlock"></i> Abrir caja</a>';
}

require APP_ROOT . '/includes/header.php';
?>

<?php if ($caja === null): ?>
    <!-- ============ SIN CAJA ============ -->
    <div class="tarjeta">
        <div class="tarjeta-cuerpo py-4 centrado">
            <i class="bi bi-safe fs-1 d-block mb-3"></i>

            <?php if ($esAdmin): ?>
                <p class="fw-bold mb-1">No hay ninguna caja abierta</p>
                <p style="color: var(--texto-suave);">
                    Contá cuánta plata hay en el cajón y abrí el turno.
                    Con esa cantidad el sistema va a ir sumando lo que
                    se cobre, y al cerrar te va a decir cuánto debería
                    haber.
                </p>
                <a href="<?= e(url('caja/abrir')) ?>" class="btn btn-atilio-oscuro btn-grande mt-3">
                    <i class="bi bi-unlock-fill"></i> Abrir la caja
                </a>
            <?php else: ?>
                <p class="fw-bold mb-1">La caja está cerrada</p>
                <p style="color: var(--texto-suave);">
                    No se puede cobrar hasta que el administrador abra
                    un turno nuevo.
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>

    <!-- ============ RESUMEN DEL TURNO ============ -->
    <div class="row g-3">

        <div class="col-12 col-lg-7">
            <div class="tarjeta-caja mb-3">
                <div class="tarjeta-cabecera">
                    <span>
                        <i class="bi bi-safe-fill"></i>
                        <?= e((string) ($caja['nombre'] !== '' ? $caja['nombre'] : 'Caja del día')) ?>
                    </span>
                    <span class="small">
                        abierta <?= e(hora((string) $caja['abierto_en'])) ?>
                        por <?= e((string) $caja['abierta_por']) ?>
                    </span>
                </div>

                <div class="tarjeta-cuerpo">
                    <!-- El número grande es el que tiene que estar
                         siempre a la vista. -->
                    <div class="monto-caja">
                        <span>Hay en el cajón ahora</span>
                        <strong><?= e(money($esperado['esperado'])) ?></strong>
                    </div>

                    <!-- El desglose importa: si al final falta
                         plata, con ver de dónde sale el número uno
                         se da cuenta al toque. -->
                    <div class="desglose-caja">
                        <div class="desglose-fila">
                            <span>Con lo que había al abrir</span>
                            <strong><?= e(money($esperado['apertura'])) ?></strong>
                        </div>
                        <div class="desglose-fila">
                            <span>Efectivo cobrado (<?= (int) $esperado['ventas'] ?>
                                  <?= ((int) $esperado['ventas'] === 1) ? 'venta' : 'ventas' ?>)</span>
                            <strong class="text-end"><?= e(money($esperado['efectivo'])) ?></strong>
                        </div>
                        <?php if ($esperado['ingresos'] > 0): ?>
                            <div class="desglose-fila">
                                <span>Entradas sueltas</span>
                                <strong><?= e(money($esperado['ingresos'])) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($esperado['retiros'] > 0): ?>
                            <div class="desglose-fila">
                                <span>Retiros del dueño</span>
                                <strong class="desglose-resta"><?= e(money(-$esperado['retiros'])) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($esperado['gastos'] > 0): ?>
                            <div class="desglose-fila">
                                <span>Gastos del bar</span>
                                <strong class="desglose-resta"><?= e(money(-$esperado['gastos'])) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($porMetodo)): ?>
                        <h4 class="subtitulo-panel">Cobrado por medio</h4>
                        <div class="medios-resumen">
                            <?php foreach ($porMetodo as $fila): ?>
                                <div class="medio-resumen-fila">
                                    <i class="bi <?= (int) $fila['afecta_caja'] === 1
                                        ? 'bi-cash-stack' : 'bi-credit-card' ?>"></i>
                                    <span><?= e((string) $fila['nombre']) ?></span>
                                    <small class="text-muted">
                                        <?= (int) $fila['operaciones'] ?>
                                        <?= ((int) $fila['operaciones'] === 1) ? 'cobro' : 'cobros' ?>
                                    </small>
                                    <strong><?= e(money($fila['total'])) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============ VENTAS DEL TURNO ============ -->
            <div class="tarjeta">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-receipt"></i> Ventas del turno</span>
                    <span class="small"><?= (int) count($ventas) ?></span>
                </div>

                <?php if (empty($ventas)): ?>
                    <div class="tarjeta-cuerpo centrado py-4">
                        <span style="color: var(--texto-suave);">
                            Todavía no se cobró nada en este turno.
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
                                    <th>Medio</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr class="<?= $venta['estado'] === 'anulada' ? 'producto-inactivo' : '' ?>">
                                        <td><?= e(hora((string) $venta['cobrada_en'])) ?></td>
                                        <td>
                                            <?php if ($esAdmin): ?>
                                                <a href="<?= e(url('ventas/ver') . '&id=' . (int) $venta['id']) ?>">
                                                    <?= e((string) $venta['numero']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?= e((string) $venta['numero']) ?>
                                            <?php endif; ?>
                                            <?php if ($venta['estado'] === 'anulada'): ?>
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
                                        <td class="text-end">
                                            <strong><?= e(money($venta['total_neto'])) ?></strong>
                                            <?php if ((float) $venta['descuento_importe'] > 0): ?>
                                                <br>
                                                <small style="color: var(--texto-suave);">
                                                    <?= e(money($venta['descuento_importe'])) ?> de descuento
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-5">

            <!-- ============ MESAS POR COBRAR ============ -->
            <?php if (!empty($pendientes)): ?>
                <div class="tarjeta mb-3">
                    <div class="tarjeta-cabecera">
                        <span style="color: #8A1B1B;">
                            <i class="bi bi-hourglass-split"></i>
                            Esperando el cobro
                        </span>
                        <span class="small"><?= (int) count($pendientes) ?></span>
                    </div>
                    <div class="tarjeta-cuerpo">
                        <?php foreach (array_slice($pendientes, 0, 6) as $pedido): ?>
                            <a href="<?= e(url('cobrar') . '&id=' . (int) $pedido['id']) ?>"
                               class="fila-cobrar fila-cobrar-compacta">
                                <span class="fila-cobrar-mesa">
                                    <?= e((string) $pedido['mesa_numero']) ?>
                                </span>
                                <span class="fila-cobrar-total">
                                    <?= e(money($pedido['total'])) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ============ MOVIMIENTOS ============ -->
            <div class="tarjeta">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-arrow-left-right"></i> Entradas y salidas</span>
                    <a href="<?= e(url('caja/abrir')) ?>" class="small">
                        <i class="bi bi-plus-lg"></i> Agregar
                    </a>
                </div>

                <?php if (empty($movimientos)): ?>
                    <div class="tarjeta-cuerpo centrado py-4">
                        <span style="color: var(--texto-suave);">
                            Nadie sacó ni puso plata en este turno.
                        </span>
                    </div>
                <?php else: ?>
                    <div class="tarjeta-cuerpo">
                        <ul class="lista-movimientos">
                            <?php foreach ($movimientos as $movimiento): ?>
                                <li>
                                    <i class="bi <?= $movimiento['tipo'] === 'ingreso'
                                        ? 'bi-plus-circle-fill' : 'bi-dash-circle-fill' ?> mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong><?= e(Caja::MOVIMIENTOS[$movimiento['tipo']] ?? '') ?></strong>
                                        <br>
                                        <span style="color: var(--texto-suave); font-size: 0.9rem;">
                                            <?= e((string) $movimiento['motivo']) ?>
                                            · <?= e((string) $movimiento['usuario']) ?>
                                            · <?= e(fecha_hora((string) $movimiento['creado_en'])) ?>
                                        </span>
                                    </div>
                                    <strong class="<?= $movimiento['tipo'] === 'ingreso'
                                        ? 'desglose-suma' : 'desglose-resta' ?>">
                                        <?= e(money($movimiento['tipo'] === 'ingreso'
                                            ? $movimiento['importe'] : -$movimiento['importe'])) ?>
                                    </strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============ HISTORIAL DE CAJAS ============ -->
<?php if ($esAdmin && !empty($historial)): ?>
    <div class="tarjeta mt-3">
        <div class="tarjeta-cabecera">
            <span><i class="bi bi-clock-history"></i> Turnos anteriores</span>
        </div>

        <div class="tabla-envoltura">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Apertura</th>
                        <th>Cierre</th>
                        <th>Abrió</th>
                        <th class="text-end">Ventas</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Esperado</th>
                        <th class="text-end">Contado</th>
                        <th class="text-end">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $turno): ?>
                        <?php $dif = $turno['diferencia'] === null ? null : (float) $turno['diferencia']; ?>
                        <tr>
                            <td><?= e(fecha_hora((string) $turno['abierto_en'])) ?></td>
                            <td>
                                <?= $turno['cerrado_en'] !== null
                                    ? e(fecha_hora((string) $turno['cerrado_en']))
                                    : '<span class="estado-libre">abierta</span>' ?>
                            </td>
                            <td style="color: var(--texto-suave); font-size: 0.9rem;">
                                <?= e((string) $turno['abierta_por']) ?>
                            </td>
                            <td class="text-end"><?= (int) $turno['ventas'] ?></td>
                            <td class="text-end"><?= e(money($turno['total_vendido'])) ?></td>
                            <td class="text-end">
                                <?= $turno['esperado_calculado'] !== null
                                    ? e(money($turno['esperado_calculado']))
                                    : '—' ?>
                            </td>
                            <td class="text-end">
                                <?= $turno['cierre_importe'] !== null
                                    ? e(money($turno['cierre_importe']))
                                    : '—' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($dif === null): ?>
                                    —
                                <?php elseif ($dif == 0): ?>
                                    <span class="estado-libre">
                                        <i class="bi bi-check-circle-fill"></i> Cuadra
                                    </span>
                                <?php elseif ($dif > 0): ?>
                                    <span class="estado-por-cobrar">
                                        <i class="bi bi-plus-circle-fill"></i>
                                        Sobraron <?= e(money($dif)) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #8A1B1B; font-weight: 700;">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        Faltaron <?= e(money(-$dif)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
