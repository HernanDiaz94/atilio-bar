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

## Actualizar a la Etapa 2 (o a las siguientes)

Cada etapa trae su propio archivo SQL. Se importa por phpMyAdmin
sobre la base que ya tiene datos, sin tocar las tablas anteriores.

1. Subí los archivos nuevos o modificados por FTP.
2. phpMyAdmin → tu base → **Importar** → `sql/etapa2.sql` → **Ejecutar**.
3. Entrá al panel y revisá **Productos**.

`etapa2.sql` es seguro de correr más de una vez: los `CREATE TABLE`
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
│   └── auth/salir.php        Cierre de sesión
│
├── assets/
│   ├── css/theme.css         Paleta y estilos (todo el diseño)
│   ├── js/app.js             Validación en el navegador
│   └── img/logo.svg          Logo (también es el favicon)
│
├── sql/
│   ├── database.sql          Esquema de la Etapa 1
│   └── etapa2.sql            Migración de la Etapa 2
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

### El stock se cambia por tres caminos, nunca a mano

1. **Compras.** Entró mercadería comprada. Suma stock y actualiza el
   costo con promedio ponderado.
2. **Ajuste.** El conteo físico no da. Corrige el número, con
   motivo obligatorio. No toca el costo.
3. **Venta.** Se descuenta al cobrar (Etapa 4).

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