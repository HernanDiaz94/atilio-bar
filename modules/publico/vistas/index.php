<?php
/**
 * modules/publico/vistas/index.php
 * -----------------------------------------------------------------
 * La página pública.
 *
 * NO USA includes/header.php
 *
 * A propósito. Ese archivo trae el menú del panel, que es para los
 * del bar: en una página que ve cualquiera, no tiene sentido y suma
 * peso. Esta pantalla trae su propio HTML mínimo.
 *
 * Pensada para el teléfono: se lee en la vereda, con una mano, con
 * poco brillo. El dato más importante (si está abierto o no) está
 * arriba de todo, sin hacer scroll.
 * -----------------------------------------------------------------
 *
 * @var array $semana, $estado, $porCategoria, $proximosCierres
 * @var string $nombre, $telefono, $direccion, $texto
 * @var bool $mostrarHorarios, $mostrarCarta, $mostrarMapa
 * @var array $horariosJsonLd
 */

$diaHoy = (int) date('w');

// Un número de teléfono tiene que servir para llamar desde el celu:
// solo dígitos y un + adelante.
$telLlamada = '';
$telWhatsapp = '';

if ($telefono !== '') {
    $soloDigitos = preg_replace('/[^0-9]/', '', $telefono) ?? '';
    $telLlamada = $telefono;

    if ($soloDigitos !== '') {
        $telLlamada = '+' . $soloDigitos;
    }
}
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#BFE3B4">

    <title><?= e($tituloPagina) ?></title>
    <meta name="description" content="<?= e($descripcion) ?>">

    <?php // Que la página se pueda compartir en WhatsApp con el nombre y el precio adentro ?>
    <meta property="og:type" content="restaurant.restaurant">
    <meta property="og:title" content="<?= e($tituloPagina) ?>">
    <meta property="og:description" content="<?= e($descripcion) ?>">
    <meta property="og:image" content="<?= e(url_absoluta('assets/img/logo.svg', true)) ?>">
    <meta name="twitter:card" content="summary">

    <?php // La dirección canónica, para que Google no cuente copias ?>
    <link rel="canonical" href="<?= e(url('publico')) ?>">

    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/publico.css')) ?>" rel="stylesheet">

    <?php // ---------- Datos estructurados para Google ----------
          // Le dicen a Google que esto es un bar y a qué hora abre.
          // Con esto puede mostrar "Abierto · Cierra 02:00" en los
          // resultados, que es justo lo que la gente busca. ?>
    <?php // ---- Datos estructurados para Google ----
          // URL absoluta: Google ignora los datos con rutas relativas.
          // Y sin claves en null: una clave vacía se interpreta
          // como "el bar no tiene dirección", que no es lo que
          // sabemos; es mejor no mandarla. ?>
    <?php
    $datosGoogle = [
        '@context' => 'https://schema.org',
        '@type'    => 'BarOrPub',
        'name'     => $nombre,
        'url'      => url_absoluta('publico'),
        'image'    => url_absoluta('assets/img/logo.svg', true),
        'description' => $descripcion,
        'openingHoursSpecification' => $horariosJsonLd,
    ];

    if ($direccion !== '') {
        $datosGoogle['address'] = [
            '@type'         => 'PostalAddress',
            'streetAddress' => $direccion,
        ];
    }

    if ($telefono !== '') {
        $datosGoogle['telephone'] = $telefono;
    }
    ?>
    <script type="application/ld+json">
    <?= json_encode($datosGoogle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
</head>

<body class="publico-cuerpo">
<div class="publico-envoltura">

    <!-- ============ CABECERA ============ -->
    <header class="publico-cabecera">
        <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>"
             alt="Logo de <?= e($nombre) ?>" class="publico-logo" width="72" height="72">

        <h1><?= e($nombre) ?></h1>

        <?php if ($direccion !== ''): ?>
            <p class="publico-direccion"><?= e($direccion) ?></p>
        <?php endif; ?>

        <?php if ($texto !== ''): ?>
            <p class="publico-texto"><?= e($texto) ?></p>
        <?php endif; ?>
    </header>

    <!-- ============ ¿ABIERTO O CERRADO? ============
         Lo primero de todo, porque es lo que la gente vino a ver. -->
    <div class="estado-grande <?= $estado['abierto'] ? 'estado-grande-abierto' : 'estado-grande-cerrado' ?>">
        <?php if ($estado['abierto']): ?>
            <span class="estado-grande-icono"><i class="bi bi-check-circle-fill"></i></span>
            <strong>Abierto ahora</strong>
            <span class="estado-grande-detalle">
                Cierra a las <?= e($estado['cierra']?->format('H:i') ?? '-') ?>
            </span>
        <?php else: ?>
            <span class="estado-grande-icono"><i class="bi bi-moon-stars-fill"></i></span>
            <strong>Cerrado ahora</strong>
            <span class="estado-grande-detalle"><?= e(Horarios::textoProxima($estado['ahora'])) ?></span>
            <?php if ($estado['motivo'] !== ''): ?>
                <span class="estado-grande-motivo"><?= e($estado['motivo']) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ============ CÓMO CONTACTAR ============ -->
    <?php if ($telLlamada !== '' || $direccion !== ''): ?>
        <div class="publico-contacto">
            <?php if ($telLlamada !== ''): ?>
                <a href="tel:<?= e($telLlamada) ?>" class="publico-boton">
                    <i class="bi bi-telephone-fill"></i>
                    <span>Llamar</span>
                    <small><?= e($telefono) ?></small>
                </a>
            <?php endif; ?>

            <?php if ($direccion !== ''): ?>
                <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($direccion)) ?>"
                   target="_blank" rel="noopener" class="publico-boton">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Cómo llegar</span>
                    <small><?= e($direccion) ?></small>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============ EL MAPA ============ -->
    <?php if ($mostrarMapa): ?>
        <div class="publico-bloque">
            <div class="publico-mapa">
                <iframe
                    title="Mapa de <?= e($nombre) ?>"
                    src="https://maps.google.com/maps?q=<?= e(rawurlencode($direccion)) ?>&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ HORARIOS ============ -->
    <?php if ($mostrarHorarios): ?>
        <div class="publico-bloque">
            <h2><i class="bi bi-clock"></i> Horarios</h2>

            <ul class="publico-horarios">
                <?php foreach ($semana as $dia): ?>
                    <li class="<?= ((int) $dia['dia'] === $diaHoy && !$dia['cerrado']) ? 'hoy' : '' ?>">
                        <span class="publico-dia"><?= e($dia['nombre']) ?></span>
                        <span class="publico-horario"><?= e($dia['texto']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!empty($proximosCierres)): ?>
                <div class="publico-cierres">
                    <strong>Próximos días cerrados:</strong>
                    <ul>
                        <?php foreach (array_slice($proximosCierres, 0, 8) as $cierre): ?>
                            <li>
                                <?= e(fecha_corta($cierre['fecha'])) ?>
                                <?= e(fecha_dia_semana($cierre['fecha'])) ?>
                                <?php if ((string) $cierre['motivo'] !== ''): ?>
                                    <span class="publico-cierre-motivo">(<?= e($cierre['motivo']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============ LA CARTA ============ -->
    <?php if ($mostrarCarta): ?>
        <div class="publico-bloque">
            <h2><i class="bi bi-cup-hot"></i> Carta</h2>

            <?php if (empty($porCategoria)): ?>
                <p class="publico-vacio">Todavía no hay productos cargados en la carta.</p>
            <?php else: ?>
                <?php foreach ($porCategoria as $categoria => $items): ?>
                    <h3 class="publico-categoria"><?= e($categoria) ?></h3>

                    <ul class="publico-carta">
                        <?php foreach ($items as $producto):
                            $formas = formas_venta((string) $producto['formas_venta']);

                            // Si el producto se vende de a medias (media
                            // docena, docena), se muestran todas las
                            // formas con su precio propio.
                            ?>
                            <li>
                                <div class="publico-producto-cabecera">
                                    <span class="publico-producto-nombre"><?= e($producto['nombre']) ?></span>
                                    <?php if (count($formas) <= 1): ?>
                                        <span class="publico-producto-precio">
                                            <?= e(money((float) $producto['precio_venta'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (count($formas) > 1): ?>
                                    <ul class="publico-formas">
                                        <?php foreach ($formas as $forma):
                                            // El precio sale del producto por
                                            // la cantidad, exactamente
                                            // igual que lo que cobra el
                                            // sistema. Si un dia se
                                            // cambiara esa regla, hay que
                                            // cambiar los dos lugares.
                                            $cant = (float) $forma['cantidad'];
                                            ?>
                                            <li>
                                                <span><?= e($forma['etiqueta']) ?></span>
                                                <span><?= e(money((float) $producto['precio_venta'] * $cant)) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ((string) $producto['descripcion'] !== ''): ?>
                                    <p class="publico-producto-descripcion"><?= e($producto['descripcion']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <footer class="publico-pie">
        <?php if (Auth::logueado()): ?>
            <?php // Si quien lo mira es del bar, un acceso directo al panel ?>
            <a href="<?= e(url('dashboard')) ?>">Ir al panel del bar</a>
        <?php endif; ?>
    </footer>

</div>
</body>
</html>