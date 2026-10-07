<?php
/**
 * modules/usuarios/formulario.php
 * -----------------------------------------------------------------
 * Alta y edición de usuarios. Solo administradores.
 *
 * Sirve para los dos casos: si viene ?id= se edita ese usuario,
 * si no viene se crea uno nuevo.
 * -----------------------------------------------------------------
*/

$idUsuario = get_id('id');
$usuario   = null;
$esNuevo   = ($idUsuario === 0);

// --- Cargar el usuario a editar --------------------------------
if (!$esNuevo) {
    $usuario = DB::one(
        'SELECT id, usuario, nombre, apellido, email, rol, activo,
                debe_cambiar_password, ultimo_acceso, creado_en
         FROM usuarios
         WHERE id = :id',
        ['id' => $idUsuario]
    );

    // Si el id no existe, no es un error grave: lo mandamos
    // al listado con un aviso.
    if ($usuario === null) {
        flash_error('Ese usuario no existe o fue eliminado.');
        redirigir('usuarios');
    }
}

// --- Datos precargados del formulario ---------------------------
// Si viene de un intento fallido, usamos lo que el admin había
// tipeado (guardar_entradas). Si no, los valores actuales.
$entradas = recuperar_entradas();

$valores = [
    'usuario'   => $entradas['usuario']   ?? ($usuario['usuario']   ?? ''),
    'nombre'    => $entradas['nombre']    ?? ($usuario['nombre']    ?? ''),
    'apellido'  => $entradas['apellido']  ?? ($usuario['apellido']  ?? ''),
    'email'     => $entradas['email']     ?? ($usuario['email']     ?? ''),
    'rol'       => $entradas['rol']       ?? ($usuario['rol']       ?? 'vendedor'),
    'activo'    => $entradas['activo']    ?? ($usuario !== null ? (int) $usuario['activo'] : 1),
];

// --- Un usuario con ventas asociadas NUNCA se borra -------------
// En la Etapa 1 todavía no existe la tabla ventas, pero el chequeo
// queda preparado: cuando se sume la Etapa 4 va a encontrar la tabla.
$tieneVentas = false;

try {
    $tieneVentas = (int) DB::valor('SELECT COUNT(*) FROM ventas WHERE usuario_id = :id', ['id' => $idUsuario]) > 0;
} catch (Throwable $e) {
    // La tabla ventas todavía no existe (Etapa 1). No es un error.
    $tieneVentas = false;
}

// --- Editarse a sí mismo tiene sus propios límites --------------
$esElMismo = (!$esNuevo && $idUsuario === Auth::id());

// --- Listado de admins activos: para no dejar al sistema sin admins
$otrosAdminsActivos = (int) DB::valor(
    "SELECT COUNT(*) FROM usuarios
     WHERE rol = 'administrador' AND activo = 1 AND id <> :id",
    ['id' => Auth::id()]
);

$esUltimoAdmin = $esElMismo && Auth::es_admin() && $otrosAdminsActivos === 0;

// Errores del POST anterior, si guardar.php lo rebotó.
$erroresGuardados = tomar_errores();

require APP_ROOT . '/modules/usuarios/vistas/formulario.php';