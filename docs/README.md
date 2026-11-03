# Documentación de Clave de Alma

El documento de bases del proyecto (modelo de negocio, decisiones, arquitectura completa, fases) se mantiene en Claude Docs y lo comparten los fundadores. Este directorio recoge lo que necesita quien trabaja con el código.

## Guías

- [Instalación de pruebas en Hostinger](instalacion-hostinger.md)
- [Despliegue con Docker en OVH](despliegue-docker.md)
- [Prototipos de diseño: matriz y tienda Farruquito](diseno/README.md)

## Decisiones técnicas (ADR)

| N.º | Decisión |
| --- | --- |
| [0001](adr/0001-monolito-modular.md) | Monolito modular en Laravel |
| [0002](adr/0002-stripe-pasarela-unica.md) | Stripe como pasarela única |
| [0003](adr/0003-base-de-datos-mariadb.md) | MariaDB/MySQL en todos los entornos |
| [0004](adr/0004-drivers-por-entorno.md) | Mismo código, drivers distintos por entorno |
| [0005](adr/0005-dinero-y-porcentajes-enteros.md) | Dinero en céntimos y porcentajes en puntos básicos |
| [0006](adr/0006-eventos-entre-modulos.md) | Los módulos se comunican con eventos |

## Orden de construcción

1. ✓ Cimientos: proyecto, módulos, roles, panel, entornos, CI.
2. **Tiendas y catálogo en el panel** ← fase actual.
3. Tienda "en blanco" para probar flujos.
4. Compra y pago con Stripe (modo test).
5. Operación: producción, envíos, devoluciones.
6. Artistas, liquidaciones y autofacturación.
7. Centro de control para superadministradores.
8. Capa visual: plantilla común y temas.
9. Pruebas completas en staging y lanzamiento.

## Módulos

`app/Modules/`: Identity, Stores, Catalog, Inventory, Cart, Orders, Payments, Production, Shipping, Returns, Settlements, Invoicing, Notifications, Audit. Existen ya `Identity`, `Stores`, `Catalog` e `Inventory`; el resto se crea al llegar a su fase, con la misma estructura de carpetas.

## Panel por rol (fase 2)

| Sección | Superadmin | Fabricante | Artista |
| --- | --- | --- | --- |
| Tiendas: artistas y tiendas | ✓ | — | — |
| Catálogo: productos, colecciones, prendas base | ✓ | — | — |
| Operación: stock | ✓ | ✓ | — |
| Administración: sistema | ✓ | — | — |

## Datos de demostración

```bash
php artisan db:seed --class=DemoSeeder
```

Crea 2 artistas con su tienda, 3 prendas base, 6 productos y 28 variantes. No se ejecuta en producción.

```bash
php artisan db:seed --class=FarruquitoSeeder
```

Primera tienda de prueba: Farruquito (oculta), con las colecciones On Stage, Artist Voice e Iconic: 35 piezas, 186 variantes e imágenes tomadas de `docs/diseno/farruquito`. Precios, comisión (25 %) y unidades (25 por variante en On Stage) son **de ejemplo**. No se ejecuta en producción.
