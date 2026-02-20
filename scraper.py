import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
import os
import time
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

class EurotradeScraper:
    def __init__(self, base_url):
        self.base_url = base_url
        self.domain = urlparse(base_url).netloc
        self.visited = set()
        self.output_dir = "scraped_data"
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept-Language': 'sk-SK,sk;q=0.9,en-US;q=0.8,en;q=0.7',
        })

        if not os.path.exists(self.output_dir):
            os.makedirs(self.output_dir)

    def clean_filename(self, url):
        parsed = urlparse(url)
        path = parsed.path.strip('/')
        query = parsed.query.replace('&', '_').replace('=', '-')
        name = path if path else "index"
        if query:
            name += "_" + query
        return name.replace('/', '_') + ".txt"

    def scrape_url(self, url):
        if url in self.visited:
            return
        
        print(f"Scraping: {url}")
        try:
            # Pridáme malý delay, aby sme nevyzerali ako robot
            time.sleep(0.3)
            response = self.session.get(url, timeout=10, verify=False)
            response.raise_for_status()
            self.visited.add(url)

            soup = BeautifulSoup(response.text, 'html.parser')
            
            # Skúsime nájsť hlavný obsah - na tomto webe to vyzerá na div s id="content" alebo podobne
            # Alebo proste vezmeme všetok text a vyčistíme ho od menu
            
            # Odstránime navigáciu a skripty pred extrakciou textu, aby sme mali len unikátny obsah
            for silent_tag in soup(["script", "style", "nav", "header", "footer"]):
                silent_tag.decompose()

            text_content = soup.get_text(separator='\n', strip=True)
            
            # Uložíme HTML aj TXT
            filename_base = self.clean_filename(url)
            
            with open(os.path.join(self.output_dir, filename_base), "w", encoding="utf-8") as f:
                f.write(f"URL: {url}\n")
                f.write("="*50 + "\n")
                f.write(text_content)
                
            with open(os.path.join(self.output_dir, filename_base.replace('.txt', '.html')), "w", encoding="utf-8") as f:
                f.write(response.text)

            return soup

        except Exception as e:
            print(f"Failed {url}: {e}")
            return None

    def run(self):
        # 1. Najprv prejdeme číselný rad, o ktorom vieme, že tam sú dáta
        for s_val in range(1, 40):
            target_url = f"https://eurotrade.sk/?lg=1&s={s_val}"
            self.scrape_url(target_url)
            
        # 2. Potom prejdeme úvodnú stránku a hľadáme ďalšie linky (pre istotu)
        soup = self.scrape_url("https://eurotrade.sk/")
        if soup:
            for a in soup.find_all('a', href=True):
                full_url = urljoin(self.base_url, a['href'])
                if self.domain in full_url and full_url not in self.visited:
                    if not any(full_url.endswith(ext) for ext in ['.pdf', '.jpg', '.png']):
                        self.scrape_url(full_url)

if __name__ == "__main__":
    scraper = EurotradeScraper("https://eurotrade.sk/")
    scraper.run()
    print("\nHotovo. Skontroluj priečinok 'scraped_data'.")
