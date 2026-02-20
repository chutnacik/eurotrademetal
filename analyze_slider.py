from PIL import Image
import collections

img = Image.open('/Users/jakubchutnak/Sites/eurotrademetal/slider.png')
print(f"Size: {img.size}")
# Colors
colors = img.getcolors(img.width * img.height)
if colors:
    colors.sort(key=lambda x: x[0], reverse=True)
    print("Top 10 colors:", [c[1] for c in colors[:10]])

# ASCII Preview to file
width = 120
ratio = img.height / img.width
height = int(width * ratio * 0.5)
img_small = img.resize((width, height)).convert('RGB')
chars = " .:-=+*#%@"
out = []
for y in range(height):
    line = ""
    for x in range(width):
        r, g, b = img_small.getpixel((x, y))
        brightness = sum([r,g,b])/3
        char_idx = int((brightness / 255) * (len(chars)-1))
        line += chars[char_idx]
    out.append(line)

with open('slider_preview.txt', 'w') as f:
    f.write('\n'.join(out))
