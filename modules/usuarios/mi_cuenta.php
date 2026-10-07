<?php
/**
 * modules/usuarios/mi_cuenta.php
 * -----------------------------------------------------------------
 * Cambio de contraseña del usuario que está logueado.
 *
 * Disponible para los dos roles: el vendedor también puede y debe
 * cambiar su propia contraseña.
 * -----------------------------------------------------------------
*/

$usuario  = Auth::usuario();
$esAdmin  = Auth::es_admin();
$miId     = Auth::id();

// Errores de validación que dejó mi_cuenta_guardar.php cuando
// redirigió de vuelta por un POST inválido. Vienen vacíos
// normalmente: solo aparecen justo después de un rechazo.
$erroresGuardados = tomar_errores();
$errores         = $erroresGuardados['campos'];
$erroresGenerales = $erroresGuardados['generales'];

// Traemos los últimos accesos de esta cuenta, para que el usuario
// vea si alguien más entró con su nombre.
$misAccesos = DB::all(
    "SELECT ip, creado_en, motivo
     FROM usuarios_login_intentos
     WHERE usuario = :usuario
       AND creado_en >= :desde
     ORDER BY id DESC
     LIMIT 15",
    [
        'usuario' => Auth::nombre(),
        // Últimos 30 días.
        'desde'   => date('Y-m-d H:i:s', time() - (30 * 24 * 60 * 60)),
    ]
);

require APP_ROOT . '/modules/usuarios/vistas/mi_cuenta.php';