"""Extract and render static translated pages without changing source markup.

Only text nodes, user-facing attributes and schema text are translated. URLs,
scripts and stylesheet rules are handled separately by the site generator.
"""
import html
import json
import re

TOKEN = re.compile(r'(<script\b[^>]*>[\s\S]*?</script\s*>|<style\b[^>]*>[\s\S]*?</style\s*>|<!--[\s\S]*?-->|<![^>]*>|<[^>]*>)', re.I)
ATTR = re.compile(r'([\w:-]+)(\s*=\s*)([\"\'])(.*?)\3', re.S)
TEXT_ATTRS = {'alt', 'title', 'aria-label', 'placeholder'}
SCHEMA_TEXT = {'name', 'description', 'caption', 'headline', 'jobTitle', 'serviceType', 'text'}
META_TEXT = {'description', 'og:title', 'og:description', 'twitter:title', 'twitter:description'}

def content(source, translate=lambda value: value):
    def text_value(raw):
        key = html.unescape(raw.strip())
        if not key or not re.search(r'[A-Za-zÀ-žА-я]', key):
            return raw
        value = translate(key)
        before = raw[:len(raw) - len(raw.lstrip())]
        after = raw[len(raw.rstrip()):]
        return before + html.escape(value, quote=False) + after

    def schema(value, key=None):
        if isinstance(value, dict):
            return {k: schema(v, k) for k, v in value.items()}
        if isinstance(value, list):
            return [schema(v, key) for v in value]
        if isinstance(value, str) and key in SCHEMA_TEXT:
            return translate(value)
        return value

    def tag_value(tag):
        lower = tag.lower()
        if lower.startswith('<script'):
            if re.search(r'type=[\"\']application/ld\+json', tag, re.I):
                opening, body, ending = re.match(r'(<script\b[^>]*>)([\s\S]*?)(</script\s*>)', tag, re.I).groups()
                return opening + '\n' + json.dumps(schema(json.loads(body)), ensure_ascii=False, indent=2) + '\n' + ending
            return tag
        if lower.startswith(('<style', '<!', '</')):
            return tag
        attributes = {m[1].lower(): html.unescape(m[4]) for m in ATTR.finditer(tag)}
        def attr_value(match):
            key, separator, quote, raw = match.groups()
            value = html.unescape(raw)
            is_text = key.lower() in TEXT_ATTRS
            if lower.startswith('<meta') and key.lower() == 'content':
                is_text = attributes.get('name', attributes.get('property')) in META_TEXT
            if lower.startswith('<input') and key.lower() == 'value':
                is_text = attributes.get('type') in {'submit', 'button', 'reset'}
            if is_text and value.strip():
                value = translate(value)
                return key + separator + quote + html.escape(value, quote=True) + quote
            return match[0]
        return ATTR.sub(attr_value, tag)

    return ''.join(tag_value(part) if index % 2 else text_value(part)
                   for index, part in enumerate(TOKEN.split(source)))

def extract(source):
    entries = {}
    def remember(value):
        entries[value] = value
        return value
    content(source, remember)
    return list(entries)
