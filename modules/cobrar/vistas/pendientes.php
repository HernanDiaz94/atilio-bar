<?php
/**
 * modules/cobrar/vistas/pendientes.php
 * -----------------------------------------------------------------
 * Mesas que pidieron la cuenta y todavía no fueron cobradas.
 *
 * Se llega acá cuando el vendedor entra a "Cobrar" sin elegir
 * mesa: es la lista de lo que hay que ir cobrando.
 *
 * Variables: $pendientes
 * -----------------------------------------------------------------
*/

$cajaAbierta = Caja::abierta();
?>

<?php if ($cajaAbierta === null): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>No hay caja abierta.</strong>
        Sin caja no se puede cobrar: la plata que entra tiene que
        saber a qué turno pertenece.
        <?php if ($esAdmin): ?>
            <div class="mt-2">
                <a href="<?= e(url('caja')) ?>" class="btn btn-atilio-amarillo btn-sm">
                    <i class="bi bi-safe-fill"></i> Ir a la caja
                </a>
            </div>
        <?php else: ?>
            <div class="mt-1">Avisale al administrador.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-hourglass-split"></i>
            Esperando el cobro
        </span>
        <span class="small"><?= (int) count($pendientes) ?></span>
    </div>

    <?php if (empty($pendientes)): ?>
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-check2-circle fs-1 d-block mb-2"></i>
            <p class="fw-bold mb-1">No hay ninguna mesa esperando</p>
            <span style="color: var(--texto-suave);">
                Cuando alguien pida la cuenta, va a aparecer acá.
            </span>
        </div>
    <?php else: ?>
        <div class="lista-cobrar">
            <?php foreach ($pendientes as $pedido): ?>
                <a href="<?= e(url('cobrar') . '&id=' . (int) $pedido['id']) ?>"
                   class="fila-cobrar">
                    <span class="fila-cobrar-mesa">
                        <?= e((string) $pedido['mesa_numero']) ?>
                    </span>
                    <span class="fila-cobrar-datos">
                        <strong><?= e((string) $pedido['numero']) ?></strong>
                        · <?= (int) $pedido['comensales'] ?>
                        <?= ((int) $pedido['comensales'] === 1) ? 'comensal' : 'comensales' ?>
                        <br>
                        <small style="color: var(--texto-suave);">
                            <?= e((string) $pedido['vendedor']) ?>
                            · abierta <?= e(hora((string) $pedido['abierta_en'])) ?>
                        </small>
                    </span>
                    <span class="fila-cobrar-total">
                        <?= e(money($pedido['total'])) ?>
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
