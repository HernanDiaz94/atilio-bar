<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------
 * Cabecera común: <head>, navbar y avisos.
 *
 * Las vistas de cada módulo lo incluyen así:
 *   $titulo = 'Usuarios';
 *   require APP_ROOT . '/includes/header.php';
 *   ... contenido de la vista ...
 *   require APP_ROOT . '/includes/footer.php';
 *
 * Variables disponibles (las define el router):
 *   $titulo      Título de la página
 *   $rutaActual  Ruta activa para pintar el enlace del menú
 *   $rutas       Mapa completo de rutas (para armar el menú)
 * -----------------------------------------------------------------
 */

// Si la vista se incluye sin pasar por el router, evitamos errores.
$titulo     = $titulo     ?? APP_NOMBRE;
$rutaActual = $rutaActual ?? '';
$rutas      = $rutas      ?? function_exists('rutas_app') ? rutas_app() : [];
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#BFE3B4">
    <title><?= e($titulo) ?> &middot; <?= e(APP_NOMBRE) ?></title>

    <!-- Favicon generado a partir del mismo SVG del logo -->
    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">

    <!-- Bootstrap 5 por CDN (no requiere instalación por consola) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Tema propio: paleta amarilla y verde en assets/css/theme.css -->

    <!-- El ?v= lleva la fecha del archivo. Sin esto, el navegador y el
         hosting guardan el CSS viejo y siguen mostrando la pantalla
         anterior aunque el archivo ya este subido: fue lo que paso con
         el boton del usuario, que el dueno subio dos veces sin ver
         ningun cambio. Con la fecha, cada version del CSS es distinta
         para el navegador y se descarga siempre la que esta subida. -->
    <?php $cssTema = APP_ROOT . '/assets/css/theme.css'; ?>
    <link href="<?= e(url_archivo('assets/css/theme.css') . '?v=' . (is_file($cssTema) ? (string) filemtime($cssTema) : '1')) ?>"
          rel="stylesheet">
</head>
<body>

<!-- ========================= NAVBAR ========================= -->
<nav class="navbar navbar-expand-lg atilio-navbar sticky-top">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('dashboard')) ?>">
            <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>" alt="Logo de <?= e(APP_NOMBRE) ?>" class="logo-atilio" width="40" height="40">
            <span class="brand-texto">
                <?= e(APP_NOMBRE) ?>
                <small class="d-block brand-sub">Sistema de bar</small>
            </span>
        </a>

        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <?php /* El boton del usuario va FUERA de este bloque colapsable, y despues
             del </div> que lo cierra.

             POR QUE: abajo de 992px, este bloque se oculta con el hamburger.
             Con el boton del usuario ADENTRO, el unico modo de llegar a
             "Salir" era abrir el menu y recorrer la lista entera de modulos
             hasta el final. Medido en un Poco X6 Pro (407x904 CSS), la barra
             abierta ocupaba 676px de 844: el 80% de la pantalla. Y con la
             barra cerrada el boton directamente no existe en pantalla
             (offsetParent null, alto cero).

             Ademas el desplegable queda a merced de Popper, que calcula la
             posicion en JavaScript y puede pisar el "bottom: 100%" del CSS.
             Afuera del collapse, el boton esta siempre en pantalla y el
             desplegable se abre hacia abajo, con espacio de sobra. */
        ?>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php
                // El menú se arma con el mapa de rutas: si la ruta no está en
                // el mapa, no aparece. Y el botón solo se oculta por rol;
                // el control real igual lo hace el router.
                foreach ($rutas as $nombre => $ruta):
                    // Rutas que existen pero NO van en la barra. Se miran
                    // por la bandera 'menu', no por el icono: vaciar el icono
                    // es una convención invisible, y el dia que alguien le
                    // ponga uno a una pantalla para otra cosa, vuelve a
                    // aparecer en el menu sin que nadie se entere.
                    //
                    // "Mi cuenta" y "Salir" estan en el desplegable del
                    // usuario, al final de la barra. Estaban tambien en la
                    // barra principal, y quedaban pegados al resto: un boton
                    // de "Salir" a dos pasos de los demas es un logout
                    // accidental esperando que alguien pase el dedo.
                    if (($ruta['menu'] ?? true) === false) {
                        continue;
                    }

                    // Solo menú: las rutas de acciones (sin icono) no van.
                    if ($ruta['icono'] === '') {
                        continue;
                    }

                    // Ocultar según el rol del usuario.
                    if ($ruta['ocultar'] === 'es_admin' && !Auth::es_admin()) {
                        continue;
                    }

                    $activo = ($rutaActual === $nombre);
                    $clase  = $activo ? 'nav-link active' : 'nav-link';
                ?>
                    <li class="nav-item">
                        <a class="<?= e($clase) ?>" href="<?= e(url($nombre)) ?>">
                            <i class="bi <?= e($ruta['icono']) ?>"></i>
                            <span><?= e($ruta['titulo']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div><!-- /#menuPrincipal -->

        <?php if (Auth::logueado()): ?>
                <!-- La clase "dropdown" es de Bootstrap y NO es decorativa: es la que
                     convierte este div en el punto de anclaje del desplegable.
                     Sin ella, el menu se posiciona contra otro elemento y el
                     "bottom: 100%" del CSS de abajo no mide desde el lugar
                     correcto, con lo cual el desplegable queda fuera de la
                     pantalla. -->
                <!-- data-bs-display="static" le dice a Bootstrap que NO use
                     Popper para posicionar este desplegable.

                     Por que importa: con Popper, el menu se posiciona en
                     JavaScript y gana contra el CSS. Abajo de 992px el CSS
                     lo pone "position: static" (para que caiga en el flujo
                     normal y se pueda deslizar hasta el), pero Popper le
                     escribia arriba un estilo inline con "position:
                     absolute", y el menu volvia a flotar sobre el pie de
                     pagina, sin espacio para deslizar.

                     Sin esto, en el celular "Salir" queda debajo del todo y
                     no hay forma de llegar con el dedo.
                -->
                <div class="usuario-menu dropdown" data-bs-display="static">
                    <button class="btn btn-atilio btn-sm usuario-boton" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle-fill"></i>
                        <span class="d-none d-sm-inline"><?= e(Auth::nombre_completo()) ?></span>
                        <span class="badge <?= e(clase_rol(Auth::rol())) ?> ms-1"><?= e(texto_rol(Auth::rol())) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                Sesión iniciada<br>
                                <strong class="text-body"><?= e(Auth::nombre()) ?></strong><br>
                                Desde <?= e(fecha_hora($_SESSION['inicio_sesion'] ?? null, false)) ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= e(url('mi-cuenta')) ?>">
                                <i class="bi bi-key-fill"></i> Mi cuenta
                                <small class="d-block text-muted">Cambiar la contraseña</small>
                            </a>
                        </li>
                        <li>
                            <!-- Logout por POST con token CSRF: no se puede desloguear
                                 a alguien con un simple link externo. -->
                            <form method="post" action="<?= e(url('salir')) ?>" class="d-inline">
                                <?= csrf_campo() ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right"></i> Salir
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
    </div>
</nav>

<!-- ========================= CONTENIDO ========================= -->
<!-- Avisos flotantes que genera assets/js/app.js -->
<div id="contenedorAvisos" class="avisos-flotantes"></div>

<main class="container-fluid atilio-contenido">

    <?php
    // Encabezado de página, opcional: solo se muestra si la vista define $subtitulo.
    if (!empty($subtitulo) || !empty($accionesCabecera)): ?>
        <div class="cabecera-pagina">
            <div>
                <h1 class="titulo-pagina"><?= e($titulo) ?></h1>
                <?php if (!empty($subtitulo)): ?>
                    <p class="subtitulo-pagina mb-0"><?= e($subtitulo) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($accionesCabecera)): ?>
                <div class="acciones-cabecera"><?= $accionesCabecera ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    // Mensajes flash (éxito, error, info) de la operación anterior.
    $mensajesFlash = flash_todos();

    if (!empty($mensajesFlash)): ?>
        <div class="avisos">
            <?php foreach ($mensajesFlash as $aviso): ?>
                <?php
                $claseAviso = match ($aviso['tipo']) {
                    'exito' => 'alert-success',
                    'error' => 'alert-danger',
                    default => 'alert-info',
                };
                $iconoAviso = match ($aviso['tipo']) {
                    'exito' => 'bi-check-circle-fill',
                    'error' => 'bi-exclamation-triangle-fill',
                    default => 'bi-info-circle-fill',
                };
                ?>
                <div class="alert <?= e($claseAviso) ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= e($iconoAviso) ?>"></i>
                    <span><?= e($aviso['mensaje']) ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>