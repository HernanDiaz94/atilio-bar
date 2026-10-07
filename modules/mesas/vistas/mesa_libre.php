<?php
/**
 * modules/mesas/vistas/mesa_libre.php
 * -----------------------------------------------------------------
 * Mesa libre: el formulario para abrirla.
 *
 * Variables: $mesa
 * -----------------------------------------------------------------
*/

$titulo    = 'Mesa ' . (string) $mesa['numero'];
$subtitulo = 'Está libre';

$errores = tomar_errores()['campos'];

$accionesCabecera = '<a href="' . e(url('mesas')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver a las mesas</a>';

require APP_ROOT . '/includes/header.php';
?>

<div class="row g-3">

    <div class="col-12 col-lg-5">
        <form method="post" action="<?= e(url('mesas/abrir')) ?>" class="tarjeta" data-validar novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="mesa_id" value="<?= (int) $mesa['id'] ?>">

            <div class="tarjeta-cabecera">
                <span>
                    <i class="bi bi-unlock-fill"></i>
                    Abrir mesa <?= e((string) $mesa['numero']) ?>
                </span>
            </div>

            <div class="tarjeta-cuerpo">
                <?php if ((int) $mesa['capacidad'] > 0): ?>
                    <div class="mb-3">
                        <span class="form-text d-block">Esta mesa banca</span>
                        <strong style="font-size: 1.2rem;">
                            <?= (int) $mesa['capacidad'] ?>
                            <?= ((int) $mesa['capacidad'] === 1) ? 'persona' : 'personas' ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <div class="campo mb-3 <?= isset($errores['comensales']) ? 'campo-error' : '' ?>">
                    <label for="comensales" class="form-label">
                        ¿Cuántos se sientan? <span class="text-danger">*</span>
                    </label>
                    <div class="selector-numeros">
                        <?php
                        // Se ofrecen los números habituales, hasta la
                        // capacidad de la mesa (con un margen si la mesa
                        // es chica).
                        $maximo = max(2, min(12, (int) $mesa['capacidad'] + 2));
                        ?>
                        <?php for ($n = 1; $n <= $maximo; $n++): ?>
                            <label class="numero-opcion">
                                <input type="radio" name="comensales" value="<?= $n ?>"
                                       <?= $n === 1 ? 'checked' : '' ?> required>
                                <span><?= $n ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <?php if (isset($errores['comensales'])): ?>
                        <span class="error-campo"><?= e((string) $errores['comensales']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo mb-3">
                    <label for="observacion" class="form-label">Nota</label>
                    <input type="text" id="observacion" name="observacion" class="form-control"
                           maxlength="255" data-etiqueta="nota"
                           placeholder="Opcional. Ej: traen una torta">
                </div>

                <button type="submit" class="btn btn-atilio-oscuro w-100">
                    <i class="bi bi-play-fill"></i> Abrir mesa
                </button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-7">
        <?php if (!empty($mesa['reserva_id'])): ?>
            <div class="tarjeta tarjeta-destacada mb-3">
                <div class="tarjeta-cuerpo">
                    <p class="fw-bold mb-2">
                        <i class="bi bi-bookmark-fill"></i>
                        Esta mesa tiene reserva hoy
                    </p>
                    <p class="mb-0">
                        <strong><?= e((string) $mesa['reserva_nombre']) ?></strong>
                        &middot; <?= e((string) $mesa['reserva_hora']) ?>
                        &middot; <?= (int) $mesa['reserva_comensales'] ?> personas
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="marco-amarillo p-3">
            <strong><i class="bi bi-lightbulb-fill"></i> También podés cargar de una.</strong>
            <p class="mb-2 mt-1">
                Si abrís la mesa y después elegís productos, son dos pasos.
                Si querés hacerlo de una, abrila vacía y después usá
                "Agregar producto".
            </p>
            <p class="mb-0">
                Si la mesa se mancha o llega más gente, usá
                <strong>Mover de mesa</strong> para no perder el pedido.
            </p>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'HTML'
<script>
(function () {
    // Al elegir la cantidad de comensales, se pinta de verde para
    // que se vea cuál quedó marcado sin tener que leer el radio.
    var opciones = document.querySelectorAll('.numero-opcion input');

    function pintar() {
        Array.prototype.forEach.call(opciones, function (opcion) {
            opcion.parentElement.classList.toggle('numero-activo', opcion.checked);
        });
    }

    Array.prototype.forEach.call(opciones, function (opcion) {
        opcion.addEventListener('change', pintar);
    });

    pintar();
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>