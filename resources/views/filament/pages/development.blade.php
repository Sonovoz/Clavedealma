<x-filament-panels::page>
    <style>
        .cda-tabs { display: flex; gap: .5rem; flex-wrap: wrap; border-bottom: 1px solid rgba(127,127,127,.25); }
        .cda-tab { padding: .6rem 1rem; border: 0; background: none; cursor: pointer; font-weight: 600; color: inherit; opacity: .65; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .cda-tab[aria-selected="true"] { opacity: 1; border-color: rgb(217 119 6); color: rgb(217 119 6); }
        .cda-doc { max-width: 60rem; line-height: 1.65; font-size: .95rem; }
        .cda-doc h1 { font-size: 1.6rem; font-weight: 800; margin: .2rem 0 1rem; }
        .cda-doc h2 { font-size: 1.2rem; font-weight: 700; margin: 2rem 0 .6rem; }
        .cda-doc h3 { font-size: 1.02rem; font-weight: 700; margin: 1.4rem 0 .4rem; }
        .cda-doc p, .cda-doc ul, .cda-doc ol { margin: .5rem 0; }
        .cda-doc ul { list-style: disc; padding-left: 1.4rem; }
        .cda-doc ol { list-style: decimal; padding-left: 1.4rem; }
        .cda-doc hr { margin: 1.5rem 0; opacity: .25; }
        .cda-doc code { font-size: .85em; padding: .1rem .3rem; border-radius: .3rem; background: rgba(127,127,127,.15); }
        .cda-doc pre { overflow-x: auto; padding: .9rem 1rem; border-radius: .6rem; background: rgba(127,127,127,.12); font-size: .82rem; line-height: 1.5; }
        .cda-doc pre code { background: none; padding: 0; }
        .cda-doc table { width: 100%; border-collapse: collapse; margin: .8rem 0; font-size: .88rem; display: block; overflow-x: auto; }
        .cda-doc th, .cda-doc td { text-align: left; vertical-align: top; padding: .45rem .6rem; border-bottom: 1px solid rgba(127,127,127,.2); }
        .cda-doc th { font-weight: 700; }
        .cda-doc a { color: rgb(217 119 6); text-decoration: underline; }
        .cda-tree { overflow-x: auto; padding: 1rem 1.1rem; border-radius: .75rem; border: 1px solid rgba(127,127,127,.25); background: rgba(127,127,127,.06); font: 12.5px/1.55 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: pre; }
        .cda-adr { border: 1px solid rgba(127,127,127,.25); border-radius: .6rem; padding: .2rem 1rem; margin-bottom: .6rem; }
        .cda-adr summary { cursor: pointer; font-weight: 600; padding: .6rem 0; }
    </style>

    <div x-data="{ tab: (location.hash || '#guia').slice(1) }" x-init="$watch('tab', t => history.replaceState(null, '', '#' + t))">
        <div class="cda-tabs" role="tablist">
            <button class="cda-tab" role="tab" type="button" :aria-selected="tab === 'guia'" @click="tab = 'guia'">Cómo está hecho</button>
            <button class="cda-tab" role="tab" type="button" :aria-selected="tab === 'arbol'" @click="tab = 'arbol'">Árbol del sistema</button>
            <button class="cda-tab" role="tab" type="button" :aria-selected="tab === 'decisiones'" @click="tab = 'decisiones'">Decisiones (ADR)</button>
        </div>

        <section x-show="tab === 'guia'" class="cda-doc" style="margin-top:1.25rem">{!! $guide !!}</section>

        <section x-show="tab === 'arbol'" x-cloak style="margin-top:1.25rem;display:grid;gap:1.25rem">
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;margin:0 0 .4rem">Esquema del sistema</h2>
                <p style="margin:0 0 .75rem;font-size:.875rem;opacity:.8">Quién usa qué, qué módulo hace cada cosa y cómo viaja una venta de principio a fin.</p>
                <div class="cda-tree">{{ $map }}</div>
            </div>
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;margin:0 0 .4rem">Árbol del código</h2>
                <p style="margin:0 0 .75rem;font-size:.875rem;opacity:.8">Generado en este momento a partir de las carpetas del proyecto: siempre está al día. En cada módulo, sus capas y sus clases.</p>
                <div class="cda-tree">{{ $tree }}</div>
            </div>
        </section>

        <section x-show="tab === 'decisiones'" x-cloak class="cda-doc" style="margin-top:1.25rem">
            <p style="opacity:.8">Cada decisión técnica importante, con su contexto y sus consecuencias. Están en <code>docs/adr</code>.</p>
            @foreach ($adrs as $adr)
                <details class="cda-adr">
                    <summary>{{ $adr['title'] }}</summary>
                    {!! $adr['html'] !!}
                </details>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
