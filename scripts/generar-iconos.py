#!/usr/bin/env python3
"""
Iconos del sitio (favicon, iOS, Android) a partir del logo de Clave de Alma.

Toma el símbolo (la clave de sol con forma de bailaora) de resources/designs/matriz/logo.png,
sin el texto, y lo pone en blanco sobre el rojo de la marca (#d4141c). En los tamaños
pequeños engrosa un poco el trazo para que se lea en la pestaña del navegador.

Uso (necesita Pillow: pip install pillow):  python3 scripts/generar-iconos.py
"""
from collections import deque
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
LOGO = ROOT / 'resources/designs/matriz/logo.png'
OUT = ROOT / 'public'
RED = (212, 20, 28, 255)
WHITE = (255, 255, 255, 255)


def symbol() -> Image.Image:
    """El símbolo del logo: las piezas que empiezan a la izquierda (el texto empieza después)."""
    im = Image.open(LOGO).convert('RGBA')
    w, h = im.size
    alpha = im.getchannel('A').load()
    keep = Image.new('L', (w, h), 0)
    out = keep.load()
    seen = set()
    for x in range(w):
        for y in range(h):
            if alpha[x, y] == 0 or (x, y) in seen:
                continue
            queue, piece = deque([(x, y)]), []
            seen.add((x, y))
            while queue:
                px, py = queue.popleft()
                piece.append((px, py))
                for dx in (-1, 0, 1):
                    for dy in (-1, 0, 1):
                        n = (px + dx, py + dy)
                        if 0 <= n[0] < w and 0 <= n[1] < h and n not in seen and alpha[n] > 0:
                            seen.add(n)
                            queue.append(n)
            if min(p[0] for p in piece) < 100:
                for p in piece:
                    out[p] = alpha[p]
    return keep.crop(keep.getbbox())


def icon(sym: Image.Image, size: int, pad: float, grow: int = 0, radius: float = 0.2) -> Image.Image:
    """Icono cuadrado: fondo rojo (esquinas redondeadas si radius > 0) y el símbolo en blanco."""
    big = 1024
    img = Image.new('RGBA', (big, big), (0, 0, 0, 0))
    ImageDraw.Draw(img).rounded_rectangle([0, 0, big - 1, big - 1], radius=int(big * radius), fill=RED)
    inner = int(big * (1 - 2 * pad))
    scale = inner / max(sym.size)
    mask = sym.resize((int(sym.width * scale), int(sym.height * scale)), Image.LANCZOS)
    if grow:
        mask = mask.filter(ImageFilter.MaxFilter(grow))
    layer = Image.new('RGBA', mask.size, WHITE)
    layer.putalpha(mask)
    img.alpha_composite(layer, ((big - mask.width) // 2, (big - mask.height) // 2))
    return img.resize((size, size), Image.LANCZOS)


def main() -> None:
    sym = symbol()
    tab = {16: icon(sym, 16, 0.06, 17), 32: icon(sym, 32, 0.07, 9), 48: icon(sym, 48, 0.08, 5)}
    tab[48].save(OUT / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)], append_images=[tab[16], tab[32]])
    tab[16].save(OUT / 'favicon-16x16.png')
    tab[32].save(OUT / 'favicon-32x32.png')
    icon(sym, 96, 0.09, 3).save(OUT / 'favicon-96x96.png')
    # iOS redondea las esquinas solo: cuadrado entero.
    icon(sym, 180, 0.14, radius=0).convert('RGB').save(OUT / 'apple-touch-icon.png')
    # Android: margen amplio para que quepa en cualquier forma («maskable»).
    icon(sym, 192, 0.18, radius=0).save(OUT / 'icon-192.png')
    icon(sym, 512, 0.18, radius=0).save(OUT / 'icon-512.png')
    print('Iconos generados en public/')


if __name__ == '__main__':
    main()
