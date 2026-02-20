import os
import re

def clean_text(text):
    lines = text.split('\n')
    cleaned = []
    skip_keywords = [
        "URL:", "====", "slovensky", "Profil spoločnosti", "Produkty", 
        "Kontakt", "Kontaktný formulár", "EUROTRADEMETAL, spol. s r.o. - HOME",
        "Obchodný register", "odd. Sro vložka"
    ]
    for line in lines:
        line = line.strip()
        if not line:
            continue
        if any(kw in line for kw in skip_keywords):
            continue
        cleaned.append(line)
    return "\n".join(cleaned)

def create_markdown():
    source_dir = "scraped_data"
    output_file = "eurotrade_obsah.md"
    files = [f for f in os.listdir(source_dir) if f.endswith(".txt") and "s-" in f]
    files.sort(key=lambda x: int(re.search(r's-(\d+)', x).group(1)) if re.search(r's-(\d+)', x) else 0)
    content_blocks = []
    seen_content = set()
    for filename in files:
        with open(os.path.join(source_dir, filename), 'r', encoding='utf-8') as f:
            raw_content = f.read()
            lines = raw_content.split('\n')
            title = "Sekcia"
            for l in lines[2:10]:
                l_clean = l.strip()
                if l_clean and l_clean not in ["slovensky", "Profil spoločnosti", "Produkty", "Kontakt", "HOME"]:
                    title = l_clean
                    break
            cleaned = clean_text(raw_content)
            if len(cleaned) < 20 or cleaned in seen_content:
                continue
            seen_content.add(cleaned)
            content_blocks.append(f"## {title}\n\n{cleaned}")
    with open(output_file, "w", encoding="utf-8") as f:
        f.write("# Obsah webu eurotrade.sk\n\n")
        f.write("\n\n---\n\n".join(content_blocks))

if __name__ == "__main__":
    create_markdown()
    print("Súbor eurotrade_obsah.md bol vytvorený.")
