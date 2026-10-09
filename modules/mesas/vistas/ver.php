<?php
/**
 * modules/mesas/vistas/ver.php
 * -----------------------------------------------------------------
 * Mesa abierta: ver el pedido y cargarle cosas.
 *
 * Variables: $mesa, $comanda, $categorias, $productos, $stockAlerta,
 *            $mesasDestino, $misMesas, $esAdmin, $estado
 * -----------------------------------------------------------------
*/

$titulo    = 'Mesa ' . (string) $comanda['mesa_numero'];
$subtitulo = $estado === 'por_cobrar'
    ? 'Pidió la cuenta: el pedido está cerrado, no se le pueden agregar cosas'
    : 'Abierta ' . (int) $comanda['comensales'] . ' '
        . (((int) $comanda['comensales'] === 1) ? 'comensal' : 'comensales')
        . ' · ' . $comanda['vendedor'];

$cerrada = ($estado !== 'abierta');

// Botones de la cabecera
$accionesCabecera = '<a href="' . e(url('mesas')) . '" class="btn btn-atilio-amarillo">'
    . '<i class="bi bi-arrow-left"></i> Mesas</a>';

if (!$cerrada) {
    $accionesCabecera .= ' <a href="' . e(url('mesas/mover') . '&comanda_id=' . (int) $comanda['id']) . '" '
        . 'class="btn btn-atilio-amarillo">'
        . '<i class="bi bi-arrow-left-right"></i> Mover</a>';
}

if ($cerrada && $estado === 'por_cobrar') {
    // Pidio la cuenta: lo natural es cobrar. Va primero porque es
    // lo que hay que hacer ahora.
    $accionesCabecera = '<a href="' . e(url('cobrar') . '&id=' . (int) $comanda['id']) . '" '
        . 'class="btn btn-atilio-oscuro">'
        . '<i class="bi bi-cash-coin"></i> Cobrar</a> ' . $accionesCabecera;
}

// En una mesa de pool, el boton de la ficha va PRIMERO en la cabecera:
// es la accion que mas se hace, una por cada vez que alguien juega.
//
// Va arriba y no solo en la grilla de productos a proposito: la ficha
// es lo unico que se cobra en una mesa de pool. Si el mozo tiene que
// buscarla entre las bebidas para encontrarla, hay chances de que se
// olvide, y una ficha olvidada es plata que no entra.
//
// Es un form y no un link a propósito: el logout tambien es un POST
// por lo mismo, para que un link externo no pueda cargar fichas sola.
if (!$cerrada && $esPool && $fichaPoolId > 0) {
    $accionesCabecera = '<form method="post" action="' . e(url('mesas/agregar-producto')) . '" '
        . 'class="d-inline">'
        . csrf_campo()
        . '<input type="hidden" name="comanda_id" value="' . (int) $comanda['id'] . '">'
        . '<input type="hidden" name="producto_id" value="' . (int) $fichaPoolId . '">'
        . '<input type="hidden" name="cantidad" value="1">'
        . '<button type="submit" class="btn btn-atilio-amarillo boton-ficha" '
        . 'title="Cargar una ficha de ' . e(money($fichaPoolPrecio)) . '">'
        . '<i class="bi bi-plus-circle-fill"></i> Ficha'
        . '</button>'
        . '</form> ' . $accionesCabecera;
}

require APP_ROOT . '/includes/header.php';
?>

<!-- ================= Aviso de pedido cerrado ================= -->
<?php if ($estado === 'por_cobrar'): ?>
    <div class="alert alert-warning">
        <i class="bi bi-cash-coin"></i>
        <strong>Esta mesa pidió la cuenta.</strong>
        El pedido quedó cerrado: no se le pueden agregar productos.
        El cobro llega en la etapa siguiente.
    </div>

    <!-- Volver atrás: pasa siempre que el cliente pide la cuenta
         y al ver la carta se acuerda de algo más. -->
    <details class="tarjeta mb-3">
        <summary class="tarjeta-cabecera" style="cursor: pointer;">
            <span>
                <i class="bi bi-arrow-counterclockwise"></i>
                ¿Se olvidaron algo? Reabrir el pedido
            </span>
        </summary>
        <div class="tarjeta-cuerpo">
            <p class="small mb-2">
                El pedido vuelve a quedar abierto y se le pueden agregar
                productos. El stock no se toca: lo que ya estaba cargado
                sigue descontado.
            </p>
            <form method="post" action="<?= e(url('mesas/reabrir')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                <input type="text" name="motivo" class="form-control mb-2"
                       placeholder="Motivo (obligatorio)" maxlength="255" required
                       aria-label="Motivo por el que se reabre el pedido">
                <button type="submit" class="btn btn-atilio">
                    <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                </button>
            </form>
        </div>
    </details>
<?php endif; ?>

<?php if (!empty($comanda['observacion'])): ?>
    <div class="marco-amarillo p-2 mb-3">
        <i class="bi bi-chat-left-text-fill"></i>
        <strong>Nota:</strong> <?= e((string) $comanda['observacion']) ?>
    </div>
<?php endif; ?>

<!-- ================= EL PEDIDO ================= -->
<div class="tarjeta mb-3">
    <div class="tarjeta-cabecera">
        <span>
            <i class="bi bi-receipt"></i>
            Pedido <?= e((string) $comanda['numero']) ?>
        </span>
        <span class="small">
            abierta <?= e(hora((string) $comanda['abierta_en'])) ?>
        </span>
    </div>

    <?php $itemsPendientes = array_filter($comanda['items'], static fn ($i) => $i['estado'] === 'pendiente'); ?>
    <?php $itemsAnulados = array_filter($comanda['items'], static fn ($i) => $i['estado'] === 'anulado'); ?>

    <?php if (empty($itemsPendientes)): ?>
        <div class="tarjeta-cuerpo centrado py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            <span style="color: var(--texto-suave);">
                Todavía no tiene productos cargados.
            </span>
        </div>
    <?php else: ?>
        <div class="tabla-envoltura">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Precio</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Importe</th>
                        <?php if (!$cerrada): ?>
                            <th class="text-end">Quitar</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemsPendientes as $item): ?>
                        <?php
                        $cantidad = (float) $item['cantidad'];
                        $puedeAnular = !$cerrada;
                        ?>
                        <tr>
                            <td><?= e((string) $item['producto_nombre']) ?></td>
                            <td class="text-end"><?= e(money($item['precio_unitario'])) ?></td>
                            <td class="text-center">
                                <?php if ($cerrada || $cantidad == (int) $cantidad): ?>
                                    <strong><?= e(cantidad($cantidad)) ?></strong>
                                <?php else: ?>
                                    <!-- Con fracciones (por ejemplo, medio gin)
                                         se deja editar la cantidad -->
                                    <form method="post" action="<?= e(url('mesas/cambiar-cantidad')) ?>"
                                          class="d-inline-flex gap-1 align-items-center">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="text" name="cantidad"
                                               class="form-control form-control-sm cantidad-rapida"
                                               value="<?= e(cantidad($cantidad)) ?>"
                                               inputmode="decimal" style="width: 76px;">
                                        <button type="submit" class="btn btn-atilio-amarillo btn-sm"
                                                title="Cambiar la cantidad">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <strong><?= e(money($item['importe'])) ?></strong>
                            </td>

                            <?php if (!$cerrada): ?>
                                <td class="text-end">
                                    <form method="post" action="<?= e(url('mesas/anular-producto')) ?>"
                                          class="d-inline">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="motivo" value="">
                                        <button type="submit" class="btn btn-peligro btn-quitar-item"
                                                title="Anular este producto"
                                                aria-label="Sacar <?= e((string) $item['producto_nombre']) ?> del pedido"
                                                data-confirmar="¿Sacar &quot;<?= e((string) $item['producto_nombre']) ?>&quot; del pedido? Se devuelve el stock.">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="tarjeta-cuerpo">
        <div class="total-pedido">
            <span>Total</span>
            <strong><?= e(money(Comanda::total((int) $comanda['id']))) ?></strong>
        </div>
    </div>
</div>

<?php if (!$cerrada): ?>
    <div class="acciones-mesa mb-4">
        <form method="post" action="<?= e(url('mesas/por-cobrar')) ?>" class="d-grid">
            <?= csrf_campo() ?>
            <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
            <button type="submit" class="btn btn-atilio-oscuro btn-grande"
                    data-confirmar="¿Cerrar el pedido y pedir la cuenta? Después no se le pueden agregar productos.">
                <i class="bi bi-cash-coin"></i>
                Pedir la cuenta
            </button>
        </form>
    </div>
<?php endif; ?>

<?php
/* ================= LA FICHA DEL POOL =================
 *
 * En una mesa de pool, el boton "+ Ficha" grande va arriba de todo, con
 * el numero de fichas ya cargadas. Un toque por ficha jugada.
 *
 * Esta ACA y no solo arriba en la cabecera porque el boton de la
 * cabecera esta en el angulo superior derecho y se mezcla con los
 * demas botones: para cobrar una ficha, que es lo que el mozo hace
 * cada vez que alguien termina de jugar, tiene que estar a la vista
 * con el numero al lado.
 *
 * El numero grande no es decorativo: es la unica forma de saber de
 * un vistazo cuantas fichas llevo. Si el mozo no lo ve, carga otra
 * y cobra de mas; si no ve nada, carga de menos.
 */
if ($esPool && !$cerrada && $fichaPoolId > 0): ?>
    <div class="bloque-ficha">
        <form method="post" action="<?= e(url('mesas/agregar-producto')) ?>" class="d-grid">
            <?= csrf_campo() ?>
            <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
            <input type="hidden" name="producto_id" value="<?= (int) $fichaPoolId ?>">
            <input type="hidden" name="cantidad" value="1">

            <button type="submit" class="boton-ficha-grande">
                <span class="boton-ficha-icono">
                    <i class="bi bi-plus-lg"></i>
                </span>
                <span class="boton-ficha-texto">
                    <strong>Cargar ficha</strong>
                    <small><?= e(money($fichaPoolPrecio)) ?> cada una</small>
                </span>
                <span class="boton-ficha-conteo" aria-label="Fichas cargadas">
                    <strong><?= (int) $fichasCargadas ?></strong>
                    <small><?= ((int) $fichasCargadas === 1) ? 'ficha' : 'fichas' ?></small>
                </span>
            </button>
        </form>

        <?php if ((int) $fichasCargadas === 0): ?>
            <p class="boton-ficha-aviso">
                <i class="bi bi-info-circle"></i>
                Todavía no cargaste ninguna ficha. Cada vez que alguien
                juega, tocá el botón.
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ================= AGREGAR PRODUCTOS ================= -->
<?php if (!$cerrada): ?>
    <div class="tarjeta mb-3">
        <div class="tarjeta-cabecera">
            <span>
                <i class="bi bi-plus-circle-fill"></i>
                Agregar al pedido
            </span>
            <span class="small">Tocá el producto y listo</span>
        </div>

        <div class="tarjeta-cuerpo">
            <!-- Buscador -->
            <div class="campo mb-3">
                <label for="buscarProducto" class="form-label">Buscar</label>
                <input type="search" id="buscarProducto" class="form-control"
                       placeholder="Escribí el nombre de lo que buscan"
                       autocomplete="off" data-filtrar-grilla="grillaProductos">
            </div>

            <?php foreach ($categorias as $categoria): ?>
                <?php
                $deEsta = array_values(array_filter(
                    $productos,
                    static fn ($p) => (int) $p['categoria_id'] === (int) $categoria['id']
                ));
                ?>
                <?php if (empty($deEsta)) { continue; } ?>

                <h3 class="categoria-titulo"><?= e((string) $categoria['nombre']) ?></h3>

                <div class="grilla-productos">
                    <?php foreach ($deEsta as $producto): ?>
                        <?php
                        $pid = (int) $producto['id'];
                        $conStock = (int) $producto['controla_stock'] === 1;
                        $alerta = isset($stockAlerta[$pid]);
                        $agotado = $conStock && (float) $producto['stock_actual'] <= 0;

                        // Si el producto se vende de varias formas (por
                        // ejemplo las empanadas: de a una, por media
                        // docena y por docena), cada forma es un boton
                        // propio. Asi el mozo nunca escribe la cantidad.
                        $formas = formas_venta((string) ($producto['formas_venta'] ?? ''));
                        $variasFormas = count($formas) > 1;
                        ?>

                        <div class="producto-boton-wrap"
                             data-nombre="<?= e(mb_strtolower((string) $producto['nombre'])) ?>">
                            <div class="producto-boton-cuerpo">
                                <span class="producto-nombre"><?= e((string) $producto['nombre']) ?></span>
                                <span class="producto-precio">
                                    <?= e(money($producto['precio_venta'])) ?>
                                    <?= $variasFormas ? 'c/u' : '' ?>
                                </span>

                                <?php if ($conStock): ?>
                                    <span class="producto-stock">
                                        <?php if ($agotado): ?>
                                            <span class="stock-cero">
                                                <i class="bi bi-x-circle-fill"></i> Sin stock
                                            </span>
                                        <?php elseif ($alerta): ?>
                                            <span class="stock-bajo">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <?= e(cantidad($producto['stock_actual'])) ?> <?= e((string) $producto['unidad']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="producto-stock-ok">
                                                <i class="bi bi-check"></i>
                                                <?= e(cantidad($producto['stock_actual'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($variasFormas): ?>
                                <!-- Un <form> por forma: cada boton carga
                                     directo lo que dice. -->
                                <div class="formas-venta">
                                    <?php foreach ($formas as $forma): ?>
                                        <form method="post" action="<?= e(url('mesas/agregar-producto')) ?>">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                                            <input type="hidden" name="producto_id" value="<?= $pid ?>">
                                            <input type="hidden" name="cantidad" value="<?= e(cantidad($forma['cantidad'])) ?>">
                                            <button type="submit"
                                                    class="forma-boton <?= $agotado ? 'forma-boton-agotado' : '' ?>"
                                                    title="<?= e(cantidad($forma['cantidad'])) ?> <?= e((string) $producto['nombre']) ?> por <?= e(money((float) $producto['precio_venta'] * $forma['cantidad'])) ?>">
                                                <strong><?= e($forma['etiqueta']) ?></strong>
                                                <small><?= e(money((float) $producto['precio_venta'] * $forma['cantidad'])) ?></small>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('mesas/agregar-producto')) ?>"
                                      class="producto-boton-cantidad">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                                    <input type="hidden" name="producto_id" value="<?= $pid ?>">

                                    <button type="button" class="btn-resta" tabindex="-1" aria-label="Restar uno">&minus;</button>
                                    <input type="text" name="cantidad" class="producto-cantidad"
                                           value="1" inputmode="numeric" aria-label="Cantidad de <?= e((string) $producto['nombre']) ?>">
                                    <button type="button" class="btn-suma" tabindex="-1" aria-label="Sumar uno">+</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($esAdmin && !$cerrada): ?>
    <div class="tarjeta">
        <div class="tarjeta-cabecera">
            <span style="color: #8A1B1B;">
                <i class="bi bi-exclamation-octagon-fill"></i>
                Zona de administrador
            </span>
        </div>
        <div class="tarjeta-cuerpo">
            <p class="mb-2">
                Anular la mesa da de baja el pedido <strong>sin cobrar</strong> y
                devuelve todo el stock. Solo para el administrador.
            </p>
            <form method="post" action="<?= e(url('mesas/anular')) ?>"
                  data-confirmar="Se va a anular esta mesa y se devuelve todo el stock. ¿Seguro?">
                <?= csrf_campo() ?>
                <input type="hidden" name="comanda_id" value="<?= (int) $comanda['id'] ?>">
                <input type="text" name="motivo" class="form-control mb-2"
                       placeholder="Motivo (obligatorio)" maxlength="255" required>
                <button type="submit" class="btn btn-peligro">
                    <i class="bi bi-x-octagon-fill"></i> Anular la mesa
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
// Datos para el JavaScript del buscador y los botones de cantidad.
$scriptsDatos = ['mesa' => (int) $comanda['id']];
$scriptsExtra = '<script>window.ATILIO_MESA = ' . json_encode($scriptsDatos, JSON_UNESCAPED_UNICODE) . ';</script>';
?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
