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
</style>
