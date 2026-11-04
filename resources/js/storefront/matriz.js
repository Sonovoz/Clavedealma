/*
 * Matriz de Clave de Alma: efectos portados del prototipo docs/diseno/matriz.
 * Sin productos ni carrito de ejemplo: las tiendas reales llegan del servidor.
 */
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine = matchMedia('(pointer: fine)').matches;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* Título: dividir en letras */
  let i = 0;
  $$('[data-split]').forEach(el => {
    el.innerHTML = [...el.textContent].map(c => c === ' ' ? ' ' : `<span class="ch" style="--i:${i++}">${c}</span>`).join('');
  });

  /* Palabras de los titulares */
  $$('.palabras').forEach(el => {
    let k = 0;
    const walk = node => {
      [...node.childNodes].forEach(n => {
        if (n.nodeType === 3) {
          const frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(t => {
            if (!t) return;
            if (/^\s+$/.test(t)) { frag.append(t); return; }
            const s = document.createElement('span'); s.className = 'w'; s.style.setProperty('--i', k++); s.textContent = t; frag.append(s);
          });
          n.replaceWith(frag);
        } else if (n.nodeType === 1) walk(n);
      });
    };
    walk(el);
  });

  /* Revelado al entrar en pantalla: solo se oculta lo que está por debajo del pliegue */
  const toReveal = $$('.rv, .palabras');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.remove('pre'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px' });
    toReveal.forEach(el => { if (el.getBoundingClientRect().top > innerHeight * .92) { el.classList.add('pre'); io.observe(el); } });
    setTimeout(() => toReveal.forEach(el => el.classList.remove('pre')), 9000);
  }

  /* Cabecera, progreso */
  const cab = $('#cab'), prog = $('.progreso');
  let lastY = scrollY;
  const onScrollHeader = () => {
    const y = scrollY;
    cab.classList.toggle('is-solid', y > 40);
    cab.classList.toggle('is-hidden', y > 400 && y > lastY && !document.body.classList.contains('lock'));
    lastY = y;
    const max = document.documentElement.scrollHeight - innerHeight;
    prog.style.transform = `scaleX(${max > 0 ? y / max : 0})`;
  };

  /* Linterna y haz que siguen al cursor */
  const lin = $('.linterna'), haz = $('#haz');
  let mx = innerWidth / 2, my = innerHeight / 3, lx = mx, ly = my;
  if (fine && !reduce) {
    addEventListener('pointermove', e => { mx = e.clientX; my = e.clientY; lin.style.opacity = 1; }, { passive: true });
    document.addEventListener('pointerleave', () => lin.style.opacity = 0);
  }

  /* Polvo y humo en el haz */
  const cv = $('#polvo'), ctx = cv.getContext('2d');
  const hero = $('#hero');
  let W = 0, H = 0, dpr = 1, parts = [], smoke = [], heroVisible = true;
  const resize = () => {
    dpr = Math.min(devicePixelRatio || 1, 2);
    W = hero.clientWidth; H = hero.clientHeight;
    cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const n = Math.round(Math.min(220, W / 6));
    parts = Array.from({ length: n }, () => newPart(true));
    smoke = Array.from({ length: 9 }, (_, k) => ({ x: W * (.25 + Math.random() * .5), y: H * (.35 + Math.random() * .6), r: 160 + Math.random() * 260, vx: (Math.random() - .5) * .15, a: .025 + Math.random() * .03, t: k * 40 }));
  };
  const newPart = (any) => ({ x: W / 2 + (Math.random() - .5) * W * .7, y: any ? Math.random() * H : H + 10, z: Math.random(), v: .08 + Math.random() * .35, s: .4 + Math.random() * 1.6, tw: Math.random() * 6.28 });
  const draw = (t) => {
    ctx.clearRect(0, 0, W, H);
    const rot = parseFloat(getComputedStyle(haz).getPropertyValue('--rot')) || 0;
    const ax = W / 2, rad = rot * Math.PI / 180;
    ctx.globalCompositeOperation = 'screen';
    smoke.forEach(s => {
      s.x += s.vx; s.t += .004;
      const g = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r);
      const a = s.a * (0.7 + 0.3 * Math.sin(s.t));
      g.addColorStop(0, `rgba(230,226,220,${a})`); g.addColorStop(1, 'rgba(230,226,220,0)');
      ctx.fillStyle = g; ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 6.283); ctx.fill();
      if (s.x < -s.r) s.x = W + s.r; if (s.x > W + s.r) s.x = -s.r;
    });
    parts.forEach(p => {
      p.y -= p.v * (0.6 + p.z); p.x += Math.sin((t / 1600) + p.tw) * .15; p.tw += .02;
      if (p.y < -10) Object.assign(p, newPart(false));
      // dentro del cono (que gira con --rot)
      const dy = p.y, cx = ax + Math.tan(rad) * -dy, half = (dy / H) * W * .34 + 8;
      const d = Math.abs(p.x - cx) / half;
      if (d > 1) return;
      const a = (1 - d) * (0.35 + 0.65 * Math.abs(Math.sin(p.tw))) * (1 - dy / H * .55);
      ctx.fillStyle = `rgba(255,250,240,${a})`;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.s * (0.5 + p.z), 0, 6.283); ctx.fill();
    });
    ctx.globalCompositeOperation = 'source-over';
  };

  /* Revelado con foco en la bailaora */
  const rev = $('#revelado');
  let rvTarget = { x: 50, y: 40 }, rvCur = { x: 50, y: 40 }, rvHover = false;
  rev.addEventListener('pointermove', e => { const r = rev.getBoundingClientRect(); rvTarget = { x: (e.clientX - r.left) / r.width * 100, y: (e.clientY - r.top) / r.height * 100 }; rvHover = true; });
  rev.addEventListener('pointerleave', () => rvHover = false);

  /* Tarjetas con inclinación 3D */
  if (fine && !reduce) $$('.tilt').forEach(c => {
    c.addEventListener('pointermove', e => {
      const r = c.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      c.style.setProperty('--ry', `${(px - .5) * 8}deg`); c.style.setProperty('--rx', `${(.5 - py) * 8}deg`);
      c.style.setProperty('--gx', `${px * 100}%`); c.style.setProperty('--gy', `${py * 100}%`);
    });
    c.addEventListener('pointerleave', () => { c.style.setProperty('--rx', '0deg'); c.style.setProperty('--ry', '0deg'); });
  });

  /* Botones magnéticos */
  if (fine && !reduce) document.addEventListener('pointermove', e => {
    const b = e.target.closest && e.target.closest('.magnet');
    $$('.magnet').forEach(m => { if (m !== b) { m.style.setProperty('--mx', '0px'); m.style.setProperty('--my', '0px'); } });
    if (!b) return;
    const r = b.getBoundingClientRect();
    b.style.setProperty('--mx', `${(e.clientX - r.left - r.width / 2) * .28}px`);
    b.style.setProperty('--my', `${(e.clientY - r.top - r.height / 2) * .35}px`);
  }, { passive: true });

  /* Filtro de tiendas por estilo */
  $$('.chip').forEach(ch => ch.addEventListener('click', () => {
    $$('.chip').forEach(c => c.setAttribute('aria-pressed', String(c === ch)));
    const g = ch.dataset.g;
    $$('.tienda').forEach(t => t.classList.toggle('is-off', g !== 'todos' && !t.dataset.g.split(' ').includes(g) && t.dataset.g !== 'todos'));
  }));

  /* Líneas: scroll horizontal fijado */
  const lineas = $('#lineas'), pista = $('#pista'), lineaN = $('#linea-n');
  const panelImgs = $$('.panel__img img');
  const desk = matchMedia('(min-width: 901px)');
  const onLineas = () => {
    if (!desk.matches) return;
    const r = lineas.getBoundingClientRect();
    const total = lineas.offsetHeight - innerHeight;
    const p = Math.min(1, Math.max(0, -r.top / total));
    const dist = pista.scrollWidth - innerWidth + parseFloat(getComputedStyle(pista).paddingLeft);
    pista.style.transform = `translate3d(${-p * dist}px,0,0)`;
    lineaN.textContent = Math.min(5, 1 + Math.floor(p * 5 * .999));
    panelImgs.forEach((im, k) => im.style.setProperty('--px', `${(p * 5 - k) * -24}px`));
  };

  /* Edición limitada: barra y cuenta atrás (ejemplo, relativa a ahora) */
  const fin = Date.now() + ((3 * 24 + 7) * 3600 + 12 * 60) * 1000;
  const tick = () => {
    let s = Math.max(0, Math.floor((fin - Date.now()) / 1000));
    const v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
    $$('#cuenta [data-u]').forEach(b => b.textContent = String(v[b.dataset.u]).padStart(2, '0'));
  };
  tick(); setInterval(tick, 1000);
  const barra = $('#barra');
  new IntersectionObserver(([e], o) => { if (e.isIntersecting) { barra.style.setProperty('--v', '63%'); o.disconnect(); } }).observe(barra);

  /* Proceso: la línea roja avanza con el scroll */
  const proceso = $('#proceso'), pasos = $$('.paso', proceso), linea = $('.proceso__linea', proceso);
  const onProceso = () => {
    const r = proceso.getBoundingClientRect();
    const p = Math.min(1, Math.max(0, (innerHeight * .75 - r.top) / (r.height + innerHeight * .25)));
    linea.style.setProperty('--p', p.toFixed(3));
    pasos.forEach((el, k) => el.classList.toggle('on', p >= (k + .5) / pasos.length - .08));
  };

  /* Club: el haz barre al hacer scroll */
  const club = $('#club'), clubHaz = $('#club-haz');
  const onClub = () => {
    const r = club.getBoundingClientRect();
    const p = Math.min(1, Math.max(0, 1 - (r.top + r.height * .3) / (innerHeight + r.height * .3)));
    clubHaz.style.setProperty('--crot', `${-28 + p * 26}deg`);
  };

  /* Agenda: imagen que sigue al cursor */
  const flota = $('#flota'), flotaImg = $('#flota-img');
  let fx = 0, fy = 0, fcx = 0, fcy = 0;
  if (fine && !reduce) $$('.noche').forEach(n => {
    n.addEventListener('pointerenter', () => { flotaImg.src = n.dataset.img; flota.classList.add('on'); });
    n.addEventListener('pointerleave', () => flota.classList.remove('on'));
    n.addEventListener('pointermove', e => { fx = e.clientX + 170; fy = e.clientY; });
  });

  /* Cita final: las palabras se encienden con el scroll */
  const cita = $('#cita');
  (() => {
    const out = [];
    cita.childNodes.forEach(n => {
      if (n.nodeType === 3) n.textContent.split(/\s+/).filter(Boolean).forEach(t => out.push(`<span class="w">${t}</span>`));
      else out.push(`<span class="w clave">${n.textContent}</span>`);
    });
    cita.innerHTML = out.join(' ');
  })();
  const citaW = $$('.w', cita), finalImg = $('#final-img'), finalSec = $('#final');
  const onCita = () => {
    const r = finalSec.getBoundingClientRect();
    const p = Math.min(1, Math.max(0, (innerHeight * .8 - r.top) / (r.height * .7)));
    citaW.forEach((w, k) => w.classList.toggle('on', p > k / citaW.length));
    finalImg.style.setProperty('--fs', (1.15 - p * .12).toFixed(3));
  };
  if (reduce) citaW.forEach(w => w.classList.add('on'));

  /* Bucle de animación */
  new IntersectionObserver(([e]) => heroVisible = e.isIntersecting).observe(hero);
  const loop = (t) => {
    lx += (mx - lx) * .12; ly += (my - ly) * .12;
    lin.style.transform = `translate3d(${lx}px,${ly}px,0)`;
    if (fine && !reduce) haz.style.setProperty('--rot', `${((lx / innerWidth) - .5) * -14}deg`);
    if (heroVisible && !document.hidden) draw(t);
    if (!rvHover && !reduce) rvTarget = { x: 50 + Math.sin(t / 2400) * 22, y: 38 + Math.cos(t / 3100) * 18 };
    rvCur.x += (rvTarget.x - rvCur.x) * .08; rvCur.y += (rvTarget.y - rvCur.y) * .08;
    rev.style.setProperty('--mx', `${rvCur.x}%`); rev.style.setProperty('--my', `${rvCur.y}%`);
    fcx += (fx - fcx) * .14; fcy += (fy - fcy) * .14;
    flota.style.transform = `translate3d(${fcx}px,${fcy}px,0) rotate(${(fx - fcx) * .02}deg)` + (flota.classList.contains('on') ? ' scale(1)' : ' scale(.8)');
    requestAnimationFrame(loop);
  };
  const onScroll = () => { onScrollHeader(); onLineas(); onProceso(); onClub(); onCita(); };
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', () => { resize(); onScroll(); });
  resize(); onScroll();
  if (reduce) { draw(0); rev.style.setProperty('--mx', '50%'); } else requestAnimationFrame(loop);

  /* Menú móvil */
  const mm = $('#menu-movil'), mb = $('#menu-btn');
  const toggleMenu = (on) => { if (on) { mm.hidden = false; requestAnimationFrame(() => mm.classList.add('on')); } else { mm.classList.remove('on'); setTimeout(() => { if (!mm.classList.contains('on')) mm.hidden = true; }, 700); } mb.setAttribute('aria-expanded', String(on)); };
  mb.addEventListener('click', () => toggleMenu(mb.getAttribute('aria-expanded') !== 'true'));
  $$('a', mm).forEach(a => a.addEventListener('click', () => toggleMenu(false)));

  /* Formularios: en el prototipo no se envía nada */
  const form = (id, msgId, ok) => $(id).addEventListener('submit', e => {
    e.preventDefault();
    const f = e.currentTarget, email = f.querySelector('input[type="email"]');
    const msg = $(msgId);
    if (!email.value || !email.checkValidity()) { msg.textContent = 'Escribe un email válido, por ejemplo nombre@dominio.com.'; email.focus(); return; }
    msg.textContent = ok;
  });
  form('#form-club', '#club-msg', 'Gracias. Las solicitudes del Club se abren muy pronto: todavía no guardamos ningún dato.');
  form('#form-news', '#news-msg', 'Gracias. La lista de la comunidad se abre muy pronto: todavía no guardamos ningún dato.');
})();
