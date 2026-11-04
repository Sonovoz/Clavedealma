# ADR 0011 · Copias de seguridad programables desde el panel

- **Estado:** aceptada
- **Fecha:** 2026-11-05

## Contexto

Los fundadores quieren programar copias de seguridad del sitio. Tienen que funcionar igual en Hostinger (sin SSH y con funciones como `exec` que pueden estar desactivadas) y en Docker/OVH.

## Decisión

- **Volcado de la base de datos en PHP puro** (`App\Platform\Backup\DatabaseDumper`), sin `mysqldump`. Admite MariaDB/MySQL y SQLite.
  - El `.sql` resultante se importa con phpMyAdmin, con `mariadb` o con `sqlite3`.
- **Una copia es un ZIP** con `base-de-datos.sql`, los archivos de `storage/app/public` y `storage/app/private` (sin las carpetas temporales) y un `LEEME.txt`.
  - No incluye ni el código ni el `.env`.
- **Disco de copias configurable** (`BACKUP_DISK`):
  - `backups`, una carpeta privada del servidor, es el valor por defecto;
  - `s3`, el Object Storage, guarda las copias fuera del servidor en OVH.
- **Programación guardada en la tabla `settings`** y editable en el panel: activas o no, diaria o semanal, día, hora de Madrid, copias que se conservan y email de aviso.
  - El programador de Laravel lee esa programación (`routes/console.php`). En Hostinger la ejecuta el cron de cada minuto y en Docker el contenedor `scheduler`.
- **Registro de cada copia** en la tabla `backups`: estado, tamaño, origen, detalle y quién la hizo.
  - La retención borra las copias buenas que sobran.
  - Las copias fallidas se conservan 30 días.
- **Avisos por email**: si una copia falla y, con `copias:revisar` cada día, si la última copia buena es demasiado antigua. Esto detecta que el cron ha dejado de funcionar.

## Consecuencias

- La copia se hace en la misma petición cuando se pulsa «Hacer copia ahora». Con el tamaño actual tarda segundos. Si con los años pasa de unos minutos, se moverá a una cola.
- La restauración es manual y está documentada en `docs/copias-de-seguridad.md`. No hay botón de restaurar en el panel, a propósito: sustituir todos los datos no debe estar a un clic.
- Hay que guardar el `.env` aparte, porque la copia no lo contiene.
