from PIL import Image
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[2]
img = Image.open(PROJECT_ROOT / 'assets' / 'images' / 'slider.png')
width, height = img.size

# Check colors in 4 quadrants or columns
blocks = []
for i in range(4):
    x = int((i + 0.5) * (width / 4))
    y = int(height / 2)
    blocks.append(img.getpixel((x, y)))

print("Column center colors:", blocks)
