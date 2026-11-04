/*
 * Diseño de tienda: Farruquito. Efectos portados del prototipo docs/diseno/farruquito.
 * La pasarela y la ficha de producto son comunes (storefront/app.js).
 */
import '../app.js';

(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine = matchMedia('(pointer: fine)').matches;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const A = document.body.dataset.assets || '';

  /* Cabecera sólida al bajar */
  const cab = $('#cab');
  const cabecera = () => cab && cab.classList.toggle('solida', scrollY > 30);
  addEventListener('scroll', cabecera, { passive: true }); cabecera();

  /* ---------- Revelado al hacer scroll ---------- */
  const els = $$('.rv');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.remove('pre'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -6% 0px' });
    els.forEach(el => { if (el.getBoundingClientRect().top > innerHeight * .95) { el.classList.add('pre'); io.observe(el); } });
    setTimeout(() => els.forEach(el => el.classList.remove('pre')), 10000);
    const orn = new IntersectionObserver(es => es.forEach(e => e.isIntersecting && e.target.classList.add('on')));
    $$('.ornamento').forEach(o => orn.observe(o));
  } else $$('.ornamento').forEach(o => o.classList.add('on'));

  /* ---------- Cursor dorado, inclinación y botones magnéticos ---------- */
  if (fine && !reduce) {
    const an = $('.anillo'); let ax = 0, ay = 0, tx = 0, ty = 0;
    addEventListener('pointermove', e => { tx = e.clientX; ty = e.clientY; an.style.opacity = 1; const g = e.target.closest && e.target.closest('a, button, [role="button"], .colores, .lupa-zona, .espejo, .ic__escena'); an.classList.toggle('grande', !!g); }, { passive: true });
    const sigue = () => { ax += (tx - ax) * .2; ay += (ty - ay) * .2; an.style.transform = `translate3d(${ax}px,${ay}px,0)`; requestAnimationFrame(sigue); }; sigue();
    document.addEventListener('pointermove', e => {
      const b = e.target.closest && e.target.closest('.magnet');
      $$('.magnet').forEach(m => { if (m !== b) { m.style.setProperty('--mx', '0px'); m.style.setProperty('--my', '0px'); } });
      if (b) { const r = b.getBoundingClientRect(); b.style.setProperty('--mx', `${(e.clientX - r.left - r.width / 2) * .25}px`); b.style.setProperty('--my', `${(e.clientY - r.top - r.height / 2) * .3}px`); }
      const c = e.target.closest && e.target.closest('.tilt');
      $$('.tilt').forEach(x => { if (x !== c) { x.style.setProperty('--rx', '0deg'); x.style.setProperty('--ry', '0deg'); } });
      if (c) { const r = c.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height; c.style.setProperty('--ry', `${(px - .5) * 9}deg`); c.style.setProperty('--rx', `${(.5 - py) * 9}deg`); }
    }, { passive: true });
  }


  /* Menú móvil */
  const mm = $('#menu-movil'), mb = $('#menu-btn');
  const menu = on => { if (on) { mm.hidden = false; requestAnimationFrame(() => mm.classList.add('on')); } else { mm.classList.remove('on'); setTimeout(() => { if (!mm.classList.contains('on')) mm.hidden = true; }, 700); } mb.setAttribute('aria-expanded', String(on)); };
  mb.addEventListener('click', () => menu(mb.getAttribute('aria-expanded') !== 'true'));
  $$('a', mm).forEach(a => a.addEventListener('click', () => menu(false)));

  /* Lo que sigue solo existe en la portada */
  if (!$('#portada')) return;
  /* ---------- Frase que se enciende ---------- */
  const frase = $('#frase');
  (() => {
    const out = [];
    frase.childNodes.forEach(n => {
      const oro = n.nodeType === 1;
      n.textContent.split(/\s+/).filter(Boolean).forEach(t => out.push(`<span class="w${oro ? ' oro' : ''}">${t}</span>`));
    });
    frase.innerHTML = out.join(' ');
  })();
  const fw = $$('.w', frase);

  /* ---------- Portada: luz, relieve y polvo dorado ---------- */
  const portada = $('#portada'), tallado = $('#tallado'), retrato = $('#retrato img');
  let mx = innerWidth * .72, my = innerHeight * .28, cx = mx, cy = my, autoT = 0, usandoRaton = false;
  if (fine) portada.addEventListener('pointermove', e => { const r = portada.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; usandoRaton = true; });
  portada.addEventListener('pointerleave', () => usandoRaton = false);

  const cv = $('#oro'), ctx = cv.getContext('2d');
  let W = 0, H = 0, motas = [], portadaVisible = true;
  const resize = () => {
    const dpr = Math.min(devicePixelRatio || 1, 2);
    W = portada.clientWidth; H = portada.clientHeight;
    cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    motas = Array.from({ length: Math.round(Math.min(150, W / 9)) }, () => ({ x: Math.random() * W, y: Math.random() * H, r: .4 + Math.random() * 1.5, v: .1 + Math.random() * .35, f: Math.random() * 6.28, z: Math.random() }));
  };
  const pintar = (t) => {
    ctx.clearRect(0, 0, W, H);
    ctx.globalCompositeOperation = 'lighter';
    for (const m of motas) {
      m.y -= m.v * (.5 + m.z); m.x += Math.sin(t / 1800 + m.f) * .22; m.f += .015;
      if (m.y < -6) { m.y = H + 6; m.x = Math.random() * W; }
      const d = Math.hypot(m.x - cx, m.y - cy), cerca = Math.max(0, 1 - d / (Math.max(W, H) * .45));
      const a = (.12 + cerca * .85) * (.5 + .5 * Math.sin(m.f * 2));
      ctx.fillStyle = `rgba(234,205,150,${a.toFixed(3)})`;
      ctx.beginPath(); ctx.arc(m.x, m.y, m.r * (.6 + m.z), 0, 6.283); ctx.fill();
    }
    ctx.globalCompositeOperation = 'source-over';
  };

  /* ---------- Lupa del diseño principal ---------- */
  const zona = $('#lupa-zona'), lupa = $('#lupa'), lupaImg = $('#lupa-img');
  const ZOOM = 2.6;
  const ponerLupa = src => { lupa.style.backgroundImage = `url("${src}")`; };
  ponerLupa(lupaImg.src);
  if (fine) {
    zona.addEventListener('pointerenter', () => lupa.classList.add('on'));
    zona.addEventListener('pointerleave', () => lupa.classList.remove('on'));
    zona.addEventListener('pointermove', e => {
      const r = zona.getBoundingClientRect(), x = e.clientX - r.left, y = e.clientY - r.top;
      lupa.style.left = `${x - 95}px`; lupa.style.top = `${y - 95}px`;
      lupa.style.backgroundSize = `${r.width * ZOOM}px ${r.height * ZOOM}px`;
      lupa.style.backgroundPosition = `${-(x * ZOOM - 95)}px ${-(y * ZOOM - 95)}px`;
    });
  }

  /* ---------- Un diseño, dos colores ---------- */
  const colores = $('#colores'), colBase = $('#col-base'), colEncima = $('#col-encima'), colEtq = $('#col-etq');
  const VERS = { negro: { src: A + 'art-01a-sepia.webp', etq: 'Negro profundo · tinta crema' }, ecru: { src: A + 'art-09-silencio.webp', etq: 'Ecru suave · tinta negra' } };
  let colorActual = 'negro', colorAuto = true, animando = false;
  const cambiarColor = (c, x = 50, y = 50) => {
    if (c === colorActual || animando) return;
    animando = true;
    colEncima.src = VERS[c].src;
    colores.style.setProperty('--cx', x + '%'); colores.style.setProperty('--cy', y + '%');
    colores.classList.remove('anim'); void colores.offsetWidth; colores.classList.add('anim');
    $$('.muestra').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.color === c)));
    colEtq.textContent = VERS[c].etq;
    setTimeout(() => { colBase.src = VERS[c].src; colores.classList.remove('anim'); animando = false; }, reduce ? 0 : 1180);
    colorActual = c;
  };
  $$('.muestra').forEach(b => b.addEventListener('click', () => { colorAuto = false; cambiarColor(b.dataset.color, b.dataset.color === 'negro' ? 0 : 100, 50); }));
  colores.addEventListener('click', e => { colorAuto = false; const r = colores.getBoundingClientRect(); cambiarColor(colorActual === 'negro' ? 'ecru' : 'negro', (e.clientX - r.left) / r.width * 100, (e.clientY - r.top) / r.height * 100); });
  if (!reduce) { const ciclo = setInterval(() => { if (colorAuto) cambiarColor(colorActual === 'negro' ? 'ecru' : 'negro', 50, 38); else clearInterval(ciclo); }, 4200); }

  /* ---------- Espejo del origen ---------- */
  const espejo = $('#espejo');
  let eObj = 50, eCur = 50, eHover = false;
  espejo.addEventListener('pointermove', e => { const r = espejo.getBoundingClientRect(); eObj = (e.clientX - r.left) / r.width * 100; eHover = true; });
  espejo.addEventListener('pointerleave', () => eHover = false);

  /* ---------- Disco del compás, mantra y lookbook con el scroll ---------- */
  const disco = $('#disco'), discoSvg = $('svg', disco), discoImg = $('.disco__img img', disco), mantra = $$('#mantra span');
  const figs = $$('#look figure');
  const alScroll = () => {
    const r = disco.getBoundingClientRect(), p = (innerHeight - r.top) / (innerHeight + r.height);
    discoSvg.style.transform = `rotate(${p * 220}deg)`;
    discoImg.style.setProperty('--giro2', `${p * -40}deg`);
    const activo = Math.floor(Math.max(0, Math.min(.999, p)) * mantra.length);
    mantra.forEach((s, k) => s.classList.toggle('on', k === activo));
    const rf = frase.getBoundingClientRect(), pf = Math.max(0, Math.min(1, (innerHeight * .85 - rf.top) / (rf.height + innerHeight * .35)));
    fw.forEach((w, k) => w.classList.toggle('on', reduce || pf > k / fw.length));
    if (!reduce) figs.forEach(f => { const rr = f.getBoundingClientRect(); f.querySelector('img').style.setProperty('--py', `${(rr.top + rr.height / 2 - innerHeight / 2) * -parseFloat(f.dataset.v)}px`); });
  };

  /* ---------- Bucle ---------- */
  new IntersectionObserver(([e]) => portadaVisible = e.isIntersecting).observe(portada);
  const bucle = t => {
    if (!usandoRaton) { autoT = t; mx = W * (.62 + Math.sin(t / 3200) * .18); my = H * (.3 + Math.cos(t / 4100) * .12); }
    cx += (mx - cx) * .08; cy += (my - cy) * .08;
    if (portadaVisible) {
      portada.style.setProperty('--lx', `${cx / W * 100}%`); portada.style.setProperty('--ly', `${cy / H * 100}%`);
      const tr = tallado.getBoundingClientRect(), pr = portada.getBoundingClientRect();
      const tx = tr.left - pr.left + tr.width / 2, ty = tr.top - pr.top + tr.height / 2;
      const dx = tx - cx, dy = ty - cy, len = Math.hypot(dx, dy) || 1;
      tallado.style.setProperty('--sx', (dx / len * 1.6).toFixed(2)); tallado.style.setProperty('--sy', (dy / len * 1.6).toFixed(2));
      retrato.style.setProperty('--rx', `${(cx / W - .5) * -14}px`); retrato.style.setProperty('--ry', `${(cy / H - .5) * -8}px`);
      if (!document.hidden) pintar(t);
    }
    if (!eHover) eObj = 50 + Math.sin(t / 2600) * 30;
    eCur += (eObj - eCur) * .07; espejo.style.setProperty('--e', eCur.toFixed(2) + '%');
    requestAnimationFrame(bucle);
  };
  addEventListener('scroll', alScroll, { passive: true });
  addEventListener('resize', () => { resize(); alScroll(); });
  resize(); alScroll();
  if (reduce) pintar(0); else requestAnimationFrame(bucle);

  /* ---------- Iconic: moneda 3D ---------- */
  const moneda = $('#moneda'), escenaIc = $('.ic__escena');
  let vuelta = 0;
  moneda.addEventListener('click', () => { vuelta += 180; moneda.style.setProperty('--vuelta', vuelta + 'deg'); });
  if (fine && !reduce) {
    escenaIc.addEventListener('pointermove', e => {
      const r = escenaIc.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      moneda.style.setProperty('--mry', `${(px - .5) * 36}deg`); moneda.style.setProperty('--mrx', `${(.5 - py) * 30}deg`);
      moneda.style.setProperty('--bx', `${px * 100}%`); moneda.style.setProperty('--by', `${py * 100}%`);
    });
    escenaIc.addEventListener('pointerleave', () => { moneda.style.setProperty('--mry', '0deg'); moneda.style.setProperty('--mrx', '0deg'); });
  }
  const avFondo = $('.av__fondo img'), avSec = $('#artist-voice');
  if (!reduce) addEventListener('scroll', () => { const r = avSec.getBoundingClientRect(); avFondo.style.setProperty('--avp', `${r.top * -.18}px`); }, { passive: true });

})();
