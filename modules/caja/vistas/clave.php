<?php
/**
 * modules/caja/vistas/clave.php
 * -----------------------------------------------------------------
 * Poner o cambiar la clave de caja.
 *
 * Variables: $errores, $configurada
 * -----------------------------------------------------------------
 */

require APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">

        <?php if ($configurada): ?>
            <div class="tarjeta mb-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-shield-lock-fill"></i> Clave puesta</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <p class="mb-0">
                        Cualquiera del turno puede abrir y cerrar la caja, pero
                        tiene que escribir esta clave. El administrador no la
                        pide nunca.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="tarjeta mb-3">
                <div class="tarjeta-cabecera">
                    <span style="color: #8A1B1B;"><i class="bi bi-exclamation-triangle-fill"></i> Sin clave</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <p class="mb-0">
                        Mientras no haya clave, <strong>el vendedor no puede abrir
                        ni cerrar la caja</strong>. Ponela abajo para que pueda
                        trabajar con la plata del cajón.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('caja/clave')) ?>" class="tarjeta" data-validar novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="guardar">

            <div class="tarjeta-cabecera">
                <span><i class="bi bi-key-fill"></i> <?= $configurada ? 'Cambiar la clave' : 'Poner la clave' ?></span>
            </div>

            <div class="tarjeta-cuerpo">
                <p class="small mb-3">
                    Son digits y letras. Anotala: el sistema guarda una versión
                    cifrada y no hay forma de volver a leerla. Si se te olvida,
                    la cambias desde acá.
                </p>

                <div class="campo mb-3 <?= isset($errores['clave']) ? 'campo-error' : '' ?>">
                    <label for="clave" class="form-label">
                        Clave de caja <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="clave" name="clave" class="form-control"
                           value=""
                           required minlength="4" maxlength="40" autocomplete="off"
                           data-etiqueta="clave de caja">
                    <?php if (isset($errores['clave'])): ?>
                        <span class="error-campo"><?= e((string) $errores['clave']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3 <?= isset($errores['clave_confirmacion']) ? 'campo-error' : '' ?>">
                    <label for="clave_confirmacion" class="form-label">
                        Repitela <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="clave_confirmacion" name="clave_confirmacion" class="form-control"
                           required minlength="4" maxlength="40" autocomplete="off"
                           data-etiqueta="repeticion de la clave">
                    <?php if (isset($errores['clave_confirmacion'])): ?>
                        <span class="error-campo"><?= e((string) $errores['clave_confirmacion']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-atilio-oscuro w-100">
                    <i class="bi bi-check-lg"></i>
                    <?= $configurada ? 'Cambiar la clave' : 'Guardar la clave' ?>
                </button>
            </div>
        </form>

        <?php if ($configurada): ?>
            <form method="post" action="<?= e(url('caja/clave')) ?>"
                  class="tarjeta mt-3" data-confirmar="¿Quitar la clave de caja? Si la sacás, cualquiera del turno va a poder abrir y cerrar la caja sin escribir nada.">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="quitar">

                <div class="tarjeta-cuerpo py-3 centrado">
                    <p class="small mb-2" style="color: var(--texto-suave);">
                        Si la clave no esta puesta, cualquiera del turno puede
                        abrir y cerrar la caja sin escribir nada.
                    </p>
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> Quitar la clave
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>