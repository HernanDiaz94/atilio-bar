-- ==================================================================
--  ATILIO-INSTALAR.sql
--  Bar Atilio · instalación completa de la base de datos
-- ------------------------------------------------------------------
--  QUÉ ES ESTE ARCHIVO
--
--  Los seis archivos de migración del sistema (las etapas 1 a 6)
--  juntos, en el orden en que se tienen que aplicar. Sirve para
--  importarlo de una sola vez desde phpMyAdmin.
--
--  CÓMO USARLO (phpMyAdmin de ByetHost)
--
--  1. Entrá a phpMyAdmin y elegí tu base en el desplegable de
--     arriba a la izquierda. OJO: tiene que ser TU base
--     (b18_43116320_atilio_bar_cta), no "information_schema".
--  2. Pestaña Importar.
--  3. Elegí este archivo y presioná Continuar.
--  4. Después entrá a https://atilio-bar-cta.byethost18.com/install.php
--     para crear los usuarios. Los usuarios NO se crean con este
--     archivo: los crea el instalador, para que las claves las
--     elijas vos.
--
--  SE PUEDE IMPORTAR VARIAS VECES
--
--  Todas las tablas se crean con CREATE TABLE IF NOT EXISTS y los
--  datos iniciales se insertan solo si no existen. Importarlo de
--  nuevo no borra nada: no te va a perder las ventas, los pedidos
--  ni el horario que ya cargaste.
--
--  QUÉ DEJA ARMADO
--
--    · 4 tablas base (usuarios, sesiones, configuración, auditoría)
--    · La carta del bar: bebidas y empanadas, con precios
--    · Las 10 mesas
--    · Las tablas de ventas, caja y pagos
--    · El costo de los productos guardado por venta
--    · Los horarios (martes a domingo de 20 a 2, lunes cerrado)
--    · La página pública lista para usar
--
--  AL TERMINAR
--
--  Borrá install.php del servidor. Mientras exista, cualquiera que
--  entre a esa dirección puede volver a instalar el sistema.
--
--  Este archivo se genera solo: si cambia algo del proyecto,
--  se vuelve a armar con la misma regla de concatenación.
-- ==================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;



-- ============================================================
--  Etapa 1  ·  database.sql
--  Tablas base: usuarios, sesiones, configuración y auditoría.
-- ============================================================

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
-- OJO: no se siembran bar_nombre, bar_direccion, bar_telefono,
-- bar_horario, ticket_pie ni ticket_mostrar_logo. Sequitaron porque
-- ningun codigo las leia y sus descripciones mentian: el nombre del
-- bar sale de APP_NOMBRE, en config/config.php. El nombre de la
-- pagina publica sale de publico_nombre (etapa 6).
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES



    ('seguridad_max_intentos', '5', 'numero', 'Intentos fallidos antes de bloquear una cuenta.', 'seguridad', 1, NOW()),
    ('seguridad_minutos_bloqueo', '15', 'numero', 'Minutos de bloqueo al superar los intentos.', 'seguridad', 1, NOW()),
    ('seguridad_minutos_sesion', '120', 'numero', 'Minutos de inactividad antes de cerrar la sesión.', 'seguridad', 1, NOW()),

    ('stock_alerta_bajo', '1', 'booleano', 'Mostrar alertas de stock bajo en el dashboard.', 'general', 1, NOW()),

    ('caja_apertura_inicial', '0', 'booleano', 'Pedir monto inicial al abrir la caja del día.', 'caja', 1, NOW()),
    ('caja_permitir_diff', '1', 'booleano', 'Permitir cerrar la caja con diferencia.', 'caja', 1, NOW())

-- Guarda de idempotencia: si la clave ya existe NO se toca el valor.
-- Sin esto, importar este archivo por segunda vez cortaba con
-- "Duplicate entry 'seguridad_max_intentos'". Los otros cinco
-- archivos de migracion ya traian esta misma guarda.
--
-- Importante: no se pone `valor` = `valor` porque eso pisaria lo
-- que el dueño haya cambiado en Configuracion. Lo que tiene que
-- pasar es que la clave este y su valor siga como esta.
ON DUPLICATE KEY UPDATE `clave` = `clave`;


-- ==================================================================
--  FIN DEL ESQUEMA DE LA ETAPA 1
-- ==================================================================


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


-- ============================================================
--  Etapa 2  ·  etapa2.sql
--  Carta de productos, categorías y stock.
-- ============================================================

-- ==================================================================
--  etapa2.sql  ·  Bar "Atilio"  ·  ETAPA 2
--  Productos, categorías, compras y stock
-- ------------------------------------------------------------------
--  CÓMO APLICARLO (ByetHost):
--    1. phpMyAdmin -> elegí la base (b12_12345678_atilio)
--    2. Pestaña "Importar" -> este archivo -> Ejecutar
--
--  Es un archivo SEPARADO a propósito: se aplica sobre una base que
--  ya tiene datos de la Etapa 1 sin tocar las tablas existentes.
--  Todas las sentencias usan CREATE TABLE IF NOT EXISTS, así que
--  correrlo dos veces no rompe nada.
-- ==================================================================

SET NAMES utf8mb4;


-- ==================================================================
--  1) CATEGORÍAS
--  Para agrupar la carta: bebidas, tragos, comidas, combos.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `categorias` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(60)  NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL DEFAULT '',
    `orden`       INT          NOT NULL DEFAULT 0
                  COMMENT 'Para acomodar la carta a gusto',
    `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `creado_en`   DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categorias_nombre` (`nombre`),
    KEY `ix_categorias_activo_orden` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías de la carta';


-- ==================================================================
--  2) PRODUCTOS
--
--  Dos tipos de producto conviven en la misma tabla, y se
--  distinguen con controla_stock:
--
--  - controls_stock = 1: es un producto que se vende por unidades y
--    se lleva cuenta. Ej: cerveza en botella, gaseosa, agua.
--    Acá stock_actual importa y se descuenta al vender.
--
--  - controla_stock = 0: es una preparación que se arma en el
--    momento. Ej: un gin tonic, una pizza, unas empanadas.
--    No tiene sentido descontar "media pizza" del stock.
--
--  Para las preparaciones que sí consumen insumos (el mojito usa
--  lima, azúcar y ron), se arma una receta en combo_componentes:
--  eso descuenta los insumos pero no el producto preparado.
--
--  El costo solo lo ve el administrador. Para el vendedor la app
--  nunca lo muestra, ni en la pantalla ni en el HTML.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `productos` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `codigo`          VARCHAR(30)   NULL DEFAULT NULL
                      COMMENT 'Código corto, opcional. NULL si no tiene',
    -- Nota sobre el índice único de codigo: tiene que ser NULL y
    -- no '' cuando el producto no tiene código. MySQL permite
    -- varios NULL en un índice único, pero NO varios '': si dos
    -- productos tuvieran '' se rompería el INSERT al cargar la
    -- carta inicial.
    `categoria_id`    INT UNSIGNED  NOT NULL
                      COMMENT 'Obligatorio: todo producto pertenece a una categoría',
    `nombre`          VARCHAR(120)  NOT NULL,
    `descripcion`     VARCHAR(255)  NOT NULL DEFAULT '',
    `precio_venta`    DECIMAL(10,2) NOT NULL
                      COMMENT 'Precio de venta al público',
    `costo`           DECIMAL(10,2) NULL DEFAULT NULL
                      COMMENT 'NULL = todavía no se sabe el costo. Solo admin lo ve',
    `stock_actual`    DECIMAL(10,2) NOT NULL DEFAULT 0.00
                      COMMENT 'Solo si controla_stock = 1',
    `stock_minimo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00
                      COMMENT 'Debajo de esto se muestra la alerta de stock bajo',
    `unidad`          VARCHAR(20)   NOT NULL DEFAULT 'unidad'
                      COMMENT 'unidad, medida, botella, litro, kg, docena',
    -- Formas en que se puede pedir, para que el mozo no tenga que
    -- escribir la cantidad. Ejemplo de las empanadas:
    --   1:unidad,6:media docena,12:docena
    -- Vacio = solo se vende de a una, que es lo normal.
    `formas_venta`    VARCHAR(160)  NOT NULL DEFAULT ''
                      COMMENT 'cantidad:etiqueta, separadas por coma',
    `controla_stock`  TINYINT(1)    NOT NULL DEFAULT 1,
    `es_combo`        TINYINT(1)    NOT NULL DEFAULT 0
                      COMMENT '1 = tiene receta que descuenta insumos',
    `activo`          TINYINT(1)    NOT NULL DEFAULT 1,
    `creado_en`       DATETIME      NOT NULL,
    `actualizado_en`  DATETIME      NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_productos_codigo` (`codigo`),
    KEY `ix_productos_categoria` (`categoria_id`),
    KEY `ix_productos_activo_nombre` (`activo`, `nombre`),
    -- Índice compuesto para la alerta de stock bajo: el dashboard
    -- filtra por esta combinación en cada carga.
    KEY `ix_productos_stock` (`controla_stock`, `stock_actual`, `stock_minimo`),

    -- No se puede borrar una categoría que tiene productos:
    -- mejor dejarla sin productos que perder el historial de ventas.
    CONSTRAINT `fk_productos_categoria`
        FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Productos de la carta';


-- ==================================================================
--  3) COMBO_COMPONENTES  (la receta)
--  Un combo o preparación que descuenta varios insumos al venderlo.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `combo_componentes` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `combo_id`     INT UNSIGNED  NOT NULL,
    `componente_id` INT UNSIGNED NOT NULL
                   COMMENT 'El producto que se descuenta (el insumo)',
    `cantidad`     DECIMAL(10,2) NOT NULL DEFAULT 1.00
                   COMMENT 'Cuánto se descuenta por cada unidad del combo',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_combo_componente` (`combo_id`, `componente_id`),
    KEY `ix_componentes_componente` (`componente_id`),

    CONSTRAINT `fk_componentes_combo`
        FOREIGN KEY (`combo_id`) REFERENCES `productos` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Restrict, no cascade: no se puede borrar un insumo que está
    -- en la receta de otro producto, porque rompería el cálculo.
    CONSTRAINT `fk_componentes_componente`
        FOREIGN KEY (`componente_id`) REFERENCES `productos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Receta de los combos: qué insumos se descuentan';


-- ==================================================================
--  4) COMPRAS  (ingreso de mercadería)
-- ==================================================================
CREATE TABLE IF NOT EXISTS `compras` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `proveedor`          VARCHAR(120) NOT NULL DEFAULT '',
    `numero_comprobante` VARCHAR(60)  NOT NULL DEFAULT '',
    `fecha`              DATE         NOT NULL,
    `total`              DECIMAL(10,2) NOT NULL DEFAULT 0.00
                         COMMENT 'Calculado, no se tipea',
    `observaciones`      VARCHAR(255) NOT NULL DEFAULT '',
    `cantidad_items`     INT UNSIGNED NOT NULL DEFAULT 0,
    `usuario_id`         INT UNSIGNED NULL DEFAULT NULL
                         COMMENT 'Quién cargó la compra',
    `creado_en`          DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_compras_fecha` (`fecha`),
    KEY `ix_compras_proveedor` (`proveedor`),

    CONSTRAINT `fk_compras_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ingresos de mercadería';


-- ==================================================================
--  5) COMPRAS_DETALLE
--  Cada línea suma stock y actualiza el costo del producto.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `compras_detalle` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `compra_id`       INT UNSIGNED  NOT NULL,
    `producto_id`     INT UNSIGNED  NOT NULL,
    `cantidad`        DECIMAL(10,2) NOT NULL,
    `costo_unitario`  DECIMAL(10,2) NOT NULL
                      COMMENT 'El costo que traer en la compra',
    `costo_anterior`  DECIMAL(10,2) NULL DEFAULT NULL
                      COMMENT 'El costo que tenía el producto antes',
    `costo_nuevo`     DECIMAL(10,2) NULL DEFAULT NULL
                      COMMENT 'Costo promedio ponderado resultante',
    `subtotal`        DECIMAL(10,2) NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_detalle_compra` (`compra_id`),
    KEY `ix_detalle_producto` (`producto_id`),

    CONSTRAINT `fk_detalle_compra`
        FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_detalle_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de cada compra';


-- ==================================================================
--  6) STOCK_MOVIMIENTOS
--  El libro único. TODO cambio de stock pasa por acá: compras,
--  ajustes manuales, y en las etapas siguientes las ventas.
--
--  stock_anterior y stock_resultante quedan guardados para poder
--  reconstruir el historial sin recalcular nada.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `stock_movimientos` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `producto_id`      INT UNSIGNED   NOT NULL,
    `tipo`             ENUM('alta_inicial','compra','ajuste','venta',
                            'anulacion_venta','anulacion_item','mesa_consumo',
                            'devolucion','baja') NOT NULL,
    `cantidad`         DECIMAL(10,2)  NOT NULL
                       COMMENT 'Positiva suma, negativa resta',
    `stock_anterior`   DECIMAL(10,2)  NOT NULL,
    `stock_resultante` DECIMAL(10,2)  NOT NULL,
    `costo_unitario`   DECIMAL(10,2)  NULL DEFAULT NULL
                       COMMENT 'Costo promedio ponderado que queda después del movimiento',
    `motivo`           VARCHAR(255)   NOT NULL DEFAULT ''
                       COMMENT 'Obligatorio en los ajustes manuales',
    `referencia_tipo`  VARCHAR(40)    NOT NULL DEFAULT ''
                       COMMENT 'compra, producto',
    `referencia_id`    INT UNSIGNED   NULL DEFAULT NULL,
    `usuario_id`       INT UNSIGNED   NULL DEFAULT NULL,
    `creado_en`        DATETIME       NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_mov_producto_fecha` (`producto_id`, `creado_en`),
    KEY `ix_mov_tipo` (`tipo`),
    KEY `ix_mov_fecha` (`creado_en`),
    KEY `ix_mov_referencia` (`referencia_tipo`, `referencia_id`),

    CONSTRAINT `fk_mov_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_mov_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Libro de movimientos de stock';


-- ==================================================================
--  7) CONFIGURACIÓN NUEVA
-- ==================================================================
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('stock_alerta_dashboard', '8', 'numero',
     'Cuántos productos con stock bajo mostrar en el dashboard.', 'general', 1, NOW()),
    ('stock_alerta_stock', '1', 'booleano',
     'Permitir guardar un producto con stock en 0 (igual se puede vender).', 'general', 1, NOW()),
    ('producto_mostrar_costo', '1', 'booleano',
     'Mostrar la columna de costo en la lista de productos. Solo para administradores.', 'general', 1, NOW())
ON DUPLICATE KEY UPDATE `clave` = `clave`;


-- ==================================================================
--  8) DATOS INICIALES DE LA CARTA
--  Del menú del bar: bebidas, tragos y comidas.
--  Se cargan solo si la tabla está vacía, así que volver a correr
--  este archivo no duplica nada.
--
--  Los precios son de ejemplo: cambialos desde la pantalla de
--  productos, o editá estos VALUES antes de importar.
--  Los costos van NULL a propósito: el administrador los carga cuando
--  compra la mercadería. El vendedor nunca los ve igual.
-- ==================================================================
INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Bebidas', 'Vasos de fernet, gancia y vino', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Bebidas');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Tragos', 'Tragos y combinados. Por ahora vacia', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Tragos');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Comidas', 'Empanadas y pizzeria. Se pueden agregar más cosas', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Comidas');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Insumos', 'Licores y zumos para las preparaciones. No se venden solos', 4, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Insumos');


-- --- BEBIDAS ---------------------------------------------------------
-- Son las tres que se venden por vaso. Todas llevan control de stock:
-- el fernét y la gancia se miden por frasco, el vino por botella, así
-- que el stock se lleva en la botella cerrada y no en el vaso servido.
--
-- Para agregar o quitar bebidas no hay que tocar este archivo: se
-- hace desde Productos.
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `formas_venta`,
     `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Vaso de fernet', '', 2200.00, NULL, 8, 2, 'vaso', '', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Vaso de fernet');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `formas_venta`,
     `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Vaso de gancia', '', 2200.00, NULL, 8, 2, 'vaso', '', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Vaso de gancia');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `formas_venta`,
     `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Vaso de vino', '', 2500.00, NULL, 6, 2, 'vaso', '', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Vaso de vino');


-- --- COMIDAS ---------------------------------------------------------
-- Las empanadas se venden de a una, por media docena o por docena.
-- Eso se declara en formas_venta, y la pantalla del pedido muestra
-- tres botones: "Unidad", "Media docena" y "Docena".
--
-- Solo carne y pollo, que es lo que se vendio al principio. Si
-- querés agregar otra de otro dia, se da de alta desde Productos.

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `formas_venta`,
     `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Empanada de carne', '', 900.00, NULL, 0, 0, 'unidad',
       '1:Unidad,6:Media docena,12:Docena', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Empanada de carne');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `formas_venta`,
     `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Empanada de pollo', '', 900.00, NULL, 0, 0, 'unidad',
       '1:Unidad,6:Media docena,12:Docena', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Empanada de pollo');



-- --- TRAGOS ----------------------------------------------------------
-- Queda vacía a propósito: hoy no se venden tragos. Cuando cargues
-- el primero, la categoría ya está y solo hay que dar de alta el
-- producto desde la pantalla de Productos.
-- Si más adelante querés que un combo descuente insumos (por ejemplo
-- un gin tonic que saca gin y tónica), se le arma la receta desde
-- el producto: la categoría Insumos está lista para eso.




-- ==================================================================
--  FIN DE LA MIGRACIÓN DE LA ETAPA 2
-- ==================================================================
--  Para probar que se aplicó bien:
--
--    SELECT * FROM categorias;
--    SELECT nombre, precio_venta, controla_stock FROM productos ORDER BY nombre;
--
--  La columna controla_stock es la clave: en 1 el producto se lleva
--  cuenta de stock, en 0 es una preparación.
-- ==================================================================


-- ============================================================
--  Etapa 3  ·  etapa3.sql
--  Mesas, pedidos y reservas.
-- ============================================================

-- ==================================================================
--  etapa3.sql  ·  Bar "Atilio"  ·  ETAPA 3
--  Mesas, comandas y reservas
-- ------------------------------------------------------------------
--  CÓMO APLICARLO (ByetHost):
--    phpMyAdmin -> elegí la base -> Importar -> este archivo -> Ejecutar
--
--  Es una migración SEPARADA a propósito: se aplica sobre una base
--  que ya tiene datos de las etapas 1 y 2 sin tocar esas tablas.
--  Todas las sentencias usan CREATE TABLE IF NOT EXISTS, así que
--  correrlo dos veces no rompe nada.
--
--  DECISIÓN DE DISEÑO: no hay una tabla "mesas_estado".
--  El estado de cada mesa se CALCULA a partir de las comandas
--  abiertas y las reservas de hoy. Con una tabla de estado
--  aparte, cualquier camino que olvide actualizarla deja la
--  pantalla mostrando un estado que no es real. Calculándolo,
--  no hay forma de que se desincronice.
-- ==================================================================

SET NAMES utf8mb4;


-- ==================================================================
--  1) MESAS
--
--  La zona sirve para agrupar la grilla: el salón, la vereda y la
--  barra se manejan distinto y conviene verlos por separado.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `mesas` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero`     VARCHAR(10)  NOT NULL COMMENT 'Lo que ve el cliente: 1, 2, A, BAR-1',
    `nombre`     VARCHAR(60)  NOT NULL DEFAULT ''
                COMMENT 'Nombre interno opcional: "Esquina del salon"',
    `capacidad`  INT UNSIGNED NOT NULL DEFAULT 4,
    `zona`       ENUM('salon','vereda','barra') NOT NULL DEFAULT 'salon',
    `orden`      INT          NOT NULL DEFAULT 0
                COMMENT 'Para acomodar la grilla a gusto, sin renumerar',
    `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
    `creado_en`  DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mesas_numero` (`numero`),
    KEY `ix_mesas_zona_activo` (`zona`, `activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mesas y_locations del bar';


-- ==================================================================
--  2) COMANDAS  (el pedido de una mesa)
--
--  Estados:
--    abierta    -> se esta consumiendo
--    por_cobrar -> se pidio la cuenta, ya no se agrega nada
--    cerrada   -> se cobró (lo completa la Etapa 4)
--    anulada   -> se canceló sin cobrar (solo administrador)
--
--  venta_id queda para la Etapa 4: cuando se cobre, esta comanda
--  se vincula a la venta.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `comandas` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero`         VARCHAR(20) NOT NULL
                    COMMENT 'Correlativo visible: C-000001',
    `mesa_id`        INT UNSIGNED NULL DEFAULT NULL
                    COMMENT 'NULL si la comanda quedó sin mesa (huérfana)',
    `estado`         ENUM('abierta','por_cobrar','cerrada','anulada')
                    NOT NULL DEFAULT 'abierta',
    `comensales`     INT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT 'Cantidad de personas sentadas',
    `vendedor_id`    INT UNSIGNED NULL DEFAULT NULL
                    COMMENT 'Quién abrió la mesa',
    `usuario_cierre_id` INT UNSIGNED NULL DEFAULT NULL,
    `observacion`    VARCHAR(255) NOT NULL DEFAULT ''
                    COMMENT 'Nota del vendedor, por ejemplo: traen una torta',
    `abierta_en`     DATETIME NOT NULL,
    `cerrada_en`     DATETIME NULL DEFAULT NULL,
    `venta_id`       INT UNSIGNED NULL DEFAULT NULL
                    COMMENT 'La Etapa 4 lo completa',
    `creado_en`      DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_comandas_numero` (`numero`),
    KEY `ix_comandas_mesa` (`mesa_id`),
    -- Índice clave para la grilla: se busca la comanda abierta de
    -- cada mesa en cada carga.
    KEY `ix_comandas_estado` (`estado`, `mesa_id`),
    KEY `ix_comandas_vendedor` (`vendedor_id`),

    -- Una mesa no se borra nunca (RESTRICT): si tiene historial de
    -- comandas, tiene que seguir existiendo para que el informe
    -- siga cuadrando.
    CONSTRAINT `fk_comandas_mesa`
        FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_comandas_vendedor`
        FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `fk_comandas_cierre`
        FOREIGN KEY (`usuario_cierre_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pedidos de las mesas';


-- ==================================================================
--  3) COMANDA_ITEMS  (las líneas del pedido)
--
--  El precio y el nombre se COPIAN al momento de agregar.
--  Si mañana subís el precio de la cerveza, las comandas viejas
--  tienen que seguir mostrando lo que se cobró ese día.
--
--  estado:
--    pendiente -> cuenta para el total
--    anulado    -> se saco; si habia descontado stock, lo repone
-- ==================================================================
CREATE TABLE IF NOT EXISTS `comanda_items` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `comanda_id`        INT UNSIGNED NOT NULL,
    `producto_id`       INT UNSIGNED NULL DEFAULT NULL
                        COMMENT 'NULL si el producto se desactivó después',
    `producto_nombre`   VARCHAR(120) NOT NULL COMMENT 'Snapshot del nombre',
    `precio_unitario`   DECIMAL(10,2) NOT NULL,
    `cantidad`          DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `importe`           DECIMAL(10,2) NOT NULL
                        COMMENT 'precio_unitario * cantidad',
    `estado`            ENUM('pendiente','anulado') NOT NULL DEFAULT 'pendiente',
    `motivo_anulacion`  VARCHAR(255) NOT NULL DEFAULT '',
    `usuario_id`        INT UNSIGNED NULL DEFAULT NULL COMMENT 'Quién la cargó',
    `creado_en`         DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_items_comanda` (`comanda_id`, `estado`),
    KEY `ix_items_producto` (`producto_id`),

    CONSTRAINT `fk_items_comanda`
        FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- RESTRICT: si un producto se puede borrar es porque nunca se
    -- consumió. Un producto con líneas no se borra, se desactiva.
    CONSTRAINT `fk_items_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_items_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de cada comanda';


-- ==================================================================
--  3b) COMANDA_ITEM_STOCK
--       (qué stock quitó cada línea, producto por producto)
--
--  POR QUÉ HACE FALTA, Y POR QUÉ NO ESTÁ DENTRO DE comanda_items:
--
--  Una línea no siempre toca un solo producto. Un gin tonic
--  descuenta el gin y la tónica. Si guardáramos el total
--  descontado en la línea, al anular no sabríamos a cuál
--  producto devolverle cada cosa, y el stock quedaría
--  desfasado justo cuando hay que cerrar el día.
--
--  Acá se guarda el detalle. Además se guarda CUÁNTO
--  consumía por unidad y no el total, así:
--
--    - Si el mozo cambia la cantidad de 3 a 5, el movimiento
--      de stock es exactamente la diferencia, sin tener que
--      volver a leer la receta.
--    - Si mañana cambian la receta del combo, la reposición
--      sigue devolviendo lo que realmente se sacó ese día.
--
--  devuelto = 1 cuando esa fila ya fue repuesta, para no
--  devolver dos veces lo mismo.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `comanda_item_stock` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`             INT UNSIGNED NOT NULL,
    `comanda_id`          INT UNSIGNED NOT NULL,
    `producto_id`         INT UNSIGNED NOT NULL,
    `cantidad_por_unidad` DECIMAL(12,4) NOT NULL
                          COMMENT 'Stock que consume la línea por cada unidad',
    `devuelto`            TINYINT(1) NOT NULL DEFAULT 0
                          COMMENT '1 = ya se repuso al anular',
    `creado_en`           DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_itemstock_item` (`item_id`),
    KEY `ix_itemstock_comanda` (`comanda_id`),
    KEY `ix_itemstock_producto` (`producto_id`),

    CONSTRAINT `fk_itemstock_item`
        FOREIGN KEY (`item_id`) REFERENCES `comanda_items` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_itemstock_comanda`
        FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_itemstock_producto`
        FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock consumido por cada línea de la comanda';


-- ==================================================================
--  4) RESERVAS
--
--  Guardan una reserva futura de una mesa. La Etapa 6 las va a
--  mostrar en la página pública, así que desde ahora ya se
--  contemplate el nombre y el teléfono del que reserva.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `reservas` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mesa_id`     INT UNSIGNED NOT NULL,
    `fecha`       DATE         NOT NULL COMMENT 'Día de la reserva',
    `hora`        TIME         NOT NULL COMMENT 'Hora de llegada',
    `comensales`  INT UNSIGNED NOT NULL DEFAULT 2,
    `nombre`      VARCHAR(120) NOT NULL COMMENT 'A nombre de quién queda',
    `telefono`    VARCHAR(40)  NOT NULL DEFAULT '',
    `notas`       VARCHAR(255) NOT NULL DEFAULT '',
    `estado`      ENUM('pendiente','confirmada','cancelada','no_show')
                 NOT NULL DEFAULT 'pendiente',
    `usuario_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'Quién la anotó',
    `creado_en`   DATETIME NOT NULL,
    `actualizado_en` DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    -- Una mesa no puede tener dos reservas en el mismo horario.
    UNIQUE KEY `uq_reservas_mesa_hora` (`mesa_id`, `fecha`, `hora`),
    KEY `ix_reservas_fecha` (`fecha`, `estado`),
    KEY `ix_reservas_mesa` (`mesa_id`),

    CONSTRAINT `fk_reservas_mesa`
        FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_reservas_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reservas futuras de mesas';


-- ==================================================================
-- ==================================================================
--  5) LAS DIEZ MESAS
--
--  Diez mesas: cinco del salón, tres de la vereda y dos de la
--  barra. Es un punto de partida, no un tope.
--
--  Para cambiar la cantidad NO hay que tocar este archivo. Desde
--  "Administrar mesas" se puede:
--
--    - dar de alta o baja una mesa de a una;
--    - generar varias de un tirón con "Generar mesas en tanda",
--      que es lo cómodo cuando el bar tiene 20 o 30 mesas.
--
--  Las mesas con historial de pedidos no se borran: se dan de
--  baja, así los informes viejos siguen cuadrando.
--
--  Se inserta mesa por mesa con NOT EXISTS, igual que los
--  productos de la etapa 2: si el archivo se corre dos veces no
--  se duplican.
-- ==================================================================

-- --- Salón: las cinco mesas de adentro --------------------------
INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '1', 'Esquina del salon', 4, 'salon', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '1');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '2', '', 4, 'salon', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '2');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '3', '', 6, 'salon', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '3');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '4', '', 4, 'salon', 4, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '4');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '5', 'Del fondo', 8, 'salon', 5, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '5');

-- --- Vereda: tres mesas afuera ----------------------------------
INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V1', 'Vereda izquierda', 2, 'vereda', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V1');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V2', '', 2, 'vereda', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V2');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V3', '', 2, 'vereda', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V3');

-- --- Barra: dos lugares ----------------------------------------
INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'B1', 'Barra alta', 2, 'barra', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'B1');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'B2', '', 2, 'barra', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'B2');


-- ==================================================================
--  6) CONFIGURACION
-- ==================================================================
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('mesa_zona_salon',  'Salon',  'texto', 'Nombre de la zona del salon.',  'general', 1, NOW()),
    ('mesa_zona_vereda', 'Vereda', 'texto', 'Nombre de la zona de la vereda.', 'general', 1, NOW()),
    ('mesa_zona_barra',  'Barra',  'texto', 'Nombre de la zona de la barra.',  'general', 1, NOW()),
    ('comanda_minutos_vigencia', '240', 'numero',
     'Minutos que una mesa queda ocupada si se abre y no se toca.', 'general', 1, NOW()),
    ('mesas_capacidad_default', '4', 'numero',
     'Lugares que se cargan por omision al crear una mesa nueva. Se cambia desde el formulario de mesa nueva.',
     'general', 1, NOW())
ON DUPLICATE KEY UPDATE `clave` = `clave`;




-- ==================================================================
--  FIN DE LA MIGRACIÓN DE LA ETAPA 3
-- ==================================================================
--  Para probar que se aplicó bien:
--
--    SELECT numero, zona, capacidad FROM mesas ORDER BY zona, orden;
--    SELECT COUNT(*) FROM comandas;      -- 0 al principio
--    SELECT COUNT(*) FROM reservas;      -- 0 al principio
-- ==================================================================


-- ============================================================
--  Etapa 4  ·  etapa4.sql
--  Ventas, cobros, medios de pago y caja.
-- ============================================================

-- ==================================================================
--  etapa4.sql  ·  Bar "Atilio"  ·  ETAPA 4
--  Cobro, ventas y caja
-- ------------------------------------------------------------------
--  CÓMO APLICARLO (ByetHost):
--    phpMyAdmin -> elegí la base -> Importar -> este archivo -> Ejecutar
--
--  Es una migración SEPARADA, como las anteriores: se aplica sobre
--  una base que ya tiene las etapas 1, 2 y 3 sin tocar esas tablas.
--
--  QUÉ SE DECIDIÓ CON EL DUEÑO DEL BAR
--
--    1. Los descuentos los aplica SOLO el administrador. El
--       vendedor cobra el total tal cual le sale.
--    2. Se cobra en efectivo, transferencia o tarjeta, y se puede
--       pagar una parte de una manera y el resto de otra.
--    3. Hay caja: se abre al empezar el turno, se cuenta la plata
--       al abrir y también al cerrar, y el sistema dice cuánto
--       debería haber y cuánto hay de más o de menos.
--
--  POR QUÉ UNA VENTA ES UNA TABLA APARTE Y NO UN CAMPO EN LA COMANDA
--
--  Una comanda dice QUÉ se pidió. Una venta dice CUÁNTO se cobró,
--  con qué medios de pago y con qué descuento. Son dos hechos
--  distintos y con fecha distinta: la comanda se puede abrir y
--  cargar durante dos horas, el cobro pasa en un minuto. Si fueran
--  la misma cosa, un cambio de precio a las 23:50 reescribiría la
--  historia de lo que se pidió a las 20:00.
-- ==================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------
--  1) METODOS DE PAGO
--
--  Se guardan en tabla y no en un ENUM para que se pueda agregar
--  uno nuevo (por ejemplo "Mercado Pago" o "Cuenta corriente")
--  sin tocar código.
--
--  afecta_caja = 1  ->  el importe entra al efectivo de la caja y
--                       se cuenta cuando se cierra el turno.
--  afecta_caja = 0  ->  no son plata en el cajón (tarjeta,
--                       transferencia). Se registra igual para
--                       saber cómo se cobró, pero no se cuenta.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metodos_pago` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clave`        VARCHAR(20)  NOT NULL COMMENT 'Identificador estable: efectivo, transferencia, tarjeta',
    `nombre`       VARCHAR(60)  NOT NULL COMMENT 'Lo que ve el vendedor: "Efectivo"',
    `afecta_caja`  TINYINT(1)   NOT NULL DEFAULT 1
                   COMMENT '1 = el importe queda en el cajón y se cuenta al cerrar',
    `orden`        INT          NOT NULL DEFAULT 0,
    `activo`       TINYINT(1)   NOT NULL DEFAULT 1,
    `creado_en`    DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_metodos_clave` (`clave`),
    KEY `ix_metodos_activo` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Medios de pago del bar';

INSERT INTO `metodos_pago` (`clave`, `nombre`, `afecta_caja`, `orden`, `activo`, `creado_en`)
SELECT 'efectivo', 'Efectivo', 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `metodos_pago` WHERE `clave` = 'efectivo');

INSERT INTO `metodos_pago` (`clave`, `nombre`, `afecta_caja`, `orden`, `activo`, `creado_en`)
SELECT 'tarjeta', 'Tarjeta', 0, 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `metodos_pago` WHERE `clave` = 'tarjeta');

INSERT INTO `metodos_pago` (`clave`, `nombre`, `afecta_caja`, `orden`, `activo`, `creado_en`)
SELECT 'transferencia', 'Transferencia', 0, 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `metodos_pago` WHERE `clave` = 'transferencia');


-- ------------------------------------------------------------------
--  2) CAJAS  (un turno)
--
--  Solo puede haber UNA caja abierta a la vez: es un solo cajón.
--  El control lo hace la aplicación, no la base: MySQL no tiene
--  índices únicos condicionales sobre un estado.
--
--  Los importes de apertura y cierre son DECIMALES, no enteros:
--  a veces hay diferencias de un peso por redondeo y no tiene que
--  faltar el campo para eso.
--
--  diferencia = cierre_importe - esperado_calculado
--    positiva  = sobró plata
--    negativa  = faltó plata
--    0         = cuadra
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`              VARCHAR(80)  NOT NULL DEFAULT ''
                           COMMENT 'Ej: "Turno del sábado"',
    `usuario_apertura_id` INT UNSIGNED NULL DEFAULT NULL,
    `apertura_importe`    DECIMAL(12,2) NOT NULL DEFAULT 0.00
                           COMMENT 'Cuánta plata se contó al abrir',
    `apertura_observacion` VARCHAR(255) NOT NULL DEFAULT '',
    `usuario_cierre_id`   INT UNSIGNED NULL DEFAULT NULL,
    `cierre_importe`      DECIMAL(12,2) NULL DEFAULT NULL
                           COMMENT 'Cuánta plata se contó al cerrar',
    `esperado_calculado`  DECIMAL(12,2) NULL DEFAULT NULL
                           COMMENT 'Cuánto debería haber: lo cobrado en efectivo más y menos los movimientos',
    `diferencia`          DECIMAL(12,2) NULL DEFAULT NULL
                           COMMENT 'Positiva sobra, negativa falta',
    `cierre_observacion`  VARCHAR(255) NOT NULL DEFAULT '',
    `estado`              ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
    `abierto_en`          DATETIME     NOT NULL,
    `cerrado_en`          DATETIME     NULL DEFAULT NULL,
    `creado_en`           DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_cajas_estado` (`estado`, `abierto_en`),
    KEY `ix_cajas_fecha` (`abierto_en`),

    CONSTRAINT `fk_cajas_apertura`
        FOREIGN KEY (`usuario_apertura_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `fk_cajas_cierre`
        FOREIGN KEY (`usuario_cierre_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Turnos de caja, con el conteo de dinero';


-- ------------------------------------------------------------------
--  3) CAJA_MOVIMIENTOS
--
--  Plata que entra o sale del cajón SIN que sea una venta: un
--  retiro para el dueño, el pago del gasista, la recarga del hielo.
--
--
--  tipo:
--    ingreso  = entró plata (devolución de un vuelto, venta en
--               efectivo que no había quedado asentada)
--    retiro   = el dueño se llevó plata
--    gasto    = se pagó algo del cajón (gas, hielo, delivery)
--
--  `ingreso` y `retiro` siempre son plata en efectivo: por eso el
--  cálculo de lo esperado los suma y resta igual que el efectivo
--  de las ventas.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `caja_movimientos` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caja_id`     INT UNSIGNED NOT NULL,
    `tipo`        ENUM('ingreso','retiro','gasto') NOT NULL,
    `importe`     DECIMAL(12,2) NOT NULL COMMENT 'Siempre positivo; el tipo dice el sentido',
    `motivo`      VARCHAR(255) NOT NULL DEFAULT '',
    `usuario_id`  INT UNSIGNED NULL DEFAULT NULL,
    `creado_en`   DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_mov_caja` (`caja_id`, `tipo`),

    CONSTRAINT `fk_mov_caja`
        FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    -- El nombre es UNICO EN TODA LA BASE, no solo en esta tabla.
    --
    -- Antes esta restriccion se llamaba fk_mov_usuario, que ya usaba
    -- stock_movimientos (etapa2). MariaDB 13 lo aceptaba duplicado y
    -- el error no aparecia ni en las pruebas ni al importar en local,
    -- pero el MySQL de ByetHost NO: cortaba la importacion con
    -- "Error: 121 Duplicate key on write or update" al llegar a esta
    -- tabla, y la base quedaba con 17 de las 22 tablas.
    --
    -- Por eso se llama fk_cajamov_usuario: es lo bastante distinto
    -- como para no chocar con ninguna otra y dice de qué tabla es.
    CONSTRAINT `fk_cajamov_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Entradas y salidas de plata del cajón, ajenas a las ventas';


-- ------------------------------------------------------------------
--  4) VENTAS
--
--  Es el hecho de "esta mesa se cobró tal cantidad". La comanda
--  queda congelada y esta tabla guarda lo que pasó en el cobro.
--
--  descuento_importe va acá y no en la comanda a propósito: un
--  descuento se decide al cobrar, no cuando se carga el pedido.
--  Guardarlo en la comanda haría que el pedido mostrara un precio
--  que nunca se pidió.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ventas` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero`                VARCHAR(20)  NOT NULL COMMENT 'V-000001',
    `comanda_id`            INT UNSIGNED NOT NULL,
    `caja_id`               INT UNSIGNED NOT NULL,
    `total_bruto`           DECIMAL(12,2) NOT NULL
                            COMMENT 'Suma del pedido, antes de descuento',
    `descuento_importe`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `descuento_motivo`      VARCHAR(255) NOT NULL DEFAULT '',
    `descuento_usuario_id`  INT UNSIGNED NULL DEFAULT NULL,
    `total_neto`            DECIMAL(12,2) NOT NULL COMMENT 'Lo que se cobró',
    `estado`                ENUM('cobrada','anulada') NOT NULL DEFAULT 'cobrada',
    `comensales`            INT UNSIGNED NOT NULL DEFAULT 1,
    `motivo_anulacion`      VARCHAR(255) NOT NULL DEFAULT '',
    `anulada_en`            DATETIME     NULL DEFAULT NULL,
    `cobrada_en`            DATETIME     NOT NULL,
    `usuario_id`            INT UNSIGNED NULL DEFAULT NULL COMMENT 'Quién cobró',
    `creado_en`             DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ventas_numero` (`numero`),
    KEY `ix_ventas_caja` (`caja_id`, `estado`),
    KEY `ix_ventas_cobrada` (`cobrada_en`),
    KEY `ix_ventas_comanda` (`comanda_id`),

    CONSTRAINT `fk_ventas_comanda`
        FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_ventas_caja`
        FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_ventas_descuento_usuario`
        FOREIGN KEY (`descuento_usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT `fk_ventas_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cobros de las mesas';


-- ------------------------------------------------------------------
--  5) VENTA_PAGOS
--
--  Una venta se puede pagar en varias partes: $5000 en efectivo y
--  $3000 con tarjeta. Por eso los pagos son filas y no columnas.
--
--  La suma de los pagos tiene que dar exactamente total_neto. No se
--  guarda un campo "pagado": se suma, que es lo único que no
--  puede desincronizarse.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `venta_pagos` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venta_id`        INT UNSIGNED NOT NULL,
    `metodo_pago_id`  INT UNSIGNED NOT NULL,
    `importe`         DECIMAL(12,2) NOT NULL,
    `referencia`      VARCHAR(60)  NOT NULL DEFAULT ''
                      COMMENT 'Alias, número de operación, etc.',
    `recibido`        DECIMAL(12,2) NOT NULL DEFAULT 0.00
                      COMMENT 'Cuánto entregó el cliente, para calcular el vuelto',
    `usuario_id`      INT UNSIGNED NULL DEFAULT NULL,
    `creado_en`       DATETIME     NOT NULL,

    PRIMARY KEY (`id`),
    KEY `ix_pagos_venta` (`venta_id`),
    KEY `ix_pagos_metodo` (`metodo_pago_id`),

    CONSTRAINT `fk_pagos_venta`
        FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_pagos_metodo`
        FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk_pagos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Con qué se pagó cada venta';


-- ------------------------------------------------------------------
--  6) ENLAZAR LA COMANDA CON LA VENTA
--
--  La columna venta_id ya existía desde la Etapa 3, por eso
--  ahora se le pone la clave foránea real.
--
--  Se hace con un guard: MySQL no tiene "ADD CONSTRAINT SI NO
--  EXISTE", así que se pregunta al diccionario de datos antes.
--  Por eso el archivo se puede correr dos veces.
-- ------------------------------------------------------------------
SET @fk_venta := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comandas'
      AND CONSTRAINT_NAME = 'fk_comandas_venta'
);

SET @sql_venta := IF(
    @fk_venta = 0,
    'ALTER TABLE `comandas` ADD CONSTRAINT `fk_comandas_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);

PREPARE stmt_venta FROM @sql_venta;
EXECUTE stmt_venta;
DEALLOCATE PREPARE stmt_venta;


-- ------------------------------------------------------------------
--  7) CONFIGURACION
-- ------------------------------------------------------------------
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('venta_numero_inicial', '1', 'numero',
     'Con qué número arranca el correlativo de ventas.', 'general', 1, NOW()),
    ('venta_permitir_descuento_vendedor', '0', 'booleano',
     'El vendedor puede aplicar descuentos. Por defecto no: los descuentos los hace el administrador.',
     'general', 1, NOW()),
    ('cobro_boton_grande', '1', 'booleano',
     'Mostrar el monto a cobrar en un botón grande, pensado para el celular.',
     'general', 1, NOW()),
    ('caja_pedir_motivo_cierre', '0', 'booleano',
     'Obligar a escribir por qué la caja no cuadró, cuando hay diferencia.',
     'general', 1, NOW())
ON DUPLICATE KEY UPDATE `clave` = `clave`;


-- ==================================================================
--  FIN DE LA MIGRACIÓN DE LA ETAPA 4
-- ==================================================================
--  Para probar que se aplicó bien:
--
--    SELECT * FROM metodos_pago;
--    SELECT * FROM caja_movimientos;   -- vacía, es normal
--    SELECT * FROM ventas;             -- vacía, es normal
--
--  Si esto tira error, casi siempre es que la etapa 3 no está
--  aplicada: el ALTER de comandas necesita la columna venta_id.
-- ==================================================================


-- ============================================================
--  Etapa 5  ·  etapa5.sql
--  Reportes: columna del costo e índices.
-- ============================================================

-- ==================================================================
--  etapa5.sql  ·  Bar "Atilio"  ·  ETAPA 5
--  Reportes y exportación a CSV
-- ------------------------------------------------------------------
--  CÓMO APLICARLO (ByetHost):
--    phpMyAdmin -> elegí la base -> Importar -> este archivo -> Ejecutar
--
--  Es una migración SEPARADA: se aplica sobre una base que ya tiene
--  las etapas 1 a 4, sin tocar los datos que ya hay.
--
--  QUÉ SE DECIDIÓ CON EL DUEÑO DEL BAR
--
--    1. Los reportes son SOLO del administrador: llevan plata,
--       costos y ganancia.
--    2. Los que se pidieron: ventas por día, qué se vende más,
--       por mesa y zona, y por medio de pago.
--    3. El período se elige con botones (hoy, ayer, esta semana,
--       este mes) o poniendo las fechas a mano.
--    4. El CSV se arma para que el Excel en español lo abra bien:
--       punto y coma, coma decimal y las tildes correctas.
--
--  POR QUÉ NO HAY TABLAS NUEVAS
--
--  Un reporte no es un hecho nuevo: es una pregunta hecha sobre
--  las ventas que ya se guardaron. Por eso esta migración NO crea
--  tablas, solo:
--
--    - una columna que faltaba (el costo del producto en el
--      momento de la venta),
--    - dos índices para que las sumas no se arrastren,
--    - una clave de configuración (cuántos días se pueden mirar).
--
--  GUARDADO POR EL HISTORIAL, NO POR EL DATO ACTUAL
--
--  La ganancia es el dato más engañoso que puede mostrar un
--  sistema, así que acá se hace una cosa importante: el costo se
--  guarda EN LA LÍNEA DE LA COMANDA, no se va a buscar al producto
--  al momento de armar el reporte.
--
--  Por qué: si el costo del vaso de fernet sube de $900 a $1.100
--  en marzo, un reporte de febrero que use el costo de hoy
--  mostraría que en febrero se ganó plata que en realidad no se
--  ganó. El número no mentía, pero contestaba otra pregunta. Con
--  el costo guardado en la línea, el reporte de febrero muestra
--  lo que de verdad pasó en febrero.
--
--  El precio ya se guardaba así desde la Etapa 3
--  (`precio_unitario`). El costo faltaba, y esta es la migration
--  que lo completa.
--
--  LO QUE NO SE PUEDE SABER (y está dicho)
--
--  `costo_unitario` es NULL cuando el producto no tiene costo
--  cargado. NULL no es cero: es "no lo sé". Los reportes usan
--  NULL para avisar que la ganancia que muestran está
--  subestimada, en vez de mirar la ganancia como si fuera cero.
--  Las ventas anteriores a esta etapa quedan con el costo de
--  hoy, y el reporte lo dice abajo, con la fecha desde la cual el
--  costo es el real.
-- ==================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------
--  1) EL COSTO DEL PRODUCTO EN EL MOMENTO DE LA VENTA
--
--  NULL = no se conoce el costo de ese producto (o es un producto
--  desactivado al que ya no se le puede leer el costo).
-- ------------------------------------------------------------------
SET @ya_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'comanda_items'
       AND COLUMN_NAME  = 'costo_unitario'
);

SET @sql := IF(@ya_existe = 0,
    'ALTER TABLE `comanda_items`
        ADD COLUMN `costo_unitario` DECIMAL(10,2) NULL
        COMMENT ''Costo del producto cuando se cargó a la mesa. NULL = no se sabe el costo''',
    'DO 0');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
--  2) RELLENAR EL COSTO DE LAS VENTAS YA HECHAS
--
--  Para lo que ya se vendió antes de esta etapa no hay costo
--  guardado (no existía), así que se completa con el costo que
--  tiene el producto hoy. Es lo más cercano a la verdad que se
--  puede obtener, pero conviene saberlo: el reporte va a
--  avisarlo con la fecha exacta.
-- ------------------------------------------------------------------
UPDATE `comanda_items` ci
  JOIN `productos` p ON p.`id` = ci.`producto_id`
   SET ci.`costo_unitario` = p.`costo`
 WHERE ci.`costo_unitario` IS NULL
   AND p.`costo` IS NOT NULL;

-- ------------------------------------------------------------------
--  3) ÍNDICES PARA QUE LOS REPORTES NO SE ARRASTREN
--
--  ventas: los reportes siempre filtran por fecha y por estado
--  (las anuladas no cuentan). Con los dos juntos, el índice se usa
--  mucho mejor que con la fecha sola.
--
--  comanda_items: "qué se vende más" agrupa por producto, y tiene
--  que filtrar las líneas anuladas.
-- ------------------------------------------------------------------
SET @ya_existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'ventas'
       AND INDEX_NAME   = 'ix_ventas_fecha_estado'
);

SET @sql := IF(@ya_existe = 0,
    'ALTER TABLE `ventas`
        ADD KEY `ix_ventas_fecha_estado` (`cobrada_en`, `estado`)',
    'DO 0');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ya_existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'comanda_items'
       AND INDEX_NAME   = 'ix_items_producto_estado'
);

SET @sql := IF(@ya_existe = 0,
    'ALTER TABLE `comanda_items`
        ADD KEY `ix_items_producto_estado` (`producto_id`, `estado`)',
    'DO 0');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
--  4) CUÁNTOS DÍOS SE PUEDEN MIRAR
--
--  ByetHost es un hosting gratis: la base de datos está compartida
--  con otros sitios y es lenta. Un reporte que suma cinco años de
--  ventas de golpe puede tardar tanto que el navegador se rinda.
--
--  Con este tope el sistema dice "mirá hasta 366 días" en vez de
--  dejar al dueño esperando. Se cambia desde configuracion si
--  alguna vez el bar necesita más.
-- ------------------------------------------------------------------
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('reporte_max_dias', '366', 'numero',
     'Máximo de días que se pueden consultar en un reporte. Sin este tope se puede pedir cinco años y dejar la página esperando.',
     'reportes', 1, NOW()),

    -- La fecha en la que se aplico ESTA migracion. Desde aca el costo
    -- guardado es el del momento de la venta. Lo que se vendio antes
    -- quedo con el costo que el producto tiene hoy, asi que su
    -- ganancia es aproximada y el reporte lo dice.
    ('reporte_costo_real_desde', DATE_FORMAT(NOW(), '%Y-%m-%d'), 'texto',
     'Desde esta fecha el costo guardado es el del momento de la venta.',
     'reportes', 0, NOW())
ON DUPLICATE KEY UPDATE `valor` = `valor`;


-- ============================================================
--  Etapa 6  ·  etapa6.sql
--  Horarios y días cerrados.
-- ============================================================

-- ==================================================================
--  etapa6.sql  ·  Bar "Atilio"  ·  ETAPA 6
--  Horarios y página pública
-- ------------------------------------------------------------------
--  CÓMO APLICARLO (ByetHost):
--    phpMyAdmin -> elegí la base -> Importar -> este archivo -> Ejecutar
--
--  Es una migración SEPARADA: se aplica sobre una base que ya tiene
--  las etapas 1 a 5, sin tocar los datos que ya hay.
--
--  QUÉ SE DECIDIÓ CON EL DUEÑO DEL BAR
--
--    1. La página pública muestra TODO: si está abierto ahora, los
--       horarios de la semana, teléfono, ubicación y la carta con
--       precios.
--    2. El bar sigue abierto después de medianoche (abre a las 20 y
--       cierra a las 2).
--    3. Se pueden marcar fechas concretas como cerrado (feriado,
--       vacaciones).
--    4. La página tiene que aparecer en Google.
--
--  EL HORARIO QUE CRUZA LA MEDIANOCHE
--
--  Este es el detalle que hace especial esta etapa. Un bar abre el
--  martes a las 20 y cierra el MIÉRCOLES a las 2. Si el cierre se
--  guardara como una hora sola ("02:00"), al compararla con la
--  apertura ("20:00") parecería que el bar cierra ANTES de abrir, y
--  el sistema concluiría que esa noche está cerrado. Al revés: un
--  bar que abre a las 00 y cierra a las 2 se rompería de la misma
--  manera.
--
--  Por eso `cierra_dia` dice explícitamente a qué día pertenece el
--  cierre:
--
--      abre 20:00, cierra 02:00, cierra_dia 1  -> abre el martes,
--                                                    cierra el miércoles
--      abre 00:00, cierra 02:00, cierra_dia 0  -> abre y cierra el mismo día
--
--  El sistema NO lo deduce. Lo deduce el dueño al marcar la
--  casilla, porque "se deduce" significa "a veces", y un horario mal
--  interpretado manda gente a un bar cerrado.
--
--  LOS DIAS CERRADOS
--
--  `horarios_cierres` es para fechas concretas: un feriado, unas
--  vacaciones, el domingo que se decide no abrir.
--
--  Semántica: la fecha es el DÍA en que el bar NO ABRE. No corta el
--  turno anterior. Si el jueves está marcado y el bar del miércoles
--  cierra el jueves a las 2, ese turno sigue adelante: lo que no hay
--  es un turno nuevo el jueves a la noche.
--
--  Esa decisión importa porque al revés sería imposible cerrar una
--  sola noche: el turno del día anterior siempre "invadiría" el
--  siguiente.
-- ==================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------
--  1) EL HORARIO DE LA SEMANA
--
--  Un renglón por día de la semana. Si `abre` y `cierra` son NULL,
--  ese día no se abre.
--
--  `dia_semana` usa la numeración de PHP: 0 = domingo, 6 = sábado.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horarios` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dia_semana`        TINYINT UNSIGNED NOT NULL
                        COMMENT '0 = domingo, 6 = sábado',
    `abre`              TIME         NULL COMMENT 'NULL = este día no se abre',
    `cierra`            TIME         NULL COMMENT 'NULL = este día no se abre',
    `cierra_dia`        TINYINT UNSIGNED NOT NULL DEFAULT 1
                        COMMENT '0 = cierra el mismo día, 1 = al día siguiente',
    `activo`            TINYINT(1)   NOT NULL DEFAULT 1,
    `creado_en`         DATETIME     NOT NULL,
    `actualizado_en`    DATETIME     NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_horarios_dia` (`dia_semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Horario de atención, un renglón por día';

-- ------------------------------------------------------------------
--  2) LOS DÍAS CERRADOS
--
--  Fechas concretas sin atención. La fecha es el día en que el bar
--  NO ABRE (ver la explicación de arriba).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horarios_cierres` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fecha`         DATE         NOT NULL,
    `motivo`        VARCHAR(120) NOT NULL DEFAULT ''
                    COMMENT 'Para qué se cierra: feriado, vacaciones',
    `creado_en`     DATETIME     NOT NULL,
    `creado_por`    INT UNSIGNED NULL COMMENT 'Quién lo marcó',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cierres_fecha` (`fecha`),
    KEY `fk_cierres_usuario` (`creado_por`),
    CONSTRAINT `fk_cierres_usuario`
        FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fechas concretas con el bar cerrado';

-- ------------------------------------------------------------------
--  3) HORARIO QUE QUEDA PUESTO
--
--  Martes a domingo de 20 a 2, y lunes cerrado: lo típico de un bar
--  de barrio. Es solo un punto de partida: el dueño lo cambia desde
--  la pantalla en un minuto, y los valores son los que se guardan.
--
--  Se cargan solo si la tabla está vacía, así que volver a importar
--  este archivo no pisa un horario ya configurado.
-- ------------------------------------------------------------------
INSERT INTO `horarios` (`dia_semana`, `abre`, `cierra`, `cierra_dia`, `activo`, `creado_en`)
SELECT s.`dia`, s.`abre`, s.`cierra`, s.`cierra_dia`, 1, NOW()
  FROM (
        # Los alias van TODOS en la primera fila: en un UNION los
        # nombres de columna salen de ahi, no de cada fila.
        SELECT 0 AS `dia`, '20:00:00' AS `abre`, '02:00:00' AS `cierra`, 1 AS `cierra_dia`
        UNION ALL SELECT 1, NULL, NULL, 1               # lunes: cerrado
        UNION ALL SELECT 2, '20:00:00', '02:00:00', 1   # martes
        UNION ALL SELECT 3, '20:00:00', '02:00:00', 1   # miercoles
        UNION ALL SELECT 4, '20:00:00', '02:00:00', 1   # jueves
        UNION ALL SELECT 5, '20:00:00', '03:00:00', 1   # viernes: mas tarde
        UNION ALL SELECT 6, '20:00:00', '02:00:00', 1   # sabado
       ) AS s
 WHERE NOT EXISTS (SELECT 1 FROM `horarios`)
ON DUPLICATE KEY UPDATE `dia_semana` = `dia_semana`;

-- ------------------------------------------------------------------
--  4) LOS DATOS DE LA PÁGINA PÚBLICA
--
--  Van en `configuracion` (grupo "publico") y no en una tabla nueva:
--  son valores sueltos que se editan en pantalla, que es
--  exactamente para lo que esa tabla existe. Así no se agrega otra
--  pantalla de formulario ni otro modelo.
--
--  OJO CON LO QUE NO ESTÁ ACÁ: el costo de los productos. No existe
--  ningún ajuste para publicarlo, y la consulta de la carta pública
--  ni siquiera pide esa columna. El costo no sale de este sistema
--  por la puerta de adelante.
-- ------------------------------------------------------------------
INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('publico_nombre', 'Atilio Bar', 'texto',
     'Nombre que aparece en la página pública y en el título de Google.',
     'publico', 1, NOW()),
    ('publico_telefono', '', 'texto',
     'Teléfono o WhatsApp. Si lo ponés, la página lo muestra como enlace para llamar.',
     'publico', 1, NOW()),
    ('publico_direccion', '', 'texto',
     'Dirección en texto. Se usa también para buscar el mapa.',
     'publico', 1, NOW()),
    ('publico_texto', '', 'texto',
     'Un párrafo libre de bienvenida, con lo que quieras contar.',
     'publico', 1, NOW()),
    ('publico_mostrar_carta', '1', 'booleano',
     'Mostrar la carta con precios en la página pública.',
     'publico', 1, NOW()),
    ('publico_mostrar_mapa', '1', 'booleano',
     'Mostrar el mapa en la página pública. Necesita la dirección.',
     'publico', 1, NOW()),
    ('publico_mostrar_horarios', '1', 'booleano',
     'Mostrar el horario en la página pública.',
     'publico', 1, NOW())
ON DUPLICATE KEY UPDATE `valor` = `valor`;

-- ------------------------------------------------------------------
--  5) EL NOMBRE, CORREGIDO
--
--  Este archivo se escribio al principio con el nombre al reves
--  ("Bar Atilio"). Va "Atilio Bar".
--
--  Solo se cambia si sigue siendo el valor por defecto: si el dueño
--  lo edito desde Configuracion, se respeta lo que puso y no se
--  pisa.
-- ------------------------------------------------------------------
UPDATE `configuracion`
   SET `valor` = 'Atilio Bar'
 WHERE `clave` = 'publico_nombre'
   AND `valor` IN ('Bar Atilio', 'Bar  Atilio', 'bar atilio');

SET FOREIGN_KEY_CHECKS = 1;

-- ==================================================================
--  FIN DE LA INSTALACIÓN
--
--  Ya se puede entrar a https://atilio-bar-cta.byethost18.com/install.php
--  para crear los usuarios, y después usar el sistema.
-- ==================================================================
