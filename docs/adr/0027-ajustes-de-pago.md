# ADR 0027 · «Ajustes → Pagos»: el TPV de Redsys en el panel

- Estado: aceptado
- Fecha: 2026-11-05

## Contexto

Con Redsys como pasarela (ADR 0026), los datos del TPV vivían solo en el `.env` del servidor. En WooCommerce, el plugin de Redsys se configura desde *WooCommerce → Ajustes → Pagos*, y quien lleva la tienda está acostumbrado a eso. Además hace falta una guía paso a paso para ponerlo en marcha con el banco.

## Decisión

- **Página *Ajustes → Pagos*** (`App\Filament\Pages\PaymentSettings`), solo para el superadministrador. Tiene cuatro bloques:
  - **Estado del TPV**: entorno, código de comercio, terminal, si la clave es válida, métodos y «Último aviso del banco». Dice de dónde sale cada dato (panel o `.env`).
  - **Datos para el banco**: la URL de notificación, lista para copiar, y lo que hay que pedir («Parámetros en las URLs: Sí», notificación HTTP).
  - **Pasos para ponerlo en marcha**: 8 pasos que se marcan solos cuando se cumplen (datos escritos, primer aviso recibido, primera compra pagada, primera devolución, Bizum, cobros reales y textos legales).
  - **Últimos cobros con Redsys**: los 10 últimos, con el número de Redsys, el método, el estado y el motivo del rechazo.
- **Los datos del TPV se escriben en el panel** («Editar datos del TPV»), como en WooCommerce. Son el código de comercio, el terminal, la clave secreta, el nombre en la página de pago y si se activan tarjeta y Bizum.
  - La **clave secreta se guarda cifrada** con la `APP_KEY` (tabla `settings`) y no se vuelve a mostrar: vacía = se queda la que había.
  - Antes de guardar se comprueba el formato de cada dato (código de 9 cifras, clave de 24 bytes en Base64, al menos un método).
  - **Cada cambio pide la contraseña** del superadministrador y queda en el registro de seguridad (`PaymentSettingsChanged`), con lo que cambió pero nunca con la clave.
  - Lo del panel **manda sobre el `.env`**. Lo que no está en el panel sale del `.env`, que sigue sirviendo, por ejemplo, para Docker.
  - Se aplica al crear la pasarela (`App\Platform\Payments\RedsysSettings::apply()`, desde `AppServiceProvider`). Así el módulo `Payments` sigue leyendo solo su `config`.
- **«Usar el comercio de pruebas de Redsys»** rellena el comercio público de pruebas para probar sin el TPV del banco.
- **«Pasar a cobros reales»** pide la contraseña y no deja hacerlo sin datos reales: ni con el comercio de pruebas ni con una clave que no vale.
- **«Último aviso del banco»** se apunta cuando llega un aviso firmado a `/webhooks/redsys`. Así se sabe que el banco ha configurado bien la URL de notificación.

## Consecuencias

- Si cambia la `APP_KEY`, la clave guardada no se puede descifrar: el panel dice «Falta» y hay que volver a escribirla.
- Un acceso indebido al panel de superadministrador podría cambiar el TPV. Por eso cada cambio pide la contraseña y queda en el registro de seguridad, y los superadministradores deben tener contraseñas fuertes. La verificación en dos pasos queda pendiente en el bloque de seguridad.
