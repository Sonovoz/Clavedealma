/*
 * Compra: cambios de unidades en la cesta, precio del envío según la dirección y
 * formulario de pago de Stripe (Payment Element). Ningún importe se calcula aquí.
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

// Datos y envío: pide el precio del envío al servidor cuando cambian país o código postal.
const form = $('[data-checkout]');
if (form) {
  const shipping = $('[data-quote-shipping]');
  const total = $('[data-quote-total]');
  const zone = $('[data-quote-zone]');
  const delivery = $('[data-quote-delivery]');
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
      } else {
        shipping.textContent = data.message;
        zone.textContent = '';
      }
    } catch { /* el servidor lo vuelve a calcular al enviar */ }
  };
  form.querySelectorAll('[data-quote-input]').forEach(el => el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(update, 350); }));
  update();
}

// Pago con Stripe: los datos de la tarjeta van directamente a Stripe.
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
