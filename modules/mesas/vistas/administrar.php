<?php
/**
 * modules/mesas/vistas/administrar.php
 * -----------------------------------------------------------------
 * Gestión de mesas (solo administradores).
 * -----------------------------------------------------------------
*/

$errores = $erroresGuardados['campos'];

$titulo    = 'Administrar mesas';
$subtitulo = 'Alta, edición y baja de las mesas del bar';

$accionesCabecera = '<a href="' . e(url('mesas')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-grid-3x3-gap-fill"></i> Ver la grilla</a>';

require APP_ROOT . '/includes/header.php';
?>

<div class="row g-3">

    <!-- ================= LISTA ================= -->
    <div class="col-12 col-lg-8">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-grid-3x3-gap-fill"></i> Mesas del bar</span>
                <span class="small">
                    <?= e((string) $totalActivas) ?> de <?= e((string) $totalMesas) ?> activas
                </span>
            </div>

            <?php if (empty($mesas)): ?>
                <div class="tarjeta-cuerpo centrado py-4">
                    <i class="bi bi-grid fs-2 d-block mb-2"></i>
                    <span style="color: var(--texto-suave);">No hay mesas cargadas.</span>
                </div>
            <?php else: ?>
                <div class="tabla-envoltura">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Mesa</th>
                                <th>Zona</th>
                                <th class="text-end">Lugares</th>
                                <th class="text-end">Pedidos</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mesas as $mesa): ?>
                                <?php
                                $activa = (int) $mesa['activo'] === 1;
                                $ocupada = (int) $mesa['comandas_abiertas'] > 0;
                                ?>
                                <tr class="<?= $activa ? '' : 'producto-inactivo' ?>">
                                    <td>
                                        <strong><?= e((string) $mesa['numero']) ?></strong>
                                        <?php if (!empty($mesa['nombre'])): ?>
                                            <br>
                                            <small style="color: var(--texto-suave);">
                                                <?= e((string) $mesa['nombre']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(Mesas::ZONAS[$mesa['zona']] ?? $mesa['zona']) ?></td>
                                    <td class="text-end"><?= (int) $mesa['capacidad'] ?></td>
                                    <td class="text-end">
                                        <?= (int) $mesa['comandas_total'] ?>
                                        <?php if ($ocupada): ?>
                                            <br>
                                            <span class="estado-ocupada">
                                                <i class="bi bi-people-fill"></i> abierta
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$activa): ?>
                                            <span class="estado-anulada">
                                                <i class="bi bi-slash-circle-fill"></i> Baja
                                            </span>
                                        <?php elseif ($ocupada): ?>
                                            <span class="estado-ocupada">
                                                <i class="bi bi-people-fill"></i> Con gente
                                            </span>
                                        <?php else: ?>
                                            <span class="estado-libre">
                                                <i class="bi bi-check-circle-fill"></i> Activa
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            <form method="post"
                                                  action="<?= e(url('mesas/acciones-mesa')) ?>"
                                                  class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="<?= $activa ? 'desactivar' : 'activar' ?>">
                                                <input type="hidden" name="id" value="<?= (int) $mesa['id'] ?>">
                                                <button type="submit"
                                                        class="btn <?= $activa ? 'btn-peligro' : 'btn-atilio' ?> btn-sm"
                                                        data-confirmar="<?= $activa
                                                            ? '¿Dar de baja la mesa ' . e((string) $mesa['numero']) . '? Deja de aparecer en la grilla.'
                                                            : '¿Activar la mesa ' . e((string) $mesa['numero']) . '?' ?>">
                                                    <i class="bi bi-<?= $activa ? 'slash-circle-fill' : 'check-circle-fill' ?>"></i>
                                                    <span class="d-none d-md-inline">
                                                        <?= $activa ? 'Dar de baja' : 'Activar' ?>
                                                    </span>
                                                </button>
                                            </form>

                                            <?php if ((int) $mesa['comandas_total'] === 0): ?>
                                                <form method="post"
                                                      action="<?= e(url('mesas/acciones-mesa')) ?>"
                                                      class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?= (int) $mesa['id'] ?>">
                                                    <button type="submit" class="btn btn-peligro btn-sm"
                                                            title="Eliminar mesa sin historial"
                                                            data-confirmar="Se va a eliminar la mesa &quot;<?= e((string) $mesa['numero']) ?>&quot;. ¿Seguro?">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

    <!-- ================= ALTA ================= -->
    <div class="col-12 col-lg-4">
        <form method="post" action="<?= e(url('mesas/guardar-mesa')) ?>"
              class="tarjeta" data-validar novalidate>

            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-plus-circle-fill"></i> Nueva mesa</span>
            </div>

            <div class="tarjeta-cuerpo">
                <div class="campo mb-3 <?= isset($errores['numero']) ? 'campo-error' : '' ?>">
                    <label for="numero" class="form-label">Número <span class="text-danger">*</span></label>
                    <input type="text" id="numero" name="numero" class="form-control"
                           value="<?= e((string) ($entradas['numero'] ?? '')) ?>"
                           maxlength="10" required autofocus
                           data-etiqueta="número de mesa"
                           placeholder="1, A, V1, BAR-1">
                    <?php if (isset($errores['numero'])): ?>
                        <span class="error-campo"><?= e((string) $errores['numero']) ?></span>
                    <?php else: ?>
                        <span class="form-text">Lo que ve el cliente.</span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['nombre']) ? 'campo-error' : '' ?>">
                    <label for="nombre" class="form-label">Nombre interno</label>
                    <input type="text" id="nombre" name="nombre" class="form-control"
                           value="<?= e((string) ($entradas['nombre'] ?? '')) ?>"
                           maxlength="60" data-etiqueta="nombre"
                           placeholder="Opcional. Ej: Esquina del salon">
                </div>

                <div class="campo mb-3 <?= isset($errores['capacidad']) ? 'campo-error' : '' ?>">
                    <label for="capacidad" class="form-label">Lugares <span class="text-danger">*</span></label>
                    <input type="number" id="capacidad" name="capacidad" class="form-control"
                           value="<?= e((string) ($entradas['capacidad'] ?? '4')) ?>"
                           min="1" max="50" required data-etiqueta="lugares">
                    <?php if (isset($errores['capacidad'])): ?>
                        <span class="error-campo"><?= e((string) $errores['capacidad']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['zona']) ? 'campo-error' : '' ?>">
                    <label for="zona" class="form-label">Zona <span class="text-danger">*</span></label>
                    <select id="zona" name="zona" class="form-select" required data-etiqueta="zona">
                        <?php foreach (Mesas::ZONAS as $clave => $etiqueta): ?>
                            <option value="<?= e($clave) ?>"
                                <?= ((string) ($entradas['zona'] ?? 'salon') === $clave) ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text">La zona agrupa las mesas en la grilla.</span>
                    <?php if (isset($errores['zona'])): ?>
                        <span class="error-campo"><?= e((string) $errores['zona']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-atilio-oscuro w-100">
                    <i class="bi bi-check-lg"></i> Crear mesa
                </button>
            </div>
        </form>

        <div class="marco-amarillo p-3 mt-3 small">
            <strong><i class="bi bi-lightbulb-fill"></i> Sobre dar de baja:</strong>
            <p class="mb-0 mt-1">
                Una mesa con pedidos NO se borra, se da de baja: así los
                informes viejos siguen cuadrando. Solo se elimina una mesa
                que nunca tuvo ningún pedido.
            </p>
        </div>

        <?php require APP_ROOT . '/modules/mesas/vistas/generar.php'; ?>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>