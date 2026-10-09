-- ==================================================================
--  etapa8.sql  ·  Las mesas de pool
--  Bar Atilio · migración para bases ya instaladas
-- ------------------------------------------------------------------
--  POR QUÉ ESTA MIGRACIÓN
--
--  Las mesas V1, V2 y V3 eran un dato de ejemplo que se inventó y que
--  el dueño sacó. En su lugar tiene dos mesas de pool, que se cobran
--  por ficha.
--
--  Esta migración hace cuatro cosas:
--
--    1. Crea la categoría "Juegos" (solo si no existe).
--
--    2. Carga el producto "Mesa de Pool", que es la ficha. Editable:
--       el dueño le cambia el precio desde la pantalla de Producto si
--       algún día la ficha sube de los $2.500.
--
--    3. Renumera V1 y V2 como P1 y P2, y les pone el nombre.
--
--    4. Da de baja V3. OJO: V3 se DESACTIVA, no se borra. Si llegara
--       a tener comandas cargadas, borrarla es imposible por la llave
--       foranea, y desactivarla tampoco se la deja hacer el sistema a
--       través de la pantalla. Se desactiva por SQL y queda en la
--       base como registro.
--
--  LA ZONA
--
--  Las mesas de pool van a su propia zona, "pool". Es una zona más,
--  como "salon" y "barra": la usa para agrupar la grilla, y desde
--  "Administrar mesas" se le puede cambiar el nombre que se ve.
--
--  OJO CON EL ORDEN DE ESTE ARCHIVO
--
--  El ALTER del ENUM va AL FINAL, después de pasar a 'pool' cualquier
--  fila. Si quedara una fila con un valor viejo y después se sacara
--  ese valor de la lista, el ALTER falla.
--
--  CÓMO AFECTA A TUS DATOS: NADA DE LO QUE ESTÁ CARGADO
--
--  Las mesas no se borran: se renumeran. La llave foránea de las
--  comandas apunta a mesas(id), no al número, así que el historial de
--  pedidos y ventas sigue igual de apuntando a la misma mesa.
--
--  CÓMO USARLO
--
--  phpMyAdmin → tu base → Importar → este archivo → Continuar.
--
--  SE PUEDE CORRER VARIAS VECES: no rompe ni duplica nada.
--
--  ANTES:  1 a 8 (mesas) · B1, B2 (barra) · V1, V2, V3 (vereda)
--  DESPUÉS:  1 a 8 (mesas) · B1, B2 (barra) · P1, P2 (pool)
-- ==================================================================


-- ------------------------------------------------------------------
--  1) La categoría "Juegos"
--
--  Va con SELECT y no con INSERT por la misma razón que el resto del
--  proyecto: para que el archivo se pueda correr dos veces.
-- ------------------------------------------------------------------
INSERT INTO `categorias` (`nombre`, `descripcion`, `orden`, `activo`, `creado_en`)
SELECT 'Juegos', 'Mesas de pool y demas.', 50, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `categorias` WHERE `nombre` = 'Juegos');


-- ------------------------------------------------------------------
--  2) El producto "Mesa de Pool"
--
--  Esto ES la ficha. Se carga una vez por ficha jugada, y al pedir la
--  cuenta se cobran todas las de esa mesa.
--
--  controla_stock = 0: la ficha no se descuenta de ningun lado. No
--  hay un frasco que se gaste ni una cantidad que se lleve cuenta.
--  Descontarlo de un stock que no existe daria negativo.
--
--  unidad = 'ficha': el "3 x ficha" que ve el mozo en el pedido. No es
--  "unidad" ni "mesa", porque lo que se cobra es la ficha.
--
--  precio_venta = 2500: el precio de la ficha hoy. Editable desde la
--  pantalla de Producto, y el precio queda copiado en cada línea del
--  pedido cuando se carga, así que subirlo no altera los pedidos
--  viejos.
-- ------------------------------------------------------------------
INSERT INTO `productos`
    (`codigo`, `categoria_id`, `nombre`, `descripcion`,
     `precio_venta`, `unidad`, `controla_stock`, `es_combo`,
     `activo`, `creado_en`)
SELECT 'POOL',
       (SELECT `id` FROM `categorias` WHERE `nombre` = 'Juegos'),
       'Mesa de Pool',
       'La ficha de la mesa de pool.',
       2500.00,
       'ficha',
       0,
       0,
       1,
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM `productos` WHERE `nombre` = 'Mesa de Pool');


-- ------------------------------------------------------------------
--  3) Las mesas: V1 y V2 pasan a ser P1 y P2
--
--  El nombre ("nombre") dice qué es la mesa, para que el mozo no
--  tenga que acordarse de si la P1 es la del rincón o la de la
--  ventana.
--
--  El "orden" se reinicia en 1 y 2 para que las dos queden juntas y en
--  orden, y no mezcladas con el orden que tenían en la vereda.
-- ------------------------------------------------------------------
-- OJO CON EL ORDEN DE ESTOS DOS
--
-- Primero se cambia el número, dejando la zona como está (vereda).
-- Después, en el paso 5, se cambia la zona de lo que quede en vereda.
--
-- ¿Por qué en ese orden? Porque la zona "pool" todavía NO está en el
-- ENUM: se agrega recién en el paso 6. Si este UPDATE pusiera
-- zona='pool' antes de ese ALTER, el MySQL lo rechaza con el error
-- 1265 ("Data truncated for column 'zona'"), porque trunca la cadena
-- a un valor que la lista no tiene. Y si el error corta la
-- migración, las mesas quedan a medio camino.
UPDATE `mesas`
   SET `numero` = 'P1',
       `nombre` = 'Mesa de pool 1',
       `orden` = 1,
       `capacidad` = 4,
       `activo` = 1
 WHERE `numero` = 'V1'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `numero` FROM `mesas`) AS t
                    WHERE t.`numero` = 'P1');

UPDATE `mesas`
   SET `numero` = 'P2',
       `nombre` = 'Mesa de pool 2',
       `orden` = 2,
       `capacidad` = 4,
       `activo` = 1
 WHERE `numero` = 'V2'
   AND NOT EXISTS (SELECT 1 FROM (SELECT `numero` FROM `mesas`) AS t
                    WHERE t.`numero` = 'P2');


-- ------------------------------------------------------------------
--  3b) Si ya existía una mesa P1 o P2 (por si el dueño la creó a
--      mano antes de correr esto), la de la vereda NO se renumera:
--      queda con su número viejo. Se renombra desde Administrar
--      mesas.
--
--      Lo que sí necesita es pasar a la zona pool, y eso lo hace el
--      paso 5 (que mueve a pool todo lo que esté en vereda). Por eso
--      acá NO se toca la zona: todavía no existe en el ENUM.
-- ------------------------------------------------------------------
UPDATE `mesas`
   SET `orden` = 900 + `id`,
       `activo` = 1
 WHERE `numero` IN ('V1', 'V2');


-- ------------------------------------------------------------------
--  4) V3 desaparece
--
--  Se DESACTIVA, no se borra.
--
--  Por qué no se borra: la llave foránea de las comandas apunta a
--  mesas(id) y es ON DELETE RESTRICT. Si esa mesa llegara a tener
--  alguna comanda cargada, el MySQL del hosting rechaza el borrado.
--  Con un historial, no hay forma de perderla.
--
--  Por qué con SQL y no desde "Administrar mesas": esa pantalla
--  impide desactivar una mesa con gente sentada, y una mesa sin usar
--  se puede dar de baja por ahí. Con SQL el movimiento queda escrito
--  y no depende de que haya alguien sentado en ese momento.
--
--  Igual queda en la base, inactiva: si algún día hay que mirar un
--  pedido viejo de esa mesa, sigue ahí.
-- ------------------------------------------------------------------
UPDATE `mesas`
   SET `activo` = 0
 WHERE `numero` = 'V3';


-- ------------------------------------------------------------------
--  5) El ENUM AGREGA 'pool'  (pero 'vereda' sigue ahí)
--
--  Este ALTER es en dos partes y el orden importa mucho.
--
--  Este primero: entra 'pool' y 'vereda' QUEDA. No se puede hacer en
--  un solo paso quitar una y agregar la otra, porque en el momento
--  del ALTER hay filas con zona='vereda', y si esa palabra ya no
--  estuviera en la lista, el MySQL las trunca a '' y rompe la fila
--  (error 1265). Ese es el error que daba antes: el renumerado
--  quedaba a medias y V3 se quedaba con un número V.
--
--  Por eso: primero entra pool con vereda todavía adentro (paso 5),
--  después se mueven las zonas (paso 6), y recién ahí sale vereda
--  (paso 7).
-- ------------------------------------------------------------------
ALTER TABLE `mesas`
    MODIFY `zona` ENUM('salon','vereda','barra','pool') NOT NULL DEFAULT 'salon';


-- ------------------------------------------------------------------
--  6) Toda mesa que quedó en la vereda pasa a ser de pool
--
--  Ahora sí se puede: 'pool' ya está en la lista.
--
--  Con el paso 3 hecho, a esta altura quedan las dos renumeradas
--  (P1, P2) con su zona vieja, y V3 desactivada con la suya. Las tres
--  pasan por acá.
--
--  Que V3 acabe en la zona pool aunque esté dada de baja es lo mismo
--  que pasa con una mesa que se desactiva: no aparece en la grilla ni
--  se le puede abrir un pedido, pero su registro queda coherente.
-- ------------------------------------------------------------------
UPDATE `mesas`
   SET `zona` = 'pool'
 WHERE `zona` = 'vereda';


-- ------------------------------------------------------------------
--  7) El ENUM: sale 'vereda'
--
--  Último paso. Ahora sí se puede sacar 'vereda' de la lista, porque
--  ya no queda ninguna fila que lo tenga: el paso 6 las movió todas.
--
--  Si se sacara antes, el MySQL truncaría esas filas a '' (error 1265)
--  y la migración quedaría a medias.
-- ------------------------------------------------------------------
ALTER TABLE `mesas`
    MODIFY `zona` ENUM('salon','barra','pool') NOT NULL DEFAULT 'salon';


-- ==================================================================
--  FIN DE LA MIGRACIÓN
--
--  Recargá la pantalla de Mesas y tiene que mostrar tres zonas:
--  Mesas (ocho), Barras (dos) y Pool (dos).
--
--  La ficha, para cambiarle el precio si algún día cambia:
--  Productos → Mesa de Pool.
-- ==================================================================