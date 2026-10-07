<?php
/**
 * includes/router.php
 * -----------------------------------------------------------------
 * Enrutador casero.
 *
 * No usamos .htaccess para reescribir URLs: en ByetHost el módulo
 * mod_rewrite no siempre está activo. En vez de eso, todas las
 * pantallas se piden como index.php?r=nombre_de_ruta y este archivo
 * decide qué controlador se ejecuta.
 *
 * Ventaja: el ruteo no depende de la configuración del servidor y
 * la app funciona en la raíz de htdocs o en una subcarpeta.
 *
 * Cada ruta declara el rol mínimo que la puede usar. El control
 * pasa acá, en el servidor: ocultar un botón no es seguridad.
 * -----------------------------------------------------------------
 */

/**
 * Mapa de rutas de la aplicación.
 *
 * Formato de cada ruta:
 *   'nombre' => [
 *      'controlador' => ruta del archivo PHP dentro de /modules,
 *      'permiso'     => 'administrador' | 'vendedor',
 *      'titulo'      => texto que se ve en el <title> y en la navbar,
 *      'icono'       => ícono de Bootstrap para la navbar,
 *      'grupo'       => 'principal' | 'gestion',
 *      'ocultar'     => nombre de la constante/variable de rol que
 *                      decide si aparece el enlace en el menú,
 *   ]
 *
 * @return array<string, array<string, mixed>>
 */
function rutas_app(): array
{
    return [
        // ---------- Pantallas principales ----------
        'dashboard' => [
            'controlador' => 'dashboard/index.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Inicio',
            'icono'       => 'bi-speedometer2',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // ---------- Usuarios (solo administrador) ----------
        'usuarios' => [
            'controlador' => 'usuarios/index.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Usuarios',
            'icono'       => 'bi-people-fill',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'usuarios/formulario' => [
            'controlador' => 'usuarios/formulario.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Editar usuario',
            'icono'       => 'bi-person-lines-fill',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'usuarios/guardar' => [
            'controlador' => 'usuarios/guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar usuario',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'usuarios/acciones' => [
            'controlador' => 'usuarios/acciones.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Acciones de usuario',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Contraseña (cualquiera puede cambiar la propia) ----------
        'mi-cuenta' => [
            'controlador' => 'usuarios/mi_cuenta.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Mi cuenta',
            'icono'       => 'bi-key-fill',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'mi-cuenta/guardar' => [
            'controlador' => 'usuarios/mi_cuenta_guardar.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Cambiar contraseña',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // ---------- Salir ----------
        'salir' => [
            'controlador' => 'auth/salir.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Salir',
            'icono'       => 'bi-box-arrow-right',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],
    ];
}

/**
 * Resuelve la ruta pedida y ejecuta el controlador correspondiente.
 */
function router_resolver(): void
{
    $ruta = get('r', 'dashboard');

    // Normalizar: sin barras al inicio ni al final, y sin dobles barras.
    $ruta = trim($ruta, '/');
    $ruta = preg_replace('#/+#', '/', $ruta) ?? 'dashboard';

    $rutas = rutas_app();

    // ---------------------------------------------------------
    // ¿La app está instalada? Si no, mandamos al instalador.
    // ---------------------------------------------------------
    if (!DB::instalado() && $ruta !== 'salir') {
        $instalador = url_archivo('install.php');

        if (!headers_sent()) {
            header('Location: ' . $instalador);
            exit;
        }

        exit;
    }

    // ---------------------------------------------------------
    // Ruta desconocida
    // ---------------------------------------------------------
    if (!isset($rutas[$ruta])) {
        http_response_code(404);
        $titulo = 'Página no encontrada';
        $rutaActual = $ruta;
        require APP_ROOT . '/includes/vistas/404.php';
        exit;
    }

    $config = $rutas[$ruta];

    // ---------------------------------------------------------
    // Control de sesión y rol. Va antes de cargar cualquier
    // controlador, así un vendedor nunca ejecuta código de admin.
    // ---------------------------------------------------------
    Auth::requiere_rol($config['permiso']);

    // Si el usuario tiene que cambiar la contraseña (el admin le
    // generó una clave temporal), queda encerrado en "mi cuenta"
    // hasta que la cambie.
    //
    // Ojo con la lista de rutas permitidas: tiene que incluir la
    // que GUARDA la contraseña nueva y la de logout. Si faltara
    // alguna, el usuario quedaría en un bucle de redirecciones
    // sin poder resolver nunca su propio problema.
    $permitidasConClaveTemporal = ['mi-cuenta', 'mi-cuenta/guardar', 'salir'];

    $usuario = Auth::usuario();

    if (
        $usuario
        && (int) $usuario['debe_cambiar_password'] === 1
        && !in_array($ruta, $permitidasConClaveTemporal, true)
    ) {
        flash_info('Por seguridad tenés que definir una contraseña nueva antes de continuar.');
        redirigir('mi-cuenta');
    }

    // ---------------------------------------------------------
    // Control CSRF: obligatorio en todo POST.
    // ---------------------------------------------------------
    if (es_post()) {
        csrf_verificar();
    }

    // ---------------------------------------------------------
    // Variables que usan los controladores y las vistas
    // ---------------------------------------------------------
    $rutaActual  = $ruta;
    $titulo      = $config['titulo'];
    $rutas       = $rutas;
    $configRuta  = $config;

    // Solo permitimos archivos .php dentro de /modules.
    $controlador = APP_ROOT . '/modules/' . $config['controlador'];

    if (!is_file($controlador) || !str_ends_with($controlador, '.php')) {
        http_response_code(500);
        exit;
    }

    require $controlador;
}