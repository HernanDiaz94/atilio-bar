-- ==================================================================
--  etapa7.sql  ·  Se saca la zona "vereda"
--  Bar Atilio · migración para bases ya instaladas
-- ------------------------------------------------------------------
--  POR QUÉ ESTA MIGRACIÓN
--
--  El bar tiene mesas y barra. Las mesas de la vereda (V1, V2, V3)
--  eran un dato de ejemplo inventado para la instalación. No existen.
--
--  Esta migración hace tres cosas, en este orden:
--
--    1. Las tres mesas de vereda pasan a ser las mesas 6, 7 y 8.
--    2. El ENUM de la zona se queda con dos valores: salon y barra.
--    3. Se borran tres claves de configuración que no las leía nadie.
--
--  CÓMO AFECTA A TUS DATOS: NADA
--
--  Las mesas NO se borran: se renumeran. La clave foránea de las
--  comandas apunta a mesas(id), no al número, así que el historial de
--  los pedidos y las ventas sigue igual, apuntando a la misma mesa.
--  Es por eso que NO se borran: una mesa con historial no se puede
--  borrar (la llave lo impide a propósito), pero renumerar siempre
--  se puede.
--
--  CÓMO USARLO
--
--  phpMyAdmin → tu base → Importar → este archivo → Continuar.
--  O pegarlo en la pestaña SQL y darle a Continuar.
--
--  SE PUEDE CORRER VARIAS VECES: no rompe ni duplica nada.
--
--  ANTES:  1, 2, 3, 4, 5 (mesas)  ·  V1, V2, V3 (vereda)  ·  B1, B2 (barra)
--  DESPUÉS: 1 a 8 (mesas)  ·  B1, B2 (barra)
-- ==================================================================


-- ------------------------------------------------------------------
--  1) Las mesas de la vereda pasan a ser las 6, 7 y 8
--
--  El número se cambia, no el id. Por eso el historial queda
--  apuntando a la misma mesa de siempre.
--
--  El "nombre" se limpia tambien: la primera se llamaba "Vereda
--  izquierda", que es un nombre de un lugar que no existe.
--
--  El "NOT EXISTS" protege la mesa 6 del dueño: si ya existe una mesa
--  con ese número (porque la agregó a mano), la de la vereda NO se
--  renumera. Queda con su número viejo, pero igual sale de la zona
--  vereda en el paso 1b. Se renombra desde Administrar mesas.
--
--  El SELECT del NOT EXISTS va envuelto en otro SELECT porque MySQL
--  no deja que un UPDATE lea la misma tabla que está modificando
--  (error 1093). Por eso el alias.
-- ------------------------------------------------------------------
UPDATE `mesas`
   SET `numero` = '6', `zona` = 'salon', `orden` = 6, `nombre` = '',
       `activo` = 1
 WHERE `numero` = 'V1'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `numero` FROM `mesas`) AS t
                    WHERE t.`numero` = '6');

UPDATE `mesas`
   SET `numero` = '7', `zona` = 'salon', `orden` = 7, `nombre` = '',
       `activo` = 1
 WHERE `numero` = 'V2'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `numero` FROM `mesas`) AS t
                    WHERE t.`numero` = '7');

UPDATE `mesas`
   SET `numero` = '8', `zona` = 'salon', `orden` = 8, `nombre` = '',
       `activo` = 1
 WHERE `numero` = 'V3'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `numero` FROM `mesas`) AS t
                    WHERE t.`numero` = '8');


-- ------------------------------------------------------------------
--  1b) Si quedo alguna mesa en vereda (una que se haya creado a mano
--       desde el sistema), pasa a ser mesa. No se borra nada.
--
--  Esto tiene que ir ANTES del ALTER del ENUM: si queda una fila con
--  'vereda' y despues se saca 'vereda' de la lista, el ALTER falla.
--
--  Se le pone un orden alto (900 y following) para que aparezca al
--  final de la lista y no se mezcle con las mesas que ya tenian su
--  lugar. El orden de cada mesa se corrige desde Administrar mesas.
-- ------------------------------------------------------------------
UPDATE `mesas`
   SET `zona` = 'salon',
       `orden` = 900 + `id`
 WHERE `zona` = 'vereda';


-- ------------------------------------------------------------------
--  NO se renumera el `orden` de las otras mesas. A propósito.
--
--  Parecia que habia que hacerlo, pero no: al renumerar, las mesas
--  6, 7 y 8 ya quedan con su orden (6, 7 y 8), y las del 1 al 5
--  siguen con el suyo. La grilla ya sale en orden.
--
--  Renumerar todo con ROW_NUMBER() ORDER BY `numero` parte mal por
--  dos cosas:
--
--    a) `numero` es texto, asi que ordenaria '10' antes que '2'.
--       La grilla terminaria mostrando 1, 10, 11, 2, 20, 3... en
--       cuanto el bar pase de nueve mesas.
--
--    b) ROW_NUMBER() necesita MySQL 8.0. Si el hosting tiene 5.7,
--       el paso falla y la migracion se corta ahi.
--
--  Los dos problemas se evitan no haciendo nada.
-- ------------------------------------------------------------------


-- ------------------------------------------------------------------
--  2) El ENUM de la zona se queda con dos valores
--
--  Este ALTER es el unico paso que puede fallar, y falla solo si
--  quedara alguna fila con 'vereda'. Por eso el paso 1b esta antes.
--
--  Si el hosting lo rechaza, el resto de la migración ya esta
--  hecho: las mesas ya se ven en la grilla igual. Se puede volver a
--  correr este archivo sin problema.
-- ------------------------------------------------------------------
ALTER TABLE `mesas`
    MODIFY `zona` ENUM('salon','barra') NOT NULL DEFAULT 'salon';


-- ------------------------------------------------------------------
--  3) Se borran las tres claves de configuracion que no leia nadie
--
--  Decian "Nombre de la zona del salon" y no cambiaban nada: el texto
--  sale de Mesas::ZONAS, en el codigo. Editarlas desde la base no
--  hacia nada. Mejor borrarlas que dejarlas mintiendo.
-- ------------------------------------------------------------------
DELETE FROM `configuracion`
 WHERE `clave` IN ('mesa_zona_salon', 'mesa_zona_vereda', 'mesa_zona_barra');


-- ==================================================================
--  FIN DE LA MIGRACIÓN
--
--  Recargá la pantalla de Mesas y tiene que mostrar dos zonas:
--  Mesas (ocho) y Barras (dos).
-- ==================================================================