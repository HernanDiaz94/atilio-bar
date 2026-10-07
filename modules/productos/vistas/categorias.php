<?php
/**
 * modules/productos/vistas/categorias.php
 * -----------------------------------------------------------------
 * Vista de categorías (solo administradores).
 * -----------------------------------------------------------------
*/

$titulo    = 'Categorías';
$subtitulo = 'Agrupan los productos de la carta';

$errores = $erroresGuardados['campos'];

$accionesCabecera = '<a href="' . e(url('productos/formulario')) . '" class="btn btn-atilio-oscuro">'
    . '<i class="bi bi-plus-lg"></i> Nuevo producto</a>';

require APP_ROOT . '/includes/header.php';
?>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) reset($errores)) ?>
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ================= LISTA ================= -->
    <div class="col-12 col-lg-8">
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-tags-fill"></i> Categorías de la carta</span>
                <span class="small"><?= e((string) $totalCategorias) ?></span>
            </div>

            <?php if (empty($categorias)): ?>
                <div class="tarjeta-cuerpo centrado py-4">
                    <i class="bi bi-tags fs-2 d-block mb-2"></i>
                    <span style="color: var(--texto-suave);">
                        Todavía no hay categorías. Creá la primera con el
                        formulario de al lado.
                    </span>
                </div>
            <?php else: ?>
                <div class="tabla-envoltura">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th class="text-end">Productos</th>
                                <th class="text-end">Orden</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $cat): ?>
                                <?php $activo = (int) $cat['activo'] === 1; ?>
                                <tr class="<?= $activo ? '' : 'producto-inactivo' ?>">
                                    <td>
                                        <strong><?= e((string) $cat['nombre']) ?></strong>
                                        <?php if (!$activo): ?>
                                            <span class="estado-anulada">
                                                <i class="bi bi-slash-circle-fill"></i> Desactivada
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($cat['descripcion'])): ?>
                                            <br>
                                            <small style="color: var(--texto-suave);">
                                                <?= e(recortar((string) $cat['descripcion'], 70)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?= e((string) $cat['productos_activos']) ?>
                                        <?php if ((int) $cat['productos_total'] > (int) $cat['productos_activos']): ?>
                                            <br>
                                            <small style="color: var(--texto-suave);">
                                                <?= e((string) ((int) $cat['productos_total'] - (int) $cat['productos_activos'])) ?> desactivados
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= (int) $cat['orden'] ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            <a href="<?= e(url('productos') . '&categoria=' . (int) $cat['id']) ?>"
                                               class="btn btn-atilio-amarillo btn-sm"
                                               title="Ver los productos de esta categoría">
                                                <i class="bi bi-list-ul"></i>
                                                <span class="d-none d-md-inline">Ver</span>
                                            </a>

                                            <form method="post"
                                                  action="<?= e(url('categorias/acciones')) ?>"
                                                  class="d-inline">
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                                <button type="submit"
                                                        class="btn <?= $activo ? 'btn-peligro' : 'btn-atilio' ?> btn-sm"
                                                        data-confirmar="<?= $activo
                                                            ? '¿Desactivar "' . e((string) $cat['nombre']) . '"? No se podrán agregar productos nuevos a esta categoría.'
                                                            : '¿Activar "' . e((string) $cat['nombre']) . '"?' ?>">
                                                    <i class="bi bi-<?= $activo ? 'slash-circle-fill' : 'check-circle-fill' ?>"></i>
                                                    <span class="d-none d-md-inline"><?= $activo ? 'Desactivar' : 'Activar' ?></span>
                                                </button>
                                            </form>

                                            <?php if ((int) $cat['productos_total'] === 0): ?>
                                                <form method="post"
                                                      action="<?= e(url('categorias/acciones')) ?>"
                                                      class="d-inline">
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                                    <button type="submit"
                                                            class="btn btn-peligro btn-sm"
                                                            title="Eliminar categoría vacía"
                                                            data-confirmar="Se va a eliminar la categoría &quot;<?= e((string) $cat['nombre']) ?>&quot;. ¿Seguro?">
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

    <!-- ================= ALTA RÁPIDA ================= -->
    <div class="col-12 col-lg-4">
        <form method="post"
              action="<?= e(url('categorias/guardar')) ?>"
              class="tarjeta"
              data-validar
              novalidate>

            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-plus-circle-fill"></i> Nueva categoría</span>
            </div>

            <div class="tarjeta-cuerpo">
                <div class="campo mb-3 <?= isset($errores['nombre']) ? 'campo-error' : '' ?>">
                    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="nombre" name="nombre" class="form-control"
                           value="<?= e((string) $valores['nombre']) ?>"
                           maxlength="60" required autofocus data-etiqueta="nombre"
                           placeholder="Bebidas, Tragos, Comidas...">
                    <?php if (isset($errores['nombre'])): ?>
                        <span class="error-campo"><?= e((string) $errores['nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['descripcion']) ? 'campo-error' : '' ?>">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control"
                           value="<?= e((string) $valores['descripcion']) ?>"
                           maxlength="255" data-etiqueta="descripción"
                           placeholder="Opcional">
                    <?php if (isset($errores['descripcion'])): ?>
                        <span class="error-campo"><?= e((string) $errores['descripcion']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['orden']) ? 'campo-error' : '' ?>">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" id="orden" name="orden" class="form-control"
                           value="<?= e((string) $valores['orden']) ?>"
                           min="0" max="999" data-etiqueta="orden">
                    <span class="form-text">El número más chico va primero en la carta.</span>
                    <?php if (isset($errores['orden'])): ?>
                        <span class="error-campo"><?= e((string) $errores['orden']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-atilio-oscuro w-100">
                    <i class="bi bi-check-lg"></i> Crear categoría
                </button>
            </div>
        </form>

        <div class="marco-amarillo p-3 mt-3 small">
            <strong><i class="bi bi-lightbulb-fill"></i> Para el bar:</strong>
            <p class="mb-0 mt-1">
                Categorías útiles: <em>Bebidas</em>, <em>Tragos</em>, <em>Comidas</em> y
                <em>Insumos</em> (para las preparaciones). Se pueden agregar
                todas las que haga falta más adelante.
            </p>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>