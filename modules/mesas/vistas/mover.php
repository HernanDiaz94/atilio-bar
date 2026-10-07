<?php
/**
 * modules/mesas/vistas/mover.php
 * -----------------------------------------------------------------
 * Elegir la mesa de destino para un pedido.
 *
 * Variables: $comanda, $mesaActual, $mesasLibres,
 *            $erroresGuardados, $entradas
 * -----------------------------------------------------------------
*/

$errores = $erroresGuardados['campos'];

$titulo    = 'Mover de mesa';
$subtitulo = 'Pedido ' . (string) $comanda['numero']
    . ' · mesa ' . (string) ($mesaActual['numero'] ?? '?');

$mesaDestinoElegida = (int) ($entradas['mesa_destino'] ?? 0);

$accionesCabecera = '<a href="' . e(url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id']) . '" '
    . 'class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Volver al pedido</a>';

require APP_ROOT . '/includes/header.php';
?>

<!-- Recordatorio de lo que tiene el pedido -->
<div class="marco-amarillo p-3 mb-3">
    <strong>Vas a mover:</strong>
    pedido <?= e((string) $comanda['numero']) ?>,
    <?= e(money(Comanda::total((int) $comanda['id']))) ?>
    en <?= (int) $comanda['comensales'] ?>
    <?= ((int) $comanda['comensales'] === 1) ? 'persona' : 'personas' ?>.
    <br>
    <span class="small">El stock no se toca: el consumo ya está registrado.</span>
</div>

<form method="post" action="<?= e(url('mesas/mover')) ?>" class="tarjeta">
    <?= csrf_campo() ?>
    <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">

    <div class="tarjeta-cabecera">
        <span><i class="bi bi-arrow-left-right"></i> Mesa de destino</span>
    </div>

    <div class="tarjeta-cuerpo">
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= e((string) reset($errores)) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($mesasLibres)): ?>
            <p class="centrado" style="color: var(--texto-suave);">
                <i class="bi bi-inbox"></i>
                No hay mesas para mostrar.
            </p>
        <?php else: ?>
            <?php foreach (Mesas::ZONAS as $claveZona => $etiquetaZona): ?>
                <?php
                $deLaZona = array_values(array_filter(
                    $mesasLibres,
                    static fn ($m) => $m['zona'] === $claveZona
                ));
                ?>
                <?php if (empty($deLaZona)) { continue; } ?>

                <h3 class="categoria-titulo"><?= e($etiquetaZona) ?></h3>

                <div class="grilla-mesas compacta">
                    <?php foreach ($deLaZona as $mesa): ?>
                        <?php
                        $esLaActual      = ($mesaActual !== null
                                            && (int) $mesaActual['id'] === (int) $mesa['id']);
                        $ocupadaPorOtro = $mesa['ocupada_por'] !== null;
                        $elegible        = !$ocupadaPorOtro || $esLaActual;
                        $estadoVisual    = $esLaActual
                            ? 'reservada'
                            : ($ocupadaPorOtro ? 'ocupada' : 'libre');
                        ?>
                        <label class="mesa mesa-<?= e(slug_estado($estadoVisual)) ?>
                                      <?= $esLaActual ? 'mesa-actual' : '' ?>
                                      <?= $elegible ? '' : 'mesa-bloqueada' ?>">
                            <input type="radio"
                                   name="mesa_destino"
                                   value="<?= (int) $mesa['id'] ?>"
                                   <?= $mesaDestinoElegida === (int) $mesa['id'] ? 'checked' : '' ?>
                                   <?= $elegible ? '' : 'disabled' ?>
                                   required>
                            <span class="mesa-numero"><?= e((string) $mesa['numero']) ?></span>

                            <span class="mesa-estado">
                                <?php if ($esLaActual): ?>
                                    <span class="estado-reservada">
                                        <i class="bi bi-arrow-right-circle-fill"></i> Está acá
                                    </span>
                                <?php elseif ($ocupadaPorOtro): ?>
                                    <span class="estado-ocupada">
                                        <i class="bi bi-lock-fill"></i> Ocupada
                                    </span>
                                <?php else: ?>
                                    <span class="estado-libre">
                                        <i class="bi bi-check-circle-fill"></i> Libre
                                    </span>
                                <?php endif; ?>
                            </span>

                            <span class="mesa-datos">
                                <span class="mesa-capacidad">
                                    <i class="bi bi-people"></i>
                                    <?= (int) $mesa['capacidad'] ?>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="<?= e(url('mesas/ver') . '&mesa=' . (int) $comanda['mesa_id']) ?>"
               class="btn btn-atilio-amarillo">
                Cancelar
            </a>
            <button type="submit" class="btn btn-atilio-oscuro">
                <i class="bi bi-arrow-left-right"></i> Mover acá
            </button>
        </div>
    </div>
</form>

<?php
$scriptsExtra = <<<'HTML'
<script>
(function () {
    // Marca visualmente la mesa elegida: sin esto, con celdas
    // grises es difícil ver cuál seleccionaste.
    var radios = document.querySelectorAll('input[name="mesa_destino"]');

    function pintar() {
        Array.prototype.forEach.call(radios, function (radio) {
            if (radio.closest('.mesa')) {
                radio.closest('.mesa').classList.toggle('mesa-elegida', radio.checked);
            }
        });
    }

    Array.prototype.forEach.call(radios, function (radio) {
        radio.addEventListener('change', pintar);
    });

    pintar();
})();
</script>
HTML;
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
