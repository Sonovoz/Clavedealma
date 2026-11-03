# ADR 0005 · Dinero en céntimos y porcentajes en puntos básicos

- **Estado:** aceptada
- **Fecha:** 2026-11-04

## Decisión

- Todo importe se guarda como **entero en céntimos** (`price_cents = 2990` → 29,90 €) y se opera con `App\Support\Money`.
- Todo porcentaje (comisión, IVA, IRPF) se guarda como **entero en puntos básicos** (`2500` → 25 %; `2100` → 21 %). La comisión se opera con `App\Modules\Stores\Domain\Commission`.
- Los redondeos se hacen al céntimo más cercano, con los medios céntimos hacia arriba, y siempre en un único sitio del código.
- El panel muestra y acepta formato español ("29,90", "12,5"); la conversión la hacen `App\Filament\Support\Fields::money()` y `::percent()`.

## Por qué

Los decimales flotantes acumulan errores (0,1 + 0,2 ≠ 0,3). En liquidaciones y facturas un céntimo de diferencia es un error contable.
