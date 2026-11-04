{{-- Portada de Farruquito: secciones del diseño + la pasarela común de productos (#coleccion). --}}
@extends('storefront.designs.farruquito.layout')

@php
    $a = fn (string $file): string => \App\Storefront\Urls::designAsset('farruquito', $file);
    $porRef = collect($cards)->keyBy('reference');
    // Enlace a la ficha de un modelo; si el artículo no está publicado, a la pasarela.
    $p = fn (string $ref): string => $porRef[$ref]['url'] ?? '#coleccion';
@endphp

@section('content')
  <!-- PORTADA -->
  <section class="portada" id="portada" aria-label="Colección On Stage">
    <p class="tallado" id="tallado" aria-hidden="true"><span>On</span><span>Stage</span><small>Collection</small></p>
    <div class="retrato" id="retrato"><img src="{{ $a('retrato.webp') }}" alt="Farruquito con la camiseta Serás Farruquito, mirando al suelo, manos sobre el pecho"></div>
    <div class="portada__luz" aria-hidden="true"></div>
    <div class="portada__luz2" aria-hidden="true"></div>
    <canvas id="oro" aria-hidden="true"></canvas>
    <div class="wrap">
      <div class="portada__in">
        <span class="etq">Tienda oficial · Tres colecciones</span>
        <img class="portada__logo" src="{{ $a('logo-farruquito.png') }}" alt="Serás Farruquito · El Musical" width="897" height="364">
        <p>Dos lenguajes. Una misma verdad. El arte hecho legado.</p>
        <div class="portada__ctas">
          <a class="btn btn--oro magnet" href="#colecciones">Ver las colecciones</a>
          <a class="btn magnet" href="#principal">El diseño principal</a>
        </div>
        <div class="portada__datos"><span>Algodón 280 g/m²</span><span>Corte oversize unisex</span><span>Fabricado en 5–7 días</span></div>
      </div>
    </div>
  </section>

  <div class="marq" aria-hidden="true"><div class="marq__pista">
    <span>Origen</span><span>Pasión</span><span>Movimiento</span><span>Compás</span><span>Memoria</span><span>Legado</span>
    <span>Origen</span><span>Pasión</span><span>Movimiento</span><span>Compás</span><span>Memoria</span><span>Legado</span>
  </div></div>

  <!-- TRES COLECCIONES -->
  <section class="escena hub" id="colecciones">
    <div class="wrap">
      <div class="titulo-sec rv">
        <span class="etq">Tienda oficial de Farruquito</span>
        <h2 class="grabado">Tres colecciones, un mismo origen</h2>
        <p>El escenario, la voz de la calle y el emblema bordado. Elige por dónde entrar.</p>
      </div>
      <div class="hub__grid">
        <a class="hub__card hub--os tilt rv" href="#principal">
          <img src="{{ $a('hero.webp') }}" alt="">
          <span class="hub__n">01</span>
          <span class="hub__t hub__t--os">On Stage</span>
          <span class="hub__s">Serás Farruquito · El Musical</span>
          <span class="hub__go">Entrar</span>
        </a>
        <a class="hub__card hub--av tilt rv" href="#artist-voice" style="--d:1">
          <img src="{{ $a('av-calle.webp') }}" alt="">
          <span class="hub__n">02</span>
          <span class="hub__t hub__t--av">Artist Voice</span>
          <span class="hub__s">Expresa. Crea. Trasciende.</span>
          <span class="hub__go">Entrar</span>
        </a>
        <a class="hub__card hub--ic tilt rv" href="#iconic" style="--d:2">
          <img src="{{ $a('ic-m08.webp') }}" alt="">
          <span class="hub__n">03</span>
          <span class="hub__t hub__t--ic">Iconic</span>
          <span class="hub__s">Built on heritage. Made to be iconic.</span>
          <span class="hub__go">Entrar</span>
        </a>
      </div>
    </div>
  </section>

  <!-- MANIFIESTO -->
  <section class="escena">
    <div class="wrap manif">
      <span class="etq rv">Serás Farruquito · El Musical</span>
      <blockquote id="frase">No es una pose. Es un <span class="oro">estado del alma.</span></blockquote>
      <svg class="ornamento" viewBox="0 0 120 18" aria-hidden="true"><path d="M2 9h40M78 9h40M60 2l7 7-7 7-7-7zM46 9c4-5 8-5 10 0M74 9c-4-5-8-5-10 0"/></svg>
      <div class="lemas rv"><span>Compás que recuerda</span><span>Memoria que transforma</span><span>Origen que inspira</span></div>
    </div>
  </section>

  <!-- DISEÑO PRINCIPAL -->
  <section class="escena" id="principal" style="padding-top:0">
    <div class="wrap">
      <div class="principal">
        <div class="marco rv">
          <span class="marco__tag">Mod. 01</span>
          <div class="lupa-zona" id="lupa-zona"><img src="{{ $a('art-01a-sepia.webp') }}" alt="Diseño Mod. 01: figura de espaldas con los brazos abiertos y su sombra proyectada, con el logotipo Serás Farruquito" id="lupa-img"><div class="lupa" id="lupa"></div></div>
        </div>
        <div class="principal__txt">
          <span class="etq rv">Diseño principal</span>
          <h2 class="grabado rv">El don que se revela</h2>
          <p class="sub rv">Inspirado en la fuerza del origen.</p>
          <p class="rv">Una figura de espaldas, con los brazos abiertos al cielo en un gesto de entrega total. Su sombra se eleva más grande que él, como símbolo del don que lo trasciende. En la espalda, un símbolo propio inspirado en la raíz: arte, linaje y espiritualidad.</p>
          <p class="cita rv">“No es una pose. Es un estado del alma.”</p>
          <div class="detalles rv">
            <a class="detalle" href="{{ $p('FQ-OS-01') }}" style="text-decoration:none"><span class="img"><img src="{{ $a('det-tipo.webp') }}" alt="Detalle de la tipografía manuscrita"></span><b>Tipografía</b><span>Tinta crema de alta densidad</span></a>
            <a class="detalle" href="{{ $p('FQ-OS-01') }}" style="text-decoration:none"><span class="img"><img src="{{ $a('det-simbolo.webp') }}" alt="Detalle del símbolo de la espalda"></span><b>Símbolo</b><span>La raíz, el arte y la espiritualidad</span></a>
            <a class="detalle" href="{{ $p('FQ-OS-01') }}" style="text-decoration:none"><span class="img"><img src="{{ $a('det-textura.webp') }}" alt="Detalle de la textura del algodón"></span><b>Textura</b><span>Algodón 280 g/m², corte oversize</span></a>
          </div>
          <div><a class="btn btn--oro magnet" href="{{ $p('FQ-OS-01') }}">Comprar Mod. 01</a></div>
        </div>
      </div>

      <div class="sellos rv">
        <div class="sello"><svg viewBox="0 0 34 34"><path d="M17 4c3 5 3 9 0 13-3-4-3-8 0-13zM17 17c5-3 9-3 13 0-4 3-8 3-13 0zM17 17c-5-3-9-3-13 0 4 3 8 3 13 0zM17 17c3 5 3 9 0 13-3-4-3-8 0-13z"/></svg><div><b>On Stage</b><span>Serás Farruquito</span></div></div>
        <div class="sello"><svg viewBox="0 0 34 34"><circle cx="17" cy="13" r="8"/><path d="M17 21v10M12 31h10"/></svg><div><b>Premium</b><span>Algodón 280 g/m² 100 % natural</span></div></div>
        <div class="sello"><svg viewBox="0 0 34 34"><path d="M11 5 5 9l3 6 3-1v15h12V14l3 1 3-6-6-4c-1 3-3 4-6 4s-5-1-6-4z"/></svg><div><b>Oversize</b><span>Unisex · atemporal</span></div></div>
        <div class="sello"><svg viewBox="0 0 34 34"><circle cx="17" cy="17" r="12"/><circle cx="12" cy="13" r="2"/><circle cx="19" cy="10" r="2"/><circle cx="23" cy="16" r="2"/></svg><div><b>Colores</b><span>Negro profundo · tinta crema</span></div></div>
        <div class="sello"><svg viewBox="0 0 34 34"><path d="M3 17h4l2-6 3 12 3-16 3 20 3-14 3 8 2-4h5"/></svg><div><b>Edición limitada</b><span>Para quien siente la música</span></div></div>
      </div>
    </div>
  </section>

  <!-- UN DISEÑO, DOS COLORES -->
  <section class="escena" style="padding-top:0">
    <div class="wrap">
      <div class="titulo-sec rv">
        <span class="etq">Mod. 01 · Mod. 09</span>
        <h2 class="grabado">Un diseño, dos colores</h2>
        <p>La misma figura frente a su sombra. Elige el color y mira cómo cambia la pieza.</p>
      </div>
      <div class="colores-cols">
        <aside class="rv"><span class="etq">Mod. 01 · La esencia</span><h3>Negro profundo</h3><p>Camiseta negra con tinta crema de alta densidad. La sombra se ilumina en sepia.</p><button class="muestra" type="button" data-color="negro" aria-pressed="true"><i style="background:#0b0907"></i>Negro</button></aside>
        <div class="colores rv" id="colores" role="img" aria-label="Diseño Serás Farruquito en negro o en ecru, según el color elegido">
          <img class="base" id="col-base" src="{{ $a('art-01a-sepia.webp') }}" alt="">
          <img class="encima" id="col-encima" src="{{ $a('art-09-silencio.webp') }}" alt="">
          <span class="colores__etq" id="col-etq">Negro profundo · tinta crema</span>
        </div>
        <aside class="rv"><span class="etq">Mod. 09 · El don en silencio</span><h3>Ecru suave</h3><p>Sudadera ecru con tinta negra: la misma obra en sobriedad y calma.</p><button class="muestra" type="button" data-color="ecru" aria-pressed="false"><i style="background:#efe6d6"></i>Ecru</button></aside>
      </div>
    </div>
  </section>

  <!-- EL COMPÁS -->
  <section class="escena" id="compas" style="padding-top:0">
    <div class="wrap compas">
      <div class="disco rv" id="disco">
        <div class="disco__pulso"></div><div class="disco__pulso"></div>
        <div class="disco__img"><img src="{{ $a('art-08-pulso.webp') }}" alt="Mod. 08: botas flamencas y manos en una composición circular"></div>
        <svg viewBox="0 0 560 560" aria-hidden="true"><defs><path id="circulo" d="M280 280m-236 0a236 236 0 1 1 472 0a236 236 0 1 1-472 0"/></defs><text><textPath href="#circulo" startOffset="0" textLength="1460" lengthAdjust="spacing">BAILA · AMA · SIENTE · CREE · BAILA · CREA · EVOLUCIONA ·</textPath></text></svg>
      </div>
      <div class="compas__txt">
        <span class="etq rv">Mod. 08 · Sudadera de felpa</span>
        <h2 class="grabado rv">El pulso del compás</h2>
        <p class="rv">Un círculo que no se cierra: se expande. Las botas evocan el oficio, las manos la entrega, y las palabras giran como una consigna interior. En esta pieza el arte no se contempla: se habita.</p>
        <div class="mantra rv" id="mantra"><span>Baila</span><span>Ama</span><span>Siente</span><span>Cree</span><span>Crea</span><span>Evoluciona</span></div>
        <p class="cita rv">“Todo compás verdadero nace del alma y regresa a ella convertido en destino.”</p>
        <div class="rv"><a class="btn magnet" href="{{ $p('FQ-OS-08') }}">Ver la sudadera</a></div>
      </div>
    </div>
  </section>

  <!-- ORIGEN -->
  <section class="escena" style="padding-top:0">
    <div class="wrap origen">
      <div class="espejo rv" id="espejo">
        <img class="der" src="{{ $a('art-06-espejo.webp') }}" alt="">
        <img class="izq" src="{{ $a('art-06-espejo.webp') }}" alt="Mod. 06: doble retrato, infancia y madurez, con el logotipo Serás Farruquito">
        <span class="espejo__etq i">Origen</span><span class="espejo__etq d">Destino</span>
      </div>
      <div class="origen__txt">
        <span class="etq rv">Mod. 06 · Camiseta ecru</span>
        <h2 class="grabado rv">El espejo del origen</h2>
        <p class="rv">Dos tiempos. Un alma. Un mismo camino. El retrato dividido revela el origen que nos forma y el destino que elegimos abrazar. Mueve el cursor sobre la imagen para recorrerlo.</p>
        <p class="cita rv">“Lo que somos hoy es lo que otros soñaron ayer.”</p>
        <div class="rv"><a class="btn magnet" href="{{ $p('FQ-OS-06') }}">Ver la camiseta</a></div>
      </div>
    </div>
  </section>

  <!-- ARTIST VOICE -->
  <section class="av" id="artist-voice">
    <div class="av__banda">
      <div class="av__fondo" aria-hidden="true"><img src="{{ $a('av-calle.webp') }}" alt=""></div>
      <div class="wrap av__cab">
        <span class="av__etq">Colección 02 · Farruquito since 1982</span>
        <h2 class="av__titulo"><span>Artist</span><span>Voice</span><em>Collection</em></h2>
        <p class="av__lema">Expresa. Crea. Trasciende. Tu voz es tu legado.</p>
        <a class="btn av__btn magnet" href="#coleccion" data-show-collection="artist-voice">Ver la colección</a>
      </div>
    </div>
    <div class="av__marq" aria-hidden="true"><div class="av__pista"><span>Alma flamenca</span><span>Bailo… luego existo</span><span>Se hace camino al andar</span><span>Trata de volar</span><span>Cuando el alma grita</span><span>Bailo mejor que Farruquito</span><span>Que la vida te pille bailando</span><span>St@y Farruquito</span><span>Alma flamenca</span><span>Bailo… luego existo</span><span>Se hace camino al andar</span><span>Trata de volar</span><span>Cuando el alma grita</span><span>Bailo mejor que Farruquito</span><span>Que la vida te pille bailando</span><span>St@y Farruquito</span></div></div>
    <div class="wrap av__muro-wrap">
      <div class="av__intro rv">
        <h3>La voz convertida en cartel</h3>
        <p>Un sistema gráfico que convierte la voz, el gesto y el imaginario de Farruquito en piezas de calle: camisetas boxy oversize, sudaderas, hoodies y gorras. Pulsa un cartel para ver su pieza.</p>
      </div>
      <div class="av__muro" id="av-muro">
        <a class="cartel rv" href="{{ $p('FQ-AV-01') }}" style="--r:-3.5deg;--d:0" aria-label="Ver la pieza Alma flamenca"><img src="{{ $a('av-d01-alma-flamenca.webp') }}" alt="Diseño Alma flamenca"><span>Alma flamenca</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-02') }}" style="--r:2.5deg;--d:1" aria-label="Ver la pieza Bailo… luego existo"><img src="{{ $a('av-d02-bailo-luego-existo.webp') }}" alt="Diseño Bailo… luego existo"><span>Bailo… luego existo</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-03') }}" style="--r:-1.5deg;--d:2" aria-label="Ver la pieza Se hace camino"><img src="{{ $a('av-d03-se-hace-camino.webp') }}" alt="Diseño Se hace camino"><span>Se hace camino</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-04') }}" style="--r:3deg;--d:3" aria-label="Ver la pieza Trata de volar"><img src="{{ $a('av-d04-trata-de-volar.webp') }}" alt="Diseño Trata de volar"><span>Trata de volar</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-13') }}" style="--r:2deg;--d:0" aria-label="Ver la pieza Cuando el alma grita"><img src="{{ $a('av-d05-cuando-el-alma-grita.webp') }}" alt="Diseño Cuando el alma grita"><span>Cuando el alma grita</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-06') }}" style="--r:-2.5deg;--d:1" aria-label="Ver la pieza Bailo mejor que Farruquito"><img src="{{ $a('av-d06-bailo-mejor.webp') }}" alt="Diseño Bailo mejor que Farruquito"><span>Bailo mejor que Farruquito</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-05') }}" style="--r:1.5deg;--d:2" aria-label="Ver la pieza Que la vida te pille bailando"><img src="{{ $a('av-d07-que-la-vida.webp') }}" alt="Diseño Que la vida te pille bailando"><span>Que la vida te pille bailando</span></a>
        <a class="cartel rv" href="{{ $p('FQ-AV-07') }}" style="--r:-3deg;--d:3" aria-label="Ver la pieza St@y Farruquito"><img src="{{ $a('av-d08-stay.webp') }}" alt="Diseño St@y Farruquito"><span>St@y Farruquito</span></a>
      </div>
    </div>
  </section>

  <!-- ICONIC -->
  <section class="ic" id="iconic">
    <div class="ic__fondo" aria-hidden="true">Iconic</div>
    <div class="wrap ic__grid">
      <div class="ic__txt">
        <span class="ic__etq rv">Colección 03 · Farruquito since 1982</span>
        <h2 class="ic__titulo rv">Iconic</h2>
        <p class="ic__lema rv">Built on heritage. Made to be iconic.</p>
        <p class="ic__desc rv">Atemporal en el diseño y con raíz en la cultura. Cuatro códigos gráficos que nacen de un mismo legado: el emblema oficial, su versión bordada, la variación tipográfica y la de la sombra.</p>
        <ul class="ic__valores rv"><li>Timeless legacy</li><li>Rooted worldwide</li><li>Premium quality</li></ul>
        <div class="rv"><a class="btn ic__btn magnet" href="#coleccion" data-show-collection="iconic">Ver la colección</a></div>
      </div>
      <div class="ic__escena rv">
        <svg class="ic__hilo" viewBox="0 0 400 400" aria-hidden="true"><circle cx="200" cy="200" r="188"/><circle cx="200" cy="200" r="176"/></svg>
        <button class="moneda" id="moneda" type="button" aria-label="Dar la vuelta al emblema">
          <span class="moneda__cara"><img src="{{ $a('ic-d01-emblema.webp') }}" alt="Emblema Farruquito bordado"></span>
          <span class="moneda__cara moneda__cara--dorso"><img src="{{ $a('ic-d02-words.webp') }}" alt="Emblema Farruquito con palabras"></span>
        </button>
        <span class="ic__pista">Mueve el cursor · pulsa para darle la vuelta</span>
      </div>
    </div>
    <div class="wrap">
      <div class="codigos">
        <article class="codigo rv" style="--d:0"><div class="codigo__img"><img src="{{ $a('ic-d01-emblema.webp') }}" alt="Código 01 Classic Embroidery"></div><span class="codigo__n">01</span><h3>Classic Embroidery</h3><p>El logo oficial de Farruquito, refinado a partir del original del artista. Define la identidad de la colección.</p><span class="codigo__tec">Efecto bordado</span></article>
        <article class="codigo rv" style="--d:1"><div class="codigo__img"><img src="{{ $a('ic-d02-words.webp') }}" alt="Código 02 Classic Words"></div><span class="codigo__n">02</span><h3>Classic Words</h3><p>El emblema escrito con las palabras del universo del artista: baile, alma, herencia, pasión y duende.</p><span class="codigo__tec">Bordado o plano</span></article>
        <article class="codigo rv" style="--d:2"><div class="codigo__img"><img src="{{ $a('ic-d03-signature.webp') }}" alt="Código 03 Classic Signature"></div><span class="codigo__n">03</span><h3>Classic Signature</h3><p>El emblema de palabras unido a la firma de Farruquito y el año de origen, 1982.</p><span class="codigo__tec">Bordado o plano</span></article>
        <article class="codigo rv" style="--d:3"><div class="codigo__img"><img src="{{ $a('ic-d04-shadow.webp') }}" alt="Código 04 Classic Shadow"></div><span class="codigo__n">04</span><h3>Classic Shadow</h3><p>La versión más conceptual: el símbolo convertido en luz, sombra y presencia escénica.</p><span class="codigo__tec">Estampado plano</span></article>
      </div>
    </div>
  </section>


  @include('storefront.partials.showcase', ['id' => 'coleccion', 'heading' => 'Todas las piezas', 'intro' => 'Filtra por colección o por tipo de prenda. Un solo carrito para todo Clave de Alma.'])

  <!-- TOTES: las piezas reales de la pasarela -->
  @php($totes = collect($cards)->filter(fn ($c) => str_starts_with((string) $c['reference'], 'FQ-OS-TOTE'))->values())
  @if ($totes->isNotEmpty())
  <section class="escena" style="padding-top:0">
    <div class="wrap">
      <div class="titulo-sec rv">
        <span class="etq">Edición especial</span>
        <h2 class="grabado">Tote bags</h2>
        <p>Cuatro miradas, un mismo origen. La bolsa de cada día convertida en objeto de escena.</p>
      </div>
      <div class="totes">
        @foreach ($totes as $k => $tote)
          <div class="tote rv" style="--d:{{ $k }}">
            <a class="marco" href="{{ $tote['url'] }}" style="background:#0d0a07" aria-label="Ver tote bag {{ $tote['name'] }}"><img src="{{ $tote['image'] }}" alt="Tote bag {{ $tote['name'] }}"></a>
            <h3>{{ $tote['name'] }}</h3>
            @include('storefront.partials.price', ['price' => $tote['price'], 'discount' => $tote['discount']])
          </div>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  <!-- LOOKBOOK -->
  <section class="escena" id="lookbook" style="padding-top:0">
    <div class="wrap">
      <div class="titulo-sec rv">
        <span class="etq">Lookbook</span>
        <h2 class="grabado">Llevar el arte puesto</h2>
      </div>
      <div class="look" id="look">
        <div class="col">
          <figure data-v=".10"><img src="{{ $a('look-collage-a.webp') }}" alt="Farruquito con la camiseta Mod. 01, apoyado en una pared"><figcaption>Mod. 01 · La esencia</figcaption></figure>
          <figure data-v=".05"><img src="{{ $a('look-02.webp') }}" alt="Dos personas con la camiseta Mod. 02 de colores"><figcaption>Mod. 02 · El don hecho color</figcaption></figure>
        </div>
        <div class="col">
          <figure data-v="-.08"><img src="{{ $a('look-06.webp') }}" alt="Mujer con la camiseta ecru Mod. 06"><figcaption>Mod. 06 · El espejo del origen</figcaption></figure>
          <figure data-v="-.04"><img src="{{ $a('look-collage-c.webp') }}" alt="Grupo caminando por la calle con prendas de la colección"><figcaption>On Stage en la calle</figcaption></figure>
        </div>
        <div class="col">
          <figure data-v=".06"><img src="{{ $a('look-09.webp') }}" alt="Farruquito con la sudadera ecru Mod. 09"><figcaption>Mod. 09 · El don en silencio</figcaption></figure>
          <figure data-v=".12"><img src="{{ $a('look-01c.webp') }}" alt="Hombre con la camiseta Mod. 01C ante un sol rojo"><figcaption>Mod. 01C · La luz del duende</figcaption></figure>
        </div>
      </div>
    </div>
  </section>

  <section class="cierre">
    <div class="wrap">
      <svg class="ornamento" viewBox="0 0 120 18" aria-hidden="true"><path d="M2 9h40M78 9h40M60 2l7 7-7 7-7-7zM46 9c4-5 8-5 10 0M74 9c-4-5-8-5-10 0"/></svg>
      <h2 class="grabado rv" style="margin-top:22px">Origen · Pasión · Movimiento</h2>
      <p class="rv">El alma responde cuando el arte llama.</p>
      <div class="ctas rv"><a class="btn btn--oro magnet" href="#coleccion">Elegir mi pieza</a></div>
    </div>
  </section>
@endsection
