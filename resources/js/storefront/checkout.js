/*
 * Compra: carrito, «Finalizar compra» (envío según la dirección y pago de Stripe en la misma
 * página) y «Pagar el pedido». Ningún importe se calcula aquí: todos vienen del servidor.
 */
const $ = (s, c = document) => c.querySelector(s);

// Cesta: al cambiar las unidades se guarda sola.
document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));

// Carrito: «Calcular envío» (como WooCommerce). El precio lo da el servidor con las tarifas del panel.
const totals = $('[data-cart-totals]');
if (totals) {
  const run = async () => {
    const country = $('[data-calc-country]', totals).value;
    const postal = $('[data-calc-postal]', totals).value.trim();
    const result = $('[data-shipping-result]', totals);
    const total = $('[data-total-result]', totals);
    if (postal.length < 4) { result.textContent = 'Escribe tu código postal'; return; }
    try {
      const res = await fetch(`${totals.dataset.quoteUrl}?country=${encodeURIComponent(country)}&postal_code=${encodeURIComponent(postal)}`, { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (data.ok) {
        result.textContent = `${data.shipping} · ${data.zone}`;
        total.textContent = data.total;
      } else {
        result.textContent = data.message;
        total.textContent = total.dataset.subtotal;
      }
    } catch { result.textContent = 'No se ha podido calcular ahora.'; }
  };
  $('[data-calc-shipping]', totals)?.addEventListener('click', run);
  $('[data-calc-postal]', totals)?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); run(); } });
}

// Carrito: avisar de que hay cambios sin guardar en las cantidades (como «Actualizar carrito» de Woo).
document.querySelectorAll('[data-cart-qty]').forEach(input => input.addEventListener('input', () => {
  const button = document.querySelector('[data-update-cart]');
  if (button) button.classList.add('is-dirty');
}));

// «Finalizar compra»: precio del envío según la dirección y, con Stripe, el pago en la misma
// página (como WooCommerce). Sin JavaScript, o si Stripe no carga, el formulario se envía
// normal y el pedido pasa a «Pagar el pedido».
const form = $('[data-checkout]');
if (form) {
  const shipping = $('[data-quote-shipping]', form);
  const total = $('[data-quote-total]', form);
  const zone = $('[data-quote-zone]', form);
  const delivery = $('[data-quote-delivery]', form);
  let elements = null;
  let timer;
  const update = async () => {
    const country = form.country.value;
    const postal = form.postal_code.value.trim();
    if (postal.length < 4) return;
    const url = `${form.dataset.quoteUrl}?country=${encodeURIComponent(country)}&postal_code=${encodeURIComponent(postal)}`;
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (data.ok) {
        shipping.textContent = data.shipping;
        total.textContent = data.total;
        zone.textContent = `· ${data.zone}`;
        delivery.textContent = `IVA incluido. ${data.delivery}, después de fabricar tu pedido.`;
        // El importe que enseñan Apple Pay o Google Pay; el que se cobra lo fija el servidor.
        elements?.update({ amount: data.total_cents });
      } else {
        shipping.textContent = data.message;
        zone.textContent = '';
      }
    } catch { /* el servidor lo vuelve a calcular al enviar */ }
  };
  form.querySelectorAll('[data-quote-input]').forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(update, 350); }));
  update();

  const button = $('[data-place-order]', form);
  const payError = $('#co-pay-error');
  const alertBox = $('[data-form-alert]', form);
  const label = button?.textContent;
  const busy = on => { if (button) { button.disabled = on; button.textContent = on ? 'Procesando…' : label; } };
  const fail = message => { payError.textContent = message; payError.hidden = false; busy(false); };

  // Errores de los campos, como los pinta el servidor.
  const showErrors = errors => {
    form.querySelectorAll('[data-js-error]').forEach(el => el.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(el => el.removeAttribute('aria-invalid'));
    let first = null;
    Object.entries(errors || {}).forEach(([name, messages]) => {
      const input = form.querySelector(`[name="${name}"]`);
      if (!input) return;
      input.setAttribute('aria-invalid', 'true');
      const span = Object.assign(document.createElement('span'), { className: 'co-error', textContent: messages[0] });
      span.dataset.jsError = '';
      (input.closest('.co-field') || input.closest('.co-check') || input.parentElement).after(span);
      first ??= input;
    });
    if (alertBox) alertBox.hidden = !first;
    first?.focus({ preventScroll: true });
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  const key = form.dataset.stripeKey;
  const mount = $('[data-payment-element]', form);
  const startStripe = () => {
    if (!window.Stripe) { setTimeout(startStripe, 100); return; }
    const stripe = window.Stripe(key);
    elements = stripe.elements({
      mode: 'payment',
      amount: Number(form.dataset.subtotalCents),
      currency: 'eur',
      locale: 'es',
      appearance: { theme: 'night', variables: { colorPrimary: '#d4141c', colorBackground: '#070707', borderRadius: '0px', fontFamily: 'Archivo, Arial, sans-serif' } },
    });
    mount.replaceChildren();
    elements.create('payment').mount(mount);
    update();

    form.addEventListener('submit', async e => {
      e.preventDefault();
      payError.hidden = true;
      busy(true);
      // 1. Stripe comprueba los datos de pago (antes de cualquier espera, lo pide Apple Pay).
      const { error: submitError } = await elements.submit();
      if (submitError) { fail(submitError.message); return; }
      // 2. El servidor valida los datos, calcula el total y crea el pedido y su cobro.
      let res; let data = {};
      try {
        res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        data = await res.json();
      } catch { fail('No se ha podido conectar. Revisa tu conexión e inténtalo de nuevo.'); return; }
      if (res.status === 422) { showErrors(data.errors); busy(false); return; }
      if (data.redirect) { window.location.assign(data.redirect); return; }
      if (!res.ok || !data.client_secret) { fail(data.message || 'No se ha podido realizar el pedido.'); return; }
      showErrors({});
      // 3. Stripe cobra; si va bien, lleva a «Pedido recibido».
      const { error } = await stripe.confirmPayment({ elements, clientSecret: data.client_secret, confirmParams: { return_url: data.return_url } });
      fail(error?.message ?? 'No se ha podido completar el pago.');
    });
  };
  if (key && mount) startStripe();
}

// «Pagar el pedido» (pedido ya creado, como en WooCommerce): los datos van directamente a Stripe.
const pay = $('#co-pay');
if (pay) {
  const start = () => {
    if (!window.Stripe) { setTimeout(start, 100); return; }
    const stripe = window.Stripe(pay.dataset.stripeKey);
    const elements = stripe.elements({
      clientSecret: pay.dataset.clientSecret,
      locale: 'es',
      appearance: { theme: 'night', variables: { colorPrimary: '#d4141c', colorBackground: '#070707', borderRadius: '0px', fontFamily: 'Archivo, Arial, sans-serif' } },
    });
    elements.create('payment', { defaultValues: { billingDetails: { email: pay.dataset.email } } }).mount('#co-payment-element');

    const button = $('#co-pay-button');
    const error = $('#co-pay-error');
    pay.addEventListener('submit', async e => {
      e.preventDefault();
      button.disabled = true;
      error.hidden = true;
      const { error: err } = await stripe.confirmPayment({ elements, confirmParams: { return_url: pay.dataset.returnUrl } });
      // Solo se llega aquí si hay un error: si va bien, Stripe redirige a la confirmación.
      error.textContent = err?.message ?? 'No se ha podido completar el pago.';
      error.hidden = false;
      button.disabled = false;
    });
  };
  start();
}
