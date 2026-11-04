# ADR 0019 · Registro de seguridad

- Estado: aceptado
- Fecha: 2026-11-04

## Decisión

Hay un registro de seguridad común (`App\Platform\Security`, tabla `security_events`) que anota quién hizo qué, cuándo y desde dónde: usuario, IP y navegador.

**Se anota solo**, con los eventos de Laravel y del modelo de usuario:
- acceso al panel, acceso fallido (con el email que se intentó), bloqueo por intentos, salida y contraseña restablecida;
- usuario creado, modificado o borrado, y contraseña cambiada.

**Se anota en cada acción del panel**:
- cambio o asignación de rol;
- pedido cancelado con devolución de dinero, y dinero devuelto por una devolución;
- datos fiscales cambiados y facturas pendientes emitidas;
- copia de seguridad hecha, descargada o borrada, y cambio de su programación;
- actualización aplicada y modo mantenimiento.

**Reglas**:
- **Nunca** se guardan contraseñas, tokens, datos de tarjeta ni el contenido de los pedidos.
- Las entradas no se modifican ni se borran a mano: el modelo lo impide.
- **Conservación**: 365 días (`SECURITY_LOG_DAYS`). `seguridad:purgar` borra cada noche lo antiguo, porque las IPs son datos personales (RGPD).
- Si escribir en el registro falla, la acción no se interrumpe: se deja aviso en el log de la aplicación.
- **Solo lo ve el superadministrador**: *Herramientas → Registro de seguridad*. El menú enseña cuántos accesos fallidos ha habido en las últimas 24 horas.

## Consecuencias

Toda acción sensible nueva del panel debe llamar a `SecurityLog::record()` con un `SecurityEventType` propio.
