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
SET FOREIGN_KEY_CHECKS = 0;

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
--  5) DATOS INICIALES
--  Un salon de 8 mesas, 4 de vereda y 3 de barra. Se adaptan
--  desde la pantalla de mesas.
--
--  Se inserta mesa por mesa con NOT EXISTS, igual que los
--  productos de la etapa 2: si el archivo se corre dos veces no
--  se duplican.
-- ==================================================================
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
SELECT '5', '', 4, 'salon', 5, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '5');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '6', '', 4, 'salon', 6, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '6');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '7', '', 6, 'salon', 7, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '7');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT '8', '', 8, 'salon', 8, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = '8');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V1', 'Vereda izquierda', 2, 'vereda', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V1');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V2', '', 2, 'vereda', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V2');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V3', '', 2, 'vereda', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V3');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'V4', '', 4, 'vereda', 4, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'V4');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'B1', 'Barra alta', 2, 'barra', 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'B1');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'B2', '', 2, 'barra', 2, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'B2');

INSERT INTO `mesas` (`numero`, `nombre`, `capacidad`, `zona`, `orden`, `activo`, `creado_en`)
SELECT 'B3', '', 2, 'barra', 3, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `mesas` WHERE `numero` = 'B3');


INSERT INTO `configuracion`
    (`clave`, `valor`, `tipo`, `descripcion`, `grupo`, `editable`, `creado_en`)
VALUES
    ('mesa_zona_salon',  'Salon',  'texto', 'Nombre de la zona del salon.',  'general', 1, NOW()),
    ('mesa_zona_vereda', 'Vereda', 'texto', 'Nombre de la zona de la vereda.', 'general', 1, NOW()),
    ('mesa_zona_barra',  'Barra',  'texto', 'Nombre de la zona de la barra.',  'general', 1, NOW()),
    ('comanda_minutos_vigencia', '240', 'numero',
     'Minutos que una mesa queda ocupada si se abre y no se toca.', 'general', 1, NOW())
ON DUPLICATE KEY UPDATE `clave` = `clave`;


SET FOREIGN_KEY_CHECKS = 1;

-- ==================================================================
--  FIN DE LA MIGRACIÓN DE LA ETAPA 3
-- ==================================================================
--  Para probar que se aplicó bien:
--
--    SELECT numero, zona, capacidad FROM mesas ORDER BY zona, orden;
--    SELECT COUNT(*) FROM comandas;      -- 0 al principio
--    SELECT COUNT(*) FROM reservas;      -- 0 al principio
-- ==================================================================