# Sistema de bar "Atilio"

Aplicación web para un bar: mesas, pedidos, ventas, cobro y caja.

PHP 8 puro, MySQL/MariaDB, Bootstrap 5 por CDN. **Sin frameworks, sin
Composer, sin SSH**: se sube por FTP a un hosting compartido.

---

## Estado del desarrollo

| Etapa | Contenido | Estado |
|---|---|---|
| 1 | Base, login, usuarios y configuración | Completada |
| 2 | Productos, categorías y stock | Pendiente |
| 3 | Mesas y pedidos | Pendiente |
| 4 | Ventas, cobro y caja | Pendiente |
| 5 | Reportes y exportación a CSV | Pendiente |
| 6 | Página pública y horarios | Pendiente |

Las etapas 5 y 6 están diseñadas en el modelo de datos pero todavía
no se desarrollan. Sus cambios de base de datos van en archivos SQL
separados (`sql/etapa5.sql`), para poder aplicarlos sobre una base
que ya tiene datos.

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
│   ├── auditoria.php         Registro de acciones
│   ├── router.php            Mapa de rutas y control de acceso
│   ├── header.php / footer.php    Layout base
│   └── vistas/               Pantallas de error (403, 404, 500)
│
├── modules/
│   ├── dashboard/            Inicio según el rol
│   ├── usuarios/             ABM de usuarios (solo admin)
│   └── auth/salir.php        Cierre de sesión
│
├── assets/
│   ├── css/theme.css         Paleta y estilos (todo el diseño)
│   ├── js/app.js             Validación en el navegador
│   └── img/logo.svg          Logo (también es el favicon)
│
├── sql/database.sql          Esquema para importar en phpMyAdmin
└── logs/                     Errores de PHP (no se versiona)
```

---

## Roles

Solo dos niveles.

**Administrador:** acceso total. Gestiona usuarios, precios, stock,
anula ventas y cierra caja.

**Vendedor:** abre y opera mesas, carga pedidos, cobra y consulta el
stock en modo lectura. No gestiona usuarios, no toca precios ni stock,
no ve costos ni ganancias, no anula ventas.

El control de rol está en el servidor, en el mapa de rutas de
`includes/router.php`. Ocultar un botón de la interfaz es solo
cosmético: si un vendedor escribe a mano la URL de una pantalla de
administrador, el servidor le responde 403.

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