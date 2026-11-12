# Instalación en el servidor: PHP 8.3 + MariaDB (VPS de OVH)

Guía para el servidor de OVH con estos contenedores de Docker: `php-web` (PHP 8.3), `mariadb` (MariaDB 10.11), `redis`, `npm` (Nginx Proxy Manager, que pone el HTTPS), `phpmyadmin`, `filebrowser` y `db-backup`.

La web se instala con un **instalador web**, como WordPress. Se sube el paquete, se abre la web en el navegador, y el asistente pide la base de datos y crea el administrador. **No hay que editar el `.env` a mano.**

> Las contraseñas solo se escriben en el instalador, en el panel o en el servidor. **Nunca en GitHub ni en el chat.**

## 1. Crear la base de datos (phpMyAdmin)

1. Entra en phpMyAdmin con el usuario `root` de MariaDB.
2. *Bases de datos* → crea `clavedealma` con el cotejamiento `utf8mb4_unicode_ci`.
3. *Cuentas de usuarios* → *Agregar cuenta de usuario*:
   - Nombre: por ejemplo `clavedealma`.
   - Servidor: `%` (cualquier servidor).
   - Contraseña: una larga (o pulsa «Generar»). Apúntala para el paso 5.
   - En *Base de datos para la cuenta*, marca «Otorgar todos los privilegios en la base de datos clavedealma».

## 2. Descargar el paquete

El paquete es un ZIP con todo listo:
- las librerías de PHP (`vendor/`);
- los estilos de la tienda;
- los archivos del panel.

Lo genera GitHub solo cada vez que se fusiona un cambio.

1. En GitHub: *Sonovoz/Clavedealma → Actions → «Paquete para el servidor»*.
2. Abre la última ejecución en verde y, abajo, en *Artifacts*, descarga **clavedealma-servidor**.
3. Descomprime lo que bajas: dentro está `clavedealma-<versión>-<fecha>.zip`, el que se sube al servidor.

También se puede generar a mano en un ordenador con PHP, Composer y Node: `bash scripts/build-zip.sh`.

## 3. Subir la web a `php-web`

1. Con **filebrowser**, entra en la carpeta que sirve el contenedor `php-web` (normalmente `/var/www/html`).
2. Sube el ZIP y descomprímelo ahí.
   - Si filebrowser no lo descomprime, hazlo desde el VPS:

```bash
docker exec php-web sh -c 'cd /var/www/html && unzip -o clavedealma-*.zip && rm clavedealma-*.zip'
```

3. Permisos: el servidor web tiene que poder escribir en la carpeta (para crear el `.env`), en `storage/` y en `bootstrap/cache/`:

```bash
docker exec php-web sh -c 'chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache'
```

**La carpeta pública es `public/`.** Lo mejor es que el `php-web` sirva directamente `/var/www/html/public`. Si no se puede cambiar y sirve `/var/www/html` (Apache), renombra el archivo `.htaccess.hostinger` de la raíz a `.htaccess`. Ese archivo hace dos cosas:
- lleva todo a `public/`;
- bloquea el `.env` y el código.

## 4. HTTPS con Nginx Proxy Manager

En Nginx Proxy Manager → *Proxy Hosts* → *Add Proxy Host*:

- *Domain Names*: `clavedealma.es` y `www.clavedealma.es`.
- *Forward Hostname / IP*: `php-web`. *Forward Port*: `80`.
- Pestaña *SSL*:
  - *Request a new SSL Certificate* (Let's Encrypt);
  - activa *Force SSL* y *HTTP/2*.

La web ya viene preparada para ir detrás de este proxy (`TRUSTED_PROXIES=*`): sabe que va por HTTPS y ve la IP real de cada visitante.

## 5. El instalador web

Abre `https://clavedealma.es`. La primera vez lleva sola a **`/instalar`**:

1. **Servidor**: comprueba PHP 8.3, sus extensiones, los permisos y que el paquete esté completo. Lo que falte sale con una ✗ y la explicación.
2. **Base de datos**:
   - **Servidor `mariadb`**: el nombre del contenedor, que tiene que estar en la misma red de Docker que `php-web`.
   - Puerto `3306`.
   - El nombre, el usuario y la contraseña del paso 1.
   - Pulsa «Probar y guardar»: si algo no va, dice exactamente qué (usuario, contraseña, base de datos o servidor).
   - Si encuentra el contenedor `redis`, lo usa para la caché y las sesiones.
3. **Administrador**:
   - la dirección de la web (`https://clavedealma.es`);
   - tu nombre, tu email y una contraseña de 12 caracteres o más.
   - Al pulsar **Instalar** crea las tablas, los roles y tu cuenta.
4. **Listo**: te da la línea del cron y una dirección secreta para las tareas programadas (paso 6). **Cópialas**: la página solo se ve una vez.

Después, el instalador desaparece (da 404). Aunque la base de datos falle un día, nadie puede volver a configurarla desde la web.

## 6. Tareas programadas (imprescindible)

Las copias de seguridad, el resumen de Minimedia del día 1 y la anulación de pedidos sin pagar necesitan que algo despierte a la web **cada minuto**. Elige una de las dos formas.

**A) Cron del VPS** (la más fiable). En el VPS, abre el cron:

```bash
crontab -e
```

Añade esta línea. Si la carpeta o el contenedor se llaman de otra forma, cámbialos:

```text
* * * * * docker exec php-web php /var/www/html/artisan schedule:run >> /dev/null 2>&1
```

**B) Una dirección secreta**, si no podéis tocar el cron. Un servicio externo (por ejemplo cron-job.org) llama cada minuto a `https://clavedealma.es/cron/<CRON_TOKEN>`. La dirección completa te la da el instalador. La clave está en el `.env` (`CRON_TOKEN`) y, sin ella, la dirección da 404.

Para comprobarlo, en el panel ve a *Herramientas → Estado del sistema → Tareas programadas*. Debe decir «Funcionan: última vez…».

## 7. Primer acceso al panel

1. Entra en `https://clavedealma.es/admin` con tu email y tu contraseña.
2. Configura la **verificación en dos pasos** con una app del móvil y guarda los códigos de recuperación.
3. Rellena lo básico:
   - *Ajustes → Correo* (SMTP) y «Enviar email de prueba»;
   - *Ajustes → Pagos* (Redsys);
   - *Ajustes → Datos de facturación*;
   - *Ajustes → Zonas de envío*.
4. Para la tienda de ejemplo de Farruquito, en el VPS:

```bash
docker exec php-web php /var/www/html/artisan db:seed --class=FarruquitoSeeder --force
```

## 8. Actualizar

1. Descarga el paquete nuevo (paso 2).
2. Súbelo y descomprímelo encima (paso 3). **No borres** el `.env` ni la carpeta `storage/`.
3. En el panel, *Herramientas → Estado del sistema → Aplicar actualización*. Pone al día la base de datos, las cachés y los archivos del panel.

## Si ya lo habíais instalado antes y no deja entrar

Si se instaló a mano antes de existir el instalador, lo más rápido es empezar de cero (todavía no hay datos reales):

1. En phpMyAdmin, vacía la base de datos `clavedealma`: selecciona todas las tablas → *Eliminar*.
2. En filebrowser, borra o renombra el `.env` de la carpeta de la web, y borra el archivo `storage/app/instalado` si existe.
3. Sube el paquete nuevo (pasos 2 y 3) y abre la web: saldrá el instalador.

## Problemas frecuentes

- **«No se encuentra el servidor mariadb»**: `php-web` y `mariadb` no están en la misma red de Docker. Para ver en qué red está cada uno:

```bash
docker inspect -f '{{json .NetworkSettings.Networks}}' php-web
```
```bash
docker inspect -f '{{json .NetworkSettings.Networks}}' mariadb
```

Si están en redes distintas, conéctalos (cambia `nombre-de-la-red` por la de `mariadb`):

```bash
docker network connect nombre-de-la-red php-web
```

- **«Faltan las dependencias»**: se ha subido el código de GitHub en lugar del paquete. Sube el ZIP del paso 2.
- **«No se puede crear la configuración»**: faltan permisos de escritura (paso 3).
- **Falta una extensión de PHP** en el paso 1 del instalador: hay que añadirla a la imagen de `php-web` (por ejemplo `docker-php-ext-install intl zip gd`).
