#!/usr/bin/env bash
# Genera el ZIP listo para subir a Hostinger (hosting PHP sin Composer ni Node).
#
# Uso:  bash scripts/build-zip.sh
# Resultado: dist/clavedealma-<version>-<fecha>.zip
#
# El ZIP incluye vendor/ (sin dependencias de desarrollo) y los recursos
# compilados. No incluye .env ni el contenido de storage/, para no pisar
# la configuración ni los archivos del servidor.

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

echo "→ Compilando CSS y JS…"
( cd "$BUILD" && (npm ci || npm install) && npm run build && rm -rf node_modules )

echo "→ Limpiando lo que no debe subirse…"
rm -rf "$BUILD"/{tests,docker,docs,.github,scripts,dist} \
       "$BUILD"/docker-compose.yml "$BUILD"/.env "$BUILD"/phpunit.xml "$BUILD"/phpstan.neon
# storage/ se sube solo como estructura vacía (con sus .gitignore)
find "$BUILD/storage" -type f ! -name '.gitignore' -delete
echo "$VERSION" > "$BUILD/VERSION"

echo "→ Empaquetando…"
mkdir -p "$OUT"
( cd "$BUILD" && zip -qr "$OUT/${NAME}.zip" . )
rm -rf "$BUILD"

echo "✓ Listo: dist/${NAME}.zip"
