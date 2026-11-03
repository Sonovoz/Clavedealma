# ADR 0004 · Mismo código, drivers distintos por entorno

- **Estado:** aceptada
- **Fecha:** 2026-11-03

## Decisión

La aplicación no depende de servicios concretos. Caché, sesiones, colas, búsqueda y archivos se eligen en `.env`:

| Pieza | Hostinger (pruebas) | OVH (Docker) |
| --- | --- | --- |
| Caché y sesiones | `database` | `redis` |
| Colas | `database` + cron | `redis` + worker |
| Búsqueda | Scout `database` | Meilisearch |
| Archivos | disco local | S3 (OVH Object Storage / MinIO) |

Plantillas: `.env.hostinger.example` y `.env.docker.example`.
