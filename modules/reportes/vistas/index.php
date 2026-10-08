<?php
/**
 * modules/reportes/vistas/index.php
 * -----------------------------------------------------------------
 * La pantalla de reportes.
 *
 * Pensada para el teléfono: botones grandes, una cosa por pantalla y
 * los números con la fecha al lado. El dueño la va a mirar de pie,
 * con una mano ocupada y sin paciencia.
 * -----------------------------------------------------------------
 *
 * @var array $vistas    Reportes::vistas()
 * @var string $vista    La elegida
 * @var array $rango     Rango normalizado
 * @var array $resumen   Totales del período
 * @var array $datos     Los del reporte elegido
 * @var array $avisosCosto  Lo que hay que avisar igual
 */

$periodos    = Reportes::periodos();
$etiqueta    = Reportes::etiquetaRango($rango);
$maxDias     = Reportes::maxDias();
$hayDatos    = false;

$titulo    = 'Reportes';
$subtitulo = $etiqueta . ' · ' . (int) $rango['dias']
    . ' ' . ((int) $rango['dias'] === 1 ? 'día' : 'días');

// El boton de descargar va arriba, a la derecha del titulo, para
// que se llegue con el pulgar y no haya que bajar hasta el final.
$accionesCabecera = '<a href="' . e($linkCsv) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-download"></i> Descargar CSV</a>';

// El link al CSV se arma primero porque va en la cabecera.
$csvParams = ['vista' => $vista];

if ($rango['periodo'] !== '') {
    $csvParams['periodo'] = $rango['periodo'];
} else {
    $csvParams['desde'] = $rango['desde'];
    $csvParams['hasta'] = $rango['hasta'];
}

$linkCsv = url('reportes/csv?' . http_build_query($csvParams));

/** Link al mismo reporte, cambiando período o vista. */
$link = static function (array $params) use ($vista, $rango): string {
    $base = ['vista' => $vista];

    // Si el período quedó como un botón, se manda el botón; si lo
    // escribieron a mano, se mandan las fechas. Nunca los dos: si no
    // se pisan entre sí.
    if ($rango['periodo'] !== '') {
        $base['periodo'] = $rango['periodo'];
    } else {
        $base['desde'] = $rango['desde'];
        $base['hasta'] = $rango['hasta'];
    }

    return url('reportes?' . http_build_query(array_merge($base, $params)));
};

/** Un número grande, con su nombre arriba. */
$figura = static function (string $etiqueta, string $valor, string $nota = '', string $clase = '') {
    ?>
    <div class="reporte-figura<?= $clase !== '' ? ' ' . $clase : '' ?>">
        <span class="reporte-figura-etiqueta"><?= e($etiqueta) ?></span>
        <strong class="reporte-figura-valor"><?= e($valor) ?></strong>
        <?php if ($nota !== ''): ?>
            <span class="reporte-figura-nota"><?= e($nota) ?></span>
        <?php endif; ?>
    </div>
    <?php
};
require APP_ROOT . '/includes/header.php';
?>

<!-- ========================= PERÍODO ========================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span><i class="bi bi-calendar-range"></i> Período</span>
    </div>
    <div class="tarjeta-cuerpo">
        <div class="botones-periodo">
            <?php foreach ($periodos as $clave => $periodo): ?>
                <a href="<?= e($link(['periodo' => $clave])) ?>"
                   class="btn-periodo<?= $rango['periodo'] === $clave ? ' activo' : '' ?>">
                    <?= e($periodo['etiqueta']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= e(url('reportes')) ?>" class="form-fechas">
            <input type="hidden" name="vista" value="<?= e($vista) ?>">

            <div class="campo-fecha">
                <label for="desde">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control"
                       value="<?= e($rango['desde']) ?>" max="<?= e(date('Y-m-d')) ?>">
            </div>

            <div class="campo-fecha">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control"
                       value="<?= e($rango['hasta']) ?>" max="<?= e(date('Y-m-d')) ?>">
            </div>

            <button type="submit" class="btn btn-atilio">
                <i class="bi bi-search"></i> Ver
            </button>
        </form>

        <p class="reporte-rango">
            Mostrando <strong><?= e($etiqueta) ?></strong>
            <span class="reporte-rango-dias"><?= (int) $rango['dias'] ?> <?= (int) $rango['dias'] === 1 ? 'día' : 'días' ?></span>
        </p>

        <?php foreach ($rango['avisos'] as $aviso): ?>
            <div class="alert alert-info" role="status">
                <i class="bi bi-info-circle"></i> <?= e($aviso) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================= TOTALES ========================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span><i class="bi bi-graph-up-arrow"></i> El período, en corto</span>
    </div>
    <div class="tarjeta-cuerpo">
        <div class="reporte-figuras">
            <?php
            $figura('Vendido', money($resumen['neto']), ($resumen['ventas']) . ' ' . ($resumen['ventas'] === 1 ? 'mesa cobrada' : 'mesas cobradas'));
            $figura('Ganancia', money($resumen['ganancia']),
                $resumen['porcentaje'] === null ? 'Sin ventas en el período' : porcentaje($resumen['porcentaje']) . ' de lo vendido',
                $resumen['ventas_sin_costo'] > 0 ? 'ganancia-parcial' : '');
            $figura('Costo de los productos', money($resumen['costo']), 'Lo que salió del depósito');
            ?>

            <?php if ($resumen['descuentos'] > 0): ?>
                <?php $figura('Descuentos', money($resumen['descuentos']), 'Plata que se dejó de cobrar'); ?>
            <?php endif; ?>

            <?php if ($resumen['comensales'] > 0): ?>
                <?php $figura('Comensales', number_format($resumen['comensales'], 0, ',', '.'),
                    $resumen['ventas'] > 0
                        ? money($resumen['neto'] / $resumen['comensales'], false) . ' por persona'
                        : ''); ?>
            <?php endif; ?>
        </div>

        <?php foreach ($avisosCosto as $aviso): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= e($aviso) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ========================= PESTAÑAS ========================= -->
<nav class="reporte-pestanas" aria-label="Tipos de reporte">
    <?php foreach ($vistas as $clave => $info): ?>
        <a href="<?= e($link(['vista' => $clave])) ?>"
           class="reporte-pestana<?= $vista === $clave ? ' activa' : '' ?>"
           <?= $vista === $clave ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= e($info['icono']) ?>"></i>
            <span><?= e($info['etiqueta']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- ========================= EL REPORTE ========================= -->
<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <span><i class="bi <?= e($vistas[$vista]['icono']) ?>"></i> <?= e($vistas[$vista]['etiqueta']) ?></span>

        <a href="<?= e($linkCsv) ?>" class="btn btn-sm btn-atilio-amarillo">
            <i class="bi bi-download"></i> CSV
        </a>
    </div>

    <div class="tarjeta-cuerpo">
        <?php
        // ==================================================
        //  POR DÍA
        // ==================================================
        if ($vista === 'dia'):
            $hayDatos = count($datos) > 0;
            ?>
            <?php if (!$hayDatos): ?>
                <p class="sin-datos">No se cobró ninguna mesa en este período.</p>
            <?php else: ?>
                <div class="tabla-envoltura">
                    <table class="tabla tabla-reporte">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th class="num">Mesas</th>
                                <th class="num">Vendido</th>
                                <th class="num">Costo</th>
                                <th class="num">Ganancia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos as $fila):
                                $neto      = (float) $fila['neto'];
                                $costo     = (float) $fila['costo'];
                                $ganancia  = $neto - $costo;
                                $parcial   = (int) $fila['ventas_sin_costo'] > 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e(fecha_corta($fila['dia'])) ?></strong>
                                        <?php if ((int) $fila['ventas'] > 0): ?>
                                            <small class="reporte-sub"><?= e(fecha_dia_semana($fila['dia'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?= (int) $fila['ventas'] ?></td>
                                    <td class="num"><strong><?= e(money($neto)) ?></strong></td>
                                    <td class="num"><?= e(money($costo)) ?></td>
                                    <td class="num <?= $parcial ? 'parcial' : '' ?>">
                                        <?= e(money($ganancia)) ?>
                                        <?php if ($parcial): ?>
                                            <i class="bi bi-exclamation-triangle" title="Falta costo en alguna venta"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php
        // ==================================================
        //  QUÉ SE VENDE MÁS
        // ==================================================
        elseif ($vista === 'productos'):
            $hayDatos = count($datos) > 0;
            ?>
            <?php if (!$hayDatos): ?>
                <p class="sin-datos">No se vendió nada en este período.</p>
            <?php else: ?>
                <p class="reporte-nota">
                    Para saber qué comprar: lo que está arriba es lo que la gente pidió.
                    Un producto que nunca aparece se puede dejar de comprar.
                </p>

                <div class="tabla-envoltura">
                    <table class="tabla tabla-reporte">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">Cantidad</th>
                                <th class="num">Vendido</th>
                                <th class="num">Ganancia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // La barra es relativa: se mide contra el
                            // producto que mas salio, no contra el
                            // total, asi se ven las diferencias.
                            $masUnidades = 0.0;

                            foreach ($datos as $fila) {
                                $masUnidades = max($masUnidades, (float) $fila['unidades']);
                            }
                            ?>
                            <?php foreach ($datos as $fila):
                                $unidades = (float) $fila['unidades'];
                                $ancho    = $masUnidades > 0 ? round(($unidades / $masUnidades) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="reporte-nombre"><?= e($fila['nombre']) ?></span>
                                        <span class="reporte-sub"><?= e($fila['categoria']) ?></span>
                                        <span class="reporte-barra" aria-hidden="true">
                                            <span style="width: <?= e((string) $ancho) ?>%"></span>
                                        </span>
                                    </td>
                                    <td class="num">
                                        <?= e(cantidad($unidades, $unidades == (int) $unidades ? 0 : 2)) ?>
                                        <small class="reporte-sub"><?= e($fila['unidad']) ?></small>
                                    </td>
                                    <td class="num"><strong><?= e(money((float) $fila['importe'])) ?></strong></td>
                                    <td class="num <?= $fila['parcial'] ? 'parcial' : '' ?>">
                                        <?= e(money((float) $fila['ganancia'])) ?>
                                        <?php if ($fila['parcial']): ?>
                                            <i class="bi bi-exclamation-triangle" title="Falta costo"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php
        // ==================================================
        //  MESAS Y ZONAS
        // ==================================================
        elseif ($vista === 'mesas'):
            $hayDatos = count($datos['mesas']) > 0;
            ?>
            <?php if (!$hayDatos): ?>
                <p class="sin-datos">No se cobró ninguna mesa en este período.</p>
            <?php else: ?>
                <h4 class="reporte-subtitulo">Por zona</h4>

                <div class="zonas">
                    <?php foreach ($datos['zonas'] as $zona): ?>
                        <div class="zona">
                            <span class="zona-etiqueta"><?= e(Mesas::ZONAS[$zona['zona']] ?? $zona['zona']) ?></span>
                            <strong class="zona-valor"><?= e(money($zona['neto'])) ?></strong>
                            <span class="zona-nota">
                                <?= (int) $zona['mesas'] ?> <?= (int) $zona['mesas'] === 1 ? 'mesa' : 'mesas' ?>
                                · <?= (int) $zona['ventas'] ?> <?= (int) $zona['ventas'] === 1 ? 'cobro' : 'cobros' ?>
                                · <?= e(money($zona['ganancia'])) ?> de ganancia
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h4 class="reporte-subtitulo">Por mesa</h4>

                <div class="tabla-envoltura">
                    <table class="tabla tabla-reporte">
                        <thead>
                            <tr>
                                <th>Mesa</th>
                                <th>Zona</th>
                                <th class="num">Cobros</th>
                                <th class="num">Vendido</th>
                                <th class="num">Ganancia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos['mesas'] as $mesa): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($mesa['numero']) ?></strong>
                                        <span class="reporte-sub"><?= e($mesa['nombre']) ?></span>
                                    </td>
                                    <td><?= e(Mesas::ZONAS[$mesa['zona']] ?? $mesa['zona']) ?></td>
                                    <td class="num"><?= (int) $mesa['ventas'] ?></td>
                                    <td class="num"><strong><?= e(money($mesa['neto'])) ?></strong></td>
                                    <td class="num <?= $mesa['parcial'] ? 'parcial' : '' ?>">
                                        <?= e(money($mesa['ganancia'])) ?>
                                        <?php if ($mesa['parcial']): ?>
                                            <i class="bi bi-exclamation-triangle" title="Falta costo en alguna venta"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php
        // ==================================================
        //  MEDIOS DE PAGO
        // ==================================================
        else:
            $hayDatos = count($datos) > 0;
            $enCaja   = 0.0;

            foreach ($datos as $medio) {
                if ($medio['afecta_caja']) {
                    $enCaja += $medio['importe'];
                }
            }
            ?>
            <?php if (!$hayDatos): ?>
                <p class="sin-datos">No se cobró nada en este período.</p>
            <?php else: ?>
                <div class="reporte-figuras">
                    <?php $figura('Entró al cajón', money($enCaja), 'Efectivo, que se cuenta al cerrar'); ?>
                    <?php
                    $tarjeta = 0.0;
                    foreach ($datos as $medio) {
                        if (!$medio['afecta_caja']) {
                            $tarjeta += $medio['importe'];
                        }
                    }
                    ?>
                    <?php $figura('Con tarjeta o transferencia', money($tarjeta), 'Esa plata nunca estuvo en el cajón'); ?>
                </div>

                <div class="medios-reporte">
                    <?php foreach ($datos as $medio): ?>
                        <div class="medio-reporte">
                            <span class="medio-reporte-nombre">
                                <?= e($medio['nombre']) ?>
                                <?php if ($medio['afecta_caja']): ?>
                                    <span class="etiqueta caja">al cajón</span>
                                <?php else: ?>
                                    <span class="etiqueta">no al cajón</span>
                                <?php endif; ?>
                            </span>

                            <strong class="medio-reporte-valor"><?= e(money($medio['importe'])) ?></strong>

                            <span class="medio-reporte-barra" aria-hidden="true">
                                <?php if ($medio['porcentaje'] !== null): ?>
                                    <span style="width: <?= e((string) $medio['porcentaje']) ?>%"></span>
                                <?php endif; ?>
                            </span>

                            <span class="medio-reporte-nota">
                                <?= (int) $medio['cobros'] ?> <?= (int) $medio['cobros'] === 1 ? 'cobro' : 'cobros' ?>
                                <?php if ($medio['porcentaje'] !== null): ?>
                                    · <?= e(porcentaje($medio['porcentaje'])) ?> de lo cobrado
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($hayDatos): ?>
            <p class="reporte-pie">
                <i class="bi bi-download"></i>
                El botón CSV de arriba baja estos mismos números, con el período escrito adentro
                del archivo.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
