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
| 3 | Mesas y pedidos | Pendiente |
| 4 | Ventas, cobro y caja | Pendiente |
| 5 | Reportes y exportación a CSV | Pendiente |
| 6 | Página pública y horarios | Pendiente |

Las etapas 5 y 6 están diseñadas en el modelo de datos pero todavía
no se desarrollan. Sus cambios de base de datos van en archivos SQL
separados (`sql/etapa5.sql`), para poder aplicarlos sobre una base
que ya tiene datos.

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

Cada migración es segura de correr más de una vez: los `CREATE TABLE`
usan `IF NOT EXISTS` y los datos iniciales se insertan solo si no
existen.

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
│   └── etapa3.sql            Migración de la Etapa 3
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

Vienen diez mesas de ejemplo (cinco del salón, tres de la vereda y
dos de la barra), pero el número se cambia desde **Administrar
mesas**: de a una, o en tanda con "Generar mesas en tanda" poniendo
el rango (de la 11 a la 20). Las mesas que ya existen no se pisan.

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