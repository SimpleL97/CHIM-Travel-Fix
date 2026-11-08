"""CHIM Travel Fix - banner (1920x1080), original artwork, no third-party logos.
Left half: CHIM - cold blue AI network and dialogue (as on the CHIM bridges' banners).
Right half: the journey - mountains at dusk, a dashed road winding to a house with a lit
window and a map pin over it, a compass rose. The halves meet on a 45 degree diagonal.
Writes banner.html; render it to PNG with a headless browser (see render_banner.ps1).
"""
import math, os, random

W, H = 1920, 1080
C = W // 2 + H // 2          # 45 degree divider: x + y = C, through the middle
rnd = random.Random(2111)


def inside_left(x, y, margin=0):
    return x + y < C - margin


out = []
add = out.append
add(f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">')
add(f'''<defs>
  <linearGradient id="cold" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#040b1c"/><stop offset="0.55" stop-color="#0a2348"/><stop offset="1" stop-color="#0f3a6e"/>
  </linearGradient>
  <linearGradient id="dusk" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#0d1a2e"/><stop offset="0.45" stop-color="#3a2a4a"/><stop offset="0.75" stop-color="#b8603a"/><stop offset="1" stop-color="#f2a65a"/>
  </linearGradient>
  <radialGradient id="coldGlow" cx="0.32" cy="0.42" r="0.55">
    <stop offset="0" stop-color="#3fc8ff" stop-opacity="0.28"/><stop offset="1" stop-color="#3fc8ff" stop-opacity="0"/>
  </radialGradient>
  <radialGradient id="sunGlow" cx="0.5" cy="0.5" r="0.5">
    <stop offset="0" stop-color="#ffd27a" stop-opacity="0.9"/><stop offset="0.4" stop-color="#ff9a4a" stop-opacity="0.35"/><stop offset="1" stop-color="#ff9a4a" stop-opacity="0"/>
  </radialGradient>
  <linearGradient id="seam" x1="0" y1="1" x2="1" y2="0">
    <stop offset="0" stop-color="#3fc8ff"/><stop offset="0.5" stop-color="#ffffff"/><stop offset="1" stop-color="#ffb35a"/>
  </linearGradient>
  <linearGradient id="titleGrad" x1="0" y1="0" x2="1" y2="0">
    <stop offset="0" stop-color="#7fdcff"/><stop offset="0.5" stop-color="#ffffff"/><stop offset="1" stop-color="#ffc27a"/>
  </linearGradient>
  <linearGradient id="goldText" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#fff0c8"/><stop offset="0.6" stop-color="#ffcf6e"/><stop offset="1" stop-color="#e98a3a"/>
  </linearGradient>
  <linearGradient id="pin" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#ffe08a"/><stop offset="1" stop-color="#e0702a"/>
  </linearGradient>
  <filter id="blur8"><feGaussianBlur stdDeviation="8"/></filter>
  <filter id="blur3" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3"/></filter>
  <filter id="soft" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="22"/></filter>
  <filter id="shadow" x="-20%" y="-50%" width="140%" height="200%">
    <feDropShadow dx="0" dy="4" stdDeviation="10" flood-color="#000" flood-opacity="0.85"/>
  </filter>
  <clipPath id="leftHalf"><polygon points="0,0 {C},0 {C - H},{H} 0,{H}"/></clipPath>
  <clipPath id="rightHalf"><polygon points="{C},0 {W},0 {W},{H} {C - H},{H}"/></clipPath>
  <path id="pinShape" d="M0,0 C-30,-40 -44,-62 -44,-86 C-44,-112 -24,-132 0,-132 C24,-132 44,-112 44,-86 C44,-62 30,-40 0,0 Z"/>
</defs>''')

# ---------------- left: CHIM
add('<g clip-path="url(#leftHalf)">')
add(f'<rect width="{W}" height="{H}" fill="url(#cold)"/><rect width="{W}" height="{H}" fill="url(#coldGlow)"/>')
for row in range(0, 30):
    for col in range(0, 40):
        x = col * 60 + (30 if row % 2 else 0)
        y = row * 52
        if inside_left(x, y):
            pts = " ".join(f"{x + 26 * math.cos(math.radians(a)):.1f},{y + 26 * math.sin(math.radians(a)):.1f}" for a in range(30, 390, 60))
            add(f'<polygon points="{pts}" fill="none" stroke="#3fc8ff" stroke-opacity="0.05" stroke-width="1"/>')
nodes = []
while len(nodes) < 70:
    x, y = rnd.uniform(40, C), rnd.uniform(40, H - 40)
    if inside_left(x, y, 60):
        nodes.append((x, y))
for (x1, y1) in nodes:
    for (x2, y2) in sorted(nodes, key=lambda p: (p[0] - x1) ** 2 + (p[1] - y1) ** 2)[1:4]:
        add(f'<line x1="{x1:.0f}" y1="{y1:.0f}" x2="{x2:.0f}" y2="{y2:.0f}" stroke="#3fc8ff" stroke-opacity="0.22" stroke-width="1.4"/>')
for (x, y) in nodes:
    r = rnd.uniform(2.5, 6)
    add(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r * 3:.1f}" fill="#3fc8ff" opacity="0.12" filter="url(#blur3)"/>')
    add(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r:.1f}" fill="#bff0ff" opacity="0.85"/>')


def bubble(cx, cy, w, h, opacity, pin_inside=False):
    tail = f"M{cx - w * 0.25:.0f},{cy + h / 2:.0f} l-28,38 l30,-38 Z"
    add(f'<g opacity="{opacity}"><rect x="{cx - w / 2:.0f}" y="{cy - h / 2:.0f}" width="{w}" height="{h}" rx="{h / 2:.0f}" fill="#0b2a52" stroke="#7fdcff" stroke-width="3"/>'
        f'<path d="{tail}" fill="#0b2a52" stroke="#7fdcff" stroke-width="3" stroke-linejoin="round"/>')
    if pin_inside:
        add(f'<use href="#pinShape" transform="translate({cx},{cy + 24}) scale(0.36)" fill="url(#pin)"/>'
            f'<circle cx="{cx}" cy="{cy - 7}" r="5" fill="#0b2a52"/>')
    else:
        add("".join(f'<circle cx="{cx + d:.0f}" cy="{cy:.0f}" r="9" fill="#bff0ff"/>' for d in (-34, 0, 34)))
    add('</g>')


bubble(330, 300, 230, 96, 0.95)
bubble(620, 190, 190, 80, 0.85, pin_inside=True)
bubble(250, 820, 160, 70, 0.6)
add('</g>')

# ---------------- right: the road home at dusk
add('<g clip-path="url(#rightHalf)">')
add(f'<rect width="{W}" height="{H}" fill="url(#dusk)"/>')
for _ in range(60):  # first stars
    x, y = rnd.uniform(C - H, W), rnd.uniform(0, 420)
    if not inside_left(x, y, -40):
        add(f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{rnd.uniform(1, 2.6):.1f}" fill="#fff6e0" opacity="{rnd.uniform(0.3, 0.9):.2f}"/>')
add('<circle cx="1560" cy="640" r="260" fill="url(#sunGlow)"/>')
add('<circle cx="1560" cy="660" r="70" fill="#ffd98a" opacity="0.9"/>')
# three mountain ranges, far to near
ranges = [
    ("#4a3552", 0.95, [(400, 780), (620, 700), (820, 760), (1000, 640), (1150, 520), (1260, 610), (1400, 430), (1520, 560), (1640, 470), (1780, 590), (1920, 500)]),
    ("#2a2236", 1.0, [(400, 880), (560, 820), (740, 880), (900, 760), (1080, 650), (1200, 720), (1330, 600), (1470, 700), (1600, 620), (1760, 730), (1920, 650)]),
    ("#141220", 1.0, [(400, 1000), (600, 940), (800, 980), (1000, 820), (1150, 860), (1290, 780), (1440, 860), (1620, 800), (1800, 870), (1920, 820)]),
]
for color, op, pts in ranges:
    d = f"M{pts[0][0]},{H} " + " ".join(f"L{x},{y}" for x, y in pts) + f" L{W},{H} Z"
    add(f'<path d="{d}" fill="{color}" opacity="{op}"/>')
# snow on the far peaks
for (x, y) in [(1150, 520), (1400, 430), (1640, 470)]:
    add(f'<path d="M{x},{y} l-34,40 l18,-6 l16,14 l16,-14 l18,6 Z" fill="#e8dcef" opacity="0.75"/>')
# the road: a dashed golden path from the seam to the house
road = "M1060,1080 C1120,980 1300,1010 1340,940 C1380,870 1280,840 1400,800 C1500,768 1600,800 1680,770"
add(f'<path d="{road}" fill="none" stroke="#ffcf6e" stroke-width="20" opacity="0.18" filter="url(#blur3)"/>')
add(f'<path d="{road}" fill="none" stroke="#ffcf6e" stroke-width="6" stroke-dasharray="22 16" stroke-linecap="round"/>')
# the house at the end of the road, a lit window
hx, hy = 1700, 770
add(f'<path d="M{hx - 70},{hy} L{hx - 70},{hy - 70} L{hx},{hy - 130} L{hx + 70},{hy - 70} L{hx + 70},{hy} Z" fill="#0a0812"/>')
add(f'<path d="M{hx - 88},{hy - 64} L{hx},{hy - 142} L{hx + 88},{hy - 64}" fill="none" stroke="#0a0812" stroke-width="16" stroke-linejoin="round"/>')
add(f'<rect x="{hx + 30}" y="{hy - 150}" width="18" height="40" fill="#0a0812"/>')
add(f'<rect x="{hx - 44}" y="{hy - 58}" width="30" height="30" rx="3" fill="#ffb347"/>')
add(f'<rect x="{hx - 44}" y="{hy - 58}" width="30" height="30" rx="3" fill="#ffb347" opacity="0.6" filter="url(#blur8)"/>')
add(f'<rect x="{hx + 10}" y="{hy - 50}" width="28" height="50" rx="2" fill="#2a1d14"/>')
# the map pin over the house
add(f'<g transform="translate({hx},{hy - 170})">'
    '<use href="#pinShape" fill="#ff9a4a" opacity="0.35" filter="url(#blur8)"/>'
    '<use href="#pinShape" fill="url(#pin)" stroke="#fff3d6" stroke-width="3"/>'
    '<circle cx="0" cy="-86" r="18" fill="#3a2010"/></g>')
# a compass rose up in the sky
cx, cy = 1810, 150
add(f'<g transform="translate({cx},{cy})" opacity="0.85">'
    '<circle r="62" fill="none" stroke="#ffcf6e" stroke-width="2" opacity="0.7"/>'
    '<circle r="48" fill="none" stroke="#ffcf6e" stroke-width="1" opacity="0.5"/>'
    '<path d="M0,-70 L12,0 L0,70 L-12,0 Z" fill="#ffcf6e"/>'
    '<path d="M-70,0 L0,12 L70,0 L0,-12 Z" fill="#ffcf6e" opacity="0.7"/>'
    '<path d="M0,-70 L12,0 L-12,0 Z" fill="#fff3d6"/>'
    '<text y="-80" text-anchor="middle" font-family="Georgia, serif" font-size="24" fill="#fff3d6">N</text></g>')
add('</g>')

# ---------------- seam
add(f'<line x1="{C - H - 20}" y1="{H + 20}" x2="{C + 20}" y2="-20" stroke="url(#seam)" stroke-width="26" opacity="0.35" filter="url(#blur8)"/>')
add(f'<line x1="{C - H - 20}" y1="{H + 20}" x2="{C + 20}" y2="-20" stroke="url(#seam)" stroke-width="5"/>')

# ---------------- title
add('<g font-family="Georgia, Palatino Linotype, serif" text-anchor="middle" filter="url(#shadow)">')
BOX = 1400
add(f'<rect x="{(W - BOX) / 2:.0f}" y="{H / 2 - 150}" width="{BOX}" height="300" rx="28" fill="#000" opacity="0.38"/>')
add(f'<text x="{W / 2}" y="{H / 2 + 10}" text-anchor="middle" font-size="120" font-weight="bold">'
    f'<tspan letter-spacing="8" fill="#7fdcff">CHIM</tspan>'
    f'<tspan dx="40" letter-spacing="3" fill="url(#goldText)">Travel</tspan></text>')
add(f'<text x="{W / 2}" y="{H / 2 + 100}" font-size="64" letter-spacing="34" fill="url(#titleGrad)">FIX</text>')
add(f'<text x="{W / 2}" y="{H - 48}" font-size="34" letter-spacing="6" fill="#ffffff" opacity="0.85">by SaurOff</text>')
add('</g>')
add('</svg>')

here = os.path.dirname(os.path.abspath(__file__))
svg = "\n".join(out)
open(os.path.join(here, "banner.svg"), "w", encoding="utf-8").write(svg)
open(os.path.join(here, "banner.html"), "w", encoding="utf-8").write(
    '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;background:#000;overflow:hidden}</style></head><body>'
    + svg + "</body></html>")
print("written", os.path.join(here, "banner.html"))
