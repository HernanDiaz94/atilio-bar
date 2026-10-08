<?php
/**
 * modules/mesas/vistas/grilla.php
 * -----------------------------------------------------------------
 * La grilla de mesas, pensada para usar con el dedo en un celular.
 *
 * Cada mesa es un botón grande. El estado se marca por color, por
 * TEXTO y por ícono: con luz de sol y a pantalla chica, dos tonos
 * parecidos no se distinguen.
 * -----------------------------------------------------------------
*/

$titulo    = 'Mesas';
$subtitulo = 'Tocá una mesa para abrirla o ver el pedido';

$accionesCabecera = '';

if ($esAdmin) {
    $accionesCabecera = '<a href="' . e(url('reservas')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-bookmark-fill"></i> Reservas</a> '
        . '<a href="' . e(url('mesas/administrar')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-gear-fill"></i> Administrar</a>';
} else {
    $accionesCabecera = '<a href="' . e(url('reservas')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-bookmark-fill"></i> Reservas</a>';
}

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= RESUMEN ================= -->
<div class="resumen-mesas">
    <span class="resumen-pill estado-libre">
        <i class="bi bi-check-circle-fill"></i>
        <?= e((string) $resumen['libre']) ?> libres
    </span>
    <span class="resumen-pill estado-ocupada">
        <i class="bi bi-people-fill"></i>
        <?= e((string) $resumen['ocupada']) ?> ocupadas
    </span>
    <span class="resumen-pill estado-por-cobrar">
        <i class="bi bi-cash-coin"></i>
        <?= e((string) $resumen['por_cobrar']) ?> por cobrar
    </span>
    <?php if ((int) $resumen['reservada'] > 0): ?>
        <span class="resumen-pill estado-reservada">
            <i class="bi bi-bookmark-fill"></i>
            <?= e((string) $resumen['reservada']) ?> reservadas
        </span>
    <?php endif; ?>
</div>

<!-- ================= FILTRO DE ZONA ================= -->
<!-- El filtro se arma con los grupos reales, no con Mesas::ZONAS.
     Si queda alguna mesa en una zona que el sistema no conoce (por
     ejemplo una base donde todavia no se corrio la etapa 7), esa zona
     aparece en el filtro: es la unica forma de llegar a esas mesas con
     el dedo, porque la grilla las muestra en su propio grupo. -->
<div class="filtro-zonas">
    <a href="<?= e(url('mesas')) ?>" class="<?= $zona === '' ? 'zona-activa' : '' ?>">
        Todas
    </a>
    <?php foreach ($grupos as $clave => $grupo): ?>
        <?php if (empty($grupo['mesas'])) { continue; } ?>
        <a href="<?= e(url('mesas') . '&zona=' . $clave) ?>"
           class="<?= $zona === $clave ? 'zona-activa' : '' ?>">
            <?= e($grupo['etiqueta']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ================= GRILLA ================= -->
<?php if (empty($mesas)): ?>
    <div class="tarjeta">
        <div class="tarjeta-cuerpo centrado py-5">
            <i class="bi bi-grid-3x3-gap fs-1 d-block mb-3"></i>
            <p class="fw-bold mb-1">No hay mesas para mostrar</p>
            <p style="color: var(--texto-suave);">
                <?php if ($esAdmin): ?>
                    Creá las mesas desde
                    <a href="<?= e(url('mesas/administrar')) ?>">Administrar mesas</a>.
                <?php else: ?>
                    Todavía no hay mesas cargadas. Hablá con el administrador.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <?php /* $grupos trae TODAS las zonas (por eso se calcula antes
         del filtro en modules/mesas/index.php): con un filtro puesto
         el listado de arriba tiene que seguir ofreciendo las demas.
         El continue de mas abajo es lo que hace que, aun asi, la
         grilla dibuje solo el grupo elegido. */
    foreach ($grupos as $claveZona => $grupo): ?>
        <?php if (empty($grupo['mesas'])) { continue; } ?>

        <?php /* Con un filtro puesto se dibuja solo ese grupo. Los
                 otros vienen en $grupos para el filtro de arriba (por
                 eso se calculan antes de filtrar), pero no en la
                 grilla: si no, el filtro no serviria de nada. */ ?>
        <?php if ($zona !== '' && $zona !== $claveZona) { continue; } ?>

        <section class="grupo-mesas">
            <h2 class="grupo-titulo">
                <?= e($grupo['etiqueta']) ?>
                <span class="grupo-cantidad"><?= e((string) count($grupo['mesas'])) ?></span>
            </h2>

            <!-- Las mesas de $grupo['mesas'] son TODAS las de esa zona:
         agrupar_por_zona() no deja ninguna afuera, ni siquiera de una
         zona que el sistema no conozca. Ver el comentario de esa
         funcion antes de "optimizar" este bucle. -->
    <div class="grilla-mesas">
                <?php foreach ($grupo['mesas'] as $mesa): ?>
                    <?php
                    $estado    = (string) $mesa['estado'];
                    $esOcupada = in_array($estado, ['ocupada', 'por_cobrar'], true);
                    ?>
                    <a href="<?= e(url('mesas/ver') . '&mesa=' . (int) $mesa['id']) ?>"
                       class="mesa mesa-<?= e(slug_estado($estado)) ?>">
                        <!-- Número grande: lo primero que se ve -->
                        <span class="mesa-numero"><?= e((string) $mesa['numero']) ?></span>

                        <!-- Estado: ícono + texto, nunca solo color -->
                        <span class="mesa-estado">
                            <span class="<?= e(clase_estado($estado)) ?>">
                                <?= icono_estado($estado) ?>
                                <?= e(texto_estado($estado)) ?>
                            </span>
                        </span>

                        <!-- Datos de la mesa -->
                        <span class="mesa-datos">
                            <span class="mesa-capacidad" title="Cuántas personas entran">
                                <i class="bi bi-people"></i>
                                <?= (int) $mesa['capacidad'] ?>
                            </span>

                            <?php if ($esOcupada): ?>
                                <span class="mesa-comensales" title="Personas sentadas">
                                    <i class="bi bi-person-fill"></i>
                                    <?= (int) $mesa['comensales'] ?>
                                </span>
                            <?php endif; ?>
                        </span>

                        <!-- Pie: total del pedido, o la reserva -->
                        <span class="mesa-pie">
                            <?php if ($estado === 'por_cobrar'): ?>
                                <span class="mesa-total">
                                    <?= e(money($mesa['total_comanda'])) ?>
                                </span>
                                <span class="mesa-nota">Pedir la cuenta</span>
                            <?php elseif ($estado === 'ocupada'): ?>
                                <?php if ((int) $mesa['items_comanda'] > 0): ?>
                                    <span class="mesa-total">
                                        <?= e(money($mesa['total_comanda'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="mesa-nota">
                                        <?= (int) $mesa['minutos_abierta'] ?> min
                                    </span>
                                <?php endif; ?>
                                <span class="mesa-nota">
                                    <?= (int) $mesa['items_comanda'] ?>
                                    <?= ((int) $mesa['items_comanda'] === 1) ? 'ítem' : 'ítems' ?>
                                </span>
                            <?php elseif ($estado === 'reservada'): ?>
                                <span class="mesa-reserva">
                                    <i class="bi bi-clock-fill"></i>
                                    <?= e((string) $mesa['hora_reserva']) ?>
                                </span>
                                <span class="mesa-nota">
                                    <?= e(recortar((string) $mesa['reserva_nombre'], 18)) ?>
                                </span>
                            <?php else: ?>
                                <span class="mesa-nota">Tocar para abrir</span>
                            <?php endif; ?>
                        </span>

                        <!-- Si el pedido lleva mucho tiempo abierto, se avisa -->
                        <?php if ($estado === 'ocupada' && (int) $mesa['minutos_abierta'] >= 120): ?>
                            <span class="mesa-alerta" title="Lleva más de dos horas">
                                <i class="bi bi-clock-history"></i>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ================= RESERVAS DE HOY ================= -->
<?php if (!empty($reservasHoy)): ?>
    <div class="tarjeta mt-3">
        <div class="tarjeta-cabecera">
            <span>
                <i class="bi bi-bookmark-fill"></i>
                Reservas de hoy
            </span>
            <span class="small"><?= e((string) count($reservasHoy)) ?></span>
        </div>
        <div class="tarjeta-cuerpo">
            <ul class="lista-movimientos">
                <?php foreach ($reservasHoy as $reserva): ?>
                    <li>
                        <i class="bi bi-clock-fill mt-1"></i>
                        <div class="flex-grow-1">
                            <strong><?= e(substr((string) $reserva['hora'], 0, 5)) ?></strong>
                            &middot; Mesa <?= e((string) $reserva['mesa_numero']) ?>
                            &middot;
                            <?= e((string) $reserva['comensales']) ?>
                            <?= ((int) $reserva['comensales'] === 1) ? 'persona' : 'personas' ?>
                            <br>
                            <span style="color: var(--texto-suave); font-size: 0.9rem;">
                                <?= e((string) $reserva['nombre']) ?>
                                <?php if (!empty($reserva['telefono'])): ?>
                                    &middot; <?= e((string) $reserva['telefono']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($reserva['estado'] === 'confirmada'): ?>
                            <span class="estado-libre">
                                <i class="bi bi-check-circle-fill"></i> Confirmada
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>