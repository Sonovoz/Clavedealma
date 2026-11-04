/*
 * Comportamiento común del escaparate (todas las tiendas):
 * filtros de la pasarela y ficha de artículo (color, talla, precio, galería y guía de tallas).
 */
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];

/* ---------- Pasarela: filtros, «Ordenar por» y paginación (como WooCommerce) ---------- */
const pasarela = $('[data-showcase]');
if (pasarela) {
  const PER_PAGE = 12;
  const state = { collection: 'todas', type: 'todo', order: 'menu_order', page: 1 };
  const grid = $('.sf-grid', pasarela);
  const items = $$('[data-card]', pasarela);
  const count = $('[data-count]', pasarela);
  const pager = $('[data-pagination]', pasarela);
  const orderby = $('[data-orderby]', pasarela);
  const num = (el, key) => Number(el.dataset[key] || 0);
  const sorters = {
    menu_order: (a, b) => num(a, 'position') - num(b, 'position'),
    popularity: (a, b) => num(b, 'popular') - num(a, 'popular') || num(a, 'position') - num(b, 'position'),
    date: (a, b) => num(b, 'new') - num(a, 'new'),
    price: (a, b) => num(a, 'price') - num(b, 'price'),
    'price-desc': (a, b) => num(b, 'price') - num(a, 'price'),
  };

  const apply = () => {
    const matching = items
      .filter(el => (state.collection === 'todas' || el.dataset.collections.split(' ').includes(state.collection))
        && (state.type === 'todo' || el.dataset.type === state.type))
      .sort(sorters[state.order] || sorters.menu_order);
    const total = matching.length;
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    state.page = Math.min(state.page, pages);
    const from = (state.page - 1) * PER_PAGE;
    const visible = new Set(matching.slice(from, from + PER_PAGE));

    if (grid) matching.forEach(el => grid.appendChild(el));
    items.forEach(el => { el.hidden = !visible.has(el); });

    if (count) {
      count.textContent = total === 0 ? 'No hay productos que coincidan'
        : total === 1 ? 'Mostrando el único resultado'
        : total <= PER_PAGE ? `Mostrando los ${total} resultados`
        : `Mostrando ${from + 1}–${Math.min(from + PER_PAGE, total)} de ${total} resultados`;
    }
    if (pager) {
      pager.hidden = pages < 2;
      pager.replaceChildren();
      const add = (label, page, current = false, aria = null) => {
        const b = Object.assign(document.createElement('button'), { type: 'button', textContent: label, className: 'sf-page' });
        if (current) b.setAttribute('aria-current', 'page');
        if (aria) b.setAttribute('aria-label', aria);
        b.addEventListener('click', () => { state.page = page; apply(); pasarela.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
        pager.appendChild(b);
      };
      if (state.page > 1) add('←', state.page - 1, false, 'Página anterior');
      for (let p = 1; p <= pages; p++) add(String(p), p, p === state.page);
      if (state.page < pages) add('→', state.page + 1, false, 'Página siguiente');
    }
  };

  $$('[data-filter]', pasarela).forEach(chip => chip.addEventListener('click', () => {
    const key = chip.dataset.filter;
    state[key] = chip.dataset.value;
    state.page = 1;
    $$(`[data-filter="${key}"]`, pasarela).forEach(c => c.setAttribute('aria-pressed', String(c === chip)));
    apply();
  }));
  orderby?.addEventListener('change', () => { state.order = orderby.value; state.page = 1; apply(); });
  // Enlaces de las secciones del diseño que abren una colección en la pasarela
  $$('[data-show-collection]').forEach(a => a.addEventListener('click', () => {
    const chip = $(`[data-filter="collection"][data-value="${a.dataset.showCollection}"]`, pasarela);
    if (chip) chip.click();
  }));
  apply();
}

/* ---------- Ficha de artículo ---------- */
const ficha = $('[data-product]');
if (ficha) {
  const data = JSON.parse($('#sf-product-data').textContent);
  const variants = data.variants;
  const sel = { color: data.colors[0]?.name ?? null, size: null };

  const main = $('[data-main-image]', ficha);
  const thumbs = $$('[data-thumb]', ficha);
  const colorName = $('[data-color-name]', ficha);
  const title = $('[data-title]', ficha);
  const price = $('[data-price]', ficha);
  const stock = $('[data-stock]', ficha);
  const sizes = $$('[data-size]', ficha);

  const lower = s => (s ?? '').toLowerCase();
  const match = (color, size) => variants.find(v => lower(v.color) === lower(color) && lower(v.size) === lower(size));

  const showImage = btn => {
    if (!btn || !main) return;
    main.src = btn.dataset.src; main.alt = btn.dataset.alt;
    thumbs.forEach(t => t.setAttribute('aria-current', String(t === btn)));
  };

  const renderPrice = v => {
    if (!price || !v) return;
    price.classList.toggle('sf-price--sale', !!v.before);
    price.innerHTML = v.before
      ? `<b>${v.price}</b><s>${v.before}</s><em>−${v.percent} %</em>`
      : `<b>${v.price}</b>`;
  };

  const render = () => {
    // Galería: fotos generales + las del color elegido
    thumbs.forEach(t => { t.hidden = !!(t.dataset.color && sel.color && lower(t.dataset.color) !== lower(sel.color)); });
    const visible = thumbs.filter(t => !t.hidden);
    const current = visible.find(t => t.getAttribute('aria-current') === 'true');
    if (!current) showImage(visible.find(t => lower(t.dataset.color) === lower(sel.color)) || visible[0]);

    $$('[data-color]', ficha).filter(b => b.matches('button.sf-swatch')).forEach(b => b.setAttribute('aria-pressed', String(lower(b.dataset.color) === lower(sel.color))));
    if (colorName) colorName.textContent = sel.color ?? '';
    if (title) title.textContent = sel.color ? `${data.name} | ${sel.color.toUpperCase()}` : data.name;

    sizes.forEach(b => {
      const v = match(sel.color, b.dataset.size);
      b.disabled = !v || !v.available;
      b.setAttribute('aria-pressed', String(sel.size === b.dataset.size));
    });
    if (sel.size && !match(sel.color, sel.size)?.available) sel.size = null;

    const v = sel.size ? match(sel.color, sel.size) : (variants.find(x => lower(x.color) === lower(sel.color)) || variants[0]);
    renderPrice(v);
    if (stock) stock.textContent = v && v.limited !== null && v.limited <= 10 ? `Edición limitada: quedan ${v.limited}` : '';

    // Botón de compra: solo con una variante concreta y disponible.
    const chosen = sizes.length ? (sel.size ? match(sel.color, sel.size) : null) : (variants.find(x => lower(x.color) === lower(sel.color)) || variants[0]);
    if (addInput) addInput.value = chosen?.id ?? '';
    if (addButton) {
      addButton.disabled = !chosen || !chosen.available;
      addButton.textContent = !chosen ? 'Elige una talla' : (chosen.available ? 'Añadir al carrito' : 'Agotado');
    }
    // Como WooCommerce: el SKU de la variación elegida.
    const sku = $('[data-sku]', ficha);
    if (sku && chosen?.sku) sku.textContent = chosen.sku;
    // La cantidad no puede pasar de las unidades que quedan (si la pieza se agota).
    const qty = $('[data-qty]', ficha);
    if (qty) qty.max = String(Math.min(10, chosen?.limited ?? 10));
  };

  const addForm = $('[data-add-form]', ficha);
  const addInput = $('[data-variant-input]', ficha);
  const addButton = $('[data-add]', ficha);
  const addMessage = $('[data-add-message]', ficha);
  addForm?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!addInput.value) return;
    addButton.disabled = true;
    try {
      const res = await fetch(addForm.action, { method: 'POST', body: new FormData(addForm), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      addMessage.hidden = false;
      addMessage.innerHTML = '';
      addMessage.append(document.createTextNode(`${data.message} `));
      if (data.ok) {
        const link = Object.assign(document.createElement('a'), { href: data.cart_url, textContent: 'Ver carrito' });
        addMessage.append(link);
        document.querySelectorAll('[data-cart-count]').forEach(b => { b.textContent = data.count; b.classList.toggle('on', data.count > 0); b.classList.add('pop'); setTimeout(() => b.classList.remove('pop'), 600); });
      }
    } catch {
      addForm.submit();
    } finally {
      addButton.disabled = false;
    }
  });

  $$('button.sf-swatch', ficha).forEach(b => b.addEventListener('click', () => { sel.color = b.dataset.color; render(); }));
  sizes.forEach(b => b.addEventListener('click', () => { sel.size = b.dataset.size; render(); }));
  thumbs.forEach(t => t.addEventListener('click', () => showImage(t)));

  // Talla única: no hay botones de talla
  if (!sizes.length) sel.size = null;
  render();

  const guide = $('#sf-size-guide');
  $$('[data-open-guide]').forEach(b => b.addEventListener('click', () => guide?.showModal()));
  $$('[data-close-guide]').forEach(b => b.addEventListener('click', () => guide?.close()));

  // Pestañas Descripción · Información adicional · Envíos y devoluciones (como WooCommerce).
  const tabs = $$('[data-tab]', ficha);
  tabs.forEach(tab => tab.addEventListener('click', () => {
    tabs.forEach(t => {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      const panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
    });
  }));
}
