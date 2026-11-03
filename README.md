# Clave de Alma

Marketplace de tiendas de artistas: gorras, sudaderas, camisetas y más, con fabricación bajo pedido. Una matriz (`clavedealma.es`), una tienda por artista en su subdominio, un carrito común y una sola pasarela de pago.

## Tecnología

- PHP 8.3+ y Laravel 13, organizado como monolito modular (`app/Modules`).
- Panel de gestión con Filament (`/admin`) para superadministradores, fabricantes y artistas.
- Roles y permisos con spatie/laravel-permission.
- MariaDB/MySQL; Redis y Meilisearch en producción.
- Pagos con Stripe (tarjeta, Bizum, Apple Pay, Google Pay).

## Puesta en marcha (desarrollo)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Antes de `migrate --seed`, rellena `SUPERADMIN_EMAIL` y `SUPERADMIN_PASSWORD` en `.env`: el seeder crea ese usuario con rol de superadministrador.

## Entornos

| Entorno | Dónde | Cómo se despliega | Guía |
| --- | --- | --- | --- |
| Pruebas | Hostinger (hosting PHP) | ZIP generado con `scripts/build-zip.sh` | [docs/instalacion-hostinger.md](docs/instalacion-hostinger.md) |
| Staging y producción | VPS de OVH con Docker | `docker compose up -d --build` | [docs/despliegue-docker.md](docs/despliegue-docker.md) |

## Calidad

```bash
composer test   # pruebas automáticas (incluye pruebas de arquitectura)
composer lint   # estilo (Pint) y análisis estático (Larastan)
```

Cada pull request ejecuta ambas en GitHub Actions.

## Documentación

- [docs/README.md](docs/README.md): índice, fases y decisiones.
- [docs/adr/](docs/adr/): registro de decisiones técnicas.
