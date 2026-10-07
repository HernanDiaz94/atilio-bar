<?php
/**
 * modules/dashboard/vistas/inicio.php
 * -----------------------------------------------------------------
 * Vista del inicio. El contenido depende de $esAdmin.
 *
 * Variables disponibles (las arma modules/dashboard/index.php):
 *   $esAdmin, $totalUsuarios, $usuariosActivos, $administradores,
 *   $vendedores, $ultimosAccesos, $intentosFallidos,
 *   $configuracion, $miIp
 * -----------------------------------------------------------------
*/

$titulo     = 'Inicio';
$subtitulo  = $esAdmin
    ? 'Resumen del sistema y acceso rápido'
    : 'Resumen de tu turno';

// Botones de la cabecera, distintos según el rol.
$accionesCabecera = '<a href="' . e(url('productos')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-cup-straw"></i> Carta</a>';

if ($esAdmin) {
    $accionesCabecera .= ' <a href="' . e(url('compras/nueva')) . '" class="btn btn-atilio-oscuro">'
        . '<i class="bi bi-truck"></i> Cargar compra</a>';
} else {
    $accionesCabecera .= ' <a href="' . e(url('mi-cuenta')) . '" class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-key-fill"></i> Mi contraseña</a>';
}

require APP_ROOT . '/includes/header.php';
?>
<?php require APP_ROOT . '/modules/dashboard/vistas/caja.php'; ?>


<!-- ================= ALERTAS DE STOCK ================= -->
<!-- Van antes del resumen: si hay algo para reponer, es lo
     primero que hay que ver al abrir el sistema.

     Le interesa a los dos roles: al administrador para reponer, al
     vendedor para saber qué no se puede ofrecer. Lo que cambia es
     que el vendedor no ve la columna de acciones. -->
<?php if (!empty($stockBajo)): ?>
        <div class="tarjeta mb-3 <?= ((int) $stockNegativo > 0) ? '' : 'tarjeta-destacada' ?>">
            <div class="tarjeta-cabecera">
                <span>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Hay que reponer stock
                </span>
                <span class="small"><?= e((string) count($stockBajo)) ?></span>
            </div>
            <div class="tabla-envoltura">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Mínimo</th>
                            <?php if ($esAdmin): ?>
                                <th class="text-end">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockBajo as $p): ?>
                            <?php
                            $stock = (float) $p['stock_actual'];
                            $minimo = (float) $p['stock_minimo'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $p['nombre']) ?></strong>
                                    <?php if ($stock < 0): ?>
                                        <span class="stock-negativo">
                                            <i class="bi bi-exclamation-octagon-fill"></i>
                                            Hay que contar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) $p['categoria']) ?></td>
                                <td class="text-end">
                                    <span class="<?= e(clase_stock($stock, $minimo, (string) $p['unidad'])) ?>">
                                        <?= e(cantidad($stock)) ?>
                                        <?= e((string) $p['unidad']) ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= e(cantidad($minimo)) ?></td>
                                <?php if ($esAdmin): ?>
                                    <td class="text-end">
                                        <a href="<?= e(url('compras/nueva')) ?>"
                                           class="btn btn-atilio-amarillo btn-sm">
                                            <i class="bi bi-truck"></i>
                                            <span class="d-none d-md-inline">Reponer</span>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($esAdmin): ?>
    <div class="tarjeta tarjeta-destacada mb-3">
        <div class="tarjeta-cuerpo centrado">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Todo el stock está por encima del mínimo.</strong>
            <?php if ((int) $stockNegativo > 0): ?>
                <span style="color: var(--texto-perdida);">
                    Hay <?= e((string) $stockNegativo) ?> producto(s) en negativo: conviene contarlos.
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($esAdmin): ?>

    <!-- ================= ADMINISTRADOR ================= -->

    <!-- Resumen de usuarios -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Usuarios</p>
                <p class="valor"><?= e((string) $totalUsuarios) ?></p>
                <span class="detalle">
                    <i class="bi bi-person-check-fill"></i>
                    <?= e((string) $usuariosActivos) ?> activo(s)
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Administradores</p>
                <p class="valor"><?= e((string) $administradores) ?></p>
                <span class="detalle">
                    <i class="bi bi-shield-lock-fill"></i> Acceso total
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor">
                <p class="etiqueta">Vendedores</p>
                <p class="valor"><?= e((string) $vendedores) ?></p>
                <span class="detalle">
                    <i class="bi bi-person-badge"></i> Mesas y cobro
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="tarjeta-valor <?= ((int) $intentosFallidos > 0) ? 'acento-amarillo' : '' ?>">
                <p class="etiqueta">Intentos fallidos</p>
                <p class="valor"><?= e((string) $intentosFallidos) ?></p>
                <span class="detalle">
                    <i class="bi bi-shield-exclamation"></i> Últimas 24 horas
                </span>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Accesos rápidos -->
        <div class="col-12 col-lg-6">
            <div class="tarjeta h-100">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-rocket-takeoff-fill"></i> Accesos rápidos</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <div class="d-grid gap-2">
                        <a href="<?= e(url('productos')) ?>" class="btn btn-atilio">
                            <i class="bi bi-cup-straw"></i> Carta de productos
                        </a>
                        <a href="<?= e(url('compras/nueva')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-truck"></i> Cargar una compra
                        </a>
                        <a href="<?= e(url('stock')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-box-seam-fill"></i> Ver el stock
                        </a>
                        <a href="<?= e(url('usuarios')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-people-fill"></i> Gestionar usuarios
                        </a>
                        <a href="<?= e(url('mi-cuenta')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-key-fill"></i> Cambiar mi contraseña
                        </a>
                    </div>

                    <hr class="my-3">

                    <p class="mb-1 fw-bold">Próximas etapas</p>
                    <ul class="mb-0" style="color: var(--texto-suave);">
                        <li><strong>Etapa 3:</strong> mesas y pedidos.</li>
                        <li><strong>Etapa 4:</strong> ventas, cobro y caja.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Movimientos recientes -->
        <div class="col-12 col-lg-6">
            <div class="tarjeta h-100">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-clock-history"></i> Accesos y cambios recientes</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <?php if (empty($ultimosAccesos)): ?>
                        <p class="text-center mb-0" style="color: var(--texto-suave);">
                            <i class="bi bi-inbox"></i>
                            Todavía no hay movimientos registrados.
                        </p>
                    <?php else: ?>
                        <ul class="lista-movimientos">
                            <?php foreach ($ultimosAccesos as $mov): ?>
                                <?php
                                // Icono y color según la acción.
                                $infoAccion = match ($mov['accion']) {
                                    'login'            => ['bi-box-arrow-in-right', 'Entró'],
                                    'logout'           => ['bi-box-arrow-right', 'Salió'],
                                    'crear_usuario'    => ['bi-person-plus-fill', 'Creó el usuario'],
                                    'editar_usuario'   => ['bi-pencil-fill', 'Editó el usuario'],
                                    'activar_usuario'  => ['bi-check-circle-fill', 'Activó el usuario'],
                                    'desactivar_usuario' => ['bi-slash-circle-fill', 'Desactivó el usuario'],
                                    'resetear_password' => ['bi-arrow-repeat', 'Reseteó la contraseña de'],
                                    'cambio_password'  => ['bi-key-fill', 'Cambió su contraseña'],
                                    default            => ['bi-dot', $mov['accion']],
                                };
                                ?>
                                <li>
                                    <i class="bi <?= e($infoAccion[0]) ?> mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong><?= e((string) $mov['usuario_nombre']) ?></strong>
                                        <?= e($infoAccion[1]) ?>
                                        <?php if (in_array($mov['accion'], ['resetear_password', 'editar_usuario'], true) && $mov['entidad_id']): ?>
                                            <br>
                                            <span class="mono">#<?= e((string) $mov['entidad_id']) ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="momento">
                                            <?= e(fecha_hora($mov['creado_en'])) ?>
                                            <?php if (!empty($mov['ip'])): ?>
                                                &middot; <?= e((string) $mov['ip']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ================= VENDEDOR ================= -->

    <div class="row g-3">

        <!-- Bienvenida -->
        <div class="col-12 col-lg-8">
            <div class="tarjeta tarjeta-destacada h-100">
                <div class="tarjeta-cuerpo">
                    <h2 class="h4 mb-2">
                        <i class="bi bi-cup-straw"></i>
                        Hola, <?= e(Auth::nombre_completo()) ?>
                    </h2>

                    <p class="mb-3" style="color: var(--texto-suave);">
                        Estás trabajando como <strong><?= e(texto_rol(Auth::rol())) ?></strong>.
                        Desde acá vas a poder manejar las mesas, cargar pedidos,
                        cobrar y consultar el stock.
                    </p>

                    <div class="d-grid gap-2">
                        <a href="<?= e(url('productos')) ?>" class="btn btn-atilio">
                            <i class="bi bi-cup-straw"></i> Ver la carta y los precios
                        </a>
                        <a href="<?= e(url('stock')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-box-seam-fill"></i> Consultar el stock
                        </a>
                        <a href="<?= e(url('mi-cuenta')) ?>" class="btn btn-atilio-amarillo">
                            <i class="bi bi-key-fill"></i> Cambiar mi contraseña
                        </a>
                    </div>

                    <p class="mt-3 mb-0 small" style="color: var(--texto-suave);">
                        <i class="bi bi-info-circle-fill"></i>
                        Las mesas, los pedidos y el cobro llegan en las próximas
                        etapas. Esta pantalla se va a completar sola.
                    </p>
                </div>
            </div>
        </div>

        <!-- Datos de la sesión -->
        <div class="col-12 col-lg-4">
            <div class="tarjeta h-100">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-person-badge"></i> Tu sesión</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <ul class="lista-movimientos">
                        <li>
                            <i class="bi bi-person-fill mt-1"></i>
                            <div>
                                <strong><?= e(Auth::nombre()) ?></strong><br>
                                <span class="detalle" style="color: var(--texto-suave); font-size: 0.85rem;">
                                    <?= e(Auth::nombre_completo()) ?>
                                </span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill mt-1"></i>
                            <div>
                                Entró a las
                                <strong><?= e(fecha_hora($_SESSION['inicio_sesion'] ?? null)) ?></strong><br>
                                <span style="color: var(--texto-suave); font-size: 0.85rem;">
                                    La sesión se cierra sola después de
                                    <?= e((string) SESION_MINUTOS_INACTIVIDAD) ?> min sin actividad.
                                </span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-shield-lock-fill mt-1"></i>
                            <div>
                                <span class="badge <?= e(clase_rol(Auth::rol())) ?>">
                                    <?= e(texto_rol(Auth::rol())) ?>
                                </span>
                            </div>
                        </li>
                    </ul>

                    <form method="post" action="<?= e(url('salir')) ?>" class="mt-3">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-atilio-amarillo w-100">
                            <i class="bi bi-box-arrow-right"></i> Salir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
