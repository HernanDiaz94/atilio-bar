<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------
 * Autenticación, autorización por rol y control de intentos fallidos.
 *
 * Dos roles solamente:
 *   - administrador: acceso total
 *   - vendedor:      mesas, pedidos, ventas, cobro y stock en lectura
 *
 * El control de rol vive acá y en el mapa de rutas, nunca solamente
 * en el botón oculto de la interfaz.
 * -----------------------------------------------------------------
 */

/**
 * Clase Auth: estado de sesión y operaciones de login/logout.
 */
final class Auth
{
    /**
     * El "rol" de las rutas que no piden sesión.
     *
     * No es un rol de verdad: es una marca para el router. Sirve para
     * que una ruta pueda declararse pública sin que haya que agregar
     * una excepción suelta en el router, que es donde las excepciones
     * se olvidan.
     */
    public const ROL_PUBLICO = 'publico';

    /** Datos del usuario logueado, cargados una vez por request. */
    private static ?array $usuario = null;
    private static bool $cargado = false;

    // ------------------------------------------------------------
    // Estado de la sesión
    // ------------------------------------------------------------

    /** ¿Hay una sesión activa? */
    public static function logueado(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    /**
     * Devuelve los datos del usuario logueado (o null).
     */
    public static function usuario(): ?array
    {
        if (!self::$cargado) {
            self::$cargado = true;

            if (self::logueado()) {
                // Se relee de la base para que un cambio de rol o un
                // desactivación se apliquen de inmediato, sin esperar
                // a que el usuario vuelva a loguearse.
                self::$usuario = DB::one(
                    'SELECT id, usuario, nombre, apellido, email, rol, activo,
                            debe_cambiar_password, ultimo_acceso, creado_en
                     FROM usuarios
                     WHERE id = :id',
                    ['id' => (int) $_SESSION['usuario_id']]
                );

                // El usuario fue borrado o desactivado mientras tenía sesión abierta.
                if (self::$usuario === null || (int) self::$usuario['activo'] !== 1) {
                    self::logout();
                    self::$usuario = null;

                    return null;
                }
            }
        }

        return self::$usuario;
    }

    /** Id del usuario logueado. */
    public static function id(): int
    {
        $usuario = self::usuario();

        return $usuario ? (int) $usuario['id'] : 0;
    }

    /** Nombre de usuario (login). */
    public static function nombre(): string
    {
        $usuario = self::usuario();

        return $usuario ? (string) $usuario['usuario'] : '';
    }

    /** Nombre y apellido para mostrar en la navbar. */
    public static function nombre_completo(): string
    {
        $usuario = self::usuario();

        if ($usuario === null) {
            return '';
        }

        $nombre = trim((string) $usuario['nombre'] . ' ' . (string) ($usuario['apellido'] ?? ''));

        return $nombre !== '' ? $nombre : (string) $usuario['usuario'];
    }

    /** Rol del usuario logueado. */
    public static function rol(): string
    {
        $usuario = self::usuario();

        return $usuario ? (string) $usuario['rol'] : '';
    }

    /** ¿Es administrador? */
    public static function es_admin(): bool
    {
        return self::rol() === 'administrador';
    }

    /**
     * Control de rol centralizado.
     *
     * @param string $rolRequerido 'administrador' o 'vendedor'
     */
    public static function es_rol(string $rolRequerido): bool
    {
        $rol = self::rol();

        // Un administrador siempre puede hacer lo que hace un vendedor.
        if ($rolRequerido === 'vendedor') {
            return in_array($rol, ['administrador', 'vendedor'], true);
        }

        return $rol === $rolRequerido;
    }

    /**
     * Exige sesión activa. Si no hay, manda al login.
     */
    public static function requiere_login(): void
{
        if (!self::logueado()) {
            flash_info('Tenés que iniciar sesión para continuar.');
            self::ir_al_login();
        }

        // Verificación de inactividad: cierra la sesión si estuvo mucho tiempo quieta.
        self::verificar_inactividad();
    }

    /**
     * Mandan al login y cortan el request.
     * Se usa en vez de redirigir('salir') para no pasar por el logout:
     * si no hay sesión abierta, no hay nada que cerrar.
     */
    public static function ir_al_login(): never
    {
        if (!headers_sent()) {
            header('Location: ' . url_archivo('login.php'));
            exit;
        }

        exit;
    }

    /**
     * Exige un rol concreto. Si no lo tiene, error 403.
     */
    public static function requiere_rol(string $rolRequerido): void
    {
        // Una ruta marcada como pública no pide sesión. Va dentro de
        // este método y no como una comprobación suelta en el router
        // a propósito: el router llama SIEMPRE a requiere_rol() antes
        // de cargar un controlador, así que la excepción no se puede
        // saltar por accidente.
        if ($rolRequerido === self::ROL_PUBLICO) {
            return;
        }

        self::requiere_login();

        if (!self::es_rol($rolRequerido)) {
            self::denegar();
        }
    }

    /** Corta el request mostrando la pantalla de acceso denegado. */
    public static function denegar(): never
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        $titulo = 'Acceso denegado';
        require APP_ROOT . '/includes/vistas/403.php';
        exit;
    }

    // ------------------------------------------------------------
    // Inicio y cierre de sesión
    // ------------------------------------------------------------

    /**
     * Intenta iniciar sesión con usuario y contraseña.
     *
     * @return array ['ok' => bool, 'mensaje' => string]
     */
    public static function login(string $usuario, string $password): array
    {
        $ip      = ip_cliente();
        $usuario = trim($usuario);

        // --------------------------------------------------------
        // 1) Límite por IP: frena ataques que prueban muchos usuarios
        //    distintos desde la misma conexión.
        // --------------------------------------------------------
        if (self::ip_bloqueada($ip)) {
            self::registrar_intento($usuario, $ip, false, 'ip_bloqueada');

            return [
                'ok'       => false,
                'mensaje'  => 'Demasiados intentos desde esta conexión. Probá de nuevo en ' . LOGIN_MINUTOS_BLOQUEO . ' minutos.',
            ];
        }

        // --------------------------------------------------------
        // 2) Buscar el usuario
        // --------------------------------------------------------
        $fila = DB::one(
            'SELECT id, usuario, nombre, apellido, password_hash, rol, activo,
                    intentos_fallidos, bloqueado_hasta, debe_cambiar_password
             FROM usuarios
             WHERE usuario = :usuario
             LIMIT 1',
            ['usuario' => $usuario]
        );

        // Usuario inexistente: mismo mensaje que contraseña incorrecta,
        // para no revelar qué nombres de usuario existen.
        if ($fila === null) {
            self::registrar_intento($usuario, $ip, false, 'usuario_inexistente');

            // Máscara de tiempo: costamos lo mismo que un hash real.
            password_verify($password, '$2y$12$usuariosinexistenteusuariosinexistente000000000000000000');

            return [
                'ok'      => false,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ];
        }

        // --------------------------------------------------------
        // 3) Usuario bloqueado por intentos fallidos
        // --------------------------------------------------------
        if (!empty($fila['bloqueado_hasta']) && strtotime((string) $fila['bloqueado_hasta']) > time()) {
            self::registrar_intento($usuario, $ip, false, 'usuario_bloqueado');

            $restante = max(1, (int) ceil((strtotime((string) $fila['bloqueado_hasta']) - time()) / 60));

            return [
                'ok'      => false,
                'mensaje' => "La cuenta está bloqueada por seguridad. Probá de nuevo en {$restante} minuto(s).",
            ];
        }

        // --------------------------------------------------------
        // 4) Usuario desactivado
        // --------------------------------------------------------
        if ((int) $fila['activo'] !== 1) {
            self::registrar_intento($usuario, $ip, false, 'usuario_inactivo');

            return [
                'ok'      => false,
                'mensaje' => 'El usuario está desactivado. Pedile a un administrador que lo active.',
            ];
        }

        // --------------------------------------------------------
        // 5) Verificar la contraseña
        // --------------------------------------------------------
        if (!password_verify($password, (string) $fila['password_hash'])) {
            self::sumar_intento_fallido($fila);
            self::registrar_intento($usuario, $ip, false, 'password_incorrecta');

            $restantes = LOGIN_MAX_INTENTOS - ((int) $fila['intentos_fallidos'] + 1);
            $mensaje   = 'Usuario o contraseña incorrectos.';

            if ($restantes > 0 && $restantes <= 3) {
                $mensaje .= " Te {$restantes} intento(s) antes de que se bloquee la cuenta.";
            }

            return ['ok' => false, 'mensaje' => $mensaje];
        }

        // --------------------------------------------------------
        // 6) Todo OK: abrir sesión
        // --------------------------------------------------------
        self::iniciar_sesion((int) $fila['id']);

        // Si el hash estaba desactualizado (cambió el costo por defecto),
        // lo refrescamos en el momento.
        if (password_needs_rehash((string) $fila['password_hash'], PASSWORD_DEFAULT)) {
            DB::actualizar('usuarios', (int) $fila['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        DB::actualizar('usuarios', (int) $fila['id'], [
            'intentos_fallidos'  => 0,
            'bloqueado_hasta'    => null,
            'ultimo_acceso'      => ahora(),
        ]);

        self::registrar_intento($usuario, $ip, true, 'login_ok');

        Auditoria::registrar(
            'login',
            'usuarios',
            (int) $fila['id'],
            null,
            ['usuario' => $fila['usuario'], 'rol' => $fila['rol']]
        );

        return [
            'ok'       => true,
            'mensaje'  => '',
            'debe_cambiar_password' => (int) $fila['debe_cambiar_password'] === 1,
        ];
    }

    /**
     * Genera el ID de sesión nuevo y guarda los datos del usuario.
     */
    private static function iniciar_sesion(int $usuarioId): void
    {
        // Regenerar el ID evita el session fixation: si alguien copió
        // el ID de sesión anterior, no le sirve para nada.
        session_regenerate_id(true);

        $_SESSION['usuario_id']    = $usuarioId;
        $_SESSION['inicio_sesion'] = time();
        $_SESSION['ultima_actividad'] = time();

        // Token nuevo para esta sesión.
        csrf_rotar();

        self::$usuario = null;
        self::$cargado = false;
    }

    /**
     * Cierra la sesión y borra todos los datos.
     */
    public static function logout(): void
    {
        $usuarioId = $_SESSION['usuario_id'] ?? null;

        // Vaciar el array de sesión
        $_SESSION = [];

        // Destruir la cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        if ($usuarioId) {
            Auditoria::registrar('logout', 'usuarios', (int) $usuarioId, null, null);
        }

        self::$usuario = null;
        self::$cargado = true;
    }

    // ------------------------------------------------------------
    // Control de intentos fallidos
    // ------------------------------------------------------------

    /**
     * Registra un intento de login (exitoso o no) en la tabla de control.
     */
    private static function registrar_intento(string $usuario, string $ip, bool $exitoso, string $motivo): void
    {
        DB::insertar('usuarios_login_intentos', [
            'usuario'   => mb_substr($usuario, 0, 50),
            'ip'        => $ip,
            'exitoso'   => $exitoso ? 1 : 0,
            'motivo'    => $motivo,
            'creado_en' => ahora(),
        ]);
    }

    /**
     * Suma un intento fallido al usuario y lo bloquea si llegó al límite.
     */
    private static function sumar_intento_fallido(array $fila): void
    {
        $intentos = (int) $fila['intentos_fallidos'] + 1;

        if ($intentos >= LOGIN_MAX_INTENTOS) {
            DB::actualizar('usuarios', (int) $fila['id'], [
                'intentos_fallidos' => 0,
                'bloqueado_hasta'   => date('Y-m-d H:i:s', time() + (LOGIN_MINUTOS_BLOQUEO * 60)),
            ]);

            return;
        }

        DB::actualizar('usuarios', (int) $fila['id'], ['intentos_fallidos' => $intentos]);
    }

    /**
     * ¿La IP excedió los intentos permitidos dentro de la ventana de bloqueo?
     *
     * No hace falta cron: se cuenta sobre los registros de la tabla,
     * y los viejos se pueden limpiar desde la propia pantalla.
     */
    public static function ip_bloqueada(string $ip): bool
    {
        $desde = date('Y-m-d H:i:s', time() - (LOGIN_MINUTOS_BLOQUEO * 60));

        $conteo = DB::valor(
            'SELECT COUNT(*) FROM usuarios_login_intentos
             WHERE ip = :ip AND exitoso = 0 AND creado_en >= :desde',
            ['ip' => $ip, 'desde' => $desde]
        );

        return ((int) $conteo) >= LOGIN_MAX_INTENTOS_IP;
    }

    /**
     * Cierra la sesión si pasó mucho tiempo sin actividad.
     */
    public static function verificar_inactividad(): void
    {
        $ultima = (int) ($_SESSION['ultima_actividad'] ?? time());

        if ((time() - $ultima) > (SESION_MINUTOS_INACTIVIDAD * 60)) {
            self::logout();

            // logout() destruye la sesión, así que abrimos una nueva
            // (vacía) para poder dejar el aviso de "tu sesión se cerró".
            session_start();

            flash_info('Tu sesión se cerró por inactividad. Volvé a iniciar sesión.');
            self::ir_al_login();
        }

        $_SESSION['ultima_actividad'] = time();
    }

    // ------------------------------------------------------------
    // Cambio de contraseña
    // ------------------------------------------------------------

    /**
     * Cambia la contraseña del usuario indicado.
     * Si se pasa $passwordActual, exige conocer la actual.
     */
    public static function cambiar_password(int $usuarioId, string $nueva, string $actual = ''): array
    {
        $errores = [];

        if (mb_strlen($nueva) < 8) {
            $errores[] = 'La nueva contraseña tiene que tener al menos 8 caracteres.';
        }

        if ($nueva !== ($_POST['password_confirmacion'] ?? $nueva)) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        }

        // Si no es el propio usuario, tiene que saber la actual.
        if ($actual !== '') {
            $fila = DB::one('SELECT password_hash FROM usuarios WHERE id = :id', ['id' => $usuarioId]);

            if ($fila === null || !password_verify($actual, (string) $fila['password_hash'])) {
                $errores[] = 'La contraseña actual no es correcta.';
            }
        }

        if (!empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        DB::actualizar('usuarios', $usuarioId, [
            'password_hash'         => password_hash($nueva, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 0,
            'actualizado_en'        => ahora(),
        ]);

        Auditoria::registrar('cambio_password', 'usuarios', $usuarioId, null, null);

        return ['ok' => true, 'errores' => []];
    }

    /**
     * Genera una contraseña temporal legible para el reseteo del admin.
     */
    public static function password_temporal(): string
    {
        // Sin caracteres que se confunden: ni 0/O ni 1/l/I.
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

        $clave = '';
        for ($i = 0; $i < 8; $i++) {
            $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $clave;
    }
}