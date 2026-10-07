<?php
/**
 * includes/vistas/instalar_pendiente.php
 * -----------------------------------------------------------------
 * Se muestra cuando alguien entra al login pero la aplicación
 * todavía no fue instalada (no hay usuarios en la base de datos).
 *
 * No se intenta iniciar sesión: directamente se le dice que falta
 * correr install.php.
 * -----------------------------------------------------------------
 */ ?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Falta instalar &middot; <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
</head>
<body class="login-fondo">

<div class="login-caja">
    <div class="text-center">
        <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>"
             alt="Logo de <?= e(APP_NOMBRE) ?>"
             class="login-logo mx-auto">

        <h1 class="login-titulo"><?= e(APP_NOMBRE) ?></h1>
        <p class="login-subtitulo mb-0">Sistema de gestión del bar</p>
    </div>

    <div class="alert alert-warning mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Falta terminar la instalación.</strong>
        Todavía no hay ningún usuario cargado en la base de datos.
    </div>

    <p class="mb-3">
        Abrí el instalador para crear las tablas y los usuarios iniciales
        (dos administradores y dos vendedores):
    </p>

    <a href="<?= e(url_archivo('install.php')) ?>" class="btn btn-atilio-oscuro w-100">
        <i class="bi bi-wrench-adjustable"></i> Ejecutar instalación ahora
    </a>

    <div class="login-pie">
        Si el instalador dice que la conexión falla, revisá los datos
        de MySQL en <span class="mono">config/config.php</span>.
    </div>
</div>

</body>
</html>