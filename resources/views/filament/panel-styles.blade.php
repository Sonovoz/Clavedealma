{{--
    Ajustes visuales del panel. El menú lateral es más alto que muchas pantallas: en lugar
    de la barra de desplazamiento gruesa de Windows, una fina que solo se ve al pasar el ratón.
--}}
<style>
    .fi-sidebar-nav {
        row-gap: 1.25rem;
        padding-block: 1.25rem;
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }
    .fi-sidebar-nav:hover,
    .fi-sidebar-nav:focus-within {
        scrollbar-color: rgba(127, 127, 127, .45) transparent;
    }
    /* «Mis ventas» del artista (ADR 0023) */
    .cda-empty { margin: 0; color: rgb(120 113 108); font-size: .9rem; }
    .cda-rank { list-style: none; margin: 0; padding: 0; display: grid; gap: .25rem; }
    .cda-rank__row { display: grid; grid-template-columns: 1.75rem 3rem minmax(0, 1fr) auto; gap: .9rem; align-items: center; padding: .7rem .25rem; border-bottom: 1px solid rgba(127, 127, 127, .15); }
    .cda-rank__row:last-child { border-bottom: 0; }
    .cda-rank__pos { font-weight: 700; font-size: 1.05rem; color: rgb(217 119 6); text-align: center; }
    .cda-rank__row:first-child .cda-rank__pos { font-size: 1.4rem; }
    .cda-rank__img { width: 3rem; aspect-ratio: 4 / 5; border-radius: .4rem; overflow: hidden; background: rgba(127, 127, 127, .12); }
    .cda-rank__img img { width: 100%; height: 100%; object-fit: cover; }
    .cda-rank__info { display: grid; gap: .2rem; min-width: 0; }
    .cda-rank__info strong { font-size: .95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cda-rank__info small { color: rgb(120 113 108); font-size: .78rem; }
    .cda-bar { display: block; height: .35rem; border-radius: 1rem; background: rgba(127, 127, 127, .14); overflow: hidden; margin-top: .2rem; }
    .cda-bar span { display: block; height: 100%; border-radius: 1rem; background: linear-gradient(90deg, rgb(245 158 11), rgb(217 119 6)); transition: width .8s ease; }
    .cda-rank__nums { display: grid; grid-template-columns: 4rem 6.5rem 9.5rem 3rem; gap: 1rem; align-items: baseline; font-variant-numeric: tabular-nums; font-size: .9rem; text-align: right; }
    .cda-rank__fee { color: rgb(22 163 74); font-weight: 600; }
    .cda-rank__share { min-width: 3rem; color: rgb(120 113 108); }
    @media (max-width: 760px) {
        .cda-rank__row { grid-template-columns: 1.5rem 2.6rem minmax(0, 1fr); }
        .cda-rank__nums { grid-column: 2 / -1; grid-template-columns: auto auto; justify-content: start; gap: .3rem 1rem; text-align: left; }
    }
    .cda-table-wrap { overflow-x: auto; }
    .cda-table { width: 100%; min-width: 26rem; border-collapse: collapse; font-size: .9rem; font-variant-numeric: tabular-nums; }
    .cda-table th { text-align: left; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(120 113 108); padding: .5rem .6rem; border-bottom: 1px solid rgba(127, 127, 127, .2); }
    .cda-table td { padding: .55rem .6rem; border-bottom: 1px solid rgba(127, 127, 127, .12); }
    .cda-table td:first-child { white-space: nowrap; }
    .cda-table td:first-child::first-letter { text-transform: uppercase; }
    .cda-table tfoot th { color: inherit; font-size: .9rem; text-transform: none; letter-spacing: 0; border-bottom: 0; border-top: 1px solid rgba(127, 127, 127, .3); }
    .cda-table th:not(:first-child), .cda-table td:not(:first-child) { text-align: right; }
    /* «Notas del pedido» como WooCommerce (ADR 0025) */
    .cda-note { position: relative; padding: .6rem .75rem; border-radius: .4rem; margin-bottom: .9rem; font-size: .85rem; background: rgba(127, 127, 127, .1); }
    .cda-note p { margin: 0; white-space: pre-line; }
    .cda-note small { display: block; margin-top: .35rem; font-size: .72rem; color: rgb(120 113 108); }
    .cda-note--private { background: rgba(245, 158, 11, .14); }
    .cda-note--customer { background: rgba(59, 130, 246, .14); }
</style>
