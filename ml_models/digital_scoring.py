"""
Digital Presence Scoring Module
Scrapes real web/social data for merchants and generates digital scores via Gemini AI.
"""
import asyncio
import re
import logging
from datetime import datetime, timezone
from typing import Optional
from urllib.parse import urlparse

import httpx
from bs4 import BeautifulSoup

logger = logging.getLogger(__name__)

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8",
}


def get_db_connection():
    import sys
    sys.path.insert(0, '/app/ml_models')
    from predict_merchant import get_db_connection as _get_conn
    return _get_conn()


def get_merchants_with_digital_info(limit: int = 50) -> list:
    """Fetch merchants with their website/social URLs from DB."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT p.partner_id, p.partner_name, p.partner_website, p.partner_facebook,
               p.partner_instagram, p.partner_mail,
               pc.partner_category_name as category,
               mc.total_visits, mc.unique_visitors, mc.active_promotion_count,
               mc.popularity_score
        FROM partner p
        LEFT JOIN partner_category pc ON p.partner_category_id = pc.partner_category_id
        LEFT JOIN cp_merchants_catalog mc ON mc.partner_id = p.partner_id
        WHERE p.partener_active = 1
        ORDER BY mc.popularity_score DESC
        LIMIT %s
    """, (limit,))
    rows = cursor.fetchall()
    conn.close()

    results = []
    for r in rows:
        results.append({
            'partner_id': int(r['partner_id']),
            'partner_name': r['partner_name'],
            'category': r['category'] or 'Autre',
            'website': r['partner_website'] or '',
            'facebook': r['partner_facebook'] or '',
            'instagram': r['partner_instagram'] or '',
            'email': r['partner_mail'] or '',
            'total_visits': int(r['total_visits'] or 0),
            'unique_visitors': int(r['unique_visitors'] or 0),
            'active_promos': int(r['active_promotion_count'] or 0),
            'popularity_score': float(r['popularity_score'] or 0),
        })
    return results


async def scrape_website(url: str, timeout: float = 10.0) -> dict:
    """Scrape a merchant website for digital presence signals."""
    result = {
        'url': url, 'accessible': False, 'has_ssl': False,
        'title': '', 'description': '', 'has_contact': False,
        'has_social_links': False, 'social_links': [],
        'has_ecommerce': False, 'has_booking': False,
        'page_load_ok': False, 'meta_tags_count': 0,
        'error': None,
    }
    if not url or len(url.strip()) < 5:
        result['error'] = 'no_url'
        return result

    # Normalize URL
    if not url.startswith(('http://', 'https://')):
        url = 'https://' + url
    result['url'] = url
    result['has_ssl'] = url.startswith('https://')

    try:
        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True, verify=False, headers=HEADERS) as client:
            resp = await client.get(url)
            result['accessible'] = resp.status_code < 400
            result['page_load_ok'] = resp.status_code == 200

            if resp.status_code == 200:
                html = resp.text
                soup = BeautifulSoup(html, 'html.parser')

                # Title
                title_tag = soup.find('title')
                result['title'] = title_tag.get_text(strip=True)[:200] if title_tag else ''

                # Meta description
                desc = soup.find('meta', attrs={'name': 'description'})
                result['description'] = desc.get('content', '')[:300] if desc else ''

                # Count meta tags (SEO signal)
                result['meta_tags_count'] = len(soup.find_all('meta'))

                # Social links
                social_patterns = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com',
                                   'linkedin.com', 'tiktok.com', 'youtube.com']
                links = soup.find_all('a', href=True)
                for link in links:
                    href = link['href'].lower()
                    for sp in social_patterns:
                        if sp in href:
                            result['social_links'].append(href)
                            result['has_social_links'] = True
                            break

                # Contact signals
                text_lower = html.lower()
                result['has_contact'] = any(k in text_lower for k in ['contact', 'tel:', 'mailto:', 'whatsapp', 'phone'])

                # E-commerce signals
                result['has_ecommerce'] = any(k in text_lower for k in ['panier', 'cart', 'shop', 'acheter', 'buy', 'checkout', 'paiement'])

                # Booking signals
                result['has_booking'] = any(k in text_lower for k in ['reservation', 'booking', 'reserver', 'rendez-vous', 'appointment'])

    except httpx.TimeoutException:
        result['error'] = 'timeout'
    except Exception as e:
        result['error'] = str(e)[:100]

    return result


async def scrape_facebook(url: str, timeout: float = 10.0) -> dict:
    """Scrape Facebook page for basic presence signals."""
    result = {
        'url': url, 'accessible': False, 'page_name': '',
        'has_about': False, 'has_posts': False,
        'category': '', 'error': None,
    }
    if not url or len(url.strip()) < 10:
        result['error'] = 'no_url'
        return result

    try:
        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True, verify=False, headers=HEADERS) as client:
            resp = await client.get(url)
            result['accessible'] = resp.status_code < 400

            if resp.status_code == 200:
                html = resp.text
                soup = BeautifulSoup(html, 'html.parser')
                title = soup.find('title')
                result['page_name'] = title.get_text(strip=True)[:200] if title else ''

                text = html.lower()
                result['has_about'] = 'about' in text or 'a propos' in text
                result['has_posts'] = 'post' in text or 'publication' in text

                # Try extracting OG metadata
                og_desc = soup.find('meta', property='og:description')
                if og_desc:
                    result['category'] = og_desc.get('content', '')[:200]

    except httpx.TimeoutException:
        result['error'] = 'timeout'
    except Exception as e:
        result['error'] = str(e)[:100]

    return result


async def scrape_instagram(url: str, timeout: float = 10.0) -> dict:
    """Scrape Instagram profile for basic presence signals."""
    result = {
        'url': url, 'accessible': False, 'username': '',
        'bio': '', 'error': None,
    }
    if not url or len(url.strip()) < 10:
        result['error'] = 'no_url'
        return result

    # Extract username
    parsed = urlparse(url)
    path_parts = [p for p in parsed.path.split('/') if p]
    result['username'] = path_parts[0] if path_parts else ''

    try:
        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True, verify=False, headers=HEADERS) as client:
            resp = await client.get(url)
            result['accessible'] = resp.status_code < 400

            if resp.status_code == 200:
                soup = BeautifulSoup(resp.text, 'html.parser')
                desc = soup.find('meta', property='og:description')
                if desc:
                    result['bio'] = desc.get('content', '')[:300]

    except httpx.TimeoutException:
        result['error'] = 'timeout'
    except Exception as e:
        result['error'] = str(e)[:100]

    return result


async def scrape_google_search(merchant_name: str, location: str = "Tunisie", timeout: float = 10.0) -> dict:
    """Search Google for merchant to find additional digital signals."""
    result = {
        'query': f'{merchant_name} {location}',
        'found': False, 'result_count': 0,
        'has_google_maps': False, 'has_reviews': False,
        'snippets': [], 'error': None,
    }
    try:
        query = f"{merchant_name} {location}"
        search_url = f"https://www.google.com/search?q={httpx.URL(f'https://g.co/?q={query}').params.get('q', query)}&hl=fr"
        # Use simple URL encoding
        import urllib.parse
        encoded_q = urllib.parse.quote_plus(query)
        search_url = f"https://www.google.com/search?q={encoded_q}&hl=fr"

        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True, verify=False, headers=HEADERS) as client:
            resp = await client.get(search_url)
            if resp.status_code == 200:
                html = resp.text
                soup = BeautifulSoup(html, 'html.parser')

                # Count results
                divs = soup.find_all('div', class_='g')
                result['result_count'] = len(divs)
                result['found'] = len(divs) > 0

                # Google Maps presence
                text = html.lower()
                result['has_google_maps'] = 'maps.google' in text or 'google.com/maps' in text or 'gstatic.com/mapfiles' in text

                # Reviews
                result['has_reviews'] = 'avis' in text or 'review' in text or 'etoile' in text

                # Extract snippets
                for div in divs[:3]:
                    snippet = div.get_text(strip=True)[:200]
                    if snippet:
                        result['snippets'].append(snippet)

    except httpx.TimeoutException:
        result['error'] = 'timeout'
    except Exception as e:
        result['error'] = str(e)[:100]

    return result


def calculate_digital_score(website_data: dict, facebook_data: dict,
                            instagram_data: dict, google_data: dict,
                            merchant: dict) -> dict:
    """Calculate a comprehensive digital presence score (0-100)."""
    score = 0
    breakdown = {}

    # === Website (30 points max) ===
    web_score = 0
    if merchant.get('website'):
        web_score += 5  # Has a website URL configured
    if website_data.get('accessible'):
        web_score += 8
    if website_data.get('has_ssl'):
        web_score += 3
    if website_data.get('title'):
        web_score += 2
    if website_data.get('description'):
        web_score += 3  # SEO
    if website_data.get('has_contact'):
        web_score += 3
    if website_data.get('has_ecommerce'):
        web_score += 3
    if website_data.get('has_booking'):
        web_score += 3
    web_score = min(web_score, 30)
    breakdown['website'] = web_score

    # === Facebook (25 points max) ===
    fb_score = 0
    if merchant.get('facebook'):
        fb_score += 5  # Has FB configured
    if facebook_data.get('accessible'):
        fb_score += 10
    if facebook_data.get('page_name'):
        fb_score += 5
    if facebook_data.get('has_about'):
        fb_score += 3
    if facebook_data.get('has_posts'):
        fb_score += 2
    fb_score = min(fb_score, 25)
    breakdown['facebook'] = fb_score

    # === Instagram (25 points max) ===
    ig_score = 0
    if merchant.get('instagram'):
        ig_score += 5  # Has IG configured
    if instagram_data.get('accessible'):
        ig_score += 10
    if instagram_data.get('username'):
        ig_score += 5
    if instagram_data.get('bio'):
        ig_score += 5
    ig_score = min(ig_score, 25)
    breakdown['instagram'] = ig_score

    # === Google Presence (20 points max) ===
    google_score = 0
    if google_data.get('found'):
        google_score += 5
    if google_data.get('result_count', 0) > 3:
        google_score += 5
    if google_data.get('has_google_maps'):
        google_score += 5
    if google_data.get('has_reviews'):
        google_score += 5
    google_score = min(google_score, 20)
    breakdown['google'] = google_score

    score = web_score + fb_score + ig_score + google_score

    # Classification
    if score >= 70:
        level = 'EXCELLENT'
    elif score >= 50:
        level = 'BON'
    elif score >= 30:
        level = 'MOYEN'
    else:
        level = 'FAIBLE'

    return {
        'digital_score': score,
        'level': level,
        'breakdown': breakdown,
        'max_score': 100,
    }


async def score_merchant_digital(merchant: dict) -> dict:
    """Scrape and score a single merchant's digital presence."""
    # Run all scraping tasks concurrently
    website_task = scrape_website(merchant.get('website', ''))
    facebook_task = scrape_facebook(merchant.get('facebook', ''))
    instagram_task = scrape_instagram(merchant.get('instagram', ''))
    google_task = scrape_google_search(merchant['partner_name'])

    website_data, facebook_data, instagram_data, google_data = await asyncio.gather(
        website_task, facebook_task, instagram_task, google_task
    )

    scoring = calculate_digital_score(website_data, facebook_data, instagram_data, google_data, merchant)

    return {
        'partner_id': merchant['partner_id'],
        'partner_name': merchant['partner_name'],
        'category': merchant['category'],
        'digital_score': scoring['digital_score'],
        'level': scoring['level'],
        'breakdown': scoring['breakdown'],
        'scrape_data': {
            'website': website_data,
            'facebook': facebook_data,
            'instagram': instagram_data,
            'google': google_data,
        },
        'has_website': bool(merchant.get('website')),
        'has_facebook': bool(merchant.get('facebook')),
        'has_instagram': bool(merchant.get('instagram')),
        'scraped_at': datetime.now(timezone.utc).isoformat(),
    }


async def score_all_merchants(limit: int = 50, batch_size: int = 5) -> dict:
    """Score all active merchants' digital presence in batches."""
    merchants = get_merchants_with_digital_info(limit=limit)

    all_scores = []
    for i in range(0, len(merchants), batch_size):
        batch = merchants[i:i + batch_size]
        tasks = [score_merchant_digital(m) for m in batch]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        for r in results:
            if isinstance(r, Exception):
                logger.warning(f"Scoring error: {r}")
            else:
                all_scores.append(r)
        # Small delay between batches to avoid rate limiting
        if i + batch_size < len(merchants):
            await asyncio.sleep(1.0)

    # Sort by digital score desc
    all_scores.sort(key=lambda x: x['digital_score'], reverse=True)

    # Stats
    levels = {'EXCELLENT': 0, 'BON': 0, 'MOYEN': 0, 'FAIBLE': 0}
    total_score = 0
    for s in all_scores:
        levels[s['level']] = levels.get(s['level'], 0) + 1
        total_score += s['digital_score']

    avg_score = round(total_score / max(len(all_scores), 1), 1)

    return {
        'success': True,
        'count': len(all_scores),
        'avg_score': avg_score,
        'distribution': levels,
        'merchants': all_scores,
        'scored_at': datetime.now(timezone.utc).isoformat(),
    }


async def generate_digital_audit(merchant_score: dict, api_key: str,
                                  model_provider: str = 'gemini',
                                  model_name: str = 'gemini-2.5-flash') -> dict:
    """Generate AI-powered digital audit recommendations for a merchant."""
    scrape = merchant_score.get('scrape_data', {})
    web = scrape.get('website', {})
    fb = scrape.get('facebook', {})
    ig = scrape.get('instagram', {})
    ggl = scrape.get('google', {})

    prompt = f"""Tu es un expert en marketing digital pour des commerces en Tunisie.
Analyse la presence digitale du marchand suivant et fournis un audit detaille avec des recommandations concretes.

MARCHAND: {merchant_score['partner_name']}
CATEGORIE: {merchant_score['category']}
SCORE DIGITAL: {merchant_score['digital_score']}/100 ({merchant_score['level']})

DONNEES SCRAPPEES:
- Site Web: {'Accessible' if web.get('accessible') else 'Non accessible'} | SSL: {'Oui' if web.get('has_ssl') else 'Non'} | Titre: {web.get('title','')} | E-commerce: {web.get('has_ecommerce')} | Booking: {web.get('has_booking')} | Contact visible: {web.get('has_contact')} | Meta tags: {web.get('meta_tags_count',0)}
- Facebook: {'Page accessible' if fb.get('accessible') else 'Non accessible'} | Nom: {fb.get('page_name','')}
- Instagram: {'Profil accessible' if ig.get('accessible') else 'Non accessible'} | Username: {ig.get('username','')} | Bio: {ig.get('bio','')}
- Google: {'Present' if ggl.get('found') else 'Non trouve'} | Resultats: {ggl.get('result_count',0)} | Google Maps: {ggl.get('has_google_maps')} | Avis: {ggl.get('has_reviews')}

BREAKDOWN SCORE:
- Site web: {merchant_score['breakdown']['website']}/30
- Facebook: {merchant_score['breakdown']['facebook']}/25
- Instagram: {merchant_score['breakdown']['instagram']}/25
- Google: {merchant_score['breakdown']['google']}/20

Reponds en JSON strict avec cette structure:
{{
    "diagnostic": "Resume en 2-3 phrases de la situation digitale",
    "points_forts": ["point 1", "point 2"],
    "points_faibles": ["point 1", "point 2"],
    "recommendations": [
        {{
            "priority": "P0|P1|P2",
            "canal": "website|facebook|instagram|google|general",
            "action": "Description concrete de l'action",
            "impact_attendu": "Impact estime",
            "effort": "faible|moyen|eleve"
        }}
    ],
    "score_potentiel": 85,
    "strategie_contenu": {{
        "frequence_publication": "X fois par semaine",
        "types_contenu": ["type 1", "type 2"],
        "ton_recommande": "Description du ton"
    }}
}}"""

    try:
        try:
            from emergentintegrations.llm.chat import LlmChat, UserMessage
            chat = LlmChat(api_key=api_key, session_id=f"digital-audit-{merchant_score['partner_id']}-{datetime.now().strftime('%Y%m%d%H%M')}", system_message="Tu es un expert en marketing digital pour des commerces en Tunisie. Tu analyses la presence digitale et fournis des audits detailles. Reponds UNIQUEMENT en JSON valide.")
            chat.with_model(model_provider, model_name)
            response = await chat.send_message(UserMessage(text=prompt))
        except ImportError:
            if model_provider == "gemini":
                import google.generativeai as genai
                genai.configure(api_key=api_key)
                gen_model = genai.GenerativeModel(model_name, system_instruction="Tu es un expert en marketing digital pour des commerces en Tunisie. Tu analyses la presence digitale et fournis des audits detailles. Reponds UNIQUEMENT en JSON valide.")
                resp = await asyncio.to_thread(gen_model.generate_content, prompt)
                response = resp.text
            else:
                from openai import AsyncOpenAI
                client = AsyncOpenAI(api_key=api_key)
                resp = await client.chat.completions.create(
                    model=model_name,
                    messages=[
                        {"role": "system", "content": "Tu es un expert en marketing digital pour des commerces en Tunisie. Tu analyses la presence digitale et fournis des audits detailles. Reponds UNIQUEMENT en JSON valide."},
                        {"role": "user", "content": prompt},
                    ],
                    temperature=0.7,
                )
                response = resp.choices[0].message.content

        # Parse JSON from response
        clean = response.strip()
        if clean.startswith('```'):
            clean = clean.split('\n', 1)[1] if '\n' in clean else clean[3:]
            if clean.endswith('```'):
                clean = clean[:-3]
            clean = clean.strip()
            if clean.startswith('json'):
                clean = clean[4:].strip()

        try:
            audit = json.loads(clean)
        except json.JSONDecodeError:
            json_match = re.search(r'\{[\s\S]*\}', clean)
            if json_match:
                audit = json.loads(json_match.group())
            else:
                audit = {'diagnostic': clean[:500], 'recommendations': []}

        return {
            'success': True,
            'partner_id': merchant_score['partner_id'],
            'partner_name': merchant_score['partner_name'],
            'digital_score': merchant_score['digital_score'],
            'level': merchant_score['level'],
            'audit': audit,
            'generated_at': datetime.now(timezone.utc).isoformat(),
        }
    except Exception as e:
        logger.error(f"Gemini audit error: {e}")
        return {
            'success': False,
            'partner_id': merchant_score['partner_id'],
            'partner_name': merchant_score['partner_name'],
            'error': str(e),
        }
