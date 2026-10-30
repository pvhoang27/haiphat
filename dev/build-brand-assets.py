from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "hai-phat-vina" / "assets" / "brand"
OUT.mkdir(parents=True, exist_ok=True)

GREEN = "#123E35"
TEAL = "#2F6B54"
GOLD = "#F1C75B"
MUTED = "#6B7F76"
FONT_REGULAR = ROOT / "hai-phat-vina" / "assets" / "fonts" / "be-vietnam-pro-400.ttf"
FONT_BOLD = ROOT / "hai-phat-vina" / "assets" / "fonts" / "be-vietnam-pro-700.ttf"


def draw_mark(draw, x, y, size):
    scale = size / 56
    def poly(points, fill):
        draw.polygon([(x + px * scale, y + py * scale) for px, py in points], fill=fill)

    # Four rising canopy panels form an abstract H / roof structure.
    poly([(3, 16), (14, 9), (14, 34), (3, 41)], GREEN)
    poly([(17, 7), (27, 1), (27, 26), (17, 32)], TEAL)
    poly([(30, 1), (40, 7), (40, 32), (30, 26)], GREEN)
    poly([(43, 9), (53, 16), (53, 41), (43, 34)], TEAL)
    poly([(17, 35), (27, 29), (40, 36), (40, 42), (27, 35), (17, 41)], GOLD)


icon = Image.new("RGBA", (512, 512), (255, 255, 255, 0))
icon_draw = ImageDraw.Draw(icon)
draw_mark(icon_draw, 54, 69, 404)
icon.save(OUT / "hai-phat-vina-icon-512.png", optimize=True)

logo = Image.new("RGBA", (1200, 300), (255, 255, 255, 0))
logo_draw = ImageDraw.Draw(logo)
draw_mark(logo_draw, 34, 48, 204)
name_font = ImageFont.truetype(str(FONT_BOLD), 88)
tagline_font = ImageFont.truetype(str(FONT_REGULAR), 34)
logo_draw.text((270, 55), "Hải Phát Vina", font=name_font, fill=GREEN)
logo_draw.text((276, 170), "MÁI CHE & BẠT CÔNG TRÌNH", font=tagline_font, fill=MUTED)
logo.save(OUT / "hai-phat-vina-logo-ngang.png", optimize=True)

