<?php
/**
 * includes/vistas/403.php
 * -----------------------------------------------------------------
 * Pantalla de acceso denegado.
 *
 * Aparece cuando un vendedor intenta abrir una pantalla de
 * administrador. La ruta ya fue bloqueada en el servidor: esta
 * pantalla solo informa.
 *
 * Variables esperadas: $titulo
 * -----------------------------------------------------------------
 */ ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado &middot; <?= e(APP_NOMBRE) ?></title>
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

            <p class="codigo">403</p>
            <h1>No tenés permiso para ver esta pantalla</h1>

            <p>
                Esta sección es solo para administradores.
                Si creés que es un error, hablá con el dueño del bar.
            </p>

            <div class="d-flex gap-2 justify-content-center flex-wrap mt-4">
                <a href="<?= e(url('dashboard')) ?>" class="btn btn-atilio">
                    <i class="bi bi-arrow-left"></i> Volver al inicio
                </a>

                <?php if (Auth::logueado()): ?>
                    <form method="post" action="<?= e(url('salir')) ?>" class="d-inline">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-atilio-amarillo">
                            <i class="bi bi-box-arrow-right"></i> Cambiar de usuario
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>