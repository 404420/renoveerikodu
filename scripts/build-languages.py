"""Build reviewed, crawlable translations. Python 3, no external dependencies."""
import importlib.util
from datetime import date
import json
import re
import sys
from pathlib import Path
from urllib.parse import urljoin, urlsplit, urlunsplit
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parent.parent
BASE = 'https://www.renoveerikodu.ee'
CATALOGS = ROOT / 'scripts/i18n'
ROUTES = json.loads((CATALOGS / 'routes.json').read_text())
LANGUAGES = ['et', 'en', 'ru']
LOCALES = {'et': 'et_EE', 'en': 'en_GB', 'ru': 'ru_RU'}
spec = importlib.util.spec_from_file_location('i18n_content', ROOT / 'scripts/i18n-content.py')
parser = importlib.util.module_from_spec(spec)
spec.loader.exec_module(parser)

def catalog_name(route):
    return route['source'].replace('/index.html', '').replace('.html', '')

def load_catalog(route):
    common = json.loads((CATALOGS / 'common.json').read_text())
    common.update(json.loads((CATALOGS / (catalog_name(route) + '.json')).read_text()))
    for key, values in common.items():
        if not isinstance(values, list) or len(values) != 2 or not all(isinstance(v, str) and v.strip() for v in values):
            raise ValueError('Invalid translation: ' + key)
    return common

def clean_source(source):
    source = re.sub(r'<nav\b[^>]*data-language-switcher[^>]*>[\s\S]*?</nav>\s*', '', source)
    source = re.sub(r'\s*<link\b[^>]*\bhreflang=[^>]*>', '', source)
    source = re.sub(r'\s*<meta\b[^>]*property="og:locale:alternate"[^>]*>', '', source)
    source = re.sub(r'\s*<link\b[^>]*href="/assets/css/languages.css[^"]*"[^>]*>', '', source)
    source = re.sub(r'\s*<script\b[^>]*src="/assets/js/i18n.js[^"]*"[^>]*></script>', '', source)
    source = re.sub(r'\s*<input type="hidden" name="language" value="(?:et|en|ru)">', '', source)
    return source

def rewrite_url(value, route, language):
    if not value or value.startswith(('#', 'mailto:', 'tel:', 'data:', 'javascript:')):
        return value
    resolved = urlsplit(urljoin(BASE + route['et'], value))
    if resolved.hostname not in {'www.renoveerikodu.ee', 'renoveerikodu.ee'}:
        return value
    for target in ROUTES:
        aliases = {target['et'], '/' + target['source']}
        if target['source'].endswith('/index.html'):
            aliases.add('/' + target['source'].removesuffix('index.html').rstrip('/'))
        if target['et'] == '/':
            aliases.add('/index.php')
        if resolved.path in aliases:
            return urlunsplit(('https', 'www.renoveerikodu.ee', target[language], resolved.query, resolved.fragment))
    # Shared assets and APIs are rooted at the domain, even on nested pages.
    return urlunsplit(('', '', resolved.path, resolved.query, resolved.fragment))

def rewrite_markup(source, route, language):
    def replace_tag(tag):
        def replace_attr(attr):
            key, separator, quote, value = attr.groups()
            if key.lower() in {'src', 'href', 'action', 'poster'}:
                value = rewrite_url(value, route, language)
            elif key.lower() == 'srcset':
                value = ', '.join(' '.join([rewrite_url(parts[0], route, language)] + parts[1:])
                                  for item in value.split(',') if (parts := item.strip().split()))
            return key + separator + quote + value + quote
        return parser.ATTR.sub(replace_attr, tag)
    # Do not rewrite HTML-looking strings inside scripts, styles or comments.
    source = ''.join(replace_tag(part)
                     if index % 2 and not part.lower().startswith(('<script', '<style', '<!')) else part
                     for index, part in enumerate(parser.TOKEN.split(source)))
    # Script opening tags are inside raw-text tokens.
    source = re.sub(r'<script\b[^>]*\bsrc=[^>]*>', lambda match: replace_tag(match[0]), source)
    source = re.sub(r'url\((["\']?)(?:\.\./)*images/', r'url(\1/images/', source)
    return source

INLINE_MESSAGES = {
    'Uudiskirjaga liitumine ebaõnnestus. Palun proovi uuesti.': ['Newsletter subscription failed. Please try again.', 'Не удалось подписаться на рассылку. Попробуйте ещё раз.'],
    'Uudiskirjast loobumine ebaõnnestus. Palun proovi uuesti.': ['Unsubscribing failed. Please try again.', 'Не удалось отписаться от рассылки. Попробуйте ещё раз.'],
    'Palun sisesta korrektne email': ['Please enter a valid email address', 'Введите корректный адрес электронной почты'],
    'Edukalt loobusite uudiskirjadest': ['You have successfully unsubscribed', 'Вы успешно отписались от рассылки'],
    'Serveri viga': ['Server error', 'Ошибка сервера'],
    'Järgmine pilt': ['Next image', 'Следующее изображение'],
    'Eelmine pilt': ['Previous image', 'Предыдущее изображение'],
    'Sulge galerii': ['Close gallery', 'Закрыть галерею'],
    'Sulge pilt': ['Close image', 'Закрыть изображение'],
    'Fassaaditööde galerii suur pilt': ['Large image from the facade project gallery', 'Увеличенное фото из галереи фасадных работ'],
    'Fassaaditööde foto': ['Facade project photo', 'Фото фасадных работ'],
    'Tehtud töö foto': ['Completed project photo', 'Фото выполненных работ'],
    'Tehtud too foto': ['Completed project photo', 'Фото выполненных работ']
}

def runtime_scripts(source, language):
    def rewrite_script(match):
        if 'application/ld+json' in match[1] or ' src=' in match[1]:
            return match[0]
        body = match[2]
        body = body.replace("a[href^='images/']", "a[href^='/images/']")
        body = re.sub(r'((?:src|href)=["\'])(?:\.\./)*images/', r'\1/images/', body)
        if 'function calculatePrice' in body:
            return '<script src="/assets/js/renoveeri-ai-chat.js?v=20261016"></script>'
        if language != 'et':
            for key, translations in INLINE_MESSAGES.items():
                body = body.replace(key, translations[LANGUAGES.index(language) - 1])
        # Legacy newsletter snippets on service pages also use the API.
        body = re.sub(r'fetch\("(?:kontakt.html|index.html#contact)"', 'fetch("/api/newsletter.php"', body)
        body = re.sub(r'^[ \t]*(?:data|formData)\.set\("language", document\.documentElement\.lang\);\n?', '', body, flags=re.M)
        body = re.sub(r'^[ \t]*formData\.set\("action", "unsubscribe"\);\n?', '', body, flags=re.M)
        body = body.replace('formData.append("unsubscribe_email", email);',
                            'formData.append("unsubscribe_email", email);\nformData.set("action", "unsubscribe");\nformData.set("language", document.documentElement.lang);')
        body = body.replace('data.set("action",', 'data.set("language", document.documentElement.lang);\ndata.set("action",')
        return match[1] + body + match[3]
    return re.sub(r'(<script\b[^>]*>)([\s\S]*?)(</script>)', rewrite_script, source, flags=re.I)

def localize_schema(source, route, language):
    def walk(value, key=None, parent_type=None):
        if isinstance(value, dict):
            kind = value.get('@type')
            if value.get('@id') == BASE + '/#localbusiness':
                kind = 'LocalBusiness'
            elif value.get('@id') == BASE + '/#organization':
                kind = 'Organization'
            return {k: walk(v, k, kind) for k, v in value.items()}
        if isinstance(value, list):
            return [walk(v, key, parent_type) for v in value]
        if key == 'inLanguage':
            return language
        if isinstance(value, str) and value.startswith(BASE):
            # Stable business identifiers are shared by the three websites.
            if value in {BASE + '/#organization', BASE + '/#localbusiness'}:
                return value
            if key == 'url' and (parent_type in ['Organization', 'LocalBusiness'] if isinstance(parent_type, str) else isinstance(parent_type, list) and 'LocalBusiness' in parent_type):
                return value
            mapped = rewrite_url(value, route, language)
            return mapped if mapped.startswith('https://') else value
        return value
    def replace(match):
        return match[1] + '\n' + json.dumps(walk(json.loads(match[2])), ensure_ascii=False, indent=2) + '\n' + match[3]
    return re.sub(r'(<script\b[^>]*type="application/ld\+json"[^>]*>)([\s\S]*?)(</script>)', replace, source, flags=re.I)

def build_page(source, route, language, translations):
    if language != 'et':
        source = parser.content(source, lambda key: translations[key][LANGUAGES.index(language) - 1])
    source = re.sub(r'<html lang="et">', '<html lang="' + language + '">', source, count=1)
    source = rewrite_markup(source, route, language)
    source = runtime_scripts(source, language)
    source = localize_schema(source, route, language)
    source = re.sub(r'(<meta property="og:locale" content=")[^"]*', r'\g<1>' + LOCALES[language], source)
    for property_name in ['canonical', 'og:url']:
        pattern = r'(<link rel="canonical" href=")[^"]*' if property_name == 'canonical' else r'(<meta property="og:url" content=")[^"]*'
        source = re.sub(pattern, r'\g<1>' + BASE + route[language], source)
    additions = '\n' + '\n'.join('<link rel="alternate" hreflang="' + lang + '" href="' + BASE + route[lang if lang != 'x-default' else 'et'] + '">' for lang in LANGUAGES + ['x-default'])
    additions += '\n' + '\n'.join('<meta property="og:locale:alternate" content="' + LOCALES[lang] + '">' for lang in LANGUAGES if lang != language)
    additions += '\n<link rel="stylesheet" href="/assets/css/languages.css?v=20261012">\n<script src="/assets/js/i18n.js?v=20261013"></script>\n'
    source = source.replace('</head>', additions + '</head>', 1)
    label = {'et': 'Vali keel', 'en': 'Choose language', 'ru': 'Выбрать язык'}[language]
    links = []
    for lang, short, full in [('et', 'ET', 'Eesti'), ('en', 'EN', 'English'), ('ru', 'RU', 'Русский')]:
        current = ' aria-current="page"' if lang == language else ''
        links.append('<a href="' + BASE + route[lang] + '" hreflang="' + lang + '" lang="' + lang + '" title="' + full + '" aria-label="' + full + '"' + current + '>' + short + '</a>')
    selector = '<nav class="language-switcher" data-language-switcher aria-label="' + label + '">' + ''.join(links) + '</nav>\n'
    source = source.replace('<nav id="nav">', selector + '<nav id="nav">', 1)
    # Privacy page used an old header title; align it with the public header.
    source = re.sub(r'(<header id="header"[^>]*>\s*)<h1>[\s\S]*?</h1>',
                    r'\g<1><div class="site-title"><a href="' + BASE + next(r for r in ROUTES if r['et'] == '/')[language] + '">Renoveeri Kodu</a></div>', source, count=1)
    source = re.sub(r'(<form\b[^>]*(?:action="/api/(?:contact|newsletter).php")[^>]*>)',
                    r'\1\n<input type="hidden" name="language" value="' + language + '">', source)
    # Ensure modified shared JavaScript is fetched after an upload.
    source = re.sub(r'(src="/assets/js/(?:main|led-design).js)\?v=\d+', r'\1?v=20261012', source)
    return source

def main():
    pages = []
    for route in ROUTES:
        source = clean_source((ROOT / route['source']).read_text())
        translations = load_catalog(route)
        missing = [key for key in parser.extract(source) if key not in translations]
        if missing:
            raise ValueError(route['source'] + ': missing translations ' + json.dumps(missing, ensure_ascii=False))
        pages.append((route, source, translations))
    # Only mutate after the complete translation set has passed validation.
    for route, source, translations in pages:
        for language in LANGUAGES:
            output = route['source'] if language == 'et' else route[language].lstrip('/') + ('index.html' if route[language].endswith('/') else '.html')
            target = ROOT / output
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(build_page(source, route, language, translations).rstrip() + '\n')
    sitemap = ['<?xml version="1.0" encoding="UTF-8"?>',
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">']
    images = json.loads((CATALOGS / 'sitemap-images.json').read_text())
    for route in ROUTES:
        if route.get('noindex'):
            continue
        for language in LANGUAGES:
            sitemap.append('  <url>\n    <loc>' + BASE + route[language] + '</loc>\n    <lastmod>' + date.today().isoformat() + '</lastmod>')
            for alternate in LANGUAGES + ['x-default']:
                sitemap.append('    <xhtml:link rel="alternate" hreflang="' + alternate + '" href="' + BASE + route[alternate if alternate != 'x-default' else 'et'] + '" />')
            for image in images.get(route['et'], []):
                sitemap.append('    <image:image><image:loc>' + escape(image['loc']) + '</image:loc>' + ('<image:caption>' + escape(image['caption']) + '</image:caption>' if language == 'et' and image.get('caption') else '') + '</image:image>')
            sitemap.append('  </url>')
    sitemap.append('</urlset>')
    (ROOT / 'sitemap.xml').write_text('\n'.join(sitemap) + '\n')
    print('Built 81 multilingual pages: 78 indexable pages and 3 privacy pages.')

if __name__ == '__main__':
    main()
