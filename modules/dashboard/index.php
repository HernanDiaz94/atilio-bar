<?php
/**
 * modules/dashboard/index.php
 * -----------------------------------------------------------------
 * Pantalla de inicio. Cambia según el rol:
 *   - administrador: ve estado del sistema, últimos accesos, atajos
 *     a gestión de usuarios y avisos importantes.
 *   - vendedor: ve un resumen simple con lo que puede hacer y
 *     ningún dato sensible (ni costos, ni ganancias, ni usuarios).
 *
 * En la Etapa 1 todavía no hay mesas ni ventas, así que el resumen
 * muestra información de usuarios y configuración. Las etapas
 * siguientes van a sumar tarjetas de mesas, ventas y stock.
 * -----------------------------------------------------------------
 */

// El router ya validó sesión, rol y CSRF. Acá solo junto datos
// para la vista.
$esAdmin = Auth::es_admin();

// --- Datos para el administrador -------------------------------
$totalUsuarios    = 0;
$usuariosActivos  = 0;
$administradores  = 0;
$vendedores       = 0;
$ultimosAccesos   = [];
$intentosFallidos = 0;

if ($esAdmin) {
    $totalUsuarios = (int) DB::valor('SELECT COUNT(*) FROM usuarios');
    $usuariosActivos = (int) DB::valor('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
    $administradores = (int) DB::valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador' AND activo = 1");
    $vendedores     = (int) DB::valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'vendedor' AND activo = 1");

    // Últimos movimientos de auditoría: quién entró, quién salió,
    // quién creó o editó un usuario.
    $ultimosAccesos = DB::all(
        "SELECT * FROM auditoria
         WHERE accion IN ('login', 'logout', 'crear_usuario', 'editar_usuario',
                          'activar_usuario', 'desactivar_usuario', 'resetear_password',
                          'cambio_password')
         ORDER BY id DESC
         LIMIT 12"
    );

    // Intentos fallidos en las últimas 24 horas.
    $desde24h = date('Y-m-d H:i:s', time() - (24 * 60 * 60));
    $intentosFallidos = (int) DB::valor(
        'SELECT COUNT(*) FROM usuarios_login_intentos
         WHERE exitoso = 0 AND creado_en >= :desde',
        ['desde' => $desde24h]
    );
}

// --- Alertas de stock (Etapa 2) ---------------------------------
// Le interesan a los dos roles: al admin para reponer, al
// vendedor para saber qué no se puede ofrecer.
$stockBajo     = [];
$stockCero     = 0;
$stockNegativo = 0;

if ($esAdmin) {
    $limiteAlerta = (int) DB::valor(
        "SELECT valor FROM configuracion WHERE clave = 'stock_alerta_dashboard'"
    );
    $limiteAlerta = ($limiteAlerta > 0) ? $limiteAlerta : 8;

    $stockBajo     = Stock::stock_bajo($limiteAlerta);
    $stockCero     = Stock::cantidad_stock_bajo();
    $stockNegativo = Stock::cantidad_stock_negativo();
} else {
    // El vendedor ve solo una lista corta, sin costos.
    $stockBajo = DB::all(
        "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, p.unidad,
                c.nombre AS categoria
         FROM productos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         WHERE p.activo = 1 AND p.controla_stock = 1
           AND p.stock_actual <= p.stock_minimo
         ORDER BY (p.stock_actual - p.stock_minimo) ASC, p.nombre ASC
         LIMIT 5"
    );
}

// --- Datos comunes (los dos roles) -----------------------------
$configuracion = DB::all('SELECT clave, valor FROM configuracion ORDER BY clave');

// Configuración con la que arrancó la aplicación: sirve para
// recordarle al admin qué tocar si algo falla.
$miIp = ip_cliente();

require APP_ROOT . '/modules/dashboard/vistas/inicio.php';