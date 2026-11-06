# ADR 0028 · Seguridad del servidor: dos pasos, avisos de errores y copias fuera

- Estado: aceptado
- Fecha: 2026-11-06

## Contexto

El servidor será un VPS de OVH con Docker (Ubuntu, 4 GB o más). Antes de ponerlo en marcha faltaban tres cosas:

- El panel guarda datos de clientes, cobros y la clave del TPV (ADR 0027), y solo se protegía con contraseña.
- Si la web fallaba, nadie se enteraba hasta que un cliente avisara.
- Las copias se guardaban en un solo sitio, el servidor *o* Object Storage.

Al revisar la instalación Docker aparecieron además varios fallos que la habrían impedido funcionar.

## Decisión

- **Verificación en dos pasos en el panel** (la de Filament):
  - Funciona con una app autenticadora (Google o Microsoft Authenticator, 1Password…) y 8 códigos de recuperación.
  - La clave y los códigos se guardan cifrados con la `APP_KEY`.
  - **Obligatoria para el equipo**: superadministrador, gestor de catálogo y fabricante (`User::mustUseTwoFactor`, middleware `EnsureTwoFactorForStaff`). Para los artistas es opcional, en «Mi perfil».
  - `TWO_FACTOR_REQUIRED=false` la quita en local.
  - Activarla, quitarla o usar un código de recuperación queda en el registro de seguridad.
  - Si alguien pierde el móvil, un superadministrador se la quita en *Usuarios* («Quitar dos pasos»). Si es el único superadministrador, en el servidor: `php artisan usuarios:quitar-dos-pasos <email>`.
- **Avisos de errores por email** (`App\Platform\Monitoring\ErrorAlerts`, `ERROR_ALERTS=true`):
  - Se avisa a `ERROR_ALERT_EMAIL` o al superadministrador.
  - El mismo error, como mucho una vez por hora, y nunca más de 10 emails a la hora.
  - El control usa archivos y no la base de datos, para avisar también cuando lo que falla es la base de datos.
  - Sin datos sensibles: sin lo que escribe el cliente, sin emails, sin tokens y sin el SQL.
  - Los errores normales (404, validación) no avisan.
  - En *Estado del sistema*, «Enviar un aviso de prueba» comprueba el correo.
- **Copias en el servidor y, además, fuera** (`BACKUP_OFFSITE=true`, disco `backups_offsite`):
  - Se suben al Object Storage de OVH, compatible con S3, con el paquete `league/flysystem-aws-s3-v3`, que faltaba.
  - Si la subida falla, la copia del servidor vale, se avisa y se reintenta desde el panel.
  - Ver `docs/copias-de-seguridad.md`.
- **Instalación Docker revisada y probada de verdad**: se construyeron las imágenes y se arrancó todo (MariaDB, Redis, web, colas, tareas programadas, Traefik y un Object Storage de prueba). Se corrigió:
  - **Traefik v3.3 → v3.6**: el Docker actual (API mínima 1.40) rechaza a Traefik v3.3 y la web se quedaba sin HTTPS.
  - **`TRUSTED_PROXIES`**: detrás de Traefik, la web no sabía que iba por HTTPS. Se generaban enlaces `http://`, también la URL de notificación de Redsys. Además, la IP del registro de seguridad era la del proxy.
  - **`.dockerignore`**: se colaban en la imagen los archivos locales de `storage/app` (fotos, copias y bases de datos de prueba). También faltaba `docs`, que lee la página *Desarrollo*.
  - Enlace `public/storage`, volumen de `logs`, registro diario, cookie de sesión solo por HTTPS y comprobación de salud del contenedor `app`.
  - Meilisearch solo arranca con `--profile busqueda`, porque aún no se usa.
  - La plantilla `.env.docker.example` viene preparada para la fase de pruebas: Redsys en `test` y `SEO_INDEXING=false`.
- **Guía paso a paso** en `docs/despliegue-docker.md`: desde un VPS vacío hasta el lanzamiento.

## Consecuencias

- Hay que guardar la `APP_KEY` (el `.env`) fuera del servidor. Sin ella no se descifran ni la clave del TPV ni la verificación en dos pasos.
- Cada persona del equipo necesita una app autenticadora en el móvil.
