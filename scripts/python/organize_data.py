import os
import shutil
import re
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[2]

def sanitize_filename(name):
    name = name.replace('EUROTRADEMETAL, spol. s r.o. - ', '')
    return re.sub(r'[\\/*?:"<>|]', '', name).strip()

def organize():
    source_dir = PROJECT_ROOT / "data" / "raw" / "scraped_data"
    target_dir = PROJECT_ROOT / "data" / "processed" / "organized_content"
    
    if target_dir.exists():
        shutil.rmtree(target_dir)
    target_dir.mkdir(parents=True, exist_ok=True)

    categories = {
        "produkty": ["plechy", "zvitky", "páska", "profily", "rúry", "tyče", "oceľ", "drôt", "valce", "tŕne"],
        "o_spolocnosti": ["profil", "o nás", "základné informacie", "história", "spoločnosť"],
        "kontakt": ["kontakt", "formulár", "adresa", "mapa"]
    }

    for cat in categories:
        os.makedirs(os.path.join(target_dir, cat), exist_ok=True)

    for filename in os.listdir(source_dir):
        if not filename.endswith(".txt"):
            continue
            
        file_path = os.path.join(source_dir, filename)
        
        with open(file_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()
            if len(lines) < 3:
                continue
            
            # Skúsime nájsť názov stránky v prvých riadkoch
            title = None
            # Vynecháme URL a oddelovač
            potential_lines = lines[2:15]
            for line in potential_lines:
                clean_line = line.strip()
                if clean_line and clean_line not in ["slovensky", "Profil spoločnosti", "Produkty", "Kontakt", "Kontaktný formulár", "HOME"]:
                    title = clean_line
                    break
            
            if not title:
                title = filename.replace('.txt', '')

            new_name = sanitize_filename(title) + ".txt"
            
            # Určenie kategórie
            assigned_cat = "ostatne"
            content_lower = "".join(lines).lower()
            
            for cat, keywords in categories.items():
                if any(kw in content_lower for kw in keywords):
                    assigned_cat = cat
                    break
            
            if assigned_cat == "ostatne" and not os.path.exists(os.path.join(target_dir, "ostatne")):
                os.makedirs(os.path.join(target_dir, "ostatne"), exist_ok=True)

            dest_path = os.path.join(target_dir, assigned_cat, new_name)
            
            counter = 1
            while os.path.exists(dest_path):
                name_part = sanitize_filename(title)
                dest_path = os.path.join(target_dir, assigned_cat, f"{name_part}_{counter}.txt")
                counter += 1

            shutil.copy2(file_path, dest_path)
            print(f"Triedim: {filename} -> {assigned_cat}/{os.path.basename(dest_path)}")

if __name__ == "__main__":
    organize()
    print("Dáta boli roztriedené v priečinku 'data/processed/organized_content'.")
