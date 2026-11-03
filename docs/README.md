# Documentación de Clave de Alma

El documento de bases del proyecto (modelo de negocio, decisiones, arquitectura completa, fases) se mantiene en Claude Docs y lo comparten los fundadores. Este directorio recoge lo que necesita quien trabaja con el código.

## Guías

- [Instalación de pruebas en Hostinger](instalacion-hostinger.md)
- [Despliegue con Docker en OVH](despliegue-docker.md)

## Decisiones técnicas (ADR)

| N.º | Decisión |
| --- | --- |
| [0001](adr/0001-monolito-modular.md) | Monolito modular en Laravel |
| [0002](adr/0002-stripe-pasarela-unica.md) | Stripe como pasarela única |
| [0003](adr/0003-base-de-datos-mariadb.md) | MariaDB/MySQL en todos los entornos |
| [0004](adr/0004-drivers-por-entorno.md) | Mismo código, drivers distintos por entorno |

## Orden de construcción

1. **Cimientos** ← fase actual: proyecto, módulos, roles, panel, entornos, CI.
2. Tiendas y catálogo en el panel.
3. Tienda "en blanco" para probar flujos.
4. Compra y pago con Stripe (modo test).
5. Operación: producción, envíos, devoluciones.
6. Artistas, liquidaciones y autofacturación.
7. Centro de control para superadministradores.
8. Capa visual: plantilla común y temas.
9. Pruebas completas en staging y lanzamiento.

## Módulos

`app/Modules/`: Identity, Stores, Catalog, Inventory, Cart, Orders, Payments, Production, Shipping, Returns, Settlements, Invoicing, Notifications, Audit. En la fase 1 solo existe `Identity`; el resto se crea al llegar a su fase, con la misma estructura de carpetas.
