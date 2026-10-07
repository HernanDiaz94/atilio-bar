<?php
/**
 * modules/mesas/vistas/reservas.php
 * -----------------------------------------------------------------
 * Listado de reservas y formulario para tomar una nueva.
 * -----------------------------------------------------------------
*/

$errores = $erroresGuardados['campos'];

$titulo    = 'Reservas';
$subtitulo = 'Quién llega, cuándo y a qué mesa';

$accionesCabecera = '<a href="' . e(url('mesas')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-grid-3x3-gap-fill"></i> Mesas</a>';

require APP_ROOT . '/includes/header.php';
?>

<div class="row g-3">

    <!-- ================= LISTA ================= -->
    <div class="col-12 col-lg-8">
        <!-- Filtro de fechas -->
        <div class="tarjeta mb-3">
            <div class="tarjeta-cuerpo">
                <form method="get" action="<?= e(url_archivo('index.php')) ?>"
                      class="row g-2 align-items-end">
                    <input type="hidden" name="r" value="reservas">

                    <div class="col-6 col-md-4">
                        <label for="desde" class="form-label">Desde</label>
                        <input type="date" id="desde" name="desde" class="form-control"
                               value="<?= e($desde) ?>">
                    </div>

                    <div class="col-6 col-md-4">
                        <label for="hasta" class="form-label">Hasta</label>
                        <input type="date" id="hasta" name="hasta" class="form-control"
                               value="<?= e($hasta) ?>">
                    </div>

                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-atilio flex-grow-1">
                            <i class="bi bi-search"></i> Ver
                        </button>
                    </div>
                </form>

                <div class="d-flex gap-1 flex-wrap mt-2">
                    <a href="<?= e(url('reservas') . '&desde=' . date('Y-m-d') . '&hasta=' . date('Y-m-d')) ?>"
                       class="btn btn-atilio-amarillo btn-sm">Hoy</a>
                    <a href="<?= e(url('reservas') . '&desde=' . date('Y-m-d') . '&hasta=' . date('Y-m-d', strtotime('+7 days'))) ?>"
                       class="btn btn-atilio-amarillo btn-sm">7 días</a>
                    <a href="<?= e(url('reservas') . '&desde=' . date('Y-m-d', strtotime('next monday')) . '&hasta=' . date('Y-m-d', strtotime('next monday +6 days'))) ?>"
                       class="btn btn-atilio-amarillo btn-sm">La semana que viene</a>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span>
                    <i class="bi bi-bookmark-fill"></i>
                    Reservas del <?= e(fecha_corta($desde)) ?> al <?= e(fecha_corta($hasta)) ?>
                </span>
                <span class="small"><?= e((string) count($reservas)) ?></span>
            </div>

            <?php if (empty($reservas)): ?>
                <div class="tarjeta-cuerpo centrado py-4">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    <span style="color: var(--texto-suave);">
                        No hay reservas para esas fechas.
                    </span>
                </div>
            <?php else: ?>
                <div class="tabla-envoltura">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Mesa</th>
                                <th>Cliente</th>
                                <th class="text-center">Personas</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $reserva): ?>
                                <?php $esHoy = ($reserva['fecha'] === date('Y-m-d')); ?>
                                <tr>
                                    <td>
                                        <strong><?= e(substr((string) $reserva['hora'], 0, 5)) ?></strong>
                                        <br>
                                        <small style="color: var(--texto-suave);">
                                            <?php if ($esHoy): ?>
                                                <span class="estado-reservada">Hoy</span>
                                            <?php else: ?>
                                                <?= e(fecha_corta((string) $reserva['fecha'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?= e((string) $reserva['mesa_numero']) ?></strong>
                                        <br>
                                        <small style="color: var(--texto-suave);">
                                            <?= e(Mesas::ZONAS[$reserva['zona']] ?? $reserva['zona']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= e((string) $reserva['nombre']) ?>
                                        <?php if (!empty($reserva['telefono'])): ?>
                                            <br>
                                            <small style="color: var(--texto-suave);">
                                                <?= e((string) $reserva['telefono']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($reserva['notas'])): ?>
                                            <br>
                                            <small style="color: var(--texto-suave);">
                                                <?= e(recortar((string) $reserva['notas'], 40)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int) $reserva['comensales'] ?></td>
                                    <td>
                                        <span class="<?= $reserva['estado'] === 'confirmada'
                                            ? 'estado-libre' : 'estado-ocupada' ?>">
                                            <?php if ($reserva['estado'] === 'confirmada'): ?>
                                                <i class="bi bi-check-circle-fill"></i> Confirmada
                                            <?php else: ?>
                                                <i class="bi bi-hourglass-split"></i> Pendiente
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            <?php if ($reserva['estado'] === 'pendiente'): ?>
                                                <form method="post"
                                                      action="<?= e(url('reservas/acciones')) ?>"
                                                      class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="accion" value="confirmar">
                                                    <input type="hidden" name="id" value="<?= (int) $reserva['id'] ?>">
                                                    <button type="submit" class="btn btn-atilio btn-sm"
                                                            title="Confirmar la reserva">
                                                        <i class="bi bi-check-lg"></i>
                                                        <span class="d-none d-md-inline">Confirmar</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="post"
                                                  action="<?= e(url('reservas/acciones')) ?>"
                                                  class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="cancelar">
                                                <input type="hidden" name="id" value="<?= (int) $reserva['id'] ?>">
                                                <button type="submit" class="btn btn-peligro btn-sm"
                                                        title="Cancelar la reserva"
                                                        data-confirmar="¿Cancelar la reserva de <?= e((string) $reserva['nombre']) ?>?">
                                                    <i class="bi bi-x-lg"></i>
                                                    <span class="d-none d-md-inline">Cancelar</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= TOMAR RESERVA ================= -->
    <div class="col-12 col-lg-4">
        <form method="post" action="<?= e(url('reservas/guardar')) ?>"
              class="tarjeta" data-validar novalidate>

            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-calendar-plus-fill"></i> Tomar una reserva</span>
            </div>

            <div class="tarjeta-cuerpo">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= e((string) reset($errores)) ?>
                    </div>
                <?php endif; ?>

                <div class="campo mb-3 <?= isset($errores['mesa_id']) ? 'campo-error' : '' ?>">
                    <label for="mesa_id" class="form-label">Mesa <span class="text-danger">*</span></label>
                    <select id="mesa_id" name="mesa_id" class="form-select"
                            required data-etiqueta="mesa">
                        <option value="">Elegí una mesa</option>
                        <?php foreach ($mesas as $mesa): ?>
                            <option value="<?= (int) $mesa['id'] ?>"
                                    data-capacidad="<?= (int) $mesa['capacidad'] ?>"
                                    <?= ((string) $valores['mesa_id'] === (string) $mesa['id']) ? 'selected' : '' ?>>
                                <?= e((string) $mesa['numero']) ?>
                                (<?= (int) $mesa['capacidad'] ?> lugares)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['mesa_id'])): ?>
                        <span class="error-campo"><?= e((string) $errores['mesa_id']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-rejilla">
                    <div class="campo mb-3 <?= isset($errores['fecha']) ? 'campo-error' : '' ?>">
                        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" id="fecha" name="fecha" class="form-control"
                               value="<?= e((string) $valores['fecha']) ?>"
                               min="<?= e(date('Y-m-d')) ?>" required data-etiqueta="fecha">
                        <?php if (isset($errores['fecha'])): ?>
                            <span class="error-campo"><?= e((string) $errores['fecha']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="campo mb-3 <?= isset($errores['hora']) ? 'campo-error' : '' ?>">
                        <label for="hora" class="form-label">Hora <span class="text-danger">*</span></label>
                        <input type="time" id="hora" name="hora" class="form-control"
                               value="<?= e(substr((string) $valores['hora'], 0, 5)) ?>"
                               required data-etiqueta="hora">
                        <?php if (isset($errores['hora'])): ?>
                            <span class="error-campo"><?= e((string) $errores['hora']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="campo mb-3 <?= isset($errores['comensales']) ? 'campo-error' : '' ?>">
                    <label for="comensales" class="form-label">Personas <span class="text-danger">*</span></label>
                    <input type="number" id="comensales" name="comensales" class="form-control"
                           value="<?= e((string) $valores['comensales']) ?>"
                           min="1" max="50" required data-etiqueta="personas">
                    <span class="form-text" id="ayudaCapacidad">
                        No puede ser más que los lugares de la mesa.
                    </span>
                    <?php if (isset($errores['comensales'])): ?>
                        <span class="error-campo"><?= e((string) $errores['comensales']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['nombre']) ? 'campo-error' : '' ?>">
                    <label for="nombre" class="form-label">
                        A nombre de quién <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="nombre" name="nombre" class="form-control"
                           value="<?= e((string) $valores['nombre']) ?>"
                           maxlength="120" required autofocus data-etiqueta="nombre"
                           placeholder="Nombre y apellido">
                    <?php if (isset($errores['nombre'])): ?>
                        <span class="error-campo"><?= e((string) $errores['nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['telefono']) ? 'campo-error' : '' ?>">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" class="form-control"
                           value="<?= e((string) $valores['telefono']) ?>"
                           maxlength="40" data-etiqueta="teléfono"
                           placeholder="Opcional">
                </div>

                <div class="campo mb-3">
                    <label for="notas" class="form-label">Notas</label>
                    <input type="text" id="notas" name="notas" class="form-control"
                           value="<?= e((string) $valores['notas']) ?>"
                           maxlength="255" data-etiqueta="notas"
                           placeholder="Opcional: cumple años, mesa Deck...">
                </div>

                <button type="submit" class="btn btn-atilio-oscuro w-100">
                    <i class="bi bi-check-lg"></i> Guardar reserva
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$scriptsExtra = <<<'HTML'
<script>
(function () {
    // Al elegir la mesa, avisa si se piden más personas de las que
    // entran. El servidor lo valida igual: esto es solo ayuda.
    var selMesa = document.getElementById('mesa_id');
    var inpPersonas = document.getElementById('comensales');
    var ayuda = document.getElementById('ayudaCapacidad');

    if (!selMesa || !inpPersonas || !ayuda) { return; }

    function revisar() {
        var opcion = selMesa.options[selMesa.selectedIndex];
        var capacidad = opcion ? parseInt(opcion.getAttribute('data-capacidad') || '0', 10) : 0;
        var personas = parseInt(inpPersonas.value || '0', 10);

        if (capacidad > 0 && personas > capacidad) {
            ayuda.textContent = 'Ojo: la mesa ' + opcion.text.trim().split('(')[0].trim()
                + ' banca ' + capacidad + ' persona(s).';
            ayuda.style.color = '#8A1B1B';
        } else if (capacidad > 0) {
            ayuda.textContent = 'La mesa banca ' + capacidad + ' persona(s).';
            ayuda.style.color = '';
        }
    }

    selMesa.addEventListener('change', revisar);
    inpPersonas.addEventListener('input', revisar);
    revisar();
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>