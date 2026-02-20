from PIL import Image

img = Image.open('/Users/jakubchutnak/Sites/eurotrademetal/produkty.png')
# Downscale for ASCII
width = 120
ratio = img.height / img.width
height = int(width * ratio * 0.5)
img = img.resize((width, height)).convert('RGB')

out = []
# Map brightness to ascii chars
chars = " .:-=+*#%@"
for y in range(height):
    line = ""
    for x in range(width):
        r,g,b = img.getpixel((x,y))
        brightness = sum([r,g,b])/3
        char_idx = int((brightness / 255) * (len(chars)-1))
        # Let's also check if it's mint or red
        if g > 200 and r < 180 and b > 180: # Mint
            line += "M"
        elif r > 200 and g < 100 and b < 100: # Red
            line += "R"
        elif sum([r,g,b]) < 50:
            line += "D" # Dark
        else:
            line += chars[char_idx]
    out.append(line)

with open('ascii_img.txt', 'w') as f:
    f.write('\n'.join(out))
