# ADR 0003 · MariaDB/MySQL en todos los entornos

- **Estado:** aceptada (pendiente de confirmación de Diego)
- **Fecha:** 2026-11-03

## Contexto

Las pruebas se hacen en un hosting de Hostinger, que ofrece MySQL/MariaDB. La producción irá en OVH con Docker.

## Decisión

Usar MariaDB (compatible con MySQL 8) en todos los entornos: Hostinger, Docker local y OVH. Las pruebas automáticas usan SQLite en memoria por velocidad, y CI ejecuta además la batería contra MariaDB.

## Consecuencias

Pruebas y producción se comportan igual. Se evita SQL específico de un motor; toda consulta pasa por Eloquent o el Query Builder.
