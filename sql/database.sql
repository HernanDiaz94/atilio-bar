-- ==================================================================
--  database.sql  ·  Bar "Atilio"  ·  ETAPA 1
-- ------------------------------------------------------------------
--  Archivo de esquema para importar desde phpMyAdmin.
--
--  CÓMO USARLO (ByetHost):
--    1. Entrá a phpMyAdmin desde el panel de hosting.
--    2. Seleccioná la base de datos (lleva prefijo, ej.
--       b12_12345678_atilio).
--    3. Pestaña "Importar" -> Elegir archivo -> este database.sql
--       -> Ejecutar.
--
--  ALTERNATIVA: en vez de importar esto, se sube la app por FTP y
--  se abre install.php, que hace exactamente lo mismo.
--
--  Este archivo SOLO contiene lo de la Etapa 1. Las etapas
--  siguientes van en archivos separados (sql/etapa2.sql,
--  sql/etapa3.sql, etc.) para poder aplicarlos sobre una base
--  que ya tiene datos.
-- ==================================================================

-- ------------------------------------------------------------------
--  Nota sobre el juego de caracteres
-- ------------------------------------------------------------------
--  utf8mb4 con collation utf8mb4_unicode_ci. Importante: si la base
--  quedó en latin1, los acentos y la ñ de los productos se van a
--  guardar mal. Para arreglarlo (si ya tenés datos, hacelo con
--  cuidado):
--
--    ALTER DATABASE `NOMBRE` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ==================================================================
--  1) USUARIOS
--     Dos niveles de acceso: administrador y vendedor.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario`               VARCHAR(50)  NOT NULL                COMMENT 'Nombre con el que se loguean',
    `nombre`                VARCHAR(100) NOT NULL,
    `apellido`              VARCHAR(100) NOT NULL DEFAULT '',
    `email`                 VARCHAR(150) NOT NULL DEFAULT ''    COMMENT 'Opcional, se usa para identificarlo',
    `password_hash`         VARCHAR(255) NOT NULL                COMMENT 'Hash de password_hash(), nunca la contraseña',
    `rol`                   ENUM('administrador','vendedor') NOT NULL DEFAULT 'vendedor',
    `activo`                TINYINT(1)   NOT NULL DEFAULT 1      COMMENT '0 = desactivado (puede tener ventas)',
    `debe_cambiar_password` TINYINT(1)   NOT NULL DEFAULT 0      COMMENT '1 = tiene que cambiar la clave al entrar',
    `intentos_fallidos`     INT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta`       DATETIME     NULL DEFAULT NULL        COMMENT 'NULL = no está bloqueado',
    `ultimo_acceso`         DATETIME     NULL DEFAULT NULL,
    `creado_en`             DATETIME     NOT NULL,
    `actualizado_en`        DATETIME     NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
    KEY `ix_usuarios_rol_activo` (`rol`, `activo`),
    KEY `ix_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema: administradores y vendedores';


-- ==================================================================
--  2) USUARIOS_LOGIN_INTENTOS
--     Control de fuerza bruta. Cada intento de login queda
--     registrado, sea exitoso o no. Sin cron: el corte se calcula
--     comparando la fecha en el momento de cada intento.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `usuarios_login_intentos` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario`    VARCHAR(50)  NOT NULL DEFAULT ''   COMMENT 'Lo que escribió, aunque no exista',
    `ip`         VARCHAR(45)  NOT NULL              COMMENT 'Soporta IPv4 e IPv6',
    `exitoso`    TINYINT(1)   NOT NULL DEFAULT 0,
    `motivo`     VARCHAR(60)  NOT NULL DEFAULT ''
                 COMMENT 'login_ok, password_incorrecta, usuario_bloqueado, usuario_inactivo, usuario_inexistente, ip_bloqueada',
    `creado_en`  DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_intentos_usuario_ip` (`usuario`, `ip`),
    KEY `ix_intentos_ip_fecha` (`ip`, `creado_en`),
    KEY `ix_intentos_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de intentos de inicio de sesión';


-- ==================================================================
--  3) CONFIGURACION
--     Parámetros del bar editables desde la interfaz.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `configuracion` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave`       VARCHAR(100) NOT NULL,
    `valor`       TEXT         NULL,
    `tipo`        ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
    `descripcion` VARCHAR(255) NOT NULL DEFAULT '',
    `grupo`       VARCHAR(50)  NOT NULL DEFAULT 'general'
                  COMMENT 'general, bar, seguridad, caja, publico',
    `editable`    TINYINT(1)   NOT NULL DEFAULT 1
                  COMMENT '0 = valor técnico, no se toca desde la interfaz',
    `creado_en`   DATETIME     NOT NULL,
    `actualizado_en` DATETIME  NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_configuracion_clave` (`clave`),
    KEY `ix_configuracion_grupo` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parámetros configurables del sistema';


-- ==================================================================
--  4) AUDITORIA
--     Quién hizo qué, cuándo y desde dónde. Nunca se borra.
--     Los nombres de usuario se guardan como texto para que el
--     historial siga siendo legible aunque el usuario se elimine.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `auditoria` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`     INT UNSIGNED  NULL DEFAULT NULL
                     COMMENT 'NULL si el usuario fue borrado',
    `usuario_nombre` VARCHAR(100)  NOT NULL DEFAULT 'sistema' COMMENT 'Snapshot al momento del hecho',
    `accion`         VARCHAR(60)   NOT NULL
                     COMMENT 'login, logout, crear_usuario, anular_venta, etc.',
    `entidad`        VARCHAR(60)   NOT NULL DEFAULT '' COMMENT 'Tabla o módulo afectado',
    `entidad_id`     INT UNSIGNED  NULL DEFAULT NULL,
    `datos_antes`    TEXT          NULL COMMENT 'JSON con el estado previo',
    `datos_despues`  TEXT          NULL COMMENT 'JSON con el estado nuevo',
    `ip`             VARCHAR(45)   NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255)  NOT NULL DEFAULT '',
    `creado_en`      DATETIME      NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_auditoria_entidad` (`entidad`, `entidad_id`),
    KEY `ix_auditoria_usuario_fecha` (`usuario_id`, `creado_en`),
    KEY `ix_auditoria_fecha` (`creado_en`),
    KEY `ix_auditoria_accion` (`accion`),

    -- La auditoría nunca se borra en cascada junto con el usuario:
    -- para eso usuario_id va con ON DELETE SET NULL.
    CONSTRAINT `fk_auditoria_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de acciones sensibles del sistema';


-- ==================================================================
--  5) CONFIGURACIÓN INICIAL
--     Los usuarios NO se crean acá a propósito: sus contraseñas
--     se generan en install.php, que además las muestra una sola
--     vez. Importar este archivo desde phpMyAdmin y después correr
--     install.php (paso 4) deja el sistema listo.
-- ==================================================================
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('bar_nombre', 'Atilio', 'texto', 'Nombre del bar. Sale en la navbar, el login y el ticket.', 'bar', 1, NOW()),

    ('bar_direccion', '', 'texto', 'Dirección del local, para los tickets.', 'bar', 1, NOW()),
    ('bar_telefono', '', 'texto', 'Teléfono de contacto.', 'bar', 1, NOW()),
    ('bar_horario', '', 'texto', 'Texto libre con los horarios de atención.', 'bar', 1, NOW()),

    ('ticket_pie', '¡Gracias por su visita!', 'texto', 'Mensaje al pie del ticket.', 'bar', 1, NOW()),
    ('ticket_mostrar_logo', '1', 'booleano', 'Si el ticket lleva el logo arriba.', 'bar', 1, NOW()),

    ('seguridad_max_intentos', '5', 'numero', 'Intentos fallidos antes de bloquear una cuenta.', 'seguridad', 1, NOW()),
    ('seguridad_minutos_bloqueo', '15', 'numero', 'Minutos de bloqueo al superar los intentos.', 'seguridad', 1, NOW()),
    ('seguridad_minutos_sesion', '120', 'numero', 'Minutos de inactividad antes de cerrar la sesión.', 'seguridad', 1, NOW()),

    ('stock_alerta_bajo', '1', 'booleano', 'Mostrar alertas de stock bajo en el dashboard.', 'general', 1, NOW()),

    ('caja_apertura_inicial', '0', 'booleano', 'Pedir monto inicial al abrir la caja del día.', 'caja', 1, NOW()),
    ('caja_permitir_diff', '1', 'booleano', 'Permitir cerrar la caja con diferencia.', 'caja', 1, NOW());


-- ==================================================================
--  FIN DEL ESQUEMA DE LA ETAPA 1
-- ==================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------
--  NOTA: en este archivo NO se crean usuarios. Si importaste el
--  SQL a mano y querés que la app funcione, tenés que:
--
--    a) entrar a install.php (paso 4) para crear los usuarios, o
--    b) insertarlos a mano con un hash generado en PHP:
--
--       INSERT INTO usuarios
--         (usuario, nombre, password_hash, rol, activo,
--          debe_cambiar_password, creado_en)
--       VALUES
--         ('admin1', 'Administrador',
--          '$2y$10$...hash...', 'administrador', 1, 1, NOW());
--
--       El hash se genera con:  password_hash('tu_clave', PASSWORD_DEFAULT);
--       nunca se guarda la contraseña en texto plano.
-- ------------------------------------------------------------------