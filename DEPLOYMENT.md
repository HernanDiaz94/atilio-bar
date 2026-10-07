# Guía de despliegue en ByetHost

Cómo subir el sistema del bar **Atilio** a un hosting gratuito de
ByetHost por FTP, sin SSH y sin Composer.

Tiempo estimado la primera vez: 15 minutos.

---

## Antes de empezar: qué necesitás

| Qué | Dónde sale |
|---|---|
| Host de MySQL | Panel de ByetHost, sección de bases de datos. Es tipo `sqlXXX.byethost.com`, **nunca** `localhost` |
| Nombre de la base | El mismo panel. Lleva prefijo, algo como `b12_12345678_atilio` |
| Usuario de MySQL | El mismo panel. **También lleva prefijo** |
| Contraseña de MySQL | La que elegiste al crear la base |
| Un cliente FTP | FileZilla (gratis) |

> **El error más común** es poner `localhost` como host de MySQL. En
> hosting compartido la base vive en otra máquina. Si la conexión
> falla, revisá eso primero.

---

## Paso 1: Crear la base de datos

1. Entrá al panel de ByetHost.
2. Buscá la sección de **bases de datos MySQL** o **Bases de datos**.
3. Creá una base nueva, por ejemplo `atilio`.
4. Anotá los cuatro datos que te muestra:
   - **Host** (tipo `sql258.byethost.com`)
   - **Nombre** de la base (con prefijo)
   - **Usuario** (con prefijo)
   - **Contraseña**

No hace falta crear tablas a mano. Eso lo hace el instalador.

---

## Paso 2: Subir los archivos por FTP

1. Abrí FileZilla.
2. Conectate por FTP al servidor de ByetHost.
3. Entrá a la carpeta `htdocs`.
4. Copiá **el contenido** de tu carpeta `AtilioBar` dentro de `htdocs`.

Opciones sobre dónde dejarlo:

- `htdocs/` → la app queda en `www.tudominio.com/atilio/` si subís la
  carpeta, o en la raíz del hosting.
- `htdocs/atilio/` → la app queda en `www.tudominio.com/atilio/`.

Las dos formas funcionan. La app detecta sola en cuál está y arma los
enlaces. Lo único que cambia es la dirección web.

> **Importante:** la carpeta `logs/` tiene que quedar con permiso de
> escritura. Es donde PHP guarda los errores. Si el hosting no
> permite escribir por FTP, creala vacía desde el panel de archivos.

---

## Paso 3: Crear el archivo de configuración

El archivo `config/config.php` **no está en el repositorio**: tiene la
contraseña de MySQL y no se versiona a propósito.

En tu máquina local, copiá la plantilla:

**Windows (cmd o PowerShell):**
```
copy config\config.example.php config\config.php
```

**Linux o Mac:**
```
cp config/config.example.php config/config.php
```

Después abrí `config/config.php` y completá estos cuatro valores:

```php
define('DB_HOST', 'sqlXXX.byethost.com');   // NO localhost
define('DB_PORT', 3306);
define('DB_NAME', 'b12_12345678_atilio');    // con prefijo
define('DB_USER', 'b12_12345678_atilio');    // con prefijo
define('DB_PASS', 'tu-contrasena-de-mysql');
```

Fijate que el host **no tenga barra al final** ni espacio.

También revisá estos dos, que afectan la seguridad:

```php
define('APP_ENV', 'produccion');
define('APP_DEBUG', false);     // tiene que ser false en el hosting
```

Subí el `config/config.php` ya editado por FTP, dentro de `config/`.

---

## Paso 4: Ejecutar el instalador

Abrí en el navegador:

```
http://www.tudominio.com/atilio/install.php
```

Si la app está en la raíz del hosting, sin subcarpeta:

```
http://www.tudominio.com/install.php
```

El instalador va por tres pasos:

**Paso 1 — Conexión.** Prueba los datos de MySQL. Si todo está bien,
decí "Continuar". Si falla, muestra una pista de qué revisar
(normalmente el host o la base).

**Paso 2 — Tablas.** Crea las 4 tablas y carga la configuración
inicial del bar.

**Paso 3 — Usuarios.** Creá los usuarios iniciales. El instalador
propone 2 administradores y 2 vendedores. Cambiá las contraseñas
antes de instalar: se muestran **una sola vez** en pantalla.

Todos los usuarios quedan marcados para cambiar su contraseña la
primera vez que entran. El sistema los obliga a hacerlo.

---

## Paso 5: Borrar el instalador

**Este paso no te lo saltes.**

Borrá `install.php` del servidor por FTP.

Mientras exista, cualquiera que abra esa dirección puede ver el
formulario de instalación. Y si alguien logra vaciar la tabla de
usuarios (por ejemplo desde phpMyAdmin), el instalador vuelve a
permitir crear cuentas nuevas.

Si lo borrás por error y lo necesitás de nuevo, está en tu máquina:
`AtilioBar/install.php`.

---

## Paso 6: Entrar

```
http://www.tudominio.com/atilio/login.php
```

1. Entrá con el usuario administrador que creaste.
2. Te pide cambiar la contraseña: la que pusiste en el instalador es
   temporal. Poné una nueva.
3. Listo. Ya podés crear al resto del personal desde **Usuarios**.

---

## Comprobación final

Después de los pasos anteriores, esto tiene que estar bien:

- [ ] Entrás al login y llegás al inicio sin errores.
- [ ] El logo se ve en la barra superior y en el login.
- [ ] La app se ve en amarillo y verde, y es usable en el celular.
- [ ] En **Usuarios** podés dar de alta a alguien.
- [ ] Con un usuario vendedor, el menú **Usuarios** no aparece, y si
      escribís a mano `index.php?r=usuarios` te dice "acceso denegado".
- [ ] `config/config.php` no se puede abrir desde el navegador
      (tiene que dar error 403 oForbidden).
- [ ] `/modules/` no muestra la lista de archivos.

Si algo de esto falla, mirá `logs/app.log` por FTP: ahí queda escrito
el detalle de cada error.

---

## Alternativa: importar el SQL desde phpMyAdmin

Si preferís que las tablas las cree phpMyAdmin en vez del instalador:

1. Entrá a **phpMyAdmin** desde el panel de ByetHost.
2. Elegí la base `b12_12345678_atilio`.
3. Pestaña **Importar** → **Elegir archivo** → `sql/database.sql`.
4. **Ejecutar**.

`sql/database.sql` crea exactamente las mismas tablas que el
instalador. Después abrí igual `install.php` y hacé solo el **paso 3**
(crear los usuarios), porque el archivo SQL no incluye usuarios a
propósito.

---

## Problemas frecuentes

### "No se pudo conectar con la base de datos"

Casi siempre es el host. Revisá:

- ¿Pusiste `localhost`? Tiene que ser `sqlXXX.byethost.com`.
- ¿La base y el usuario llevan el prefijo? (`b12_12345678_atilio`)
- ¿La contraseña está completa y sin espacios al principio o al final?

### "Falta terminar la instalación"

Significa que no hay ningún usuario en la base. Pasá por
`install.php`.

### Los estilos no cargan, todo se ve sin colores

Faltan archivos de `assets/css/` o `assets/js/` en el servidor. Volvé
a subir la carpeta `assets` completa. Si sigue igual, revisá que
`APP_BASE_URL` se haya detectado bien: si instalaste en la raíz pero
estás entrando por subcarpeta (o al revés), las rutas se rompen.

### "La aplicación ya está instalada"

Correcto, ya corriste el instalador. Si querés volver a correrlo,
borrá los usuarios de la tabla `usuarios` desde phpMyAdmin. Pero
normalmente no hace falta: entrá por `login.php`.

### Me bloqueó la cuenta a mí mismo

Pasaron los 5 intentos de login. Esperá 15 minutos, o desde phpMyAdmin
ponele `bloqueado_hasta` en `NULL` y `intentos_fallidos` en `0`.

### Se me olvidó la contraseña de administrador

Desde phpMyAdmin, en la tabla `usuarios`, poné `activo` en `1`,
`intentos_fallidos` en `0` y `bloqueado_hasta` en `NULL`. Para
cambiarla de verdad necesitás un hash nuevo: generalo con
`password_hash('tu-clave-nueva', PASSWORD_DEFAULT)` desde PHP y
copialo en `password_hash`.

---

## Sobre las etapas siguientes

Cada etapa trae su propio archivo SQL en `sql/` (por ejemplo
`sql/etapa2.sql`). Cuando agregues una etapa:

1. Subí los archivos nuevos o modificados por FTP.
2. Importá el `.sql` de esa etapa desde phpMyAdmin.
3. Entrá al panel para probarla.

Los archivos SQL son separados a propósito: así los aplicás sobre una
base que ya tiene datos, sin romper lo que ya está cargado.

Son seguros de correr más de una vez: los `CREATE TABLE` usan
`IF NOT EXISTS` y los datos iniciales (categorías, productos de la
carta) se insertan solo si no existen. Si los importás dos veces no
se duplica nada.

### Qué trae la Etapa 2

Al importarla aparecen la carta del bar con productos de ejemplo
(bebidas, tragos, pizza y empanadas) y cuatro categorías: Bebidas,
Tragos, Comidas e Insumos.

Los **precios** son de ejemplo: cambialos desde la pantalla de
Productos. Los **costos** arrancan vacíos a propósito, porque son
datos del negocio: se completan solos a medida que cargues compras.

Para cambiar los datos de ejemplo antes de importar, editá los
`INSERT` de `sql/etapa2.sql`. Si preferís empezar de cero, borrá las
tablas `productos` y `categorias` desde phpMyAdmin después de
importar, y armá la carta a mano.

### Qué trae la Etapa 3

Al importarla aparecen las tablas del bar: 8 del salón, 4 de la
vereda y 3 de la barra. Y aparece la pantalla **Mesas**, que ya es
la principal del sistema.

Las mesas de ejemplo se ajustan desde **Administrar mesas**
(disponible solo para el administrador). Si querés empezar con las
tuyas, borrá la tabla `mesas` desde phpMyAdmin después de importar
y cargalas a mano.

Lo que podés hacer con esta etapa:

- Ver la grilla de mesas con el estado al día (libre, ocupada, por
  cobrar, reservada), por color, texto e ícono.
- Abrir una mesa, cargar productos con botones grandes y ajustar la
  cantidad.
- Mover un pedido a otra mesa sin perderlo.
- Pedir la cuenta (congela el pedido) y reabrirlo si hace falta.
- Tomar reservas, con nombre y teléfono.

**El stock se descuenta al cargar el producto**, no al cobrar: si se
anula la línea, vuelve al depósito. Si vendés algo que no tenés, el
número queda en negativo a propósito, para que al contar el
depósito se note el faltante.