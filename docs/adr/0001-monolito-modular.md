# ADR 0001 · Monolito modular en Laravel

- **Estado:** aceptada
- **Fecha:** 2026-11-03

## Contexto

Clave de Alma es un marketplace con una matriz y tiendas de artistas en subdominios. Un único vendedor (Clave de Alma), un carrito común y una sola pasarela. El equipo técnico es pequeño.

## Decisión

Una sola aplicación Laravel y una sola base de datos, dividida en módulos de negocio dentro de `app/Modules/`. Cada módulo es dueño de sus tablas y se comunica con los demás a través de su servicio público (`Contracts/`) o de eventos de dominio.

## Consecuencias

- Un solo despliegue y una sola base de datos: sencillo de operar en Hostinger y en OVH.
- Las fronteras entre módulos las vigilan pruebas de arquitectura (`tests/Architecture`).
- Si un módulo necesitara escalar por separado, sus fronteras permiten extraerlo más adelante.

## Alternativas descartadas

- **Microservicios:** demasiada complejidad operativa para el tamaño del proyecto.
- **Base de datos por tienda:** complica el carrito común y los informes globales.
