<?php
/**
 * modules/mesas/vistas/generar.php
 * -----------------------------------------------------------------
 * Formulario para generar varias mesas de un tirón.
 *
 * Va aparte del ABM de a una: son dos cosas distintas. Una suma o
 * saca una mesa concreta; esta arma un rango entero, que es lo que
 * hace falta cuando el bar tiene veinte o treinta mesas.
 *
 * Variables: $errores, $entradas
 * -----------------------------------------------------------------
*/

?>

<!-- ============ GENERAR EN TANDA ============ -->
<!-- Hoy hay diez mesas, pero mañana puede haber treinta. Cargar
     una por una es un engorro. -->
<form method="post" action="<?= e(url('mesas/generar')) ?>"
      class="tarjeta mt-3" data-validar novalidate>
    <?= csrf_campo() ?>

    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-collection-fill"></i>
            Generar mesas en tanda
        </span>
    </div>

    <div class="tarjeta-cuerpo">
        <p class="small mb-3">
            Si tenés 20 mesas y solo cargaste 10, poné
            <strong>de la 11 a la 20</strong> y listo. Las que ya
            existen no se pisan.
        </p>

        <div class="campo mb-3 <?= isset($errores['rango']) ? 'campo-error' : '' ?>">
            <label for="desde" class="form-label">
                Números <span class="text-danger">*</span>
            </label>
            <div class="input-group">
                <input type="text" id="desde" name="desde" class="form-control"
                       value="<?= e((string) ($entradas['desde'] ?? '')) ?>"
                       maxlength="6" required data-etiqueta="primer número"
                       placeholder="11" inputmode="numeric">
                <span class="input-group-text">a</span>
                <input type="text" id="hasta" name="hasta" class="form-control"
                       value="<?= e((string) ($entradas['hasta'] ?? '')) ?>"
                       maxlength="6" required data-etiqueta="último número"
                       placeholder="20" inputmode="numeric">
            </div>
            <?php if (isset($errores['rango'])): ?>
                <span class="error-campo"><?= e((string) $errores['rango']) ?></span>
            <?php endif; ?>
        </div>

        <div class="campo mb-3 <?= isset($errores['prefijo']) ? 'campo-error' : '' ?>">
            <label for="prefijo" class="form-label">Prefijo</label>
            <input type="text" id="prefijo" name="prefijo" class="form-control"
                   value="<?= e((string) ($entradas['prefijo'] ?? '')) ?>"
                   maxlength="5" data-etiqueta="prefijo"
                   placeholder="Vacío = 11, 12, 13. Con prefijo V = V11, V12">
            <span class="form-text">Para las de la vereda, por ejemplo.</span>
            <?php if (isset($errores['prefijo'])): ?>
                <span class="error-campo"><?= e((string) $errores['prefijo']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-rejilla">
            <div class="campo mb-3 <?= isset($errores['zona']) ? 'campo-error' : '' ?>">
                <label for="zona_gen" class="form-label">Zona</label>
                <select id="zona_gen" name="zona" class="form-select" data-etiqueta="zona">
                    <?php foreach (Mesas::ZONAS as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>"
                            <?= ((string) ($entradas['zona'] ?? 'salon') === $clave) ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['zona'])): ?>
                    <span class="error-campo"><?= e((string) $errores['zona']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo mb-3 <?= isset($errores['capacidad']) ? 'campo-error' : '' ?>">
                <label for="capacidad_gen" class="form-label">Lugares</label>
                <input type="number" id="capacidad_gen" name="capacidad" class="form-control"
                       value="<?= e((string) ($entradas['capacidad'] ?? '4')) ?>"
                       min="1" max="50" required data-etiqueta="lugares">
                <?php if (isset($errores['capacidad'])): ?>
                    <span class="error-campo"><?= e((string) $errores['capacidad']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="campo mb-3">
            <label for="nombre_gen" class="form-label">Nombre interno</label>
            <input type="text" id="nombre_gen" name="nombre" class="form-control"
                   value="<?= e((string) ($entradas['nombre'] ?? '')) ?>"
                   maxlength="60" data-etiqueta="nombre"
                   placeholder="Opcional. Ej: Mesas de la pared">
        </div>

        <button type="submit" class="btn btn-atilio w-100"
                data-confirmar="Se van a crear las mesas indicadas. ¿Seguís?">
            <i class="bi bi-plus-lg"></i> Crear las mesas
        </button>
    </div>
</form>
