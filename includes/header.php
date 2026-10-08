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
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
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

            <?php if (Auth::logueado()): ?>
                <!-- La clase "dropdown" es de Bootstrap y NO es decorativa: es la que
                     convierte este div en el punto de anclaje del desplegable.
                     Sin ella, el menu se posiciona contra otro elemento y el
                     "bottom: 100%" del CSS de abajo no mide desde el lugar
                     correcto, con lo cual el desplegable queda fuera de la
                     pantalla. -->
                <div class="usuario-menu dropdown">
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