from PIL import Image

img = Image.open('/Users/jakubchutnak/Sites/eurotrademetal/slider.png')
width, height = img.size

# Check colors in 4 quadrants or columns
blocks = []
for i in range(4):
    x = int((i + 0.5) * (width / 4))
    y = int(height / 2)
    blocks.append(img.getpixel((x, y)))

print("Column center colors:", blocks)
