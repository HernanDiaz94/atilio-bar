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
