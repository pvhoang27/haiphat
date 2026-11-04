"""Build the outlined brand SVG: pip install fonttools pillow, then run this file."""
from pathlib import Path
from xml.etree import ElementTree as ET

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.ttLib import TTFont
from PIL import ImageFont

ROOT = Path(__file__).resolve().parents[1]
BRAND = ROOT / 'hai-phat-vina/assets/brand'
FONTS = ROOT / 'hai-phat-vina/assets/fonts'
svg = ET.Element('svg', xmlns='http://www.w3.org/2000/svg', viewBox='0 0 1200 300', width='1200', height='300')
ET.SubElement(svg, 'title').text = 'Hải Phát Vina — Mái che & bạt công trình'
mark = ET.SubElement(svg, 'g', transform='translate(34 48) scale(3.642857142857143)')
for points, color in [
    ('3,16 14,9 14,34 3,41', '#123E35'),
    ('17,7 27,1 27,26 17,32', '#2F6B54'),
    ('30,1 40,7 40,32 30,26', '#123E35'),
    ('43,9 53,16 53,41 43,34', '#2F6B54'),
    ('17,35 27,29 40,36 40,42 27,35 17,41', '#F1C75B'),
]:
    ET.SubElement(mark, 'polygon', points=points, fill=color)

for text, weight, size, x, y, color in [
    ('Hải Phát Vina', 700, 88, 270, 55, '#123E35'),
    ('MÁI CHE & BẠT CÔNG TRÌNH', 400, 34, 276, 170, '#6B7F76'),
]:
    path = FONTS / f'be-vietnam-pro-{weight}.ttf'
    font = TTFont(path)
    glyphs = font.getGlyphSet()
    cmap = font.getBestCmap()
    scale = size / font['head'].unitsPerEm
    baseline = y + ImageFont.truetype(str(path), size).getmetrics()[0]
    group = ET.SubElement(svg, 'g', fill=color, transform=f'translate({x} {baseline}) scale({scale} {-scale})')
    advance = 0
    for char in text:
        glyph = glyphs[cmap[ord(char)]]
        pen = SVGPathPen(glyphs)
        glyph.draw(pen)
        if pen.getCommands():
            ET.SubElement(group, 'path', d=pen.getCommands(), transform=f'translate({advance} 0)')
        advance += glyph.width

ET.indent(svg)
ET.ElementTree(svg).write(BRAND / 'hai-phat-vina-logo-ngang.svg', encoding='unicode', xml_declaration=False)
