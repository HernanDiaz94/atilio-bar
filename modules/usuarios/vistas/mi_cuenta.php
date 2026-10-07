<?php
/**
 * modules/usuarios/vistas/mi_cuenta.php
 * -----------------------------------------------------------------
 * Vista del cambio de contraseña y los datos de la sesión.
 *
 * Variables: $usuario, $esAdmin, $miId, $misAccesos,
 *            $errores (los define mi_cuenta_guardar.php)
 * -----------------------------------------------------------------
*/

$titulo    = 'Mi cuenta';
$subtitulo = 'Tus datos y tu contraseña';

$errores = $errores ?? [];

// Errores generales del POST, si los hubo (los de campo ya vienen
// en $errores y se pintan junto al input correspondiente).
if (!empty($erroresGenerales)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= e((string) $erroresGenerales[0]) ?>
    </div>
<?php endif;

// Si el usuario fue marcado para cambiar la clave, se le explica
// por qué no lo puede evitar.
$debeForzar = (int) ($usuario['debe_cambiar_password'] ?? 0) === 1;

require APP_ROOT . '/includes/header.php';
?>

<?php if ($debeForzar): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Tenés que cambiar tu contraseña.</strong>
        Es una clave temporal que generó el administrador.
        Mientras no la cambies, no vas a poder usar el resto del sistema.
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ================= CAMBIAR CONTRASEÑA ================= -->
    <div class="col-12 col-lg-7">
        <form method="post"
              action="<?= e(url('mi-cuenta/guardar')) ?>"
              class="tarjeta"
              data-validar
              novalidate>

            <?= csrf_campo() ?>

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-key-fill"></i> Cambiar contraseña</span>
            </div>

            <div class="tarjeta-cuerpo">

                <!--
                    Reglas para elegir una buena contraseña:
                    - al menos 8 caracteres
                    - que no sea tu usuario ni el nombre del bar
                    - que no sea 123456 ni.atilio
                    El primer cambio después de una clave temporal
                    tiene que cumplir estas mismas reglas.
                -->

                <div class="campo mb-3 <?= isset($errores['password_actual']) ? 'campo-error' : '' ?>">
                    <label for="password_actual" class="form-label">
                        Contraseña actual <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password"
                               id="password_actual"
                               name="password_actual"
                               class="form-control"
                               autocomplete="current-password"
                               required
                               autofocus
                               data-etiqueta="contraseña actual">
                        <button class="btn btn-atilio-amarillo" type="button"
                                data-ver-password="password_actual">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                    </div>
                    <?php if (isset($errores['password_actual'])): ?>
                        <span class="error-campo"><?= e((string) $errores['password_actual']) ?></span>
                    <?php else: ?>
                        <span class="form-text">
                            La que usás hoy. Si no te acordás, pedile al
                            administrador que te resetee la clave.
                        </span>
                    <?php endif; ?>
                </div>

                <hr class="my-3 sin-borde-abajo">

                <div class="campo mb-3 <?= isset($errores['password']) ? 'campo-error' : '' ?>">
                    <label for="password" class="form-label">
                        Contraseña nueva <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               autocomplete="new-password"
                               required
                               minlength="8"
                               data-etiqueta="contraseña nueva">
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
                        Repetir la nueva <span class="text-danger">*</span>
                    </label>
                    <input type="password"
                           id="password_confirmacion"
                           name="password_confirmacion"
                           class="form-control"
                           autocomplete="new-password"
                           required
                           minlength="8"
                           data-etiqueta="repetición de la contraseña">
                    <?php if (isset($errores['password_confirmacion'])): ?>
                        <span class="error-campo"><?= e((string) $errores['password_confirmacion']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Medidor simple de qué tan fuerte es la clave -->
                <div class="marco p-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold">Seguridad de la contraseña</span>
                        <span class="small fw-bold" id="fuerzaTexto">-</span>
                    </div>
                    <div class="progress" style="height: 8px;" role="progressbar">
                        <div class="progress-bar" id="fuerzaBarra" style="width: 0%;"></div>
                    </div>
                    <span class="form-text mt-1 d-block">
                        Combina letras y números. Palabras como &laquo;atilio&raquo;,
                        tu usuario o el año no sirven como contraseña.
                    </span>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= e(url('dashboard')) ?>" class="btn btn-atilio-amarillo">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-atilio-oscuro">
                        <i class="bi bi-check-lg"></i> Guardar contraseña
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ================= DATOS DE LA CUENTA ================= -->
    <div class="col-12 col-lg-5">

        <div class="tarjeta mb-3">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-person-badge"></i> Tus datos</span>
            </div>
            <div class="tarjeta-cuerpo">
                <ul class="lista-movimientos">
                    <li>
                        <i class="bi bi-person-fill mt-1"></i>
                        <div>
                            <strong><?= e((string) $usuario['usuario']) ?></strong><br>
                            <span style="color: var(--texto-suave); font-size: 0.9rem;">
                                <?= e(Auth::nombre_completo()) ?>
                            </span>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-shield-fill mt-1"></i>
                        <div>
                            <span class="badge <?= e(clase_rol((string) $usuario['rol'])) ?>">
                                <?= e(texto_rol((string) $usuario['rol'])) ?>
                            </span>
                            <br>
                            <span style="color: var(--texto-suave); font-size: 0.85rem;">
                                <?php if ($esAdmin): ?>
                                    Acceso total: usuarios, precios, stock,
                                    anulaciones y caja.
                                <?php else: ?>
                                    Mesas, pedidos, cobro y stock en lectura.
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-clock-fill mt-1"></i>
                        <div>
                            Sesión abierta desde el
                            <strong><?= e(fecha_hora($_SESSION['inicio_sesion'] ?? null)) ?></strong>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-wifi-fill mt-1"></i>
                        <div>
                            Tu sesión se cierra sola después de
                            <strong><?= e((string) SESION_MINUTOS_INACTIVIDAD) ?> minutos</strong>
                            sin hacer nada.
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Últimos ingresos a esta cuenta -->
        <div class="tarjeta">
            <div class="tarjeta-cabecera">
                <span><i class="bi bi-door-open-fill"></i> Ingresos a tu cuenta</span>
                <span class="small">30 días</span>
            </div>
            <div class="tarjeta-cuerpo">
                <?php if (empty($misAccesos)): ?>
                    <p class="mb-0 centrado" style="color: var(--texto-suave);">
                        <i class="bi bi-inbox"></i> Sin registros.
                    </p>
                <?php else: ?>
                    <ul class="lista-movimientos">
                        <?php foreach ($misAccesos as $acceso): ?>
                            <?php
                            // Icono según si el intento fue exitoso o no.
                            $exitoso = (int) $acceso['motivo'] === 'login_ok';
                            $etiquetas = [
                                'login_ok'              => 'Entró bien',
                                'password_incorrecta'   => 'Contraseña incorrecta',
                                'usuario_inexistente'   => 'Usuario inexistente',
                                'usuario_bloqueado'     => 'Cuenta bloqueada',
                                'usuario_inactivo'      => 'Cuenta desactivada',
                                'ip_bloqueada'          => 'IP bloqueada',
                            ];
                            $texto = $etiquetas[(string) $acceso['motivo']] ?? (string) $acceso['motivo'];
                            ?>
                            <li>
                                <i class="bi <?= $exitoso ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> mt-1"
                                   style="color: <?= $exitoso ? 'var(--verde-medio)' : '#A33A3A' ?>;"></i>
                                <div class="flex-grow-1">
                                    <strong><?= e($texto) ?></strong>
                                    <br>
                                    <span class="momento">
                                        <?= e(fecha_hora((string) $acceso['creado_en'])) ?>
                                        &middot; <?= e((string) $acceso['ip']) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="form-text mt-2 mb-0">
                        <i class="bi bi-info-circle-fill"></i>
                        ¿Vés un ingreso que no fuiste vos? Avisale al
                        administrador y cambiá la contraseña.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Script inline: medidor de fuerza de la contraseña.
// Es una ayuda visual, no una garantía: la validación real la
// hace Auth::cambiar_password() en el servidor.
$scriptsExtra = <<<'HTML'
<script>
(function () {
    var campo     = document.getElementById('password');
    var barra     = document.getElementById('fuerzaBarra');
    var texto     = document.getElementById('fuerzaTexto');
    var formulario = document.querySelector('form[data-validar]');

    if (!campo || !barra || !texto) { return; }

    // Paleta del tema: se usan los mismos verdes y amarillos.
    var colores = {
        1: { ancho: '25%',  color: '#A33A3A', texto: 'Muy débil' },
        2: { ancho: '50%',  color: '#D9B400', texto: 'Débil' },
        3: { ancho: '75%',  color: '#8CC98A', texto: 'Buena' },
        4: { ancho: '100%', color: '#2F4F2F', texto: 'Fuerte' }
    };

    function medir() {
        var clave = campo.value || '';
        var puntos = 0;

        if (clave.length >= 8) { puntos++; }
        if (clave.length >= 12) { puntos++; }
        if (/[a-z]/.test(clave) && /[A-Z]/.test(clave)) { puntos++; }
        if (/[0-9]/.test(clave)) { puntos++; }
        if (/[^A-Za-z0-9]/.test(clave)) { puntos++; }

        // Arranca en 1 aunque esté vacía, para no molestar.
        var nivel = Math.max(1, Math.min(4, puntos));

        barra.style.width = colores[nivel].ancho;
        barra.style.backgroundColor = colores[nivel].color;
        texto.textContent = clave === '' ? '-' : colores[nivel].texto;
        texto.style.color = clave === '' ? '' : colores[nivel].color;
    }

    campo.addEventListener('input', medir);

    // Mientras se escribe la nueva, no exigir la confirmación:
    // si no, el navegador la reclama antes de que la escriban.
    if (formulario) {
        var confirmacion = document.getElementById('password_confirmacion');

        if (confirmacion) {
            confirmacion.addEventListener('input', function () {
                confirmacion.removeAttribute('required');

                if (campo.value !== '' && confirmacion.value !== '') {
                    confirmacion.setCustomValidity(
                        campo.value === confirmacion.value ? '' : 'Las contraseñas no coinciden.'
                    );
                } else {
                    confirmacion.setCustomValidity('');
                }
            });
        }
    }
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>