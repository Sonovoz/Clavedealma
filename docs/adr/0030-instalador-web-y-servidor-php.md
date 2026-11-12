# ADR 0030 · Instalador web y servidor PHP 8.3 + MariaDB (OVH)

- Estado: aceptado
- Fecha: 2026-11-12
- Sustituye en parte: la nota «no hay instalador web, a propósito» de `docs/instalacion-hostinger.md`. Completa el ADR 0028: la imagen Docker propia queda como alternativa.

## Contexto

El servidor de OVH ya tiene sus contenedores, montados por el equipo:
- `php-web` (PHP 8.3);
- `mariadb` (MariaDB 10.11);
- `redis`;
- `npm` (Nginx Proxy Manager, que pone el certificado);
- `phpmyadmin`, `filebrowser` y `db-backup`.

No hay contenedores de colas ni de tareas programadas, y no usaremos los nuestros (Traefik, `worker`, `scheduler`).

El equipo quiere instalar la web como un WordPress: dar los datos de la base de datos (nombre, usuario y contraseña) desde el navegador, sin editar el `.env` por SSH. El primer intento a mano falló al entrar al panel: faltaban los archivos del panel (`public/js/filament`), que no están en el repositorio.

## Decisión

### Primer arranque, sin Laravel

`bootstrap/primer-arranque.php` va en `public/index.php`, antes de cargar Laravel:
- Sin `vendor/`, muestra una página que explica que hay que subir el paquete, no el código de GitHub.
- Sin `.env`:
  - lo crea desde `.env.servidor.example`, con una `APP_KEY` nueva y la dirección que llega del proxy;
  - lo deja con permisos 600.
- Mientras no hay instalación, usa caché y sesiones en archivos, porque todavía no hay base de datos.

### Asistente `/instalar`

Lo hace `InstallController`, en cuatro pasos:
1. **Servidor** (`Requirements`):
   - PHP 8.3 y sus extensiones;
   - los permisos de escritura;
   - el paquete completo (estilos y archivos del panel; estos últimos los publica si faltan).
2. **Base de datos** (`DatabaseProbe`):
   - prueba la conexión antes de guardar, exige MariaDB 10.6 o más y explica cada error en castellano;
   - si llega a `redis`, lo usa para caché y sesiones.
3. **Administrador** (`RunInstall`):
   - crea las tablas, los roles y el superadministrador, con una contraseña de 12 caracteres o más;
   - genera `CRON_TOKEN`.
4. **Listo**: la línea del cron y la dirección secreta. Se ven una sola vez.

Seguridad del asistente:
- Instalado (marca `storage/app/instalado` o tablas ya creadas), **todo `/instalar` da 404**. Nadie puede volver a configurar la base de datos desde la web aunque falle.
- Las rutas tienen límite de peticiones.
- La contraseña de la base de datos solo va al `.env`, nunca a la pantalla ni a GitHub.

### Tareas programadas sin contenedor propio

Dos formas:
- el cron del VPS: `docker exec php-web php …/artisan schedule:run`, cada minuto;
- la dirección `/cron/<CRON_TOKEN>`: 40 caracteres, comparada en tiempo constante, 404 si no coincide y limitada a 6 llamadas por minuto.

La tarea `latido` apunta cada minuto la hora en ajustes, y *Estado del sistema* avisa si las tareas no corren. Las colas van en `sync`, porque no hay worker.

### Paquete

La acción de GitHub «Paquete para el servidor» (`.github/workflows/paquete.yml`) ejecuta `scripts/build-zip.sh` en cada fusión a `main` y publica el ZIP en *Artifacts*, 30 días.

El ZIP lleva:
- `vendor/`;
- los estilos compilados;
- los archivos del panel;
- `docs/`.

Del SDK de AWS solo se deja S3 (de 298 MB a 14 MB). *Aplicar actualización* vuelve a publicar los archivos del panel.

## Consecuencias

- La instalación ya no necesita SSH ni editar el `.env`: subir el ZIP, abrir la web y rellenar el asistente.
- Sigue valiendo para Hostinger y para la imagen Docker. Si el `.env` ya existe y hay tablas, el asistente no aparece.
- Sin cron, no hay copias, ni cierre de Minimedia, ni anulación de pedidos caducados. Por eso el aviso en *Estado del sistema*.
- Las colas en `sync` hacen que el email se envíe dentro de la petición: algo más lento, pero sin procesos aparte. Si un día hay worker, basta `QUEUE_CONNECTION=redis`.
