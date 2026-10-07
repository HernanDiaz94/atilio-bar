<?php
/**
 * modules/usuarios/vistas/formulario.php
 * -----------------------------------------------------------------
 * Vista del formulario de alta / edición de usuarios.
 *
 * Variables: $idUsuario, $usuario, $esNuevo, $valores,
 *            $tieneVentas, $esElMismo, $esUltimoAdmin,
 *            $errores (la define guardar.php), $erroresGenerales
 * -----------------------------------------------------------------
*/

$titulo    = $esNuevo ? 'Nuevo usuario' : 'Editar usuario';
$subtitulo = $esNuevo
    ? 'Crear un acceso al sistema'
    : 'Modificando los datos de ' . (string) ($usuario['usuario'] ?? '');

$accionesCabecera = '<a href="' . e(url('usuarios')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver al listado</a>';

// Errores que dejó guardar.php en la sesión al rebotar el POST.
// Normalmente vienen vacíos: solo hay algo después de un rechazo.
$errores         = $erroresGuardados['campos'];
$erroresGenerales = $erroresGuardados['generales'];

require APP_ROOT . '/includes/header.php';
?>

<?php if (!$esNuevo && !empty($usuario)): ?>
    <!-- Datos informative del usuario que se está editando -->
    <div class="tarjeta mb-3">
        <div class="tarjeta-cuerpo d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <div>
                <p class="mb-1">
                    <span class="badge <?= e(clase_rol((string) $usuario['rol'])) ?>">
                        <?= e(texto_rol((string) $usuario['rol'])) ?>
                    </span>
                    <?php if ((int) $usuario['activo'] === 1): ?>
                        <span class="estado-libre"><i class="bi bi-check-circle-fill"></i> Activo</span>
                    <?php else: ?>
                        <span class="estado-anulada"><i class="bi bi-slash-circle-fill"></i> Desactivado</span>
                    <?php endif; ?>
                    <?php if ($esElMismo): ?>
                        <span class="estado-ocupada"><i class="bi bi-person-fill"></i> Sos vos</span>
                    <?php endif; ?>
                </p>
                <p class="mb-0 small" style="color: var(--texto-suave);">
                    Creado el <?= e(fecha_hora((string) $usuario['creado_en'], false)) ?>
                    <?php if (!empty($usuario['ultimo_acceso'])): ?>
                        &middot; Último acceso <?= e(fecha_hora((string) $usuario['ultimo_acceso'])) ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($tieneVentas): ?>
                <div class="marco-amarillo px-3 py-2 small" style="max-width: 320px;">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong>No se puede borrar.</strong>
                    Este usuario tiene ventas registradas, así que el
                    historial tiene que quedar. Si deja de trabajar,
                    desactivalo.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($esUltimoAdmin): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Cuidado:</strong> sos el único administrador activo.
        Si te desactivás o cambiás tu rol, nadie más va a poder
        administrar el sistema. Creá primero otro administrador.
    </div>
<?php endif; ?>

<?php if (!empty($erroresGenerales)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) $erroresGenerales[0]) ?>
    </div>
<?php endif; ?>

<form method="post"
      action="<?= e(url('usuarios/guardar')) ?>"
      class="tarjeta"
      data-validar
      novalidate>

    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $idUsuario ?>">

    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-person-lines-fill"></i>
            <?= $esNuevo ? 'Datos del usuario' : 'Modificar datos' ?>
        </span>
    </div>

    <div class="tarjeta-cuerpo">

        <div class="form-rejilla">

            <!-- ================= NOMBRE ================= -->
            <div class="campo mb-3 <?= isset($errores['nombre']) ? 'campo-error' : '' ?>">
                <label for="nombre" class="form-label">
                    Nombre <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="nombre"
                       name="nombre"
                       class="form-control"
                       value="<?= e((string) $valores['nombre']) ?>"
                       maxlength="100"
                       required
                       autofocus
                       data-etiqueta="nombre">
                <?php if (isset($errores['nombre'])): ?>
                    <span class="error-campo"><?= e((string) $errores['nombre']) ?></span>
                <?php else: ?>
                    <span class="form-text">Cómo lo van a llamar en el turno.</span>
                <?php endif; ?>
            </div>

            <!-- ================= APELLIDO ================= -->
            <div class="campo mb-3 <?= isset($errores['apellido']) ? 'campo-error' : '' ?>">
                <label for="apellido" class="form-label">Apellido</label>
                <input type="text"
                       id="apellido"
                       name="apellido"
                       class="form-control"
                       value="<?= e((string) $valores['apellido']) ?>"
                       maxlength="100"
                       data-etiqueta="apellido">
                <?php if (isset($errores['apellido'])): ?>
                    <span class="error-campo"><?= e((string) $errores['apellido']) ?></span>
                <?php endif; ?>
            </div>

            <!-- ================= USUARIO ================= -->
            <div class="campo mb-3 <?= isset($errores['usuario']) ? 'campo-error' : '' ?>">
                <label for="usuario" class="form-label">
                    Usuario <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="usuario"
                       name="usuario"
                       class="form-control"
                       value="<?= e((string) $valores['usuario']) ?>"
                       maxlength="50"
                       pattern="[a-zA-Z0-9._\-]{3,50}"
                       autocapitalize="none"
                       spellcheck="false"
                       required
                       data-etiqueta="usuario">
                <?php if (isset($errores['usuario'])): ?>
                    <span class="error-campo"><?= e((string) $errores['usuario']) ?></span>
                <?php else: ?>
                    <span class="form-text">
                        Con este nombre entra al sistema. Letras, números,
                        punto, guion y guion bajo. Mínimo 3 caracteres.
                    </span>
                <?php endif; ?>
            </div>

            <!-- ================= EMAIL ================= -->
            <div class="campo mb-3 <?= isset($errores['email']) ? 'campo-error' : '' ?>">
                <label for="email" class="form-label">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?= e((string) $valores['email']) ?>"
                       maxlength="150"
                       data-etiqueta="email">
                <?php if (isset($errores['email'])): ?>
                    <span class="error-campo"><?= e((string) $errores['email']) ?></span>
                <?php else: ?>
                    <span class="form-text">
                        Opcional. Sirve para identificarlo en el historial.
                    </span>
                <?php endif; ?>
            </div>

            <!-- ================= ROL ================= -->
            <div class="campo mb-3 <?= isset($errores['rol']) ? 'campo-error' : '' ?>">
                <label for="rol" class="form-label">
                    Rol <span class="text-danger">*</span>
                </label>
                <select id="rol" name="rol" class="form-select" required data-etiqueta="rol">
                    <option value="vendedor" <?= ((string) $valores['rol'] === 'vendedor') ? 'selected' : '' ?>>
                        Vendedor
                    </option>
                    <option value="administrador" <?= ((string) $valores['rol'] === 'administrador') ? 'selected' : '' ?>>
                        Administrador
                    </option>
                </select>
                <?php if (isset($errores['rol'])): ?>
                    <span class="error-campo"><?= e((string) $errores['rol']) ?></span>
                <?php else: ?>
                    <span class="form-text" id="ayudaRol">
                        <?php if ((string) $valores['rol'] === 'administrador'): ?>
                            <strong>Administrador:</strong> acceso total. Gestiona
                            usuarios, precios, stock, anula ventas y cierra caja.
                        <?php else: ?>
                            <strong>Vendedor:</strong> abre mesas, carga pedidos,
                            cobra y consulta el stock. No gestiona usuarios,
                            precios ni caja.
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- ================= ESTADO ================= -->
            <div class="campo mb-3">
                <label for="activo" class="form-label">Estado</label>
                <select id="activo" name="activo" class="form-select">
                    <option value="1" <?= ((int) $valores['activo'] === 1) ? 'selected' : '' ?>>
                        Activo (puede entrar)
                    </option>
                    <option value="0" <?= ((int) $valores['activo'] === 0) ? 'selected' : '' ?>>
                        Desactivado (no puede entrar)
                    </option>
                </select>
                <span class="form-text">
                    Desactivar es mejor que borrar: el historial del usuario
                    (ventas, auditoría) queda intacto.
                </span>
            </div>

            <!-- ================= CONTRASEÑA ================= -->
            <div class="ancho-total">
                <hr class="my-3 sin-borde-abajo">
                <p class="fw-bold mb-2">
                    <i class="bi bi-key-fill"></i> Contraseña
                </p>

                <?php if ($esNuevo): ?>
                    <p class="small mb-3" style="color: var(--texto-suave);">
                        Para un usuario nuevo la contraseña es obligatoria.
                        Cuando la definas, el sistema le va a pedir que la
                        cambie la primera vez que entre.
                    </p>
                <?php else: ?>
                    <p class="small mb-3" style="color: var(--texto-suave);">
                        Si la dejamos vacía no se cambia nada. Para cambiar
                        la contraseña de otro usuario conviene usar
                        <strong>Resetear clave</strong> en el listado: se genera
                        una temporal y el usuario la cambia al entrar.
                    </p>
                <?php endif; ?>

                <div class="form-rejilla">
                    <div class="campo mb-3 <?= isset($errores['password']) ? 'campo-error' : '' ?>">
                        <label for="password" class="form-label">
                            <?= $esNuevo ? 'Contraseña' : 'Contraseña nueva' ?>
                            <?php if ($esNuevo): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   autocomplete="new-password"
                                   <?= $esNuevo ? 'required' : '' ?>
                                   minlength="8"
                                   data-etiqueta="contraseña">
                            <button class="btn btn-atilio-amarillo" type="button"
                                    data-ver-password="password">
                                <i class="bi bi-eye"></i> Ver
                            </button>
                        </div>
                        <?php if (isset($errores['password'])): ?>
                            <span class="error-campo"><?= e((string) $errores['password']) ?></span>
                        <?php else: ?>
                            <span class="form-text">Mínimo 8 caracteres.</span>
                        <?php endif; ?>
                    </div>

                    <div class="campo mb-3 <?= isset($errores['password_confirmacion']) ? 'campo-error' : '' ?>">
                        <label for="password_confirmacion" class="form-label">
                            Repetir contraseña
                            <?php if ($esNuevo): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="password"
                               id="password_confirmacion"
                               name="password_confirmacion"
                               class="form-control"
                               autocomplete="new-password"
                               minlength="8"
                               data-etiqueta="repetición de la contraseña">
                        <?php if (isset($errores['password_confirmacion'])): ?>
                            <span class="error-campo"><?= e((string) $errores['password_confirmacion']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= BOTONES ================= -->
        <div class="d-flex gap-2 flex-wrap justify-content-end mt-3">

            <?php if (!$esNuevo && !$tieneVentas && !$esElMismo): ?>
                <!--
                    Solo se puede borrar si no tiene ventas y no sos vos.
                    El botón apunta al formulario formBorrar (atributo form=),
                    que está más abajo y es un formulario aparte.
                -->
                <button type="submit"
                        form="formBorrar"
                        class="btn btn-peligro me-auto">
                    <i class="bi bi-trash-fill"></i> Eliminar usuario
                </button>
            <?php endif; ?>

            <a href="<?= e(url('usuarios')) ?>" class="btn btn-atilio-amarillo">
                Cancelar
            </a>

            <button type="submit" class="btn btn-atilio-oscuro">
                <i class="bi bi-check-lg"></i>
                <?= $esNuevo ? 'Crear usuario' : 'Guardar cambios' ?>
            </button>
        </div>
    </div>
</form>

<?php if (!$esNuevo && !$tieneVentas && !$esElMismo): ?>
    <!--
        Formulario aparte para borrar: separado del de guardar para que un
        clic en "Guardar cambios" nunca borre nada por accidente.
    -->
    <form id="formBorrar"
      method="post"
      action="<?= e(url('usuarios/acciones')) ?>"
      class="d-none"
      data-confirmar="Se va a eliminar el usuario &quot;<?= e((string) $usuario['usuario']) ?>&quot;. Esta acción no se puede deshacer. ¿Seguro?">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= (int) $idUsuario ?>">
    </form>
<?php endif; ?>

<?php
// JavaScript inline: cambia el texto de ayuda del rol al cambiar
// el selector, así el admin entiende qué le está dando.
$scriptsExtra = <<<'HTML'
<script>
(function () {
    var selectRol = document.getElementById('rol');
    var ayudaRol  = document.getElementById('ayudaRol');

    if (!selectRol || !ayudaRol) { return; }

    var textos = {
        vendedor: '<strong>Vendedor:</strong> abre mesas, carga pedidos, cobra y consulta el stock. No gestiona usuarios, precios ni caja.',
        administrador: '<strong>Administrador:</strong> acceso total. Gestiona usuarios, precios, stock, anula ventas y cierra caja.'
    };

    selectRol.addEventListener('change', function () {
        ayudaRol.innerHTML = textos[selectRol.value] || '';
    });
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>