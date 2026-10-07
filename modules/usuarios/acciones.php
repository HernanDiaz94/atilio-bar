<?php
/**
 * modules/usuarios/acciones.php
 * -----------------------------------------------------------------
 * Acciones puntuales sobre un usuario (solo administradores):
 *
 *   activar        Lo deja entrar al sistema otra vez.
 *   desactivar     Lo bloquea sin borrar su historial.
 *   resetear_password  Genera una contraseña temporal.
 *   eliminar       Borra el usuario SOLO si no tiene ventas asociadas.
 *
 * Todas llegan por POST (el router ya verificó el token CSRF) y
 * todas dejan rastro en la tabla auditoria.
 * -----------------------------------------------------------------
*/

$accion    = post('accion');
$idUsuario = (int) post('id', '0');

// --- Usuario objetivo -------------------------------------------
if ($idUsuario <= 0) {
    flash_error('No se indicó qué usuario modificar.');
    redirigir('usuarios');
}

$objetivo = DB::one(
    'SELECT id, usuario, nombre, rol, activo FROM usuarios WHERE id = :id',
    ['id' => $idUsuario]
);

if ($objetivo === null) {
    flash_error('Ese usuario no existe.');
    redirigir('usuarios');
}

$nombreUsuario = (string) $objetivo['usuario'];

/**
 * Cuenta cuántas ventas tiene asociadas el usuario.
 * En la Etapa 1 la tabla ventas todavía no existe, así que el
 * try/catch deja el valor en 0. Cuando llegue la Etapa 4, este
 * mismo chequeo va a impedir borrar a quien tenga historial.
 */
function usuario_tiene_ventas(int $usuarioId): bool
{
    try {
        return (int) DB::valor(
            'SELECT COUNT(*) FROM ventas WHERE usuario_id = :id',
            ['id' => $usuarioId]
        ) > 0;
    } catch (Throwable $e) {
        // La tabla ventas todavía no existe.
        return false;
    }
}

// --- Realizar la acción -----------------------------------------
switch ($accion) {

    // ------------------------------------------------------------
    case 'activar':
        DB::actualizar('usuarios', $idUsuario, [
            'activo'           => 1,
            'intentos_fallidos' => 0,
            'bloqueado_hasta'  => null,
            'actualizado_en'   => ahora(),
        ]);

        Auditoria::registrar(
            'activar_usuario',
            'usuarios',
            $idUsuario,
            ['activo' => (int) $objetivo['activo']],
            ['activo' => 1]
        );

        flash_exito('Usuario "' . $nombreUsuario . '" activado. Ya puede entrar.');
        break;

    // ------------------------------------------------------------
    case 'desactivar':
        // No te podés desactivar a vos mismo: si te equivocás,
        // perdés el acceso al sistema.
        if ($idUsuario === Auth::id()) {
            flash_error('No podés desactivar tu propio usuario.');
            redirigir('usuarios');
        }

        // No dejar el sistema sin ningún administrador activo.
        if ((string) $objetivo['rol'] === 'administrador' && (int) $objetivo['activo'] === 1) {
            $otrosAdmins = (int) DB::valor(
                "SELECT COUNT(*) FROM usuarios
                 WHERE rol = 'administrador' AND activo = 1 AND id <> :id",
                ['id' => $idUsuario]
            );

            if ($otrosAdmins === 0) {
                flash_error('No podés desactivar al único administrador activo. Creá otro administrador primero.');
                redirigir('usuarios');
            }
        }

        DB::actualizar('usuarios', $idUsuario, [
            'activo'         => 0,
            'bloqueado_hasta' => null,
            'actualizado_en' => ahora(),
        ]);

        Auditoria::registrar(
            'desactivar_usuario',
            'usuarios',
            $idUsuario,
            ['activo' => 1],
            ['activo' => 0]
        );

        flash_exito(
            'Usuario "' . $nombreUsuario . '" desactivado. '
            . 'No puede entrar, pero su historial queda intacto.'
        );
        break;

    // ------------------------------------------------------------
    case 'resetear_password':
        $claveTemporal = Auth::password_temporal();

        DB::actualizar('usuarios', $idUsuario, [
            'password_hash'         => password_hash($claveTemporal, PASSWORD_DEFAULT),
            // El usuario va a tener que cambiarla al entrar.
            'debe_cambiar_password' => 1,
            'intentos_fallidos'     => 0,
            'bloqueado_hasta'       => null,
            'actualizado_en'        => ahora(),
        ]);

        Auditoria::registrar(
            'resetear_password',
            'usuarios',
            $idUsuario,
            null,
            ['usuario' => $nombreUsuario]
        );

        // La clave se muestra una sola vez, acá. No se guarda en
        // ningún lado en texto plano, así que si se pierde hay que
        // generar otra.
        flash_exito(
            'Contraseña temporal de "' . $nombreUsuario . '": ' . $claveTemporal
            . '. Anotála y pasásela: va a tener que cambiarla al entrar.'
        );
        break;

    // ------------------------------------------------------------
    case 'eliminar':
        // No te podés borrar a vos mismo.
        if ($idUsuario === Auth::id()) {
            flash_error('No podés eliminar tu propio usuario.');
            redirigir('usuarios');
        }

        // Con historial de ventas, nunca se borra: se desactiva.
        if (usuario_tiene_ventas($idUsuario)) {
            flash_error(
                'No se puede eliminar a "' . $nombreUsuario . '" porque tiene ventas registradas. '
                . 'Desactivalo en su lugar para que conserve el historial.'
            );
            redirigir('usuarios');
        }

        // Última protección del sistema: nunca eliminar al único admin.
        if ((string) $objetivo['rol'] === 'administrador' && (int) $objetivo['activo'] === 1) {
            $otrosAdmins = (int) DB::valor(
                "SELECT COUNT(*) FROM usuarios
                 WHERE rol = 'administrador' AND activo = 1 AND id <> :id",
                ['id' => $idUsuario]
            );

            if ($otrosAdmins === 0) {
                flash_error('No podés eliminar al único administrador. Creá otro administrador primero.');
                redirigir('usuarios');
            }
        }

        Auditoria::registrar(
            'eliminar_usuario',
            'usuarios',
            $idUsuario,
            $objetivo,
            null
        );

        DB::eliminar('usuarios', $idUsuario);

        flash_exito('Usuario "' . $nombreUsuario . '" eliminado.');
        break;

    // ------------------------------------------------------------
    default:
        flash_error('La acción solicitada no existe.');
        break;
}

redirigir('usuarios');