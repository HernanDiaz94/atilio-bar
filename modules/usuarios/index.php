<?php
/**
 * modules/usuarios/index.php
 * -----------------------------------------------------------------
 * Listado de usuarios. Solo administradores.
 *
 * Incluye búsqueda por texto y filtro por rol, hechos en el
 * servidor con consultas preparadas.
 * -----------------------------------------------------------------
 */

// --- Filtros de búsqueda ---------------------------------------
$buscar = get('q');
$rolFiltro = get('rol');

// Validamos el rol del filtro para que no entre cualquier cosa
// en la consulta.
if (!in_array($rolFiltro, ['administrador', 'vendedor'], true)) {
    $rolFiltro = '';
}

// --- Consulta ---------------------------------------------------
// El LIKE va con parámetro, nunca concatenado: así un usuario no
// puede romper la consulta escribiendo % en el buscador.
$sql = 'SELECT id, usuario, nombre, apellido, email, rol, activo,
               debe_cambiar_password, intentos_fallidos, bloqueado_hasta,
               ultimo_acceso, creado_en
        FROM usuarios
        WHERE 1 = 1';

$parametros = [];

if ($buscar !== '') {
    // Cada placeholder tiene que ser distinto. Con
    // EMULATE_PREPARES apagado (que es lo correcto para evitar
    // inyecciones), MySQL NO admite repetir el mismo nombre en una
    // consulta: "Invalid parameter number".
    $sql .= ' AND (usuario  LIKE :buscar_usuario
                   OR nombre   LIKE :buscar_nombre
                   OR apellido LIKE :buscar_apellido
                   OR email    LIKE :buscar_email)';

    $patron = '%' . $buscar . '%';

    $parametros['buscar_usuario']   = $patron;
    $parametros['buscar_nombre']    = $patron;
    $parametros['buscar_apellido']  = $patron;
    $parametros['buscar_email']     = $patron;
}

if ($rolFiltro !== '') {
    $sql .= ' AND rol = :rol';
    $parametros['rol'] = $rolFiltro;
}

$sql .= ' ORDER BY activo DESC, nombre ASC, apellido ASC';

$usuarios = DB::all($sql, $parametros);

// --- Datos para el resumen de arriba ----------------------------
// El admin no puede desactivarse a sí mismo ni quedar sin admins
// activos, así que conviene verlo siempre en pantalla.
$totalUsuarios = (int) DB::valor('SELECT COUNT(*) FROM usuarios');
$usuariosActivos = (int) DB::valor('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
$usuariosInactivos = (int) DB::valor('SELECT COUNT(*) FROM usuarios WHERE activo = 0');
$debenCambiar = (int) DB::valor('SELECT COUNT(*) FROM usuarios WHERE debe_cambiar_password = 1 AND activo = 1');
$bloqueados = (int) DB::valor('SELECT COUNT(*) FROM usuarios WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()');

require APP_ROOT . '/modules/usuarios/vistas/lista.php';