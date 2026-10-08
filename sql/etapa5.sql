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