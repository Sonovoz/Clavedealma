# Pruebas en Hostinger (subida por ZIP)

En las pruebas no se usa Docker ni Git en el servidor: se sube un ZIP listo para funcionar.

## 1. Generar el ZIP (en el ordenador de desarrollo)

Requisitos: Git, PHP 8.3+, Composer, Node 20+ y `zip`. En Windows, ejecutar desde Git Bash.

```bash
bash scripts/build-zip.sh
```

Resultado: `dist/clavedealma-<versión>-<fecha>.zip`. Incluye `vendor/` y los recursos compilados; no incluye `.env` ni el contenido de `storage/`.

## 2. Primera instalación

1. En el panel de Hostinger, elegir **PHP 8.3** o superior para el dominio de pruebas.
2. Crear una base de datos MySQL/MariaDB y su usuario.
3. Con el administrador de archivos, subir el ZIP a una carpeta **fuera** de `public_html` (por ejemplo `clavedealma/`) y descomprimirlo.
4. Apuntar el dominio o subdominio de pruebas a `clavedealma/public`.
   - Si el plan no lo permite: descomprimir dentro de la carpeta del dominio y renombrar `.htaccess.hostinger` a `.htaccess` en la raíz del proyecto. Redirige todo a `public/` y bloquea `.env` y el código.
5. Copiar `.env.hostinger.example` como `.env` y rellenar: `APP_KEY`, datos de la base de datos, `APP_URL`, `APP_DOMAIN`, `SUPERADMIN_*` y los datos del TPV de Redsys en modo de pruebas (`REDSYS_ENVIRONMENT=test`, ver `docs/compra`).
   - `APP_KEY`: generarla en el ordenador de desarrollo con `php artisan key:generate --show` y pegarla.
6. Dar permisos de escritura a `storage/` y `bootstrap/cache/` (755 en carpetas).
7. Crear un **cron cada minuto**:

   ```text
   cd /home/<usuario>/clavedealma && php artisan schedule:run >> /dev/null 2>&1
   ```

   El programador procesa también las colas (driver `database`). Y hace las **copias de seguridad** a la hora elegida en el panel ([copias de seguridad](copias-de-seguridad.md)): sin este cron no se hacen solas.
8. Crear las tablas y el superadministrador. Lo más fácil es el **instalador web** (ADR 0030): sin `.env`, al abrir la web sale `/instalar`, que pide la base de datos y crea el administrador (ver [instalación en el servidor](instalacion-servidor.md), paso 5). Si se prefiere a mano, con un cron de una sola ejecución:

   ```text
   cd /home/<usuario>/clavedealma && php artisan migrate --force --seed
   ```

   Ejecutarlo una vez desde el panel de cron y borrarlo después.
9. Entrar en `https://<dominio-pruebas>/admin` con el superadministrador.

## 3. Cada actualización

1. Panel → **Sistema** → activar mantenimiento.
2. Descargar una copia de la base de datos desde el panel de Hostinger.
3. Subir el ZIP nuevo y descomprimirlo encima, **sin tocar `.env` ni `storage/`**.
4. Panel → **Sistema** → **Aplicar actualización**: ejecuta migraciones pendientes, regenera cachés y el enlace de `storage`.
5. Comprobar la versión en **Sistema** y desactivar el mantenimiento.

## Limitaciones conocidas

En un hosting compartido normalmente no hay Redis, Meilisearch, procesos permanentes ni subdominios comodín. Las pruebas validan la lógica de negocio; el rendimiento y la infraestructura definitiva se validan en el staging de OVH.
