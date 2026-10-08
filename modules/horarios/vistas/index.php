<?php
/**
 * modules/horarios/vistas/index.php
 * -----------------------------------------------------------------
 * Editar el horario y marcar días cerrados.
 * -----------------------------------------------------------------
 *
 * @var array  $semana    Horarios::semana()
 * @var array  $estado    Horarios::estado()
 * @var array  $cierres   Fechas marcadas
 * @var array  $entradas  Lo que se había escrito (para no perderlo)
 */

// Los errores vienen anidados en 'campos'; hay que bajar ahi.
$errores = tomar_errores()['campos'];

/** El valor que hay que mostrar en un campo. */
$valor = static function (string $campo, string $porDefecto = '') use ($entradas): string {
    if (array_key_exists($campo, $entradas)) {
        return (string) $entradas[$campo];
    }

    return $porDefecto;
};

/** "20:00:00" -> "20:00" */
$corto = static function (?string $hora): string {
    return $hora !== null && $hora !== '' ? substr($hora, 0, 5) : '';
};

require APP_ROOT . '/includes/header.php';
?>

<!-- ============ CÓMO ESTÁ AHORA ============ -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span><i class="bi bi-broadcast"></i> Cómo está ahora</span>
    </div>
    <div class="tarjeta-cuerpo">
        <?php if ($estado['abierto']): ?>
            <div class="estado-barra estado-abierto">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Abierto ahora</strong>
                    <span>Cierra a las <?= e($estado['cierra']?->format('H:i') ?? '-') ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="estado-barra estado-cerrado">
                <i class="bi bi-moon-stars-fill"></i>
                <div>
                    <strong>Cerrado ahora</strong>
                    <span><?= e(Horarios::textoProxima($estado['ahora'])) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ EL HORARIO DE LA SEMANA ============ -->
<form method="post" action="<?= e(url('horarios/guardar')) ?>">
    <?= csrf_campo() ?>

    <div class="tarjeta mb-3">
        <div class="tarjeta-cabecera">
            <span><i class="bi bi-clock-history"></i> Horario de la semana</span>
        </div>

        <div class="tarjeta-cuerpo">
            <p class="ayuda-horarios">
                Dejá la apertura y el cierre vacíos para decir que ese día no se abre.
                Si el cierre es más temprano que la apertura (por ejemplo 20:00 a 02:00),
                marcá <strong>cierra al día siguiente</strong>.
            </p>

            <div class="tabla-envoltura">
                <table class="tabla tabla-horarios">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Abre</th>
                            <th>Cierra</th>
                            <th>Al día siguiente</th>
                            <th>Queda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semana as $dia):
                            $n = (int) $dia['dia'];
                            $abreVal   = $valor('abre_' . $n, $corto($dia['abre']));
                            $cierraVal = $valor('cierra_' . $n, $corto($dia['cierra']));

                            // La casilla viene marcada si el horario ya
                            // dice que cierra al día siguiente.
                            $marcarSiguiente = array_key_exists('cierra_al_dia_siguiente_' . $n, $entradas)
                                ? ($entradas['cierra_al_dia_siguiente_' . $n] !== '')
                                : ((int) $dia['cierra_dia'] === 1);
                            ?>
                            <tr>
                                <td class="dia-semana">
                                    <strong><?= e($dia['nombre']) ?></strong>
                                    <?php if ((int) date('w') === $n): ?>
                                        <span class="etiqueta caja">hoy</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <input type="time" step="300"
                                           name="abre_<?= $n ?>"
                                           id="abre_<?= $n ?>"
                                           class="form-control <?= isset($errores['abre_' . $n]) ? 'campo-error' : '' ?>"
                                           value="<?= e($abreVal) ?>">
                                    <?php if (isset($errores['abre_' . $n])): ?>
                                        <small class="campo-error"><?= e($errores['abre_' . $n]) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <input type="time" step="300"
                                           name="cierra_<?= $n ?>"
                                           id="cierra_<?= $n ?>"
                                           class="form-control <?= isset($errores['cierra_' . $n]) ? 'campo-error' : '' ?>"
                                           value="<?= e($cierraVal) ?>">
                                    <?php if (isset($errores['cierra_' . $n])): ?>
                                        <small class="campo-error"><?= e($errores['cierra_' . $n]) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td class="centro">
                                    <label class="casilla-grande">
                                        <input type="checkbox"
                                               name="cierra_al_dia_siguiente_<?= $n ?>"
                                               value="1"
                                               class="form-check-input"
                                               data-dia="<?= $n ?>">
                                        <span>sí</span>
                                    </label>
                                </td>

                                <td class="resultado-dia">
                                    <?php if ($abreVal !== '' && $cierraVal !== ''): ?>
                                        <?= e(Horarios::textoHorario(
                                            Horarios::validarHora($abreVal) ?? $abreVal,
                                            Horarios::validarHora($cierraVal) ?? $cierraVal,
                                            $marcarSiguiente ? 1 : 0,
                                            $n
                                        )) ?>
                                    <?php elseif ($abreVal !== ''): ?>
                                        <span class="texto-vacio">falta el cierre</span>
                                    <?php else: ?>
                                        <span class="texto-vacio">cerrado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="btn btn-atilio btn-grande">
                    <i class="bi bi-check-lg"></i> Guardar el horario
                </button>
            </div>
        </div>
    </div>
</form>

<!-- ============ DÍAS CERRADOS ============ -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span><i class="bi bi-calendar-x"></i> Días cerrados</span>
    </div>

    <div class="tarjeta-cuerpo">
        <p class="ayuda-horarios">
            Para feriados o vacaciones. El día marcado no se abre,
            <strong>pero el turno de la noche anterior sigue</strong>:
            si el jueves está cerrado, el bar del miércoles a la noche
            no se corta a la medianoche.
        </p>

        <form method="post" action="<?= e(url('horarios/cierre')) ?>" class="form-cierre">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="marcar">

            <div class="campo-fecha">
                <label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" class="form-control"
                       value="<?= e($valor('fecha', date('Y-m-d'))) ?>" required>
            </div>

            <div class="campo-motivo">
                <label for="motivo">Motivo <span class="opcional">(opcional)</span></label>
                <input type="text" id="motivo" name="motivo" class="form-control"
                       maxlength="120" placeholder="Feriado, vacaciones..."
                       value="<?= e($valor('motivo')) ?>">
            </div>

            <button type="submit" class="btn btn-atilio-amarillo">
                <i class="bi bi-plus-lg"></i> Marcar cerrado
            </button>
        </form>

        <?php if (empty($cierres)): ?>
            <p class="sin-datos">No hay ningún día cerrado marcado.</p>
        <?php else: ?>
            <div class="tabla-envoltura">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cierres as $cierre): ?>
                            <tr>
                                <td>
                                    <strong><?= e(fecha_corta($cierre['fecha'])) ?></strong>
                                    <span class="reporte-sub"><?= e(fecha_dia_semana($cierre['fecha'])) ?></span>
                                    <?php if ((string) $cierre['fecha'] >= date('Y-m-d')): ?>
                                        <span class="etiqueta">próximo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($cierre['motivo'] !== '' ? $cierre['motivo'] : '-') ?></td>
                                <td class="centro">
                                    <form method="post" action="<?= e(url('horarios/cierre')) ?>" class="form-sacar-cierre">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="sacar">
                                        <input type="hidden" name="id" value="<?= (int) $cierre['id'] ?>">
                                        <button type="submit" class="btn-quitar-fila"
                                                aria-label="Sacar el <?= e(fecha_corta($cierre['fecha'])) ?> de los cerrados">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>