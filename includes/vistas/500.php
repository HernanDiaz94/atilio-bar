<?php
/**
 * includes/vistas/500.php
 * -----------------------------------------------------------------
 * Pantalla de error interno.
 *
 * IMPORTANTE: acá NO se muestra el detalle del error. El mensaje
 * real (con archivo, línea y traza) queda escrito en logs/app.log.
 * Mostrarlo en pantalla leaky información de la estructura interna
 * del sitio y puede ayudar a un atacante.
 * -----------------------------------------------------------------
 */ ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error &middot; <?= e(APP_NOMBRE) ?></title>
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

            <p class="codigo">500</p>
            <h1>Algo salió mal de nuestro lado</h1>

            <p>
                No pasa nada grave: la operación no se guardó a medias.
                Probá de nuevo en un momento.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-4">
                <a href="<?= e(url('dashboard')) ?>" class="btn btn-atilio">
                    <i class="bi bi-arrow-left"></i> Volver al inicio
                </a>

                <?php if (isset($titulo) && $titulo === 'Error de conexión'): ?>
                    <a href="<?= e(url_archivo('install.php')) ?>" class="btn btn-atilio-amarillo">
                        <i class="bi bi-wrench-adjustable"></i> Revisar instalación
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($mensajeError)): ?>
                <!-- Mensaje propio de la app, sin detalle técnico. -->
                <p class="alert alert-warning mt-4 mx-auto" style="max-width: 520px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= e($mensajeError) ?>
                </p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>