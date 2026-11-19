# ADR 0031 · Recuperar el acceso al panel con una clave del `.env`

- Estado: aceptado
- Fecha: 2026-11-19

## Contexto

El superadministrador perdió la contraseña. El servidor se gestiona con un panel web propio (proyectos, botón «.env», FileBrowser, phpMyAdmin), sin usar siempre la línea de comandos. El correo SMTP aún no está configurado, así que tampoco sirve «¿Has olvidado tu contraseña?».

Un usuario o una contraseña temporal escritos en el código no valen: estarían en GitHub y servirían para cualquiera.

## Decisión

Un modo de recuperación como el de WordPress: solo lo abre quien puede editar el `.env` del servidor.

- `App\Platform\Security\RecoverAccess`:
  - Se activa si el `.env` tiene `RECUPERAR_ACCESO` con 20 caracteres o más.
  - Se lee del archivo, no de la configuración en caché: vale en cuanto se guarda.
- `/recuperar-acceso` (`RecoverAccessController`):
  - Sin la clave da 404. Admite 5 intentos por minuto.
  - Pide la clave (comparada en tiempo constante), el email y una contraseña nueva de 12 caracteres o más.
  - Pone la contraseña con `CreateSuperadmin`. Si el email no existe, crea un superadministrador con él.
  - Quita la verificación en dos pasos de esa cuenta: hay que volver a configurarla al entrar.
- La clave sirve **una vez**:
  - se vacía en el `.env`;
  - su huella queda anotada como gastada, aunque no se pueda escribir el `.env` o alguien la vuelva a poner.
- Queda en el registro de seguridad con nivel de peligro (`AccessRecovered`), sin la contraseña.

## Consecuencias

- Recuperar el acceso solo necesita editar el `.env` (botón «.env» del panel del servidor o FileBrowser) y abrir una página.
- Quien pueda editar el `.env` ya controla la web (tiene la `APP_KEY` y la base de datos), así que esto no abre ninguna puerta nueva.
- La línea de comandos sigue valiendo: `php artisan clavedealma:superadmin <email>`.
