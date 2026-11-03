# Despliegue con Docker en OVH

Staging y producción corren en un VPS de OVH con Docker Compose. La misma imagen sirve para la aplicación, las colas y las tareas programadas.

## Servicios

| Servicio | Función |
| --- | --- |
| `traefik` | HTTPS, certificado comodín `*.clavedealma.es`, enrutado de subdominios |
| `web` | Nginx con los archivos públicos compilados |
| `app` | PHP-FPM con la aplicación |
| `worker` | Procesa las colas |
| `scheduler` | Tareas programadas (`schedule:work`) |
| `db` | MariaDB 11.4 |
| `redis` | Caché, sesiones y colas |
| `meilisearch` | Búsqueda de productos |

## Preparación del VPS

1. VPS con Ubuntu LTS, Docker Engine y el plugin Compose.
2. Cortafuegos: abrir solo 22 (SSH con clave), 80 y 443.
3. DNS en OVH: registros `A` para `clavedealma.es` y `*.clavedealma.es` apuntando al VPS.
4. Crear credenciales de la API de OVH para el reto DNS en https://api.ovh.com/createToken/ con permisos `GET/POST/DELETE` sobre `/domain/zone/clavedealma.es/*`.

## Primer despliegue

```bash
git clone git@github.com:Sonovoz/clavedealma.git && cd clavedealma
cp .env.docker.example .env        # rellenar claves y contraseñas
docker compose up -d --build
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan storage:link
```

## Actualizaciones

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

## Copias de seguridad

- Base de datos: volcado diario (`mariadb-dump`) enviado a OVH Object Storage.
- Archivos subidos: volumen `storage`, sincronizado a Object Storage.
- Probar la restauración al menos una vez al trimestre.

(La automatización de copias y monitorización llega con la fase de centro de control.)
