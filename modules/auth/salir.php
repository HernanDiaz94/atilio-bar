<?php
/**
 * modules/auth/salir.php
 * -----------------------------------------------------------------
 * Cierre de sesión.
 *
 * Solo acepta POST: si fuera un link (GET), cualquiera podría
 * desloguear a un usuario con una imagen suelta en un foro.
 * El token CSRF lo verifica el router antes de llegar acá.
 * -----------------------------------------------------------------
 */

// Si alguien entra directo por URL sin estar logueado,
// lo mandamos al login y listo.
if (!Auth::logueado()) {
    if (!headers_sent()) {
        header('Location: ' . url_archivo('login.php'));
        exit;
    }

    exit;
}

// Guardamos el nombre para el mensaje de despedida.
$nombre = Auth::nombre_completo();

// Destruye la sesión y registra el logout en la auditoría.
Auth::logout();

// logout() destruye la sesión por completo, así que abrimos una nueva
// (vacía) solo para poder dejar el mensaje de despedida.
session_start();

flash_info('Hasta luego, ' . $nombre . '. Sesión cerrada.');

// Volvemos al login. Ojo: no a la ruta 'salir' otra vez,
// porque ahí entramos en boucle. Va directo al archivo login.php.
if (!headers_sent()) {
    header('Location: ' . url_archivo('login.php'));
    exit;
}

exit;