<x-filament-panels::page>
    <style>
        .cda-legal { line-height: 1.6; font-size: .92rem; }
        .cda-legal h1 { font-size: 1.4rem; font-weight: 800; margin: 0 0 .8rem; }
        .cda-legal h2 { font-size: 1.05rem; font-weight: 700; margin: 1.2rem 0 .4rem; }
        .cda-legal p, .cda-legal ul, .cda-legal ol { margin: .45rem 0; }
        .cda-legal ul { list-style: disc; padding-left: 1.3rem; }
        .cda-legal ol { list-style: decimal; padding-left: 1.3rem; }
        .cda-legal table { width: 100%; border-collapse: collapse; font-size: .85rem; display: block; overflow-x: auto; }
        .cda-legal th, .cda-legal td { text-align: left; padding: .35rem .5rem; border-bottom: 1px solid rgba(127,127,127,.2); vertical-align: top; }
        .cda-legal blockquote { border-left: 3px solid rgba(127,127,127,.35); padding-left: .8rem; opacity: .85; }
        .cda-legal code { font-size: .85em; padding: .05rem .25rem; border-radius: .25rem; background: rgba(127,127,127,.15); }
    </style>
    <p style="margin:0;font-size:.875rem;opacity:.85">
        Se generan solos con los datos fiscales, las tiendas, las tarifas de envío y las reglas de devolución del sistema. Cada cambio crea una versión nueva (cada pedido guarda la versión de las condiciones que aceptó el cliente).
        <strong>Son una base: la asesoría legal debe revisarlos</strong> y marcarlos como revisados. Lo que falte aparece en rojo como «pendiente».
    </p>
    {{ $this->table }}
</x-filament-panels::page>
