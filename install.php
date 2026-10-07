<?php
/**
 * install.php
 * -----------------------------------------------------------------
 * Instalador de "Atilio".
 *
 * Qué hace:
 *   1. Prueba la conexión a MySQL con los datos de config/config.php.
 *   2. Crea todas las tablas de la Etapa 1.
 *   3. Carga la configuración inicial.
 *   4. Crea los usuarios iniciales: 2 administradores y 2 vendedores.
 *
 * IMPORTANTE:
 *   - Después de instalarlo, BORRÁ este archivo del servidor.
 *   - Si ya hay usuarios cargados, no vuelve a hacer nada.
 *   - Escribe las contraseñas en texto plano SOLO dentro de este
 *     archivo; en la base de datos siempre queda el hash.
 *
 * Si preferís importar el SQL a mano desde phpMyAdmin, usá
 * sql/database.sql y después volvé a esta pantalla solo para crear
 * los usuarios (paso 4).
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/includes/bootstrap.php';

// ------------------------------------------------------------
// Bloqueo: mientras se está instalando, nadie más entra.
// El instalador funciona sin sesión de usuario, así que el
// bloqueo es propio: si ya corrió, avisa y listo.
// ------------------------------------------------------------
$instalado = false;

try {
    $instalado = DB::instalado();
} catch (Throwable $e) {
    $instalado = false;
}

$paso   = (int) (get('paso', '1'));
$errores = [];
$avisos = [];
$exito  = false;
$resumenUsuarios = [];

// Si ya está instalado y no piden reinstalar, mostrar el cartel.
if ($instalado && $paso < 4) {
    $paso = 0;
}

// ================================================================
// PASO 1: chequear la conexión
// ================================================================

$estadoConexion = [
    'conexion' => false,
    'servidor' => false,
    'base'     => false,
    'mensaje'  => '',
];

if ($paso >= 1) {
    try {
        // 1) ¿Llegamos al servidor MySQL?
        $estadoConexion['servidor'] = true;
        $estadoConexion['conexion'] = true;
        $estadoConexion['base']     = true;
        $estadoConexion['mensaje']  = 'Conexión correcta a la base "' . DB_NAME . '".';

        // 2) ¿La base acepta utf8mb4?
        $charset = DB::valor('SELECT @@character_set_database');
        $estadoConexion['charset'] = (string) $charset;

        if ($charset !== 'utf8mb4') {
            $avisos[] = 'La base está en ' . $charset . '. Funciona igual, pero lo ideal es utf8mb4.';
        }
    } catch (PDOException $e) {
        // NO mostramos el mensaje de MySQL: puede contener usuario,
        // host y detalles internos. Solo damos una pista útil.
        $estadoConexion['mensaje'] = error_consejo_conexion($e);
    }
}

/**
 * Traduce un error de MySQL a una pista útil para el usuario,
 * sin exponer credenciales ni estructura del servidor.
 */
function error_consejo_conexion(PDOException $e): string
{
    $codigo = (string) ($e->errorInfo[1] ?? $e->getCode());

    return match ($codigo) {
        '1045' => 'El usuario o la contraseña de MySQL están mal. Revisá DB_USER y DB_PASS en config/config.php.',
        '1049' => 'La base de datos "' . DB_NAME . '" no existe. Creala desde el panel de ByetHost.',
        '2002' => 'No se puede llegar al servidor de base de datos. Revisá DB_HOST (tiene que ser sqlXXX.byethost.com, no localhost).',
        '2005' => 'No se puede llegar al servidor de base de datos. Revisá DB_HOST (tiene que ser sqlXXX.byethost.com, no localhost).',
        '1044' => 'El usuario de MySQL no tiene permiso sobre esa base. Revisá DB_USER.',
        default => 'No se pudo conectar con la base de datos. Revisá los 4 datos de MySQL en config/config.php.',
    };
}

// ================================================================
// PASO 4: crear los usuarios iniciales
// ================================================================

/**
 * ¿Ya existen las tablas de la aplicación?
 *
 * Se usa para que el paso 4 funcione incluso si el instalador se
 * abrió directamente en ese paso (por ejemplo, después de
 * importar sql/database.sql a mano).
 */
function tablas_existentes(): bool
{
    try {
        DB::valor('SELECT 1 FROM usuarios LIMIT 1');
        DB::valor('SELECT 1 FROM configuracion LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// Se procesa aparte porque se puede correr incluso si las tablas
// las cargaste importando sql/database.sql desde phpMyAdmin.
if ($paso === 4 && es_post() && $estadoConexion['conexion']) {
    // Si alguien entra directo al paso 4 (por ejemplo, porque
    // importó sql/database.sql a mano y después recargó la
    // página), las tablas pueden no existir todavía. En ese caso
    // las creamos acá y seguimos.
    if (!tablas_existentes()) {
        require_once __DIR__ . '/includes/instalador_esquema.php';

        $resultadoEsquema = instalar_esquema();

        if (!$resultadoEsquema['ok']) {
            $errores = array_merge($errores, $resultadoEsquema['errores']);
            $avisos[] = 'Se crearon las tablas para poder cargar los usuarios.';
        }
    }

    $datosUsuarios = [
        [
            'usuario'   => post('usuario_admin_1', 'admin1'),
            'nombre'    => post('nombre_admin_1', 'Administrador Uno'),
            'apellido'  => post('apellido_admin_1', ''),
            'password'  => post_crudo('password_admin_1'),
            'rol'       => 'administrador',
        ],
        [
            'usuario'   => post('usuario_admin_2', 'admin2'),
            'nombre'    => post('nombre_admin_2', 'Administrador Dos'),
            'apellido'  => post('apellido_admin_2', ''),
            'password'  => post_crudo('password_admin_2'),
            'rol'       => 'administrador',
        ],
        [
            'usuario'   => post('usuario_vendedor_1', 'vendedor1'),
            'nombre'    => post('nombre_vendedor_1', 'Vendedor Uno'),
            'apellido'  => post('apellido_vendedor_1', ''),
            'password'  => post_crudo('password_vendedor_1'),
            'rol'       => 'vendedor',
        ],
        [
            'usuario'   => post('usuario_vendedor_2', 'vendedor2'),
            'nombre'    => post('nombre_vendedor_2', 'Vendedor Dos'),
            'apellido'  => post('apellido_vendedor_2', ''),
            'password'  => post_crudo('password_vendedor_2'),
            'rol'       => 'vendedor',
        ],
    ];

    foreach ($datosUsuarios as $indice => $datos) {
        $etiqueta = $datos['rol'] === 'administrador' ? 'administrador' : 'vendedor';
        $campoUsuario = 'usuario_' . $etiqueta . '_' . (($indice % 2) + 1);
        $campoPassword = 'password_' . $etiqueta . '_' . (($indice % 2) + 1);

        $usuario = $datos['usuario'];
        $password = $datos['password'];

        // Validación
        if ($usuario === '' || mb_strlen($usuario) < 3) {
            $errores[] = "Falta el nombre de usuario del {$etiqueta} (mínimo 3 caracteres).";

            continue;
        }

        if ($password === '' || mb_strlen($password) < 8) {
            $errores[] = "La contraseña de \"{$usuario}\" tiene que tener al menos 8 caracteres.";

            continue;
        }

        // ¿Ya existe?
        $existe = DB::valor('SELECT id FROM usuarios WHERE usuario = :usuario', ['usuario' => $usuario]);

        if ($existe) {
            $errores[] = "El usuario \"{$usuario}\" ya existe. No se creó de nuevo.";

            continue;
        }

        // Alta
        try {
            $nuevoId = DB::insertar('usuarios', [
                'usuario'        => $usuario,
                'nombre'         => $datos['nombre'],
                'apellido'       => $datos['apellido'],
                // Ojo: la columna email es NOT NULL DEFAULT ''.
                // No se puede mandar null (MySQL da error 1048).
                'email'          => '',
                'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
                'rol'            => $datos['rol'],
                'activo'         => 1,
                // Los usuarios iniciales también cambian su clave
                // la primera vez: si filtran este archivo del
                // servidor, la contraseña queda sin valer.
                'debe_cambiar_password' => 1,
                'intentos_fallidos'     => 0,
                'creado_en'             => ahora(),
            ]);

            $resumenUsuarios[] = [
                'id'       => $nuevoId,
                'usuario'  => $usuario,
                'rol'      => $datos['rol'],
                'password' => $password,
            ];
        } catch (PDOException $e) {
            $errores[] = "No se pudo crear el usuario \"{$usuario}\".";
        }
    }

    if (empty($errores) && !empty($resumenUsuarios)) {
        Auditoria::registrar('instalar_usuarios', 'usuarios', null, null, [
            'creados' => count($resumenUsuarios),
        ]);

        $exito = true;
    }
}

// ================================================================
// PASOS 2 y 3: crear tablas y configuración
// ================================================================

if ($paso === 2 && $estadoConexion['conexion'] && !$instalado) {
    require_once __DIR__ . '/includes/instalador_esquema.php';

    $resultadoEsquema = instalar_esquema();

    if ($resultadoEsquema['ok']) {
        $avisos[] = 'Se crearon las tablas: ' . implode(', ', $resultadoEsquema['tablas']) . '.';
        $avisos[] = 'Se cargó la configuración inicial.';
    } else {
        $errores = array_merge($errores, $resultadoEsquema['errores']);
    }
}

?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url_archivo('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url_archivo('assets/css/theme.css')) ?>" rel="stylesheet">
</head>
<body class="login-fondo">

<div class="login-caja" style="max-width: 720px;">

    <div class="text-center">
        <img src="<?= e(url_archivo('assets/img/logo.svg')) ?>"
             alt="Logo de <?= e(APP_NOMBRE) ?>"
             class="login-logo mx-auto">
        <h1 class="login-titulo">Instalar <?= e(APP_NOMBRE) ?></h1>
        <p class="login-subtitulo mb-0">Paso a paso, sin complicarse</p>
    </div>

    <!-- ================= YA INSTALADO ================= -->
    <?php if ($paso === 0): ?>
        <div class="alert alert-success mt-3" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <strong>La aplicación ya está instalada.</strong>
            Hay usuarios cargados en la base de datos.
        </div>

        <p>
            Borralo del servidor por seguridad y entrá con tu usuario:
        </p>

        <div class="d-grid gap-2">
            <a href="<?= e(url_archivo('login.php')) ?>" class="btn btn-atilio-oscuro">
                <i class="bi bi-box-arrow-in-right"></i> Ir al login
            </a>

            <?php if (!empty($_GET['crear_usuarios'])): ?>
                <a href="<?= e(url_archivo('install.php') . '?paso=4') ?>" class="btn btn-atilio-amarillo">
                    <i class="bi bi-person-plus-fill"></i> Igual quiero crear más usuarios
                </a>
            <?php endif; ?>
        </div>

        <div class="login-pie">
            Para volver a usar el instalador, borrá los usuarios de la
            tabla <span class="mono">usuarios</span> desde phpMyAdmin.
        </div>

    <?php else: ?>

        <!-- ================= INDICADOR DE PASOS ================= -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <?php
            $pasos = [
                1 => 'Conexión',
                2 => 'Tablas',
                4 => 'Usuarios',
            ];
            ?>
            <?php foreach ($pasos as $numero => $nombre): ?>
                <div class="text-center flex-fill">
                    <span class="badge rounded-circle"
                          style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
                                 background-color: <?= $paso >= $numero ? 'var(--verde-oscuro)' : 'var(--borde)' ?>;
                                 color: <?= $paso >= $numero ? '#FFFFFF' : 'var(--texto-suave)' ?>;">
                        <?= $paso > $numero ? '✓' : e((string) $numero) ?>
                    </span>
                    <div class="small mt-1 fw-bold" style="color: var(--texto-suave);">
                        <?= e($nombre) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ================= ERRORES Y AVISOS ================= -->
        <?php foreach ($errores as $error): ?>
            <div class="alert alert-danger mt-3" role="alert">
                <i class="bi bi-x-octagon-fill"></i> <?= e($error) ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($avisos as $avisoTexto): ?>
            <div class="alert alert-info mt-3" role="alert">
                <i class="bi bi-info-circle-fill"></i> <?= e($avisoTexto) ?>
            </div>
        <?php endforeach; ?>

        <!-- ============================================================
             PASO 1: conexión
             ============================================================ -->
        <?php if ($paso === 1): ?>
            <div class="tarjeta mt-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-plug-fill"></i> Paso 1: conexión con la base</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <p>
                        Vamos a probar los datos de MySQL que están en
                        <span class="mono">config/config.php</span>.
                    </p>

                    <ul class="lista-movimientos">
                        <li>
                            <i class="bi bi-server"></i>
                            <div>
                                Servidor
                                <span class="mono"><?= e(DB_HOST) ?>:<?= e((string) DB_PORT) ?></span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-database"></i>
                            <div>
                                Base de datos
                                <span class="mono"><?= e(DB_NAME) ?></span>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-person"></i>
                            <div>
                                Usuario
                                <span class="mono"><?= e(DB_USER) ?></span>
                            </div>
                        </li>
                    </ul>

                    <?php if ($estadoConexion['conexion']): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Todo bien.</strong> <?= e($estadoConexion['mensaje']) ?>
                            <?php if (!empty($estadoConexion['charset'])): ?>
                                <br>Juego de caracteres: <span class="mono"><?= e($estadoConexion['charset']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$instalado): ?>
                            <a href="<?= e(url_archivo('install.php') . '?paso=2') ?>"
                               class="btn btn-atilio-oscuro w-100 mt-3">
                                Continuar: crear tablas <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?= e(url_archivo('install.php') . '?paso=4') ?>"
                               class="btn btn-atilio-oscuro w-100 mt-3">
                                Continuar: crear usuarios <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>No pudimos conectar.</strong><br>
                            <?= e($estadoConexion['mensaje']) ?>
                        </div>

                        <div class="marco-amarillo p-3 mt-3">
                            <strong>En ByetHost tenés que revisar esto:</strong>
                            <ol class="mb-0 mt-2 ps-3">
                                <li>La base <strong>DB_HOST</strong> no es <span class="mono">localhost</span>: es tipo <span class="mono">sqlXXX.byethost.com</span>.</li>
                                <li>El nombre de la base (<span class="mono">DB_NAME</span>) lleva prefijo, ejemplo <span class="mono">b12_12345678_atilio</span>.</li>
                                <li>El usuario (<span class="mono">DB_USER</span>) también lleva prefijo.</li>
                                <li>Primero creá la base desde el panel de ByetHost.</li>
                            </ol>
                        </div>

                        <a href="<?= e(url_archivo('install.php') . '?paso=1') ?>"
                           class="btn btn-atilio w-100 mt-3">
                            <i class="bi bi-arrow-repeat"></i> Probar de nuevo
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

        <!-- ============================================================
             PASO 2: tablas creadas
             ============================================================ -->
        <?php if ($paso === 2): ?>
            <div class="tarjeta mt-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-table-fill"></i> Paso 2: tablas y configuración</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <p>
                        Se crearon las tablas y se cargaron los valores
                        de configuración del bar.
                    </p>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>Listo.</strong> Ahora creamos los usuarios.
                    </div>

                    <a href="<?= e(url_archivo('install.php') . '?paso=4') ?>"
                       class="btn btn-atilio-oscuro w-100">
                        Continuar: crear usuarios <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             PASO 4: usuarios
             ============================================================ -->
        <?php if ($paso === 4): ?>
            <div class="tarjeta mt-3">
                <div class="tarjeta-cabecera">
                    <span><i class="bi bi-people-fill"></i> Paso 3: usuarios iniciales</span>
                </div>
                <div class="tarjeta-cuerpo">
                    <p>
                        Creamos <strong>2 administradores</strong> (acceso total)
                        y <strong>2 vendedores</strong> (mesas y cobro).
                    </p>

                    <?php if ($exito): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Usuarios creados.</strong>
                            Anotá las claves: se muestran una sola vez.
                        </div>

                        <div class="tabla-envoltura">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Contraseña</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumenUsuarios as $creado): ?>
                                        <tr>
                                            <td><strong><?= e($creado['usuario']) ?></strong></td>
                                            <td>
                                                <span class="badge <?= e(clase_rol($creado['rol'])) ?>">
                                                    <?= e(texto_rol($creado['rol'])) ?>
                                                </span>
                                            </td>
                                            <td><span class="mono"><?= e($creado['password']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Dos cosas importantes:</strong>
                            <ol class="mb-0 mt-1 ps-3">
                                <li>Todos van a tener que cambiar su contraseña la primera vez que entren.</li>
                                <li>Borrá este archivo <span class="mono">install.php</span> del servidor ahora.</li>
                            </ol>
                        </div>

                        <a href="<?= e(url_archivo('login.php')) ?>"
                           class="btn btn-atilio-oscuro w-100 mt-2">
                            <i class="bi bi-box-arrow-in-right"></i> Ir al login
                        </a>

                    <?php else: ?>
                        <form method="post" action="<?= e(url_archivo('install.php') . '?paso=4') ?>">

                            <?= csrf_campo() ?>

                            <h6 class="fw-bold mt-3 mb-2">
                                <i class="bi bi-shield-fill-check"></i> Administradores
                            </h6>

                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" name="usuario_admin_1" class="form-control"
                                           value="admin1" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contraseña (mín. 8)</label>
                                    <input type="text" name="password_admin_1" class="form-control"
                                           value="Atilio2026" required minlength="8">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" name="usuario_admin_2" class="form-control"
                                           value="admin2" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contraseña (mín. 8)</label>
                                    <input type="text" name="password_admin_2" class="form-control"
                                           value="Atilio2026" required minlength="8">
                                </div>
                            </div>

                            <h6 class="fw-bold mt-4 mb-2">
                                <i class="bi bi-person-badge"></i> Vendedores
                            </h6>

                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" name="usuario_vendedor_1" class="form-control"
                                           value="vendedor1" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contraseña (mín. 8)</label>
                                    <input type="text" name="password_vendedor_1" class="form-control"
                                           value="Atilio2026" required minlength="8">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" name="usuario_vendedor_2" class="form-control"
                                           value="vendedor2" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Contraseña (mín. 8)</label>
                                    <input type="text" name="password_vendedor_2" class="form-control"
                                           value="Atilio2026" required minlength="8">
                                </div>
                            </div>

                            <div class="alert alert-info mt-3 mb-3">
                                <i class="bi bi-info-circle-fill"></i>
                                Cambiá estas contraseñas antes de instalar.
                                Después las cambian los usuarios en
                                &laquo;Mi cuenta&raquo;.
                            </div>

                            <button type="submit" class="btn btn-atilio-oscuro w-100">
                                <i class="bi bi-check-lg"></i> Crear usuarios e instalar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="login-pie">
        <?= e(APP_NOMBRE) ?> &middot; Instalador v1 (Etapa 1)
    </div>
</div>

</body>
</html>