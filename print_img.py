from PIL import Image
import sys

# Load image
img = Image.open('/Users/jakubchutnak/Sites/eurotrademetal/produkty.png')
# Resize to fit terminal roughly (e.g. 100 chars wide)
width = 80
ratio = img.height / img.width
height = int(width * ratio * 0.5) # 0.5 because terminal chars are twice as tall as they are wide roughly
img = img.resize((width, height))
img = img.convert('RGB')

# ANSI escape code for truecolor background
def print_color(r, g, b):
    return f"\033[48;2;{r};{g};{b}m \033[0m"

for y in range(height):
    line = ""
    for x in range(width):
        r, g, b = img.getpixel((x, y))
        line += print_color(r, g, b)
    print(line)
