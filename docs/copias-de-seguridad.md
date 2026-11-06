# Copias de seguridad

Panel: **Herramientas → Copias de seguridad** (solo superadmin).

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

- **En el servidor**, en `storage/app/backups`. Esa carpeta no es accesible desde Internet.
- **Y fuera del servidor**, en OVH (`BACKUP_OFFSITE=true`, ADR 0028). Cada copia se sube además al **Object Storage de OVH**: contenedor `BACKUP_OFFSITE_BUCKET`, carpeta `copias/`, con las credenciales `AWS_*`. Así, si el servidor se pierde, las copias siguen a salvo.
  - En la tabla, la columna **Fuera del servidor** dice si cada copia se subió.
  - Si una subida falla, la copia del servidor sigue siendo buena. Se avisa por email y se reintenta con **Subir fuera**.
  - **Probar Object Storage** escribe, lee y borra un archivo de prueba para comprobar las credenciales.
  - La revisión diaria también avisa si la última copia no está fuera.
  - Al borrar una copia (a mano o por antigüedad) se borra en los dos sitios.
  - **Descargar** baja la del servidor o, si ya no está, la de fuera.
- **En Hostinger** (sin Object Storage): descargad una copia de vez en cuando (botón **Descargar**) y guardadla fuera, en vuestro ordenador o en una nube.
- Guardad también **el `.env`** (sobre todo la `APP_KEY`) en un gestor de contraseñas. Sin la `APP_KEY` no se pueden leer la clave del TPV ni la verificación en dos pasos de una copia.

## Cómo restaurar

Restaurar sustituye los datos actuales por los de la copia, así que hacedlo con calma:

1. Si el sitio sigue funcionando, haced antes una copia del estado actual.
2. Activad el modo mantenimiento (*Herramientas → Estado del sistema*).

### Hostinger

1. Descomprimid la copia en vuestro ordenador.
2. En **hPanel → Bases de datos → phpMyAdmin**, abrid la base de datos de la tienda y usad **Importar** con `base-de-datos.sql`. El archivo borra y vuelve a crear cada tabla.
3. En el **Administrador de archivos**, subid el contenido de `archivos/public/` a `storage/app/public/` y el de `archivos/private/` a `storage/app/private/`.
4. En *Sistema*, pulsad **Aplicar actualización** y desactivad el mantenimiento.

### Docker / OVH

En el VPS, dentro de la carpeta `clavedealma`, con la copia descargada del panel o del Object Storage:

```bash
unzip copia-AAAA-MM-DD-HHMMSS.zip -d restaurar
docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < restaurar/base-de-datos.sql
docker compose cp restaurar/archivos/public/. app:/var/www/html/storage/app/public/
docker compose cp restaurar/archivos/private/. app:/var/www/html/storage/app/private/
docker compose exec app php artisan optimize
```

### Desde la consola, sin el panel

```bash
php artisan copias:crear      # hace una copia ahora
php artisan copias:revisar    # comprueba que la última copia no sea antigua
```

## Comprobado

Las pruebas automáticas hacen una copia, la restauran en una base de datos vacía y comprueban que los datos vuelven. Aun así, **probad una restauración de verdad** en el servidor de pruebas antes del lanzamiento.
