<?php
/**
 * login.php
 * -----------------------------------------------------------------
 * Pantalla de inicio de sesión.
 *
 * Es un archivo de acceso directo (no pasa por index.php), pero
 * carga el mismo bootstrap.php, así que tiene disponibles la base
 * de datos, los helpers y la sesión.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/includes/bootstrap.php';

// ------------------------------------------------------------
// Si el usuario ya tiene sesión abierta, no tiene sentido
// mostrarle el login: lo mandamos directo al dashboard.
// ------------------------------------------------------------
if (Auth::logueado()) {
    redirigir('dashboard');
}

// ------------------------------------------------------------
// Si la aplicación todavía no está instalada, todo este login
// no sirve de nada. Mandamos al instalador.
// ------------------------------------------------------------
if (!DB::instalado()) {
    $titulo = 'Instalación pendiente';
    require __DIR__ . '/includes/vistas/instalar_pendiente.php';
    exit;
}

// Si veníamos de un error anterior, recuperamos lo que se había tipeado
// para no obligar a escribirlo de nuevo.
$erroresAntiguos = recuperar_entradas();

$errores = [];
$usuarioIngresado = (string) ($erroresAntiguos['usuario'] ?? '');

if (es_post()) {
    // 1) Token CSRF: sin esto, alguien de afuera podría
    //    mandarle un POST a esta página desde su página.
    csrf_verificar();

    // 2) Datos del formulario
    $usuario   = post('usuario');
    $password  = post_crudo('password');

    $errores = validar(
        ['usuario' => $usuario],
        [
            'usuario' => ['requerido' => true, 'mensaje' => ['requerido' => 'Escribí tu usuario.']],
        ]
    );

    if ($password === '') {
        $errores['password'] = 'Escribí tu contraseña.';
    }

    if (empty($errores)) {
        // 3) Intentar iniciar sesión
        $resultado = Auth::login($usuario, $password);

        // Guardamos lo tipeado por si hay que volver atrás.
        guardar_entradas(['usuario' => $usuario]);

        if ($resultado['ok']) {
            flash_exito('¡Bienvenido de vuelta, ' . Auth::nombre_completo() . '!');

            // Si el usuario tiene que cambiar la contraseña, el router
            // lo va a mandar a mi-cuenta en la primera pantalla.
            redirigir('dashboard');
        }

        $errores['general'] = $resultado['mensaje'];
    }

    $usuarioIngresado = $usuario;
}

$titulo = 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#BFE3B4">
    <title>Iniciar sesión &middot; <?= e(APP_NOMBRE) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
</head>
<body class="login-fondo">

<div class="login-caja">

    <!-- ========================= LOGO ========================= -->
    <div class="text-center">
        <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>"
             alt="Logo de <?= e(APP_NOMBRE) ?>"
             class="login-logo mx-auto">

        <h1 class="login-titulo"><?= e(APP_NOMBRE) ?></h1>
        <p class="login-subtitulo mb-0">Sistema de gestión del bar</p>
    </div>

    <!-- ========================= ERROR GENERAL ========================= -->
    <?php if (!empty($errores['general'])): ?>
        <div class="alert alert-danger mt-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= e($errores['general']) ?>
        </div>
    <?php endif; ?>

    <!-- ========================= FORMULARIO ========================= -->
    <form method="post" action="<?= e(url_archivo('login.php')) ?>"
          class="mt-3" data-validar novalidate>

        <?= csrf_campo() ?>

        <!-- Usuario -->
        <div class="campo mb-3 <?= isset($errores['usuario']) ? 'campo-error' : '' ?>">
            <label for="usuario" class="form-label">
                <i class="bi bi-person-fill"></i> Usuario
            </label>
            <input type="text"
                   id="usuario"
                   name="usuario"
                   class="form-control"
                   value="<?= e($usuarioIngresado) ?>"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required
                   autofocus
                   data-etiqueta="usuario">
            <?php if (isset($errores['usuario'])): ?>
                <span class="error-campo"><?= e($errores['usuario']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Contraseña -->
        <div class="campo mb-3 <?= isset($errores['password']) ? 'campo-error' : '' ?>">
            <label for="password" class="form-label">
                <i class="bi bi-lock-fill"></i> Contraseña
            </label>
            <div class="input-group">
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       autocomplete="current-password"
                       required
                       data-etiqueta="contraseña">
                <button class="btn btn-atilio-amarillo" type="button"
                        data-ver-password="password" aria-label="Ver contraseña">
                    <i class="bi bi-eye"></i> Ver
                </button>
            </div>
            <?php if (isset($errores['password'])): ?>
                <span class="error-campo"><?= e($errores['password']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Botón -->
        <button type="submit" class="btn btn-atilio-oscuro w-100 mb-3">
            <i class="bi bi-box-arrow-in-right"></i> Entrar
        </button>
    </form>

    <!-- Aviso de seguridad -->
    <div class="login-local">
        <i class="bi bi-shield-lock-fill"></i>
        Después de varios intentos fallidos la cuenta se bloquea un rato.
        Si te quedás afuera, pedile la clave a un administrador.
    </div>

    <div class="login-pie">
        <?= e(APP_NOMBRE) ?> &middot; <?= e(date('Y')) ?> &middot; Todos los derechos reservados
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url_archivo('assets/js/app.js')) ?>"></script>

</body>
</html>