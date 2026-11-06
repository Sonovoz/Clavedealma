# Instalación en OVH: VPS con Ubuntu y Docker

Guía paso a paso para poner Clave de Alma en un VPS de OVH, primero **en modo de pruebas** (Redsys sin cobrar y sin indexar en Google) y después en real. Los comandos se copian y pegan en la terminal del VPS.

> Las contraseñas y las claves solo se escriben en el `.env` del servidor o en el panel. **Nunca en GitHub ni en el chat.**

## Cómo está montado

Todo corre en el VPS con Docker Compose (`docker-compose.yml`). La misma imagen sirve para la web, las colas y las tareas programadas.

| Servicio | Función |
| --- | --- |
| `traefik` | HTTPS con certificado de Let's Encrypt para `clavedealma.es` y `*.clavedealma.es` (se renueva solo) |
| `web` | Nginx: sirve las páginas y las fotos |
| `app` | PHP con la aplicación |
| `worker` | Las colas: emails, avisos… |
| `scheduler` | Las tareas programadas: copias de seguridad, pedidos caducados, revisión de copias |
| `db` | MariaDB 11.4 (la base de datos) |
| `redis` | Caché, sesiones y colas |
| `meilisearch` | Búsqueda de productos. Aún no se usa: solo arranca con `--profile busqueda` |

Los datos viven en volúmenes de Docker que no se pierden al actualizar: `db` (base de datos), `storage` (fotos, diseños y copias), `logs` y `letsencrypt` (certificados).

## Antes de empezar

- **VPS de OVH** con **Ubuntu 24.04 LTS** y **4 GB de RAM o más**. Al contratarlo, añade tu clave SSH si te lo ofrece.
- **El dominio `clavedealma.es` en OVH**, con su zona DNS en OVH. El certificado de los subdominios (`*.clavedealma.es`) se pide con la API de OVH.
- **Una cuenta de correo** para enviar los emails, por ejemplo `pedidos@clavedealma.es` (Email Pro o MX Plan de OVH).
- **Object Storage de OVH** para las copias fuera del servidor: en *Public Cloud → Object Storage*, crea un **contenedor privado** (por ejemplo `clavedealma-copias`) y un **usuario S3** con sus claves de acceso.
- Unos 30 minutos.

## 1. Preparar el VPS (una sola vez)

Entra por SSH. La dirección IP y el usuario (normalmente `ubuntu`) están en el panel de OVH:

```bash
ssh ubuntu@IP_DEL_VPS
```

Actualiza el sistema e instala Docker:

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

Sal (`exit`) y vuelve a entrar por SSH para que el cambio de grupo valga. Comprueba Docker:

```bash
docker run --rm hello-world
```

Cortafuegos, abierto solo para SSH, HTTP y HTTPS:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
```

## 2. DNS en OVH

En el panel de OVH, *Dominios → clavedealma.es → Zona DNS*, deja estos registros apuntando a la IP del VPS:

| Tipo | Subdominio | Destino |
| --- | --- | --- |
| A | (vacío) | IP del VPS |
| A | `*` | IP del VPS |

Si hay registros `A` antiguos para lo mismo (de un alojamiento anterior), bórralos. El cambio puede tardar hasta una hora.

## 3. Claves de la API de OVH (para el certificado)

Traefik las usa para demostrar a Let's Encrypt que el dominio es vuestro y pedir el certificado de `*.clavedealma.es`.

1. Entra en <https://eu.api.ovh.com/createToken/> con tu cuenta de OVH.
2. Nombre: `clavedealma-certificados`. Validez: *Unlimited*.
3. Permisos, uno por línea:
   - `GET` → `/domain/zone/clavedealma.es/*`
   - `POST` → `/domain/zone/clavedealma.es/*`
   - `DELETE` → `/domain/zone/clavedealma.es/*`
4. Guarda las tres claves que te da (*Application key*, *Application secret* y *Consumer key*): van en el `.env` del paso 5.

## 4. Descargar el código

El repositorio es privado, así que el VPS necesita una **clave de despliegue** (solo lectura):

```bash
ssh-keygen -t ed25519 -C "vps-clavedealma" -f ~/.ssh/github_clavedealma -N ""
cat ~/.ssh/github_clavedealma.pub
```

Copia la línea que sale. En GitHub, *Sonovoz/Clavedealma → Settings → Deploy keys → Add deploy key*. Título `VPS OVH`, pega la clave y **no** marques «Allow write access».

```bash
printf 'Host github.com\n  IdentityFile ~/.ssh/github_clavedealma\n' >> ~/.ssh/config
git clone git@github.com:Sonovoz/Clavedealma.git clavedealma
cd clavedealma
```

## 5. El archivo `.env`

```bash
cp .env.docker.example .env
chmod 600 .env
```

Genera la clave de la aplicación y las contraseñas de la base de datos (cada comando da una distinta):

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
openssl rand -hex 24
```

Abre el `.env` y rellena (`nano .env`; para guardar, *Ctrl+O*, *Intro* y *Ctrl+X*):

| Variable | Qué poner |
| --- | --- |
| `APP_KEY` | La línea `APP_KEY=base64:…` del comando anterior |
| `APP_URL` / `APP_DOMAIN` | `https://clavedealma.es` / `clavedealma.es` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Dos contraseñas distintas de `openssl rand -hex 24` |
| `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | Tu email y una contraseña larga para el primer acceso al panel |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | La cuenta de correo que envía los emails |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Las claves del usuario S3 del Object Storage |
| `AWS_DEFAULT_REGION`, `AWS_ENDPOINT` | La región del contenedor (por ejemplo `gra` y `https://s3.gra.io.cloud.ovh.net`) |
| `BACKUP_OFFSITE_BUCKET` | El nombre del contenedor de las copias |
| `ACME_EMAIL` | Tu email (Let's Encrypt avisa ahí si un certificado va a caducar) |
| `OVH_APPLICATION_KEY`, `OVH_APPLICATION_SECRET`, `OVH_CONSUMER_KEY` | Las claves del paso 3 |
| `RETURNS_ADDRESS` | La dirección a la que los clientes envían las devoluciones |

Lo demás ya viene preparado para la **fase de pruebas**:
- `REDSYS_ENVIRONMENT=test`: no se cobra nada.
- `SEO_INDEXING=false`: Google no indexa.
- `ERROR_ALERTS=true`: avisos de errores por email.
- `TWO_FACTOR_REQUIRED=true`: verificación en dos pasos.
- `BACKUP_OFFSITE=true`: copias también fuera del servidor.

Los datos del TPV de Redsys no van aquí: se escriben en el panel, en *Ajustes → Pagos*.

> **Guarda una copia del `.env`** fuera del servidor, en un gestor de contraseñas. La `APP_KEY` descifra la clave del TPV y la verificación en dos pasos: sin ella, una copia de seguridad no basta para recuperar la web.

## 6. Arrancar

La primera vez tarda unos 10 minutos (construye la imagen):

```bash
docker compose up -d --build
```

Crea las tablas, los roles y tu usuario de superadministrador:

```bash
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan optimize
```

Comprueba que todo está en marcha (todo debe decir `running` o `healthy`):

```bash
docker compose ps
```

Abre `https://clavedealma.es/up` en el navegador: debe salir una página verde. El certificado puede tardar un par de minutos la primera vez.

Ya no hace falta la contraseña inicial en el `.env`: vacía la línea `SUPERADMIN_PASSWORD=`.

## 7. Primer acceso al panel

1. Entra en `https://clavedealma.es/admin` con `SUPERADMIN_EMAIL` y su contraseña.
2. Te pedirá configurar la **verificación en dos pasos**:
   - escanea el código QR con una app autenticadora (Google Authenticator, Microsoft Authenticator, 1Password…);
   - escribe el código de 6 cifras;
   - **guarda los códigos de recuperación** en un sitio seguro: sirven si pierdes el móvil.
3. Repasa el panel:
   - *Herramientas → Estado del sistema*: todo en verde. Pulsa **Enviar un aviso de prueba** y comprueba que llega el email; así sabes que el correo funciona.
   - *Herramientas → Copias de seguridad*: **Probar Object Storage** y **Hacer copia ahora**. La copia debe salir «Subida» en «Fuera del servidor».
   - *Ajustes → Pagos*: sigue los pasos de Redsys; mientras no tengas el TPV, usa «Usar el comercio de pruebas de Redsys».
   - *Ajustes → Datos de facturación*, *Zonas de envío* y *Textos legales*.
4. Crea los usuarios del equipo en *Herramientas → Usuarios*. Cada uno configurará su verificación en dos pasos al entrar.

## 8. Actualizar (cada vez que haya cambios nuevos)

Antes de una actualización grande, haz una copia en *Herramientas → Copias de seguridad*. Después:

```bash
cd ~/clavedealma
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

## 9. Ver qué pasa

- Los errores llegan por email a `ERROR_ALERT_EMAIL` (o a `SUPERADMIN_EMAIL`). El mismo error, como mucho uno cada hora.
- Lo que hace cada servicio, en directo (*Ctrl+C* para salir):

```bash
docker compose logs -f --tail=100 app worker scheduler
```

- El registro de la aplicación (un archivo por día, se guardan 14):

```bash
docker compose exec app sh -c 'tail -n 100 storage/logs/laravel-$(date +%F).log'
```

## 10. Si algo va mal

- **Un servicio parado**: `docker compose ps` lo dice; `docker compose up -d` lo vuelve a arrancar.
- **Alguien ha perdido el móvil de la verificación en dos pasos**: un superadministrador se la quita en *Herramientas → Usuarios* («Quitar dos pasos»). Si es el único superadministrador:

```bash
docker compose exec app php artisan usuarios:quitar-dos-pasos tu@email.es
```

- **Restaurar una copia**: `docs/copias-de-seguridad.md`.
- **Reiniciar todo**: `docker compose restart`.

## 11. Pasar a real (lanzamiento)

Cuando todo esté probado:

1. TPV de Redsys real: datos del banco en *Ajustes → Pagos* y **Pasar a cobros reales**.
2. En el `.env`, `SEO_INDEXING=true`.
3. `docker compose up -d` y `docker compose exec app php artisan optimize`.
4. Haz una compra pequeña de verdad y devuélvela.
5. Revisa la lista «Antes de vender de verdad» de `docs/compra/README.md`.
