# Copias de seguridad

Panel: **Administración → Copias de seguridad** (solo superadmin).

## Qué se guarda

Cada copia es un ZIP con fecha (`copia-2026-11-05-031500.zip`) que contiene:

| Dentro del ZIP | Qué es |
| --- | --- |
| `base-de-datos.sql` | La base de datos completa: tiendas, artistas, artículos, precios, stock, usuarios, envíos… |
| `archivos/public/` | Fotos de productos y demás archivos públicos. Van en `storage/app/public/`. |
| `archivos/private/` | Archivos privados, como los diseños para producción. Van en `storage/app/private/`. |
| `LEEME.txt` | Resumen de la copia. |

**No se guardan:**
- el código, que está en GitHub;
- el `.env`, que lleva las contraseñas. Guardadlo aparte, en un sitio seguro, al instalar el servidor.

## Programación

En **Programación** se elige:
- copias diarias o semanales;
- el día y la hora (hora de Madrid). Por defecto es cada día a las 03:15;
- cuántas copias se conservan (por defecto 14). Las más antiguas se borran solas;
- a quién avisar por email.

Para que se hagan solas, el servidor tiene que ejecutar el programador de Laravel:

- **Hostinger:** el cron de cada minuto de la guía de instalación (`php artisan schedule:run`).
- **Docker/OVH:** el contenedor `scheduler`, que ya está en `docker-compose.yml`.

**Avisos por email** al superadmin (o al email elegido en Programación):
- si una copia falla;
- si la última copia buena es demasiado antigua: más de 36 horas con copias diarias, o más de 8 días con semanales. Suele significar que el cron ha dejado de funcionar. La revisión se hace cada día a las 09:10.

El botón **Hacer copia ahora** hace una copia al momento. Conviene usarlo antes de una actualización importante.

## Dónde se guardan

- Por defecto, en el propio servidor, en `storage/app/backups`. Esa carpeta no es accesible desde Internet.
- Una copia que solo está en el servidor no sirve si el servidor se pierde. Por eso:
  - **En Hostinger:** descargad una copia de vez en cuando (botón **Descargar**) y guardadla fuera, en vuestro ordenador o en una nube.
  - **En OVH:** con `BACKUP_DISK=s3` en el `.env` y las credenciales `AWS_*` del Object Storage, las copias se guardan fuera del servidor automáticamente.

## Cómo restaurar

Restaurar sustituye los datos actuales por los de la copia, así que hacedlo con calma:

1. Si el sitio sigue funcionando, haced antes una copia del estado actual.
2. Activad el modo mantenimiento (*Administración → Sistema*).

### Hostinger

1. Descomprimid la copia en vuestro ordenador.
2. En **hPanel → Bases de datos → phpMyAdmin**, abrid la base de datos de la tienda y usad **Importar** con `base-de-datos.sql`. El archivo borra y vuelve a crear cada tabla.
3. En el **Administrador de archivos**, subid el contenido de `archivos/public/` a `storage/app/public/` y el de `archivos/private/` a `storage/app/private/`.
4. En *Sistema*, pulsad **Aplicar actualización** y desactivad el mantenimiento.

### Docker / OVH

```bash
unzip copia-AAAA-MM-DD-HHMMSS.zip -d restaurar
docker compose exec -T mariadb mariadb -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < restaurar/base-de-datos.sql
docker compose cp restaurar/archivos/public/. app:/var/www/html/storage/app/public/
docker compose cp restaurar/archivos/private/. app:/var/www/html/storage/app/private/
docker compose exec app php artisan optimize:clear
```

### Desde la consola, sin el panel

```bash
php artisan copias:crear      # hace una copia ahora
php artisan copias:revisar    # comprueba que la última copia no sea antigua
```

## Comprobado

Las pruebas automáticas hacen una copia, la restauran en una base de datos vacía y comprueban que los datos vuelven. Aun así, **probad una restauración de verdad** en el servidor de pruebas antes del lanzamiento.
