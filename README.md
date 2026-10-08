# Sistema de bar "Atilio"

Aplicación web para un bar: mesas, pedidos, ventas, cobro y caja.

PHP 8 puro, MySQL/MariaDB, Bootstrap 5 por CDN. **Sin frameworks, sin
Composer, sin SSH**: se sube por FTP a un hosting compartido.

---

## Estado del desarrollo

| Etapa | Contenido | Estado |
|---|---|---|
| 1 | Base, login, usuarios y configuración | Completada |
| 2 | Productos, categorías y stock | Completada |
| 3 | Mesas y pedidos | Completada |
| 4 | Ventas, cobro y caja | Completada |
| 5 | Reportes y exportación a CSV | Completada |
| 6 | Horarios y página pública | Completada |

Las seis etapas están completas. Queda lo que se agrega a partir de
acá: por ejemplo, imprimir tickets o mandar el reporte por mail.

Cada etapa trae su migración en un archivo aparte, para poder
aplicarla sobre una base que ya tiene datos sin romper nada.

---

## Actualizar de etapa (Etapa 2, 3 y las siguientes)

Cada etapa trae su propio archivo SQL. Se importa por phpMyAdmin
sobre la base que ya tiene datos, sin tocar las tablas anteriores.

1. Subí los archivos nuevos o modificados por FTP.
2. phpMyAdmin → tu base → **Importar** → `sql/etapaN.sql` → **Ejecutar**.
3. Entrá al panel y revisá lo nuevo.

| Etapa  | Archivo             | Qué aparece                        |
|-------:|---------------------|------------------------------------|
| 1      | (por `install.php`) | Usuarios, sesiones y configuración |
| 2      | `sql/etapa2.sql`    | Carta de productos y stock         |
| 3      | `sql/etapa3.sql`    | Mesas, pedidos y reservas          |
| 4      | `sql/etapa4.sql`    | Cobro, ventas y caja               |
| 5      | `sql/etapa5.sql`    | Reportes y CSV                     |
| 6      | `sql/etapa6.sql`    | Horarios y página pública          |
| 7      | `sql/etapa7.sql`    | Saca la zona "vereda" (bases viejas)|

Cada migración es segura de correr más de una vez: los `CREATE TABLE`
usan `IF NOT EXISTS` y los datos iniciales se insertan solo si no
existen.

La etapa 7 es distinta: no agrega nada, *saca* la zona vereda que
venía de ejemplo. Solo hace falta en bases que ya tenían las mesas
V1, V2 y V3. Si instalás de cero con `ATILIO-INSTALAR.sql`, no la
necesitás: ese archivo ya trae la etapa 3 nueva, con ocho mesas y sin
vereda.

---

## Cobro, ventas y caja (Etapa 4)

Una mesa pide la cuenta, el vendedor la cobra, la mesa se libera.

### Antes de cobrar hay que abrir la caja

No se puede cobrar nada con la caja cerrada. Se abre el turno
contando cuánta plata hay en el cajón, y con esa cantidad el sistema
va sumando lo que se cobre. Al cerrar el turno se cuenta otra vez y
el sistema dice cuánto **debería** haber:

```
Con lo que había al abrir      $ 15.000,00
Efectivo cobrado (12 ventas)   $  86.400,00
Retiros del dueño              - $ 20.000,00
Gastos del bar                 - $  3.000,00
                                -----------
Debería haber                  $ 78.400,00
```

Si lo que se cuenta no da, el sistema lo dice con todas las letras:
cuánto sobró o cuánto faltó. El desglose está a propósito: si al
final faltan $500, ver de dónde sale el número ayuda a saber si fue
un error de tipeo o un gasto que no se cargó.

### Los pagos se pueden mezclar

Una venta puede cobrarse en efectivo, tarjeta o transferencia, y
también partido: $5.000 en efectivo y $3.000 con tarjeta es un caso
normal, no una excepción. Por eso los pagos son filas y no columnas.

Solo el **efectivo** entra al cajón y se cuenta al cerrar. La tarjeta
y la transferencia se registran para saber cómo se cobró, pero esa
plata nunca estuvo en el cajón.

### Los descuentos los hace el administrador

El vendedor cobra el total tal cual. No es desconfianza: un
descuento es plata que sale del bar, y si el sistema deja que
cualquiera lo haga, un error de tipeo se come la ganancia sin que
nadie se entere hasta el cierre.

El descuento se aplica **mientras se está cobrando**, no al cargar el
pedido, y queda anotado en la venta con su motivo y con quién lo
hizo. Si algún día hace falta que el vendedor también pueda, se
enciende el interruptor `venta_permitir_descuento_vendedor` de la
configuración y no hay que tocar código.

### Anular una venta

Solo el administrador, solo con la caja del día abierta, y siempre
con motivo. Anula devuelve la plata a la caja y el stock al
depósito, pero **no borra el registro**: queda claro que se cobró y
se devolvió.

---

## Reportes (Etapa 5)

Cuatro números que el dueño miraba de memoria y ahora están en pantalla.

### Los cuatro

| Reporto | Para qué sirve |
|---|---|
| **Por día** | Cuánto se facturó cada día, y cuánto se ganó |
| **Qué se vende** | Los productos ordenados por cantidad, para saber qué comprar |
| **Mesas y zonas** | Cuánto factura cada mesa, y mesas contra barras |
| **Medios de pago** | Cuánto entró en efectivo y cuánto con tarjeta |

El período se elige con botones (hoy, ayer, esta semana, este mes,
mes anterior) o poniendo las fechas a mano. Arriba siempre está el
total del período: cuánto se vendió, cuánto se ganó, y cuánto por
persona.

Cada reporte baja a **CSV** con el botón de arriba. El archivo está
armado para que el Excel en español lo abra bien: separador punto y
coma, coma decimal, y las tildes correctas (lleva el BOM, que sin él
el Excel lee las tildes como dos letras raras y "Café" aparece con la
A acentuada y la e con un signo arriba).

### La ganancia usa el costo del momento, no el de hoy

Cuando se carga un producto a una mesa, el sistema guarda el costo que
tenía **en ese momento**, igual que ya guardaba el precio. Por eso, si
mañana el costo del fernet sube de $900 a $1.100, el reporte de hoy
sigue diciendo lo que pasó hoy.

Si el sistema usara el costo actual, un reporte de marzo estaría
contando con precios de junio: el número no mentía, pero contestaba
otra pregunta.

### Cuando un producto no tiene costo cargado, el reporte lo dice

`NULL` no es cero. Si a un producto nunca le cargaron el costo, el
reporte **no** lo trata como si costara nada (eso haría que la
ganancia salga inflada: "vendí $10.000 y me quedaron $10.000", que es
falso). En cambio avisa arriba, con letra:

> 4 ventas del período no tienen costo de producto cargado, así que
> la ganancia que ves está SUBESTIMADA.

Esa cifra está en amarillo, no en verde, para que no se tome por
buena. Cuando se carga el costo en la carta, el número se corrige
solo.

### Las ventas anuladas no cuentan

Una venta anulada fue cobrada y devuelta. Sumarla mostraría plata que
no está. Todos los reportes filtran las anuladas.

### Solo el administrador

Los reportes llevan la ganancia y el costo, que no son asunto del
vendedor. El vendedor recibe un 403 si entra por la URL, y el botón no
le aparece.

### Por qué no hay tablas nuevas

Un reporte es una pregunta sobre datos que ya existen, no un dato
nuevo. `sql/etapa5.sql` solo agrega la columna del costo, dos índices
para que las sumas no se arrastren en un hosting lento, y dos claves
de configuración:

- `reporte_max_dias` (366): hasta dónde se puede mirar. Por hosting
  son lento, y un reporte sobre cinco años puede tardar tanto que el
  dueño piense que se colgó.
- `reporte_costo_real_desde`: la fecha en que se aplicó la Etapa 5. Las
  ventas anteriores usaron el costo que el producto tiene hoy, y el
  reporte lo dice con esa fecha.

---

## Horarios y página pública (Etapa 6)

La cara del bar para cualquiera que la encuentre en Google.

### El horario que cruza la medianoche

Un bar abre el martes a las 20 y cierra el **miércoles a las 2**. Las
dos horas son números: "02:00" es más chico que "20:00", así que el
sistema no puede deducir solo si esa noche está cerrada. Y si lo
hiciera por su cuenta, algún día se equivocaría y mandaría gente a un
bar cerrado.

Por eso el horario guarda explícitamente `cierra_dia`: a qué día
pertenece el cierre. Lo marca el dueño en una casilla, y el sistema
solo lo deduce cuando el cierre es más temprano que la apertura, que es
el caso obvio.

```
Martes    20:00 a 02:00 (Miércoles)     ← cruzando la medianoche
Viernes   20:00 a 03:00 (Sábado)        ← el viernes cierra más tarde
Lunes     Cerrado
```

También funciona al revés: un bar que abre a la 00 y cierra a las 2 se
guarda como "00:00 a 02:00, mismo día", que es lo que dice.

### ¿Está abierto ahora?

La primera respuesta de la página. Mira el turno de hoy **y el de
ayer**, porque a la 01:30 del miércoles el turno que está corriendo es
el del martes. Cuando está cerrado dice cuándo vuelve a abrir, saltando
los días marcados como cerrado.

Los días cerrados son fechas concretas (feriado, vacaciones) y la
semántica importa: la fecha es el día en que el bar **no abre**, no
corta el turno anterior. Si el jueves está marcado, el bar del
miércoles a la noche sigue hasta las 2. Al revés sería imposible cerrar
una sola noche.

### La página pública

Es lo único del sistema que se abre sin sesión. Muestra:

- si está abierto ahora y hasta cuándo;
- el horario de la semana;
- teléfono y ubicación con mapa;
- **la carta con precios**.

La consulta de los productos **no pide la columna `costo`**. No es que
esté escondido: no se pide. No hay forma de que el precio de compra
del fernet llegue al HTML, ni por error ni por descuido.

### Para que aparezca en Google

La página lleva los datos estructurados de tipo `BarOrPub` con el
horario de atención. Eso es lo que Google usa para mostrar "Abierto ·
Cierra 02:00" en los resultados, que es justo lo que la gente busca.

Dos detalles que importan: las URLs tienen que ser **absolutas**
(`https://sitio/index.php?r=publico`) porque Google ignora las
relativas, y el título y la descripción cambian según esté abierto o
cerrado.

### Editar el horario

Lo edita el administrador, desde **Horarios**. Botones grandes, la hora
de apertura y la de cierre para cada día, y una casilla para "cierra al
día siguiente".

Si un horario tiene un error, **no se guarda ninguno**: o se guarda la
semana entera o no se guarda nada. Si se guardara a medias, el dueño
corrige el día marcado y cree que los otros sí quedaron. El mensaje lo
dice con todas las letras.

---

## Instalación rápida en tu máquina

Necesitás PHP 8 con `pdo_mysql` y un MySQL o MariaDB.

```bash
# 1. Configurar la base
copy config\config.example.php config\config.php
#    (Linux/Mac: cp config/config.example.php config/config.php)
#    Después editá DB_HOST, DB_NAME, DB_USER y DB_PASS en config/config.php

# 2. Levantar el servidor embebido de PHP
php -S localhost:8000

# 3. Abrir el instalador
#    http://localhost:8000/install.php

# 4. Entrar
#    http://localhost:8000/login.php
```

`install.php` crea las tablas, carga la configuración inicial y crea
los usuarios de arranque. Después **borralo** (salvo que estés en tu
PC, donde no importa).

Para desplegar en un hosting real, seguí [DEPLOYMENT.md](DEPLOYMENT.md).

---

## Estructura

```
AtilioBar/
├── index.php                 Controlador frontal: index.php?r=ruta
├── login.php                 Inicio de sesión
├── install.php               Instalador (borralo después de usarlo)
├── DEPLOYMENT.md             Guía de despliegue en ByetHost
│
├── config/
│   ├── config.example.php    Plantilla versionada (sin secretos)
│   └── config.php            Tus datos de MySQL (NO se versiona)
│
├── includes/                 Núcleo. Bloqueado por .htaccess
│   ├── bootstrap.php         Arranque: config, errores, sesión
│   ├── db.php                PDO y consultas preparadas
│   ├── auth.php              Login, roles, bloqueo de intentos
│   ├── csrf.php              Protección CSRF
│   ├── helpers.php           Formato de dinero, fechas, validación
│   ├── stock.php             Servicio de stock (Etapa 2)
│   ├── mesas.php             Mesas, comandas y reservas (Etapa 3)
│   ├── ventas.php            Ventas, cobros y caja (Etapa 4)
│   ├── descuentos.php        Descuento pendiente mientras se cobra
│   ├── schema.php            Detecta si falta importar una migración
│   ├── reportes.php          Los cuatro reportes y el CSV
│   ├── horarios.php          Horario, días cerrados y datos para Google
│   ├── auditoria.php         Registro de acciones
│   ├── router.php            Mapa de rutas y control de acceso
│   ├── header.php / footer.php    Layout base
│   └── vistas/               Pantallas de error (403, 404, 500)
│
├── modules/
│   ├── dashboard/            Inicio según el rol
│   ├── usuarios/             ABM de usuarios (solo admin)
│   ├── productos/            Carta, categorías (Etapa 2)
│   ├── stock/                Historial, ajustes y compras (Etapa 2)
│   ├── mesas/                Grilla, pedidos, reservas (Etapa 3)
│   ├── cobrar/               Pantalla de cobro (Etapa 4)
│   ├── caja/                 Turnos y conteo de dinero (Etapa 4)
│   ├── ventas/               Historial de ventas (Etapa 4)
│   ├── reportes/             Reportes y descarga CSV (Etapa 5)
│   ├── horarios/             Editar el horario y cerrar días (Etapa 6)
│   ├── publico/              La página sin sesión (Etapa 6)
│   └── auth/salir.php        Cierre de sesión
│
├── assets/
│   ├── css/theme.css         Paleta y estilos (todo el diseño)
│   ├── js/app.js             Validación en el navegador
│   └── img/logo.svg          Logo (también es el favicon)
│
├── sql/
│   ├── database.sql          Esquema de la Etapa 1
│   ├── etapa2.sql            Migración de la Etapa 2
│   ├── etapa3.sql            Migración de la Etapa 3
│   ├── etapa4.sql            Migración de la Etapa 4
│   ├── etapa5.sql            Migración de la Etapa 5
│   ├── etapa6.sql            Migración de la Etapa 6
│   └── etapa7.sql            Saca la zona vereda (solo bases viejas)
└── logs/                     Errores de PHP (no se versiona)
```

---

## Roles

Solo dos niveles.

**Administrador:** acceso total. Gestiona usuarios, productos,
precios, stock, compras, anula ventas y cierra caja.

**Vendedor:** abre y opera mesas, carga pedidos, cobra y consulta el
stock y los precios. No gestiona usuarios, no toca precios ni stock,
no ve costos ni ganancias, no anula ventas.

El control de rol está en el servidor, en el mapa de rutas de
`includes/router.php`. Ocultar un botón de la interfaz es solo
cosmético: si un vendedor escribe a mano la URL de una pantalla de
administrador, el servidor le responde 403.

---

## Productos y stock (Etapa 2)

Hay dos tipos de producto, y se distinguen con `controla_stock`:

- **Con control de stock:** se lleva cuenta y se descuenta al vender.
  Cerveza, gaseosas, agua, insumos.
- **Preparación:** se arma en el momento y no se descuenta nada.
  Tragos, pizza, empanadas.

Un combo (como un gin tonic que descuenta tónica y gin) es una
preparación con receta: al venderlo resta del stock de los insumos.

### Formas de venta: de a una, por media docena, por docena

Además del precio, un producto puede declarar **en qué cantidades se
vende**. Se escribe en el campo "Formas de venta":

```
1:Unidad,6:Media docena,12:Docena
```

En la pantalla del pedido aparece un botón por forma, con el importe
ya calculado:

```
[Unidad]      [Media docena]   [Docena]
 $ 900,00      $ 5.400,00      $ 10.800,00
```

El precio que se carga es el de **una** unidad y el sistema multiplica.
Un producto sin formas de venta sigue usando el control de `+`/`-`
de siempre, así que nada se rompe para el resto de la carta.
preparación con receta: al venderlo resta del stock de los insumos.

### El stock se cambia por tres caminos, nunca a mano

1. **Compras.** Entró mercadería comprada. Suma stock y actualiza el
   costo con promedio ponderado.
2. **Ajuste.** El conteo físico no da. Corrige el número, con
   motivo obligatorio. No toca el costo.
3. **Venta.** Se descuenta al cargar el producto en la mesa (Etapa 3)
   y, si se anula la línea, se repone. El cobro no toca el stock:
   ya salió del depósito cuando lo serviste.

Todo cambio pasa por `Stock::aplicar()`, que a la vez actualiza el
producto y escribe en `stock_movimientos`. Por eso el stock actual
siempre se puede reconstruir sumando el historial: no hay forma de
que un número cambie sin quedar asentado.

### Sobre los precios y los importes

Los importes se tipean en formato argentino y el sistema los
interpreta bien:

| Se escribe | Se guarda |
|---|---|
| `1.800` | 1800 |
| `1.80` | 1.80 |
| `1.234,56` | 1234.56 |

`1.800` son mil ochocientos y `1.80` es un peso con ochenta
centavos: la diferencia la hace la cantidad de dígitos después del
punto.

---

## Mesas y pedidos (Etapa 3)

La grilla de mesas es la pantalla principal. Cada mesa es un botón
grande, con el número enorme y el estado escrito al lado del color:
libre, ocupada, por cobrar o reservada. Nunca hay que adivinar por
el color.

Vienen diez mesas de ejemplo (ocho de adentro y dos de la barra), pero
el número se cambia desde **Administrar mesas**: de a una, o en tanda
con "Generar mesas en tanda" poniendo el rango (de la 11 a la 20). Las
mesas que ya existen no se pisan.

La grilla tiene dos zonas y solo dos: **Mesas** y **Barras**. Los
nombres salen de `Mesas::ZONAS`, en el código, y no de la base: por
eso no hay claves de configuración para cambiarlos, porque cambiarlas
desde phpMyAdmin no hacía nada.

El generador pide el rango y no la cantidad a propósito: las mesas
de un bar tienen números que el mozo reconoce de memoria. Si el
sistema inventara los números, el mozo terminaría diciendo "mesa
tres" yendo a otra.

### El estado de la mesa se calcula, no se guarda

No hay una tabla de estados. El estado sale de las comandas abiertas
y de las reservas del día. Si hubiera una tabla aparte, cualquier
camino que se olvide de actualizarla dejaría la pantalla mintiendo.
Calculándolo no hay forma de que se desincronice.

Si una mesa está ocupada y además tiene reserva más tarde, se
muestra **ocupada**: la reserva aparece recién cuando la mesa se
libera.

### El pedido se congela cuando piden la cuenta

`abierta` → `por_cobrar` → (Etapa 4) `cerrada`.

Al pedir la cuenta el pedido queda congelado: no se le pueden agregar
productos ni cambiar cantidades. Si igual falta algo, el botón
**Reabrir** lo vuelve a abrir y queda asentado quién y por qué.

### El stock sale cuando se carga, no cuando se cobra

Cada línea guarda, producto por producto, cuánto del depósito
consumió por unidad (tabla `comanda_item_stock`). Eso permite que:

- anular la línea devuelva **exactamente** lo que salió, incluso si
  el mozo cambió la cantidad a 5 y después a 2;
- un combo devuelva el gin y la tónica por separado;
- si mañana cambian la receta del combo, la reposición siga
  devolviendo lo que realmente se sacó ese día.

Anular una línea **no la borra**: queda marcada como anulada con su
motivo. Por eso se puede ver después qué se pidió, qué se sacó y
quién lo hizo.

Una mesa con historial no se borra nunca, se da de baja: así los
informes viejos siguen cuadrando.

---

## Seguridad

- Consultas preparadas en todo el código, con
  `ATTR_EMULATE_PREPARES => false`.
- `htmlspecialchars()` en toda salida hacia el navegador.
- Token CSRF en todos los formularios y acciones POST.
- Contraseñas con `password_hash` / `password_verify`, nunca en texto
  plano.
- Sesiones con cookie `HttpOnly` y `SameSite`, y el ID se regenera al
  iniciar sesión y al cambiar la contraseña.
- Límite de intentos fallidos por usuario y por IP, guardado en la
  base y evaluado en el momento del intento (sin cron).
- `display_errors` apagado en producción: los errores van a
  `logs/app.log` y el usuario ve una pantalla genérica.
- `.htaccess` bloqueando `/config`, `/includes`, `/modules`, `/sql` y
  `/logs`, y el listado de directorios.

`config/config.php` está fuera del repositorio porque tiene la
contraseña de la base. Se crea copiando `config/config.example.php`.

---

## Rutas

No se usa `.htaccess` para reescribir URLs, porque en ByetHost
`mod_rewrite` no siempre está activo. El ruteo va en un parámetro:

```
index.php?r=dashboard
index.php?r=usuarios
index.php?r=usuarios/formulario&id=3
```

La ruta base se detecta sola, así que la app funciona igual en la raíz
de `htdocs` que dentro de una subcarpeta.

---

## Cosas del entorno que conviene conocer

- **Zona horaria:** `America/Argentina/Buenos_Aires`.
- **Moneda:** pesos argentinos, formato `$ 1.234,50`.
- **Textos:** español rioplatense.
- **Colores:** amarillo claro y verde claro, definidos como variables
  CSS en `assets/css/theme.css`. Para cambiar la paleta, se cambian
  esas líneas y nada más.
- **Estados de mesa:** usan color **y** texto **y** ícono, para que se
  entiendan sin depender del color.

---

## Git

```bash
git add .
git commit -m "Descripción de lo que cambiaste"
git push
```

`config/config.php` no se versiona (tiene la contraseña de MySQL).
Si clonás el repo en otra máquina, creá tu propio `config.php` a
partir de `config/config.example.php`.