<?php
/**
 * modules/usuarios/mi_cuenta_guardar.php
 * -----------------------------------------------------------------
 * Procesa el cambio de contraseña propia.
 *
 * El router ya verificó sesión, rol y token CSRF.
 * Acá se valida que sepa su contraseña actual, se controlan las
 * reglas mínimas de seguridad y se rotan los tokens.
 * -----------------------------------------------------------------
*/

$errores = [];

$actual       = post_crudo('password_actual');
$nueva        = post_crudo('password');
$confirmacion = post_crudo('password_confirmacion');
$miId         = Auth::id();

// --- Validación -------------------------------------------------

// 1) La contraseña actual tiene que ser la correcta.
//    Esto protege contra alguien que use la sesión abierta en un
//    celular prestado para cambiar la clave y quedarse con la cuenta.
$fila = DB::one('SELECT password_hash FROM usuarios WHERE id = :id', ['id' => $miId]);

if ($fila === null) {
    flash_error('No se encontró tu usuario.');
    redirigir('mi-cuenta');
}

if (!password_verify($actual, (string) $fila['password_hash'])) {
    $errores['password_actual'] = 'La contraseña actual no es correcta.';
}

// 2) La nueva tiene que ser suficientemente larga.
if (mb_strlen($nueva) < 8) {
    $errores['password'] = 'La contraseña nueva tiene que tener al menos 8 caracteres.';
}

// 3) Tiene que coincidir con la repetición.
if ($nueva !== $confirmacion) {
    $errores['password_confirmacion'] = 'Las contraseñas nuevas no coinciden.';
}

// 4) Tiene que ser distinta de la actual.
if ($nueva !== '' && password_verify($nueva, (string) $fila['password_hash'])) {
    $errores['password'] = 'La contraseña nueva tiene que ser distinta de la actual.';
}

// 5) Reglas de seguridad básicas contra contraseñas fáciles.
if ($nueva !== '') {
    $usuarioActual = mb_strtolower(Auth::nombre());
    $contrasenaBaja = mb_strtolower($nueva);

    // Contraseñas que se adivinan con diccionario.
    $prohibidas = [
        '12345678', '123456789', 'password', 'clave1234',
        'qwertyui', '11111111', 'admin123', 'usuario123',
    ];

    foreach ($prohibidas as $prohibida) {
        if ($contrasenaBaja === $prohibida) {
            $errores['password'] = 'Esa contraseña es demasiado fácil de adivinar. Elegí otra.';

            break;
        }
    }

    // No puede ser tu propio nombre de usuario ni el del bar.
    if ($usuarioActual !== '' && mb_strpos($contrasenaBaja, $usuarioActual) !== false) {
        $errores['password'] = 'La contraseña no puede contener tu nombre de usuario.';
    }

    if (mb_strpos($contrasenaBaja, mb_strtolower(APP_NOMBRE)) !== false) {
        $errores['password'] = 'La contraseña no puede contener el nombre del bar.';
    }

    // Ni el año actual.
    if (str_contains($contrasenaBaja, date('Y'))) {
        $errores['password'] = 'No uses el año en la contraseña: es de las primeras cosas que se prueban.';
    }
}

// --- Si hay errores, volver con los mensajes -------------------
if (!empty($errores)) {
    // Los errores van en la sesión porque redirigiendo se borran
    // las variables locales: mi_cuenta.php los recupera con
    // tomar_errores() y los muestra junto al campo que falla.
    guardar_errores($errores);

    flash_error('Revisá los campos marcados en rojo.');

    if (!headers_sent()) {
        header('Location: ' . url('mi-cuenta'));
        exit;
    }

    exit;
}

// --- Guardar ----------------------------------------------------
// En transacción: o se cambia la clave y se marca el flag, o no
// pasa nada.
DB::enTransaccion(function () use ($miId, $nueva): void {
    DB::actualizar('usuarios', $miId, [
        'password_hash'         => password_hash($nueva, PASSWORD_DEFAULT),
        'debe_cambiar_password' => 0,
        'actualizado_en'        => ahora(),
    ]);
});

// --- Después de cambiar la clave -------------------------------
// Se registra en auditoría (sin guardar la contraseña, obviamente).
Auditoria::registrar('cambio_password', 'usuarios', $miId, null, ['propia']);

// Por seguridad, después de cambiar la contraseña se regenera el
// ID de sesión. Si alguien hubiera copiado la cookie anterior,
// deja de servir.
session_regenerate_id(true);
csrf_rotar();

// Se reinician los intentos fallidos y cualquier bloqueo.
DB::actualizar('usuarios', $miId, [
    'intentos_fallidos' => 0,
    'bloqueado_hasta'   => null,
]);

flash_exito('¡Listo! Tu contraseña quedó actualizada.');

redirigir('dashboard');