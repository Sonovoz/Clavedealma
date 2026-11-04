{{--
    Matriz de Clave de Alma: portada del market. Diseño portado de docs/diseno/matriz.
    La sección «Tiendas oficiales» se rellena sola con las tiendas visibles (MarketController).
--}}
@php($a = fn (string $file): string => \App\Storefront\Urls::designAsset('matriz', $file))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#070707">
    @include('storefront.partials.seo')
    @vite(['resources/css/storefront/matriz.css', 'resources/js/storefront/matriz.js'])
</head>
<body>
@if ($staff && collect($stores)->contains('hidden', true))
    <div class="sf-preview" role="status">Vista previa para el equipo: las tiendas marcadas «solo equipo» no las ve el público. <a href="{{ url('/admin') }}">Volver al panel</a></div>
@endif
<!-- Telón de apertura -->
<div class="telon" aria-hidden="true"><div class="telon__haz"></div><img class="telon__logo" src="{{ $a('logo.png') }}" alt=""></div>
<div class="grano" aria-hidden="true"></div>
<div class="linterna" aria-hidden="true"></div>
<div class="progreso" aria-hidden="true"></div>

<header class="cab" id="cab">
  <div class="wrap cab__in">
    <a class="cab__logo" href="#inicio" aria-label="Clave de Alma, inicio"><img src="{{ $a('logo.png') }}" alt="Clave de Alma" width="614" height="243"></a>
    <nav class="cab__nav" aria-label="Principal">
      <a href="#tiendas">Tiendas</a>
      <a href="#lineas">Colecciones</a>
      <a href="#lineas">Drops</a>
      <a href="#club">The Club</a>
      <a href="#manifiesto">Manifiesto</a>
    </nav>
    <div class="cab__acc">
      <button class="icono" type="button" aria-label="Buscar"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></button>
      <button class="icono" type="button" aria-label="Mi cuenta"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5c1.4-4 4.2-6 7.5-6s6.1 2 7.5 6"/></svg></button>
      <button class="icono" type="button" id="abrir-carrito" aria-label="Carrito (la compra se activa en la fase de pagos)" disabled><svg viewBox="0 0 24 24"><path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg><span class="badge" id="badge">0</span></button>
      <button class="icono menu-btn" type="button" id="menu-btn" aria-label="Abrir menú" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 8h16M4 16h16"/></svg></button>
    </div>
  </div>
</header>

<nav class="menu-movil" id="menu-movil" aria-label="Menú móvil" hidden>
  <a href="#tiendas">Tiendas</a><a href="#lineas">Colecciones</a><a href="#lineas">Drops</a><a href="#club">The Club</a><a href="#manifiesto">Manifiesto</a>
</nav>

<main id="inicio">
  <!-- ESCENA 1 · El foco -->
  <section class="hero" id="hero" aria-label="Portada">
    <div class="haz" id="haz" aria-hidden="true"></div>
    <div class="foco-punto" aria-hidden="true"></div>
    <div class="suelo" aria-hidden="true"></div>
    <img class="proyeccion" src="{{ $a('logo.png') }}" alt="" aria-hidden="true">
    <canvas id="polvo" aria-hidden="true"></canvas>
    <div class="wrap hero__in">
      <span class="eyebrow" style="color:var(--plata)">Premium Artists Platform</span>
      <h1 class="cartel hero__titulo" aria-label="El arte no se fabrica">
        <span class="ln" aria-hidden="true" data-split>El arte</span>
        <span class="ln" aria-hidden="true" data-split>no se fabrica</span>
      </h1>
      <p class="voz hero__sub">Se escucha, se observa y se transforma en emociones y productos para cada comunidad.</p>
      <div class="hero__ctas">
        <a class="btn btn--rojo magnet" href="#tiendas">Explorar tiendas <svg viewBox="0 0 24 24"><path d="M4 12h15m-5-5 5 5-5 5"/></svg></a>
        <a class="btn magnet" href="#club">Entrar al Club</a>
      </div>
    </div>
    <div class="hero__pie">
      <div class="wrap">
        <span class="scroll-ind ficha muted"><i></i>Baja al escenario</span>
        <span class="ficha muted">Madrid · Merchandising oficial · Club</span>
      </div>
    </div>
  </section>

  <!-- Marquesina de pilares -->
  <div class="marquesina" aria-label="Pilares de Clave de Alma">
    <div class="marquesina__pista">
      <span>Merchandising <img src="{{ $a('clave.png') }}" alt=""></span><span>The Club <img src="{{ $a('clave.png') }}" alt=""></span><span>Colecciones <img src="{{ $a('clave.png') }}" alt=""></span><span>Promotora <img src="{{ $a('clave.png') }}" alt=""></span><span>Productora <img src="{{ $a('clave.png') }}" alt=""></span>
      <span aria-hidden="true">Merchandising <img src="{{ $a('clave.png') }}" alt=""></span><span aria-hidden="true">The Club <img src="{{ $a('clave.png') }}" alt=""></span><span aria-hidden="true">Colecciones <img src="{{ $a('clave.png') }}" alt=""></span><span aria-hidden="true">Promotora <img src="{{ $a('clave.png') }}" alt=""></span><span aria-hidden="true">Productora <img src="{{ $a('clave.png') }}" alt=""></span>
    </div>
  </div>

  <!-- ESCENA 2 · Manifiesto -->
  <section class="escena" id="manifiesto">
    <div class="wrap manif">
      <figure class="revelado rv" id="revelado" style="margin:0">
        <img class="base" src="{{ $a('bailaora.webp') }}" alt="" aria-hidden="true">
        <img class="luz" src="{{ $a('bailaora.webp') }}" alt="Bailaora de flamenco con mantón, en blanco y negro, entre humo">
        <figcaption class="ficha">Mueve el foco</figcaption>
      </figure>
      <div class="manif__txt">
        <span class="eyebrow rv">Manifiesto de marca</span>
        <h2 class="cartel palabras">El arte y las emociones <em>no se fabrican</em></h2>
        <p class="rv" style="--d:1">Clave de Alma nace para escuchar el universo creativo de cada artista y convertirlo en productos oficiales, experiencias y comunidad.</p>
        <ul class="principios rv" style="--d:2">
          <li>No hacemos merchandising genérico: creamos piezas oficiales con alma.</li>
          <li>Cada creador guarda una historia, una sensibilidad y una forma única de emocionar.</li>
          <li>El producto debe conservar algo verdadero del artista que lo inspira.</li>
        </ul>
        <p class="voz firma rv" style="--d:3">Escucha, sensibilidad y respeto como sistema creativo.</p>
      </div>
    </div>
  </section>

  <!-- ESCENA 3 · Tiendas -->
  <section class="escena" id="tiendas" style="padding-top:0">
    <div class="wrap">
      <div class="cabecera-seccion">
        <div>
          <span class="eyebrow rv">Artist Goods</span>
          <h2 class="cartel palabras">Tiendas oficiales</h2>
        </div>
        <p class="rv">Cada artista tiene su propia tienda, con su universo visual y su dirección. Un solo carrito y un solo pago para todas.</p>
      </div>
      <div class="generos rv" role="group" aria-label="Filtrar por estilo">
        <button class="chip" type="button" aria-pressed="true" data-g="todos">Todos</button>
        <button class="chip" type="button" aria-pressed="false" data-g="flamenco">Flamenco</button>
        <button class="chip" type="button" aria-pressed="false" data-g="latina">Música latina</button>
        <button class="chip" type="button" aria-pressed="false" data-g="fusion">Fusión</button>
        <button class="chip" type="button" aria-pressed="false" data-g="dj">DJ's</button>
      </div>
      <div class="tiendas">
        @foreach ($stores as $k => $shop)
          <a class="tienda {{ $k === 0 ? 'tienda--primera' : '' }} {{ $shop['image'] ? '' : 'tienda--sin-foto' }} tilt rv" href="{{ $shop['url'] }}" data-g="{{ $shop['genres'] }}" style="--d:{{ $k % 4 }}">
            @if ($shop['image'])<img src="{{ $shop['image'] }}" alt="" style="object-position:50% 20%">@endif
            @if ($shop['hidden'])<span class="tienda__oculta">{{ $shop['status'] }} · solo equipo</span>@endif
            <span class="ficha">{{ $shop['ficha'] }}</span>
            <h3>{{ $shop['name'] }}</h3>
            <span class="url"><b>{{ $shop['subdomain'] }}</b>.{{ $domain }}</span>
          </a>
        @endforeach
        <a class="tienda tilt rv" href="#lineas" data-g="fusion" style="--d:1">
          <img src="{{ $a('tango.webp') }}" alt="">
          <span class="ficha">Colección cápsula</span>
          <h3>Fusión</h3>
          <span class="url">Ediciones limitadas por cantidad o por fecha</span>
        </a>
        <a class="tienda tilt rv" href="#lineas" data-g="dj latina" style="--d:2">
          <img src="{{ $a('concierto.webp') }}" alt="">
          <span class="ficha">Tour Collection</span>
          <h3>Gira y directo</h3>
          <span class="url">Prendas de cada concierto y cada gira</span>
        </a>
        <div class="tienda tienda--artista tilt rv" data-g="todos" style="--d:1">
          <div>
            <span class="ficha">Para artistas</span>
            <h3>Tu línea oficial</h3>
            <ul>
              <li>Dirección creativa: concepto, narrativa y estética</li>
              <li>Producción bajo pedido y control de calidad</li>
              <li>Tienda, pagos, envíos y devoluciones</li>
              <li>Liquidación mensual de tus ventas</li>
            </ul>
          </div>
          <a class="btn magnet" href="#club" style="align-self:flex-start">Hablemos</a>
        </div>
        <a class="tienda tilt rv" href="#lineas" data-g="flamenco" style="--d:2">
          <img src="{{ $a('giro.webp') }}" alt="">
          <span class="ficha">Editorial y coleccionable</span>
          <h3>Láminas firmadas</h3>
          <span class="url">Prints, vinilos y piezas numeradas</span>
        </a>
        <a class="tienda tilt rv" href="#lineas" data-g="latina dj" style="--d:3">
          <img src="{{ $a('microfono.webp') }}" alt="">
          <span class="ficha">Colaboraciones</span>
          <h3>Artista × marca</h3>
          <span class="url">Dos universos creativos, una pieza</span>
        </a>
      </div>
    </div>
  </section>

  <!-- ESCENA 4 · Merchan dinámico -->
  <section class="lineas" id="lineas">
    <div class="lineas__sticky">
      <div class="wrap lineas__top">
        <div>
          <span class="eyebrow">Merchan dinámico</span>
          <h2 class="cartel">Siempre vivo</h2>
        </div>
        <div class="contador" aria-hidden="true"><b id="linea-n">1</b> / 5</div>
      </div>
      <div class="pista" id="pista">
        <article class="panel">
          <div class="panel__img"><img src="{{ $a('bailaora.webp') }}" alt=""></div>
          <div class="panel__txt">
            <span class="ficha muted">Permanente</span>
            <h3>Merchan oficial</h3>
            <p>La colección fija de cada artista: las piezas que siempre están y que identifican a su comunidad.</p>
            <div class="panel__extra"><span class="etiqueta-ej">Fabricado bajo pedido en 5–7 días</span></div>
          </div>
        </article>
        <article class="panel">
          <div class="panel__img"><img src="{{ $a('piano.webp') }}" alt=""></div>
          <div class="panel__txt">
            <span class="ficha muted">Periódicas</span>
            <h3>Colecciones</h3>
            <p>Lanzamientos cada cierto tiempo para que siempre haya novedades: nuevas historias, nuevos diseños.</p>
            <div class="panel__extra"><span class="etiqueta-ej">Una colección nueva por temporada</span></div>
          </div>
        </article>
        <article class="panel">
          <div class="panel__img"><img src="{{ $a('giro.webp') }}" alt=""></div>
          <div class="panel__txt">
            <span class="ficha muted">Hasta agotar existencias</span>
            <h3>Ediciones limitadas</h3>
            <p>Diseños con unidades fijadas, a veces firmados por el artista. Se venden hasta acabar el stock o hasta una fecha.</p>
            <div class="panel__extra">
              <div style="display:flex;justify-content:space-between" class="ficha"><span>Vendidas</span><span><b id="vendidas">63</b> / 100</span></div>
              <div class="barra" id="barra"><i></i></div>
              <div class="cuenta" id="cuenta" aria-label="Tiempo restante">
                <div><b data-u="d">03</b><span class="etiqueta-ej">días</span></div>
                <div><b data-u="h">07</b><span class="etiqueta-ej">horas</span></div>
                <div><b data-u="m">00</b><span class="etiqueta-ej">min</span></div>
                <div><b data-u="s">00</b><span class="etiqueta-ej">seg</span></div>
              </div>
              <span class="etiqueta-ej">Ejemplo de edición</span>
            </div>
          </div>
        </article>
        <article class="panel">
          <div class="panel__img"><img src="{{ $a('tango.webp') }}" alt=""></div>
          <div class="panel__txt">
            <span class="ficha muted">Cápsulas</span>
            <h3>Colaboraciones</h3>
            <p>Colecciones exclusivas entre artistas, diseñadores o marcas, en número limitado o en fechas concretas.</p>
            <div class="panel__extra"><span class="etiqueta-ej">Artista × diseñador · Artista × marca</span></div>
          </div>
        </article>
        <article class="panel">
          <div class="panel__img"><img src="{{ $a('concierto.webp') }}" alt=""></div>
          <div class="panel__txt">
            <span class="ficha muted">Conciertos y giras</span>
            <h3>Tour Collection</h3>
            <p>Piezas creadas para un concierto, una gira o un evento concreto. Para llevarse la noche puesta.</p>
            <div class="panel__extra">
              <ul class="fechas">
                <li><span>14 NOV · Madrid</span><span>Teatro</span></li>
                <li><span>22 NOV · Sevilla</span><span>Auditorio</span></li>
                <li><span>06 DIC · Barcelona</span><span>Sala</span></li>
              </ul>
              <span class="etiqueta-ej">Fechas de ejemplo</span>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ESCENA 6 · Sistema de trabajo -->
  <section class="escena" style="padding-top:0">
    <div class="wrap">
      <div class="cabecera-seccion">
        <div>
          <span class="eyebrow rv">Sistema de trabajo</span>
          <h2 class="cartel palabras">Del análisis al producto final</h2>
        </div>
        <p class="rv">Cada proyecto empieza escuchando al artista y termina en una experiencia de venta que refuerza su identidad.</p>
      </div>
      <div class="proceso" id="proceso">
        <div class="proceso__linea"><i></i></div>
        <div class="paso"><span class="num">01</span><h3>Escucha</h3><p>Historia, códigos, público, estética y sensibilidad.</p></div>
        <div class="paso"><span class="num">02</span><h3>Concepto</h3><p>Narrativa, nombre, colección, cápsula o drop.</p></div>
        <div class="paso"><span class="num">03</span><h3>Diseño</h3><p>Producto, gráfica, packaging, visuales y catálogo.</p></div>
        <div class="paso"><span class="num">04</span><h3>Producción</h3><p>Selección, acabados, calidad, stock y logística.</p></div>
        <div class="paso"><span class="num">05</span><h3>Activación</h3><p>Lanzamiento, club, campaña, contenido y venta.</p></div>
      </div>
      <p class="voz cita-proceso rv">El producto oficial no se improvisa: se construye como una pieza de identidad.</p>
    </div>
  </section>

  <!-- ESCENA 7 · The Club -->
  <section class="club escena" id="club">
    <div class="club__haz" id="club-haz" aria-hidden="true"></div>
    <div class="wrap">
      <div class="club__head">
        <h2 class="cartel palabras">The Club <span class="club__sub">un club, no una sala</span></h2>
        <p class="rv">La sede física donde el universo del artista se convierte en experiencia, comunidad y memoria. Conciertos boutique, jam sessions, lanzamientos y noches con aforo limitado en Madrid.</p>
      </div>

      <div class="formatos">
        <div class="formato rv"><h3>Artist Goods Launch</h3><p>Colección oficial, actuación, firma y venta exclusiva.</p></div>
        <div class="formato rv" style="--d:1"><h3>Album Release</h3><p>Escucha, conversación, visuales, fiesta y cápsula limitada.</p></div>
        <div class="formato rv" style="--d:2"><h3>Concierto boutique</h3><p>El artista a un metro del público. La emoción se vuelve exclusiva.</p></div>
        <div class="formato rv"><h3>Meet the Artist</h3><p>Firma, fotos, piezas especiales y vínculo directo.</p></div>
        <div class="formato rv" style="--d:1"><h3>Jam Session</h3><p>Colaboraciones inesperadas. Lo imprevisible es el lujo.</p></div>
        <div class="formato rv" style="--d:2"><h3>Culture &amp; Brand Night</h3><p>Arte, diseño, moda, producto, networking y música.</p></div>
      </div>

      <div class="agenda-head">
        <h3>Próximas noches</h3>
        <span class="etiqueta-ej">Programación de ejemplo</span>
      </div>
      <ul class="agenda" id="agenda">
        <li class="noche" data-img="{{ $a('bailaora.webp') }}"><time datetime="2026-11-13"><b>13</b><span>NOV · VIE</span></time><h4>Noche flamenca</h4><span class="muted">Concierto boutique · 21:30</span><span class="aforo aforo--ultimas">Últimas 12 plazas</span></li>
        <li class="noche" data-img="{{ $a('microfono.webp') }}"><time datetime="2026-11-20"><b>20</b><span>NOV · VIE</span></time><h4>Vocal session</h4><span class="muted">DJ + voces en directo · 23:00</span><span class="aforo">Aforo 120</span></li>
        <li class="noche" data-img="{{ $a('tango.webp') }}"><time datetime="2026-11-27"><b>27</b><span>NOV · VIE</span></time><h4>Jam de fusión</h4><span class="muted">Artistas invitados · 22:00</span><span class="aforo">Aforo 90</span></li>
        <li class="noche" data-img="{{ $a('concierto.webp') }}"><time datetime="2026-12-04"><b>04</b><span>DIC · VIE</span></time><h4>Artist Goods Launch</h4><span class="muted">Presentación de colección · 20:30</span><span class="aforo">Solo con invitación</span></li>
      </ul>

      <div class="estilos rv" aria-label="Estilos musicales">
        <span>Flamenco</span><span>Música latina</span><span>Fusión</span><span>DJ's</span><span>Performances</span>
      </div>

      <div class="acceso rv">
        <div>
          <h3>Solicita tu acceso</h3>
          <p>El Club funciona con aforo reducido. Déjanos tus datos y te avisaremos de la próxima noche que encaje contigo.</p>
        </div>
        <form class="form" id="form-club" novalidate>
          <div class="form__fila">
            <div style="display:grid;gap:8px"><label for="club-nombre">Nombre</label><input id="club-nombre" name="nombre" autocomplete="name" required></div>
            <div style="display:grid;gap:8px"><label for="club-email">Email</label><input id="club-email" name="email" type="email" autocomplete="email" required></div>
          </div>
          <div style="display:grid;gap:8px"><label for="club-interes">Lo que más te interesa</label>
            <select id="club-interes" name="interes"><option>Conciertos boutique</option><option>Jam sessions</option><option>Lanzamientos de colecciones</option><option>DJ y vocal sessions</option></select>
          </div>
          <button class="btn btn--rojo magnet" type="submit" style="justify-self:start">Solicitar acceso</button>
          <p class="form__msg" id="club-msg" role="status"></p>
        </form>
      </div>
    </div>
  </section>

  <!-- ESCENA 8 · Cita final -->
  <section class="final" id="final">
    <div class="final__bg" aria-hidden="true"><img src="{{ $a('concierto.webp') }}" alt="" id="final-img"></div>
    <div class="wrap">
      <p class="final__cita" id="cita">El producto genera <span class="clave">deseo.</span> La experiencia genera <span class="clave">pertenencia.</span> La comunidad construye <span class="clave">valor.</span></p>
    </div>
  </section>

  <!-- Pilares -->
  <section class="escena" style="padding-top:40px" aria-label="Cinco pilares">
    <div class="wrap">
      <span class="eyebrow rv" style="margin-bottom:24px">Cinco pilares, un solo universo</span>
      <div class="pilares">
        <div class="pilar rv"><span class="ficha">Producto</span><h3>Merchandising</h3><p>El núcleo comercial: piezas oficiales de cada artista.</p></div>
        <div class="pilar rv" style="--d:1"><span class="ficha">Experiencia</span><h3>The Club</h3><p>Comunidad, eventos y lanzamientos en un espacio físico.</p></div>
        <div class="pilar rv" style="--d:2"><span class="ficha">Cápsulas</span><h3>Colecciones</h3><p>Colaboraciones y ediciones limitadas.</p></div>
        <div class="pilar rv" style="--d:3"><span class="ficha">Activación</span><h3>Promotora</h3><p>Productos, eventos y fiestas que impulsan cada proyecto.</p></div>
        <div class="pilar rv" style="--d:4"><span class="ficha">Creación</span><h3>Productora</h3><p>Espectáculos y producciones con sello propio.</p></div>
      </div>
    </div>
  </section>
</main>

<footer class="pie">
  <div class="wrap">
    <div class="pie__grid">
      <div class="pie__news">
        <h4>Comunidad</h4>
        <p>Drops, ediciones limitadas y noches del Club, antes que nadie.</p>
        <form class="form" id="form-news" novalidate>
          <label for="news-email">Email</label>
          <div class="form__fila"><input id="news-email" type="email" autocomplete="email" placeholder="tu@email.com" required><button class="btn btn--rojo" type="submit">Unirme</button></div>
          <p class="form__msg" id="news-msg" role="status"></p>
        </form>
      </div>
      <div><h4>Comprar</h4><ul><li><a href="#tiendas">Tiendas de artistas</a></li><li><a href="#lineas">Colecciones</a></li><li><a href="#lineas">Ediciones limitadas</a></li><li><a href="#lineas">Novedades</a></li></ul></div>
      <div><h4>Ayuda</h4><ul><li><a href="#lineas">Envíos</a></li><li><a href="#lineas">Devoluciones</a></li><li><a href="#lineas">Fabricación bajo pedido</a></li><li><a href="#lineas">Guía de tallas</a></li></ul></div>
      <div><h4>Clave de Alma</h4><ul><li><a href="#manifiesto">Manifiesto</a></li><li><a href="#club">The Club</a></li><li><a href="#tiendas">Para artistas</a></li><li><a href="#inicio">Aviso legal y privacidad</a></li></ul></div>
    </div>
    <div class="pie__marca"><img src="{{ $a('logo.png') }}" alt="Clave de Alma" width="614" height="243"></div>
    <div class="pie__legal"><span>Clave de Alma · Premium Artists Platform · Madrid</span><span>Un solo carrito y un solo pago para todas las tiendas</span></div>
  </div>
</footer>

<div class="flota" id="flota" aria-hidden="true"><img alt="" id="flota-img"></div>
</body>
</html>
