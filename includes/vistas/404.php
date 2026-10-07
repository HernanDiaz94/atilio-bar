<?php
/**
 * includes/vistas/404.php
 * -----------------------------------------------------------------
 * Pantalla de página no encontrada.
 *
 * Aparece cuando se pide una ruta que no existe en el mapa de
 * rutas_app(). También se muestra si alguien tipea mal la URL.
 * -----------------------------------------------------------------
 */ ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada &middot; <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
</head>
<body>
    <main class="pantalla-error container-fluid">
        <div>
            <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>"
                 alt="Logo de <?= e(APP_NOMBRE) ?>"
                 class="login-logo mb-3">

            <p class="codigo">404</p>
            <h1>No encontramos esa pantalla</h1>

            <p>
                La dirección que abriste no existe o cambió de lugar.
                Volvé al inicio para seguir trabajando.
            </p>

            <a href="<?= e(url('dashboard')) ?>" class="btn btn-atilio mt-3">
                <i class="bi bi-house-fill"></i> Ir al inicio
            </a>
        </div>
    </main>
</body>
</html>