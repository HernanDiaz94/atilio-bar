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
