<?php
/**
 * modules/usuarios/guardar.php
 * -----------------------------------------------------------------
 * Procesa el alta y la edición de usuarios.
 *
 * Este archivo NO muestra nada: solo valida, guarda y redirige.
 * Si hay errores, vuelve al formulario con los mensajes.
 * -----------------------------------------------------------------
*/

$idUsuario = (int) post('id', '0');
$esNuevo   = ($idUsuario === 0);

// --- Datos del formulario --------------------------------------
$datos = [
    'usuario'  => post('usuario'),
    'nombre'   => post('nombre'),
    'apellido' => post('apellido'),
    'email'    => post('email'),
    'rol'      => post('rol'),
    'activo'   => (int) post('activo', '1') === 1 ? 1 : 0,
];

$password          = post_crudo('password');
$passwordConfirm   = post_crudo('password_confirmacion');

// Errores que no pertenecen a un campo puntual sino a la operación
// completa (por ejemplo: "no podés dejar el sistema sin admins").
$erroresGenerales = [];

// --- Usuario existente (en modo edición) -----------------------
$usuarioExistente = null;

if (!$esNuevo) {
    $usuarioExistente = DB::one(
        'SELECT id, usuario, rol, activo FROM usuarios WHERE id = :id',
        ['id' => $idUsuario]
    );

    if ($usuarioExistente === null) {
        flash_error('Ese usuario no existe.');
        redirigir('usuarios');
    }

    // No te desactives a vos mismo ni te saques el rol de admin:
    // el sistema quedaría sin nadie que lo administre.
    if ($idUsuario === Auth::id()) {
        if ($datos['activo'] === 0) {
            $erroresGenerales[] = 'No podés desactivar tu propio usuario.';
            $datos['activo'] = 1;   // lo dejamos activo igual
        }

        if ($datos['rol'] !== 'administrador') {
            $erroresGenerales[] = 'No podés sacarte a vos mismo el rol de administrador.';
            $datos['rol'] = 'administrador';
        }
    }
}

// --- Validación ------------------------------------------------

// Reglas del usuario. El nombre de usuario se valida aparte
// porque en edición el valor no cambia (ver más abajo).
$reglas = [
    'usuario' => [
        'requerido' => true,
        'usuario'   => true,
        'etiqueta'  => 'usuario',
    ],
    'nombre' => [
        'requerido' => true,
        'min'       => 2,
        'max'       => 100,
        'etiqueta'  => 'nombre',
    ],
    'apellido' => [
        'max'      => 100,
        'etiqueta' => 'apellido',
    ],
    'email' => [
        'email'    => true,
        'max'      => 150,
        'etiqueta' => 'email',
    ],
    'rol' => [
        'requerido' => true,
        'etiqueta'  => 'rol',
    ],
];

// El usuario solo se puede cambiar al crear. Si ya existe,
// se ignora lo que venga en el formulario (así nadie se queda
// fuera por error de tipeo).
if (!$esNuevo) {
    unset($reglas['usuario']);
    $datos['usuario'] = (string) $usuarioExistente['usuario'];
}

$errores = validar($datos, $reglas);

// --- Email: tiene que ser único --------------------------------
if ($datos['email'] !== '') {
    $emailTomado = DB::valor(
        'SELECT id FROM usuarios WHERE email = :email AND id <> :id',
        ['email' => $datos['email'], 'id' => $idUsuario]
    );

    if ($emailTomado) {
        $errores['email'] = 'Ese email ya lo está usando otro usuario.';
    }
}

// --- Usuario: tiene que ser único ------------------------------
if ($esNuevo) {
    $usuarioTomado = DB::valor(
        'SELECT id FROM usuarios WHERE usuario = :usuario',
        ['usuario' => $datos['usuario']]
    );

    if ($usuarioTomado) {
        $errores['usuario'] = 'Ese nombre de usuario ya existe. Elegí otro.';
    }
}

// --- Rol: solo dos valores válidos -----------------------------
if (!in_array($datos['rol'], ['administrador', 'vendedor'], true)) {
    $errores['rol'] = 'El rol tiene que ser administrador o vendedor.';
}

// --- Contraseña ------------------------------------------------
if ($esNuevo && $password === '') {
    $errores['password'] = 'Poné una contraseña para el usuario nuevo.';
}

if ($password !== '') {
    if (mb_strlen($password) < 8) {
        $errores['password'] = 'La contraseña tiene que tener al menos 8 caracteres.';
    }

    if ($password !== $passwordConfirm) {
        $errores['password_confirmacion'] = 'Las contraseñas no coinciden.';
    }
}

// --- Última comprobación: que quede al menos un admin activo ----
// Si el único administrador se degradara a vendedor o se desactivara,
// el sistema se quedaría sin administración posible.
if (!$esNuevo && $usuarioExistente !== null) {
    $degradado = (
        (string) $usuarioExistente['rol'] === 'administrador'
        && ($datos['rol'] !== 'administrador' || $datos['activo'] === 0)
    );

    if ($degradado) {
        $otrosAdmins = (int) DB::valor(
            "SELECT COUNT(*) FROM usuarios
             WHERE rol = 'administrador' AND activo = 1 AND id <> :id",
            ['id' => $idUsuario]
        );

        if ($otrosAdmins === 0) {
            $erroresGenerales[] = 'No podés dejar el sistema sin ningún administrador activo.';
            $datos['rol'] = 'administrador';
            $datos['activo'] = 1;
        }
    }
}

// --- Si hay errores, volver al formulario -----------------------
if (!empty($errores) || !empty($erroresGenerales)) {
    // Guardamos lo tipeado para repoblar el formulario.
    guardar_entradas([
        'usuario'   => $datos['usuario'],
        'nombre'    => $datos['nombre'],
        'apellido'  => $datos['apellido'],
        'email'     => $datos['email'],
        'rol'       => $datos['rol'],
        'activo'    => $datos['activo'],
    ]);

    // Los errores van aparte: uno es "qué escribió" (entradas) y el
    // otro "qué está mal" (errores). La redirección borra las
    // variables locales, así que Viajan por la sesión.
    guardar_errores($errores, $erroresGenerales);

    flash_error('Revisá los campos marcados en rojo.');

    $destino = $esNuevo
        ? url('usuarios/formulario')
        : url('usuarios/formulario') . '&id=' . $idUsuario;

    if (!headers_sent()) {
        header('Location: ' . $destino);
        exit;
    }

    exit;
}

// --- Guardar ---------------------------------------------------
// Se guarda usuario, rol, estado y (si corresponde) la contraseña.
// El hash lo genera password_hash: nunca se guarda la contraseña
// en texto plano.
$datosGuardar = [
    'usuario'        => $datos['usuario'],
    'nombre'         => $datos['nombre'],
    'apellido'       => $datos['apellido'],
    // La columna es NOT NULL DEFAULT '': si no hay email, va cadena
    // vacía, nunca null (MySQL rechazaría el INSERT).
    'email'          => $datos['email'],
    'rol'            => $datos['rol'],
    'activo'         => $datos['activo'],
    'actualizado_en' => ahora(),
];

if ($password !== '') {
    $datosGuardar['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
}

if ($esNuevo) {
    // Usuario nuevo: se le pide cambiar la clave al primer ingreso.
    $datosGuardar['debe_cambiar_password'] = 1;
    $datosGuardar['creado_en']             = ahora();
    $datosGuardar['intentos_fallidos']    = 0;
    $datosGuardar['bloqueado_hasta']       = null;

    $nuevoId = DB::insertar('usuarios', $datosGuardar);

    Auditoria::registrar(
        'crear_usuario',
        'usuarios',
        $nuevoId,
        null,
        [
            'usuario' => $datosGuardar['usuario'],
            'nombre'  => $datosGuardar['nombre'],
            'rol'     => $datosGuardar['rol'],
            'activo'  => $datosGuardar['activo'],
        ]
    );

    flash_exito(
        'Usuario "' . $datosGuardar['usuario'] . '" creado. '
        . 'Va a tener que cambiar la contraseña la primera vez que entre.'
    );
} else {
    // Estado anterior para la auditoría.
    $antes = DB::one(
        'SELECT usuario, nombre, apellido, email, rol, activo FROM usuarios WHERE id = :id',
        ['id' => $idUsuario]
    );

    DB::actualizar('usuarios', $idUsuario, $datosGuardar);

    Auditoria::registrar(
        'editar_usuario',
        'usuarios',
        $idUsuario,
        $antes,
        [
            'usuario' => $datosGuardar['usuario'],
            'nombre'  => $datosGuardar['nombre'],
            'email'   => $datosGuardar['email'],
            'rol'     => $datosGuardar['rol'],
            'activo'  => $datosGuardar['activo'],
            'cambio_password' => $password !== '',
        ]
    );

    $mensaje = 'Usuario "' . $datosGuardar['usuario'] . '" actualizado.';

    if ($password !== '') {
        $mensaje .= ' Le cambiamos la contraseña.';
    }

    flash_exito($mensaje);
}

// --- Volver al listado ----------------------------------------
redirigir('usuarios');