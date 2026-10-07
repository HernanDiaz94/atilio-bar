<?php
/**
 * includes/instalador_esquema.php
 * -----------------------------------------------------------------
 * Esquema de la base de datos para install.php.
 *
 * Las tablas están escritas como sentencias PHP, no se leen de
 * sql/database.sql. ¿Por qué? Porque partir un archivo .SQL por
 * los punto y coma es frágil: un punto y coma dentro de un
 * comentario rompe todo. Acá cada sentencia está explícita y no
 * hay forma de que se corte mal.
 *
 * El contenido es EXACTAMENTE el mismo que sql/database.sql, así
 * que da igual cuál de los dos caminos uses: importar el SQL a
 * mano o correr el instalador.
 *
 * Este archivo se borra junto con install.php cuando terminás de
 * instalar (o queda inaccesible: /includes está bloqueado por
 * .htaccess y nadie lo incluye salvo install.php).
 * -----------------------------------------------------------------
 */

// Tablas que este instalador crea. Se usa solo para el resumen
// que muestra la pantalla.
$tablasEsquema = ['usuarios', 'usuarios_login_intentos', 'configuracion', 'auditoria'];

/**
 * Crea las tablas y carga la configuración inicial.
 *
 * @return array ['ok' => bool, 'tablas' => array, 'errores' => array]
 */
function instalar_esquema(): array
{
    $errores = [];
    $tablasCreadas = [];

    // Se desactiva el chequeo de claves foráneas durante la
    // creación: así el orden de las tablas no importa.
    DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        // --------------------------------------------------------
        // 1) USUARIOS
        // --------------------------------------------------------
        DB::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `usuarios` (
                `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `usuario`               VARCHAR(50)  NOT NULL COMMENT 'Nombre con el que se loguean',
                `nombre`                VARCHAR(100) NOT NULL,
                `apellido`              VARCHAR(100) NOT NULL DEFAULT '',
                `email`                 VARCHAR(150) NOT NULL DEFAULT '',
                `password_hash`         VARCHAR(255) NOT NULL COMMENT 'Hash de password_hash()',
                `rol`                   ENUM('administrador','vendedor') NOT NULL DEFAULT 'vendedor',
                `activo`                TINYINT(1)   NOT NULL DEFAULT 1,
                `debe_cambiar_password` TINYINT(1)   NOT NULL DEFAULT 0,
                `intentos_fallidos`     INT UNSIGNED NOT NULL DEFAULT 0,
                `bloqueado_hasta`       DATETIME     NULL DEFAULT NULL,
                `ultimo_acceso`         DATETIME     NULL DEFAULT NULL,
                `creado_en`             DATETIME     NOT NULL,
                `actualizado_en`        DATETIME     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
                KEY `ix_usuarios_rol_activo` (`rol`, `activo`),
                KEY `ix_usuarios_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Usuarios del sistema: administradores y vendedores'"
        );
        $tablasCreadas[] = 'usuarios';

        // --------------------------------------------------------
        // 2) USUARIOS_LOGIN_INTENTOS
        // --------------------------------------------------------
        DB::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `usuarios_login_intentos` (
                `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `usuario`    VARCHAR(50)  NOT NULL DEFAULT '',
                `ip`         VARCHAR(45)  NOT NULL,
                `exitoso`    TINYINT(1)   NOT NULL DEFAULT 0,
                `motivo`     VARCHAR(60)  NOT NULL DEFAULT '',
                `creado_en`  DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `ix_intentos_usuario_ip` (`usuario`, `ip`),
                KEY `ix_intentos_ip_fecha` (`ip`, `creado_en`),
                KEY `ix_intentos_fecha` (`creado_en`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Historial de intentos de inicio de sesión'"
        );
        $tablasCreadas[] = 'usuarios_login_intentos';

        // --------------------------------------------------------
        // 3) CONFIGURACION
        // --------------------------------------------------------
        DB::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `configuracion` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `clave`          VARCHAR(100) NOT NULL,
                `valor`          TEXT         NULL,
                `tipo`           ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
                `descripcion`    VARCHAR(255) NOT NULL DEFAULT '',
                `grupo`          VARCHAR(50)  NOT NULL DEFAULT 'general',
                `editable`       TINYINT(1)   NOT NULL DEFAULT 1,
                `creado_en`      DATETIME     NOT NULL,
                `actualizado_en` DATETIME     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_configuracion_clave` (`clave`),
                KEY `ix_configuracion_grupo` (`grupo`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Parámetros configurables del sistema'"
        );
        $tablasCreadas[] = 'configuracion';

        // --------------------------------------------------------
        // 4) AUDITORIA
        // --------------------------------------------------------
        DB::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `auditoria` (
                `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `usuario_id`     INT UNSIGNED  NULL DEFAULT NULL,
                `usuario_nombre` VARCHAR(100)  NOT NULL DEFAULT 'sistema',
                `accion`         VARCHAR(60)   NOT NULL,
                `entidad`        VARCHAR(60)   NOT NULL DEFAULT '',
                `entidad_id`     INT UNSIGNED  NULL DEFAULT NULL,
                `datos_antes`    TEXT          NULL,
                `datos_despues`  TEXT          NULL,
                `ip`             VARCHAR(45)   NOT NULL DEFAULT '',
                `user_agent`     VARCHAR(255)  NOT NULL DEFAULT '',
                `creado_en`      DATETIME      NOT NULL,
                PRIMARY KEY (`id`),
                KEY `ix_auditoria_entidad` (`entidad`, `entidad_id`),
                KEY `ix_auditoria_usuario_fecha` (`usuario_id`, `creado_en`),
                KEY `ix_auditoria_fecha` (`creado_en`),
                KEY `ix_auditoria_accion` (`accion`),
                CONSTRAINT `fk_auditoria_usuario`
                    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Registro de acciones sensibles del sistema'"
        );
        $tablasCreadas[] = 'auditoria';

        // --------------------------------------------------------
        // 5) Configuración inicial
        // --------------------------------------------------------
        // Se insertan solo las claves que no existan todavía, así
        // el instalador se puede volver a correr sin pisar
        // cambios hechos desde la interfaz.
        $valoresIniciales = [
            ['bar_nombre', 'Atilio', 'texto', 'Nombre del bar. Sale en la navbar, el login y el ticket.', 'bar', 1],
            ['bar_direccion', '', 'texto', 'Dirección del local, para los tickets.', 'bar', 1],
            ['bar_telefono', '', 'texto', 'Teléfono de contacto.', 'bar', 1],
            ['bar_horario', '', 'texto', 'Texto libre con los horarios de atención.', 'bar', 1],
            ['ticket_pie', '¡Gracias por su visita!', 'texto', 'Mensaje al pie del ticket.', 'bar', 1],
            ['ticket_mostrar_logo', '1', 'booleano', 'Si el ticket lleva el logo arriba.', 'bar', 1],
            ['seguridad_max_intentos', '5', 'numero', 'Intentos fallidos antes de bloquear una cuenta.', 'seguridad', 1],
            ['seguridad_minutos_bloqueo', '15', 'numero', 'Minutos de bloqueo al superar los intentos.', 'seguridad', 1],
            ['seguridad_minutos_sesion', '120', 'numero', 'Minutos de inactividad antes de cerrar la sesión.', 'seguridad', 1],
            ['stock_alerta_bajo', '1', 'booleano', 'Mostrar alertas de stock bajo en el dashboard.', 'general', 1],
            ['caja_apertura_inicial', '0', 'booleano', 'Pedir monto inicial al abrir la caja del día.', 'caja', 1],
            ['caja_permitir_diff', '1', 'booleano', 'Permitir cerrar la caja con diferencia.', 'caja', 1],
        ];

        foreach ($valoresIniciales as $valor) {
            [$clave, $contenido, $tipo, $descripcion, $grupo, $editable] = $valor;

            DB::query(
                'INSERT INTO configuracion
                    (clave, valor, tipo, descripcion, grupo, editable, creado_en)
                 VALUES (:clave, :valor, :tipo, :descripcion, :grupo, :editable, :creado_en)
                 ON DUPLICATE KEY UPDATE clave = clave',
                [
                    'clave'       => $clave,
                    'valor'       => $contenido,
                    'tipo'        => $tipo,
                    'descripcion' => $descripcion,
                    'grupo'       => $grupo,
                    'editable'    => $editable,
                    'creado_en'   => ahora(),
                ]
            );
        }

        // Se limpia la caché de "¿instalado?" para que el router
        // sepa que ya hay tablas.
        DB::resetearCacheInstalado();

        return [
            'ok'      => true,
            'tablas'  => $tablasCreadas,
            'errores' => [],
        ];
    } catch (PDOException $e) {
        // El detalle va al log; al usuario se le da un mensaje útil.
        error_log('install.php error al crear tablas: ' . $e->getMessage());

        $errores[] = 'No se pudieron crear las tablas: ' . $e->getMessage();

        return [
            'ok'      => false,
            'tablas'  => $tablasCreadas,
            'errores' => $errores,
        ];
    } finally {
        DB::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}