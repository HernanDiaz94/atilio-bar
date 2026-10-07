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
--  Todas lasSentencias usan CREATE TABLE IF NOT EXISTS, así que
--  correrlo dos veces no rompe nada.
-- ==================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
SELECT 'Bebidas', 'Cerveza, gaseosas, agua y demas listos para servir', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Bebidas');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Tragos', 'Combinados y tragos de la carta', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Tragos');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Comidas', 'Pizza y empanadas. Se pueden agregar más cosas', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Comidas');

INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Insumos', 'Licores, zumos y cosas para las preparaciones. No se venden solos', 4, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Insumos');


-- --- Bebidas: estas SÍ llevan control de stock --------------------
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Cerveza en botella', '', 2500.00, NULL, 24, 6, 'botella', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Cerveza en botella');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Gaseosa', '', 1500.00, NULL, 48, 12, 'unidad', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Gaseosa');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Agua mineral', '', 1200.00, NULL, 36, 12, 'unidad', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Bebidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Agua mineral');


-- --- Tragos: preparaciones, sin control de stock -----------------
-- El cóctel no descuenta nada por sí solo. Si más adelante querés
-- que el gin tonic descuente la tónica, se le arma la receta desde
-- la pantalla del producto.
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Gin tonic', '', 4500.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Tragos'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Gin tonic');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Cerveza rubia Guinness 357', '', 5000.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Tragos'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Cerveza rubia Guinness 357');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Fernet', '', 3000.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Tragos'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Fernet');


-- --- Comidas: preparaciones, sin control de stock ----------------
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Pizza muzzarella', '', 6500.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Pizza muzzarella');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Pizza especial', '', 7500.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Pizza especial');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Empanadas de carne (x3)', '', 3500.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` LIKE 'Empanadas de carne%');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Empanadas de pollo (x3)', '', 3500.00, NULL, 0, 0, 'unidad', 0, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Comidas'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` LIKE 'Empanadas de pollo%');


-- --- Insumos: para armar recetas de tragos -----------------------
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Tónica', 'Insumo para el gin tonic', 0.00, NULL, 30, 8, 'unidad', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Insumos'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Tónica');

INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`, `precio_venta`, `costo`,
     `stock_actual`, `stock_minimo`, `unidad`, `controla_stock`, `es_combo`, `activo`, `creado_en`)
SELECT NULL, c.id, 'Gin', 'Insumo para tragos', 0.00, NULL, 2, 1, 'botella', 1, 0, 1, NOW()
FROM `categorias` c WHERE c.`nombre` = 'Insumos'
  AND NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Gin');


SET FOREIGN_KEY_CHECKS = 1;

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