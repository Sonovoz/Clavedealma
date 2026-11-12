#!/usr/bin/env bash
# Genera el ZIP listo para subir a un servidor PHP sin Composer ni Node (el VPS de OVH con
# php-web y mariadb, o Hostinger). También lo genera GitHub (Actions → «Paquete para el servidor»).
#
# Uso:  bash scripts/build-zip.sh
# Resultado: dist/clavedealma-<version>-<fecha>.zip
#
# El ZIP incluye vendor/ (sin dependencias de desarrollo), los recursos compilados y los
# archivos del panel (Filament). No incluye .env ni el contenido de storage/, para no pisar
# la configuración ni los archivos del servidor. La primera vez, el instalador web
# (/instalar) crea el .env y pide la base de datos.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(git -C "$ROOT" describe --tags --always 2>/dev/null || echo dev)"
DATE="$(date +%Y-%m-%d)"
NAME="clavedealma-${VERSION}-${DATE}"
BUILD="$(mktemp -d)"
OUT="$ROOT/dist"

echo "→ Copiando el código versionado (git archive)…"
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$BUILD"

echo "→ Instalando dependencias PHP de producción…"
composer install --working-dir="$BUILD" --no-dev --optimize-autoloader --no-interaction --prefer-dist
# Archivos del panel (estilos y JavaScript de Filament): no van en Git.
( cd "$BUILD" && php artisan filament:assets --no-interaction >/dev/null )

echo "→ Compilando CSS y JS…"
( cd "$BUILD" && (npm ci || npm install) && npm run build && rm -rf node_modules )

echo "→ Limpiando lo que no debe subirse…"
# docs/ se queda: la página «Desarrollo» del panel la lee.
rm -rf "$BUILD"/{tests,docker,.github,scripts,dist} \
       "$BUILD"/docker-compose.yml "$BUILD"/.env "$BUILD"/phpunit.xml "$BUILD"/phpstan.neon
# Si Composer bajó algún paquete desde Git, sin su historial (cientos de MB).
find "$BUILD/vendor" -name .git -type d -prune -exec rm -rf {} +
# storage/ se sube solo como estructura vacía (con sus .gitignore)
find "$BUILD/storage" -type f ! -name '.gitignore' -delete
echo "$VERSION" > "$BUILD/VERSION"

echo "→ Empaquetando…"
mkdir -p "$OUT"
( cd "$BUILD" && zip -qr "$OUT/${NAME}.zip" . )
rm -rf "$BUILD"

echo "✓ Listo: dist/${NAME}.zip"
