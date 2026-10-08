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
 *      'menu'        => false para las rutas que existen pero NO van
 *                      en la barra. Sin esta bandera la barra se
 *                      armaba salteando las rutas sin icono, y eso
 *                      metia en el menu cualquier pantalla a la que
 *                      se le pusiera un icono: "Mi cuenta" y "Salir"
 *                      quedaron pegadas al resto, y "Salir" era un
 *                      boton de dos pasos del que lo rodea.
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

            // Se llega desde el listado de Usuarios. En la barra solo
            // ocupaba un lugar que el usuario no busca.
            'menu'        => false,
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

            // Va en el menu desplegable del usuario, no en la barra.
            'menu'        => false,
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

            // Va en el menu desplegable del usuario, no en la barra.
            'menu'        => false,
        ],

        // ---------- Productos (Etapa 2) ----------
        // El vendedor PUEDE ver la carta: necesita conocer los
        // productos y sus precios para atender. Lo que no puede es
        // modificarla, ver costos ni tocar el stock.
        'productos' => [
            'controlador' => 'productos/index.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Productos',
            'icono'       => 'bi-cup-straw',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Solo administradores: la pantalla trae los formularios de
        // alta y desactivación. El vendedor ya ve la categoría de
        // cada producto en la carta, así que no pierde nada.
        'categorias' => [
            'controlador' => 'productos/categorias.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Categorías',
            'icono'       => 'bi-tags-fill',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'productos/formulario' => [
            'controlador' => 'productos/formulario.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Editar producto',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'productos/guardar' => [
            'controlador' => 'productos/guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar producto',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'productos/acciones' => [
            'controlador' => 'productos/acciones.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Acciones de producto',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'categorias/guardar' => [
            'controlador' => 'productos/categoria_guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar categoría',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'categorias/acciones' => [
            'controlador' => 'productos/categoria_acciones.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Acciones de categoría',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Stock (Etapa 2) ----------
        // El historial es lectura para los dos: sirve para entender
        // por qué cambió un número. Ajustar stock no.
        'stock' => [
            'controlador' => 'stock/historial.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Stock',
            'icono'       => 'bi-box-seam-fill',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],

        'stock/ajuste' => [
            'controlador' => 'stock/ajuste.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Ajuste de stock',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'stock/ajuste-guardar' => [
            'controlador' => 'stock/ajuste_guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar ajuste',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'compras' => [
            'controlador' => 'stock/compras.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Compras',
            'icono'       => 'bi-truck',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'compras/nueva' => [
            'controlador' => 'stock/compra_nueva.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Nueva compra',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'compras/guardar' => [
            'controlador' => 'stock/compra_guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar compra',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'compras/ver' => [
            'controlador' => 'stock/compra_ver.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Ver compra',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Mesas y comandas (Etapa 3) ----------
        // La grilla la ven los dos: es la pantalla principal del
        // bar. Abrir mesa, agregar productos y pedir la cuenta es
        // tarea del vendedor.
        'mesas' => [
            'controlador' => 'mesas/index.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Mesas',
            'icono'       => 'bi-grid-3x3-gap-fill',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'mesas/ver' => [
            'controlador' => 'mesas/ver.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Ver pedido',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Abrir mesa
        'mesas/abrir' => [
            'controlador' => 'mesas/abrir.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Abrir mesa',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Agregar producto al pedido
        'mesas/agregar-producto' => [
            'controlador' => 'mesas/agregar_producto.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Agregar producto',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Anular un producto del pedido
        'mesas/anular-producto' => [
            'controlador' => 'mesas/anular_producto.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Anular producto',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Cambiar la cantidad de un producto del pedido
        'mesas/cambiar-cantidad' => [
            'controlador' => 'mesas/cambiar_cantidad.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Cambiar cantidad',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'mesas/por-cobrar' => [
            'controlador' => 'mesas/por_cobrar.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Pedir la cuenta',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Volver a abrir un pedido que ya habia pedido la cuenta.
        // Los dos roles: en un bar pasa seguido que el cliente
        // pide la cuenta y despues se agrega algo mas.
        'mesas/reabrir' => [
            'controlador' => 'mesas/reabrir.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Reabrir pedido',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'mesas/mover' => [
            'controlador' => 'mesas/mover.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Mover de mesa',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Anular una mesa (dar de baja el pedido sin cobrar):
        // SOLO ADMINISTRADOR, porque repone todo el stock.
        'mesas/anular' => [
            'controlador' => 'mesas/anular.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Anular mesa',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Configuración de mesas (solo admin) ----------
        'mesas/administrar' => [
            'controlador' => 'mesas/administrar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Administrar mesas',
            'icono'       => 'bi-gear-fill',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'mesas/guardar-mesa' => [
            'controlador' => 'mesas/guardar_mesa.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar mesa',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'mesas/acciones-mesa' => [
            'controlador' => 'mesas/acciones_mesa.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Acciones de mesa',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // Generar varias mesas de un tirón. Es lo cómodo cuando el
        // bar tiene 20 o 30 mesas y no querés cargarlas de a una.
        'mesas/generar' => [
            'controlador' => 'mesas/generar_mesas.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Generar mesas',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Reservas (Etapa 3) ----------
        // Las ve el vendedor: tiene que saber quién llega a las 22.
        'reservas' => [
            'controlador' => 'mesas/reservas.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Reservas',
            'icono'       => 'bi-bookmark-fill',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],

        'reservas/guardar' => [
            'controlador' => 'mesas/reserva_guardar.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Guardar reserva',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],

        'reservas/acciones' => [
            'controlador' => 'mesas/reserva_acciones.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Acciones de reserva',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],
        // ---------- Cobro (Etapa 4) ----------
        // Cobrar es tarea del vendedor: es lo que hace todo el día.
        // El descuento NO: ese lo hace el administrador, y el
        // control real está en Venta::cobrar(), no acá.
        'cobrar' => [
            'controlador' => 'cobrar/index.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Cobrar mesa',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'cobrar/confirmar' => [
            'controlador' => 'cobrar/confirmar.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Confirmar cobro',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Aplicar (o quitar) el descuento de una mesa mientras se
        // cobra. El permiso es de vendedor porque el control real
        // esta en Venta::cobrar() y en el propio controlador: aca
        // solo se evita que el vendedor llegue a la pantalla.
        'cobrar/descuento' => [
            'controlador' => 'cobrar/descuento.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Aplicar descuento',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        // Anular una venta devuelve plata y stock: solo admin.
        'cobrar/anular' => [
            'controlador' => 'cobrar/anular.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Anular venta',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Caja (Etapa 4) ----------
        // La caja la ven los dos: el vendedor necesita saber si
        // puede cobrar. Abrir y cerrar es del administrador.
        'caja' => [
            'controlador' => 'caja/index.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Caja',
            'icono'       => 'bi-safe-fill',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],

        // Esta ruta la ven los dos. Cuando la caja ya esta abierta,
        // la pantalla SOLO muestra el formulario de entrada o salida
        // de plata, y eso lo puede hacer cualquiera del turno (esta
        // misma pantalla tiene el boton "Registrar movimiento" en la
        // caja, visible para los dos).
        //
        // Estava en 'administrador' y por eso el vendedor que le
        // daba click al boton caia en un 403: el boton lo llevaba a
        // una pantalla de la que no tenia permiso.
        //
        // ABRIR la caja sigue siendo solo del administrador, y eso se
        // controla adentro de caja/abrir.php: es el unico lugar que
        // sabe si el pedido es para abrir el turno o para registrar
        // un movimiento. Si se quisiera dejar "abrir" en una ruta
        // aparte habria que partir el.controller en dos, porque los
        // dos formularios salen de la misma vista.
        'caja/abrir' => [
            'controlador' => 'caja/abrir.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Abrir caja',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'caja/cerrar' => [
            'controlador' => 'caja/cerrar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Cerrar caja',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // Entradas y salidas de plata que no son ventas: las puede
        // hacer cualquiera del turno, no hace falta ser admin.
        'caja/movimiento' => [
            'controlador' => 'caja/movimiento.php',
            'permiso'     => 'vendedor',
            'titulo'      => 'Movimiento de caja',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => null,
        ],

        // ---------- Historial de ventas (solo admin) ----------
        'ventas' => [
            'controlador' => 'ventas/index.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Ventas',
            'icono'       => 'bi-receipt-cutoff',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Reportes (solo admin) ----------
        //
        // Llevan la ganancia y el costo de los productos, que no son
        // asunto del vendedor. El permiso va en la ruta porque es lo
        // primero que se mira al entrar: si el usuario no tiene el
        // permiso, ni siquiera se carga el archivo del controlador.
        'reportes' => [
            'controlador' => 'reportes/index.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Reportes',
            'icono'       => 'bi-graph-up',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'reportes/csv' => [
            'controlador' => 'reportes/csv.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Descargar reporte',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Horarios (solo admin) ----------
        //
        // El horario y los días cerrados los edita el dueño: es un
        // dato que cambia pocas veces y hay que saber que quedó bien.
        'horarios' => [
            'controlador' => 'horarios/index.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Horarios',
            'icono'       => 'bi-clock',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'horarios/guardar' => [
            'controlador' => 'horarios/guardar.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Guardar horarios',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        'horarios/cierre' => [
            'controlador' => 'horarios/cierre.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Días cerrados',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
        ],

        // ---------- Página pública (Etapa 6) ----------
        //
        // SIN SESIÓN. Es lo único del sistema que se abre sin
        // identificarse: el horario y la carta, para que alguien que
        // no tiene cuenta pueda ver si el bar está abierto.
        //
        // Conoce 'publico', que en Auth::requiere_rol() significa
        // "no pidas sesión". No lleva icono a propósito: así no
        // aparece en el menú del panel, que es para los del bar.
        'publico' => [
            'controlador' => 'publico/index.php',
            'permiso'     => 'publico',
            'titulo'      => 'Atilio Bar',
            'icono'       => '',
            'grupo'       => 'principal',
            'ocultar'     => null,
        ],

        'ventas/ver' => [
            'controlador' => 'ventas/ver.php',
            'permiso'     => 'administrador',
            'titulo'      => 'Ver venta',
            'icono'       => '',
            'grupo'       => 'gestion',
            'ocultar'     => 'es_admin',
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
    $permitidasConClaveTemporal = [
        'mi-cuenta',
        'mi-cuenta/guardar',
        'salir',
        'publico',          // No tiene nada que ver con claves: se puede mirar sin haberla cambiado.
    ];

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
