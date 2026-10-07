<?php
/**
 * modules/usuarios/vistas/lista.php
 * -----------------------------------------------------------------
 * Vista del listado de usuarios (solo administradores).
 *
 * Variables: $usuarios, $buscar, $rolFiltro, $totalUsuarios,
 *            $usuariosActivos, $usuariosInactivos,
 *            $debenCambiar, $bloqueados
 * -----------------------------------------------------------------
*/

$titulo    = 'Usuarios';
$subtitulo = 'Alta, edición y desactivación de los que pueden entrar al sistema';

// Botón principal de la cabecera: dar de alta un usuario nuevo.
$accionesCabecera = '<a href="' . e(url('usuarios/formulario')) . '" class="btn btn-atilio-oscuro">'
    . '<i class="bi bi-person-plus-fill"></i> Nuevo usuario</a>';

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= RESUMEN ================= -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor">
            <p class="etiqueta">Total</p>
            <p class="valor"><?= e((string) $totalUsuarios) ?></p>
            <span class="detalle">usuarios registrados</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor">
            <p class="etiqueta">Activos</p>
            <p class="valor"><?= e((string) $usuariosActivos) ?></p>
            <span class="detalle">
                <i class="bi bi-check-circle-fill"></i> pueden entrar
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor">
            <p class="etiqueta">Desactivados</p>
            <p class="valor"><?= e((string) $usuariosInactivos) ?></p>
            <span class="detalle">
                <i class="bi bi-slash-circle-fill"></i> no pueden entrar
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="tarjeta-valor <?= ((int) $debenCambiar > 0 || (int) $bloqueados > 0) ? 'acento-amarillo' : '' ?>">
            <p class="etiqueta">Requieren atención</p>
            <p class="valor"><?= e((string) ((int) $debenCambiar + (int) $bloqueados)) ?></p>
            <span class="detalle">
                <?= e((string) $debenCambiar) ?> con clave temporal &middot;
                <?= e((string) $bloqueados) ?> bloqueado(s)
            </span>
        </div>
    </div>
</div>

<!-- ================= BUSCADOR ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cuerpo">
        <form method="get" action="<?= e(url_archivo('index.php')) ?>" class="row g-2 align-items-end">
            <!-- hidden: mantenemos la ruta en el buscador -->
            <input type="hidden" name="r" value="usuarios">

            <div class="col-12 col-md-6">
                <label for="q" class="form-label">Buscar</label>
                <input type="text"
                       id="q"
                       name="q"
                       class="form-control"
                       value="<?= e($buscar) ?>"
                       placeholder="Nombre, usuario o email"
                       data-filtrar="#tablaUsuarios">
            </div>

            <div class="col-8 col-md-4">
                <label for="rol" class="form-label">Rol</label>
                <select id="rol" name="rol" class="form-select">
                    <option value="">Todos</option>
                    <option value="administrador" <?= $rolFiltro === 'administrador' ? 'selected' : '' ?>>
                        Administradores
                    </option>
                    <option value="vendedor" <?= $rolFiltro === 'vendedor' ? 'selected' : '' ?>>
                        Vendedores
                    </option>
                </select>
            </div>

            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-atilio w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>

            <?php if ($buscar !== '' || $rolFiltro !== ''): ?>
                <div class="col-12">
                    <a href="<?= e(url('usuarios')) ?>" class="btn btn-atilio-amarillo btn-sm">
                        <i class="bi bi-x-circle"></i> Quitar filtros
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ================= TABLA ================= -->
<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-people-fill"></i>
            Lista de usuarios
        </span>
        <span class="small">
            <?= e((string) count($usuarios)) ?> resultado(s)
        </span>
    </div>

    <div class="tabla-envoltura">
        <table class="tabla" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="6" class="centrado py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            <span style="color: var(--texto-suave);">
                                No hay usuarios que coincidan con la búsqueda.
                            </span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $activo       = (int) $u['activo'] === 1;
                        $esElMismo   = (int) $u['id'] === Auth::id();
                        $bloqueado   = !empty($u['bloqueado_hasta']) && strtotime((string) $u['bloqueado_hasta']) > time();
                        $debeCambiar = (int) $u['debe_cambiar_password'] === 1;
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $u['usuario']) ?></strong>
                                <?php if ($esElMismo): ?>
                                    <span class="badge rol-vendedor">vos</span>
                                <?php endif; ?>
                                <?php if (!empty($u['email'])): ?>
                                    <br>
                                    <small style="color: var(--texto-suave);"><?= e((string) $u['email']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e(trim((string) $u['nombre'] . ' ' . (string) ($u['apellido'] ?? ''))) ?>
                            </td>

                            <td>
                                <span class="badge <?= e(clase_rol((string) $u['rol'])) ?>">
                                    <?= e(texto_rol((string) $u['rol'])) ?>
                                </span>
                            </td>

                            <td>
                                <?php if (!$activo): ?>
                                    <span class="estado-anulada">
                                        <i class="bi bi-slash-circle-fill"></i> Desactivado
                                    </span>
                                <?php elseif ($bloqueado): ?>
                                    <span class="estado-por-cobrar">
                                        <i class="bi bi-lock-fill"></i> Bloqueado
                                    </span>
                                    <br>
                                    <small style="color: var(--texto-suave);">
                                        hasta <?= e(fecha_hora($u['bloqueado_hasta'])) ?>
                                    </small>
                                <?php elseif ($debeCambiar): ?>
                                    <span class="estado-ocupada">
                                        <i class="bi bi-key-fill"></i> Clave temporal
                                    </span>
                                    <br>
                                    <small style="color: var(--texto-suave);">debe cambiar su contraseña</small>
                                <?php else: ?>
                                    <span class="estado-libre">
                                        <i class="bi bi-check-circle-fill"></i> Activo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($u['ultimo_acceso'])): ?>
                                    <?= e(fecha_hora((string) $u['ultimo_acceso'])) ?>
                                <?php else: ?>
                                    <span style="color: var(--texto-suave);">nunca</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                                    <!-- Editar -->
                                    <a href="<?= e(url('usuarios/formulario') . '&id=' . (int) $u['id']) ?>"
                                       class="btn btn-atilio-amarillo btn-sm"
                                       title="Editar usuario">
                                        <i class="bi bi-pencil-fill"></i>
                                        <span class="d-none d-md-inline">Editar</span>
                                    </a>

                                    <!-- Activar / desactivar -->
                                    <?php if ($esElMismo): ?>
                                        <!-- El admin no puede desactivarse a sí mismo:
                                             si se equivoca, se queda sin acceso. -->
                                        <button type="button"
                                                class="btn btn-atilio-amarillo btn-sm"
                                                disabled
                                                title="No podés desactivar tu propio usuario">
                                            <i class="bi bi-slash-circle"></i>
                                            <span class="d-none d-md-inline">Desactivar</span>
                                        </button>
                                    <?php else: ?>
                                        <form method="post"
                                              action="<?= e(url('usuarios/acciones')) ?>"
                                              class="d-inline">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">

                                            <button type="submit"
                                                    class="btn <?= $activo ? 'btn-peligro' : 'btn-atilio' ?> btn-sm"
                                                    title="<?= $activo ? 'Desactivar' : 'Activar' ?> usuario"
                                                    data-confirmar="<?= $activo
                                                        ? '¿Desactivar a ' . e((string) $u['usuario']) . '? No va a poder entrar al sistema.'
                                                        : '¿Activar a ' . e((string) $u['usuario']) . '?' ?>">
                                                <i class="bi bi-<?= $activo ? 'slash-circle-fill' : 'check-circle-fill' ?>"></i>
                                                <span class="d-none d-md-inline"><?= $activo ? 'Desactivar' : 'Activar' ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Resetear la contraseña -->
                                    <form method="post"
                                          action="<?= e(url('usuarios/acciones')) ?>"
                                          class="d-inline">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="resetear_password">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">

                                        <button type="submit"
                                                class="btn btn-atilio-amarillo btn-sm"
                                                title="Generar una contraseña nueva"
                                                data-confirmar="Se genera una contraseña temporal para <?= e((string) $u['usuario']) ?>. ¿Seguís adelante?">
                                            <i class="bi bi-arrow-repeat"></i>
                                            <span class="d-none d-md-inline">Resetear clave</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!--
    Nota de diseño: no hay botón de "borrar" a propósito.
    Un usuario con ventas asociadas no se puede borrar nunca: se
    desactiva. Si no tuviera ventas, un administrador lo puede
    eliminar desde el formulario de edición.
-->

<?php require APP_ROOT . '/includes/footer.php'; ?>