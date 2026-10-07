<?php
/**
 * includes/csrf.php
 * -----------------------------------------------------------------
 * Protección CSRF (Cross-Site Request Forgery).
 *
 * Un token único por sesión que viaja en todos los formularios POST.
 * Si alguien carga la app desde una página externa preparada por un
 * atacante, el token no coincide y el POST se rechaza.
 *
 * El router verifica el token automáticamente antes de ejecutar
 * cualquier controlador, así que alcanza con poner csrf_campo()
 * dentro de cada <form method="post">.
 * -----------------------------------------------------------------
 */

/**
 * Devuelve (y crea si hace falta) el token CSRF de la sesión.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Campo oculto listo para meter dentro de un formulario.
 */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Verifica que el token recibido coincida con el de la sesión.
 *
 * Se usa comparación constante (hash_equals) para no filtrar
 * información por tiempo de respuesta.
 */
function csrf_valido(?string $tokenRecibido = null): bool
{
    if ($tokenRecibido === null) {
        $tokenRecibido = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (!is_string($tokenRecibido) || $tokenRecibido === '' || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $tokenRecibido);
}

/**
 * Si el token no es válido, corta el request con error 419.
 *
 * El 419 es un código no estándar que usamos para "oración rechazada".
 * No importa mucho: lo importante es que nunca se ejecute la acción.
 */
function csrf_verificar(): void
{
    if (csrf_valido()) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(419);
    }

    // Si vino de un AJAX, respondemos en JSON.
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => 'Sesión vencida. Volvé a cargar la página.']);
        exit;
    }

    flash_error('La sesión venció o el formulario no es válido. Volvé a intentar.');
    redirigir('dashboard');
}

/**
 * Rota el token CSRF. Se llama después de operaciones sensibles
 * (login, logout, cambio de contraseña).
 */
function csrf_rotar(): void
{
    unset($_SESSION['_csrf_token']);
}