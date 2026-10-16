import fs from "node:fs";
import path from "node:path";
import { createHash } from "node:crypto";

const ROOT = process.cwd();
const BASE = "https://www.renoveerikodu.ee";
const LANGUAGES = ["et", "en", "ru"];
const ignored = new Set(["admin", "node_modules", ".git", "work", "outputs", "_site"]);
const errors = [];

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    if (entry.isDirectory() && ignored.has(entry.name)) return [];
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : entry.name.endsWith(".html") ? [full] : [];
  });
}

function decode(value) {
  return value.replace(/&(?:amp|quot|apos|lt|gt|#\d+|#x[\da-f]+);/gi, (entity) => {
    const names = { "&amp;": "&", "&quot;": '"', "&apos;": "'", "&lt;": "<", "&gt;": ">" };
    if (names[entity.toLowerCase()]) return names[entity.toLowerCase()];
    const number = entity.slice(2, -1);
    return String.fromCodePoint(number[0].toLowerCase() === "x" ? parseInt(number.slice(1), 16) : Number(number));
  });
}

function attrs(tag) {
  const result = {};
  for (const match of tag.matchAll(/([:\w-]+)\s*=\s*(["'])(.*?)\2/g)) result[match[1].toLowerCase()] = decode(match[3]);
  return result;
}

function canonicalFor(rel) {
  return rel === "index.html" ? `${BASE}/`
    : rel.endsWith("/index.html") ? `${BASE}/${rel.slice(0, -"index.html".length)}`
      : `${BASE}/${rel.replace(/\.html$/i, "")}`;
}

function languageFor(rel) {
  return /^(en|ru)\//.exec(rel)?.[1] ?? "et";
}

const pageByPath = new Map();
const pageByCanonical = new Map();
const pages = [];
const multilingualPages = [];

for (const file of walk(ROOT)) {
  const rel = path.relative(ROOT, file).replaceAll("\\", "/");
  // Ignore retired demo markup inside comments; retain runtime HTML templates
  // so broken asset URLs in dynamically created lightboxes are still detected.
  const html = fs.readFileSync(file, "utf8").replace(/<!--[\s\S]*?-->/g, "");
  const metas = [...html.matchAll(/<meta\b[^>]*>/gi)].map((m) => attrs(m[0]));
  const links = [...html.matchAll(/<link\b[^>]*>/gi)].map((m) => attrs(m[0]));
  const robots = metas.find((a) => a.name?.toLowerCase() === "robots")?.content?.toLowerCase() ?? "";
  const canonicals = links.filter((a) => a.rel?.toLowerCase() === "canonical").map((a) => a.href);
  const alternates = links.filter((a) => a.rel?.toLowerCase() === "alternate" && a.hreflang);
  const indexable = robots.includes("index") && !robots.includes("noindex");
  const canonical = canonicalFor(rel);
  const language = languageFor(rel);
  const page = { rel, html, metas, links, canonical, language, alternates, indexable };
  pageByPath.set(rel, page);
  pageByCanonical.set(canonical, page);
  if (indexable) pages.push(page);
  if (indexable || alternates.length || /^(en|ru)\//.test(rel)) multilingualPages.push(page);

  if (indexable) {
    if (!html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1]?.trim()) errors.push(`${rel}: missing title`);
    if (!metas.find((a) => a.name?.toLowerCase() === "description")?.content) errors.push(`${rel}: missing meta description`);
    const h1Count = (html.match(/<h1\b/gi) ?? []).length;
    if (h1Count !== 1) errors.push(`${rel}: expected one H1, found ${h1Count}`);
  }
  if (indexable || alternates.length || /^(en|ru)\//.test(rel)) {
    if (canonicals.length !== 1 || canonicals[0] !== canonical) errors.push(`${rel}: expected one canonical equal to ${canonical}`);
    const htmlLanguage = attrs(html.match(/<html\b[^>]*>/i)?.[0] ?? "").lang;
    if (htmlLanguage !== language) errors.push(`${rel}: HTML lang must be ${language}, found ${htmlLanguage ?? "none"}`);
    const ogUrl = metas.filter((a) => a.property === "og:url");
    if (ogUrl.length !== 1 || ogUrl[0].content !== canonical) errors.push(`${rel}: og:url must equal canonical`);
    const ogLocale = metas.filter((a) => a.property === "og:locale");
    const allowedLocales = { et: ["et_EE"], en: ["en_GB", "en_US"], ru: ["ru_RU"] };
    if (ogLocale.length !== 1 || !allowedLocales[language].includes(ogLocale[0].content)) errors.push(`${rel}: incorrect og:locale`);
  }
}

function repoPage(pathname) {
  let clean;
  try { clean = decodeURIComponent(pathname).replace(/^\/+/, ""); } catch { return; }
  const candidates = pathname.endsWith("/") ? [`${clean}index.html`]
    : /\.html$/i.test(clean) ? [clean] : [`${clean}.html`, `${clean}/index.html`];
  return candidates.map((candidate) => pageByPath.get(candidate)).find(Boolean);
}

function localUrl(href, page) {
  if (/^(?:#|mailto:|tel:|javascript:|data:)/i.test(href)) return;
  let url;
  try { url = new URL(href, page.canonical); } catch { return; }
  return ["renoveerikodu.ee", "www.renoveerikodu.ee"].includes(url.hostname) ? url : undefined;
}

function localFile(pathname) {
  try {
    const full = path.resolve(ROOT, `.${decodeURIComponent(pathname)}`);
    return full.startsWith(`${ROOT}${path.sep}`) && fs.existsSync(full) && fs.statSync(full).isFile();
  } catch { return false; }
}

function languageMap(page) {
  return Object.fromEntries(page.alternates.map((alternate) => [alternate.hreflang, alternate.href]));
}

for (const page of multilingualPages) {
  const { rel, html, canonical, language, alternates } = page;
  const expectedLanguages = [...LANGUAGES, "x-default"];
  if (alternates.length !== 4 || expectedLanguages.some((lang) => alternates.filter((a) => a.hreflang === lang).length !== 1)) {
    errors.push(`${rel}: expected exactly one et/en/ru/x-default hreflang`);
  }
  const map = languageMap(page);
  if (map[language] !== canonical) errors.push(`${rel}: self hreflang must equal canonical`);
  if (map["x-default"] !== map.et) errors.push(`${rel}: x-default must point to the Estonian counterpart`);
  for (const lang of LANGUAGES) {
    const target = pageByCanonical.get(map[lang]);
    if (!target) { errors.push(`${rel}: missing ${lang} hreflang target ${map[lang] ?? ""}`); continue; }
    if (target.language !== lang || target.indexable !== page.indexable) errors.push(`${rel}: ${lang} target has incorrect language/indexing`);
    const reciprocal = languageMap(target);
    if (expectedLanguages.some((key) => reciprocal[key] !== map[key])) errors.push(`${rel}: non-reciprocal ${lang} hreflang group`);
  }

  const switchers = [...html.matchAll(/<(nav|div|ul)\b[^>]*\bdata-language-switcher(?:\s*=\s*(["']).*?\2)?[^>]*>[\s\S]*?<\/\1>/gi)];
  if (switchers.length !== 1) errors.push(`${rel}: expected one visible native language selector`);
  const switcher = switchers[0];
  const switchLinks = [...(switcher?.[0] ?? "").matchAll(/<a\b[^>]*>[\s\S]*?<\/a>/gi)].map((m) => ({ ...attrs(m[0]), label: m[0].replace(/<[^>]*>/g, "").trim() }));
  if (switchLinks.length !== 3 || LANGUAGES.some((lang) => switchLinks.filter((a) => a.hreflang === lang).length !== 1)) errors.push(`${rel}: language selector must contain et/en/ru links`);
  for (const link of switchLinks) {
    if (!link.label || link.href !== map[link.hreflang]) errors.push(`${rel}: invalid ${link.hreflang ?? "unknown"} selector link`);
    if ((link["aria-current"] === "page") !== (link.hreflang === language)) errors.push(`${rel}: selector aria-current does not identify ${language}`);
  }

  for (const match of html.matchAll(/<a\b[^>]*>/gi)) {
    const href = attrs(match[0]).href;
    if (!href) { errors.push(`${rel}: anchor without href`); continue; }
    const url = localUrl(href, page);
    if (!url) continue;
    const target = repoPage(url.pathname);
    if (target) {
      const expected = `${target.canonical}${url.search}${url.hash}`;
      if (href !== expected) errors.push(`${rel}: internal page link must be direct: ${href} -> ${expected}`);
      const inSwitcher = switcher && match.index >= switcher.index && match.index < switcher.index + switcher[0].length;
      if (!inSwitcher && target.language !== language) errors.push(`${rel}: internal navigation changes language: ${href}`);
    } else if (!localFile(url.pathname) && !/^\/(?:admin|api)(?:\/|$)/.test(url.pathname) && !/^\/paneel(?:\.php)?\/?$/.test(url.pathname)) {
      errors.push(`${rel}: missing internal link target: ${href}`);
    }
  }

  // Resolve assets from clean public URLs, as browsers and search engines do.
  for (const match of html.matchAll(/<(img|script|link|source)\b[^>]*>/gi)) {
    const attributes = attrs(match[0]);
    const href = attributes.src ?? (match[1].toLowerCase() === "link" && /^(?:stylesheet|icon|preload|manifest|apple-touch-icon)$/.test(attributes.rel ?? "") ? attributes.href : undefined);
    const values = href ? [href] : [];
    if (attributes.srcset && !attributes.srcset.startsWith("data:")) values.push(...attributes.srcset.split(",").map((value) => value.trim().split(/\s+/)[0]));
    for (const value of values) {
      const url = localUrl(value, page);
      if (url && !localFile(url.pathname)) errors.push(`${rel}: missing local asset: ${value}`);
    }
  }

  let webPages = 0;
  let schemaCount = 0;
  function inspectSchema(value) {
    if (!value || typeof value !== "object") return;
    if (value.inLanguage !== undefined && value.inLanguage !== language) errors.push(`${rel}: JSON-LD inLanguage must be ${language}`);
    const types = Array.isArray(value["@type"]) ? value["@type"] : [value["@type"]];
    if (types.includes("WebPage")) {
      webPages++;
      if (value.url !== canonical || value.inLanguage !== language) errors.push(`${rel}: JSON-LD WebPage URL/language does not match page`);
    }
    for (const child of Object.values(value)) inspectSchema(child);
  }
  for (const match of html.matchAll(/<script\b[^>]*type\s*=\s*(["'])application\/ld\+json\1[^>]*>([\s\S]*?)<\/script>/gi)) {
    schemaCount++;
    try { inspectSchema(JSON.parse(match[2])); } catch { errors.push(`${rel}: invalid JSON-LD`); }
  }
  if (!schemaCount || !webPages) errors.push(`${rel}: missing JSON-LD WebPage`);
}

const routes = JSON.parse(fs.readFileSync(path.join(ROOT, "scripts/i18n/routes.json"), "utf8"));
const indexedRoutes = routes.filter((route) => !route.noindex);
if (indexedRoutes.length !== 26) errors.push(`routes.json: expected 26 indexable route groups, found ${indexedRoutes.length}`);
for (const route of routes) for (const language of LANGUAGES) {
  const page = pageByCanonical.get(`${BASE}${route[language]}`);
  if (!page) { errors.push(`routes.json: missing ${language} page ${route[language]}`); continue; }
  if (page.indexable === Boolean(route.noindex)) errors.push(`${page.rel}: indexing does not match routes.json`);
  const map = languageMap(page);
  for (const alternate of LANGUAGES) if (map[alternate] !== `${BASE}${route[alternate]}`) errors.push(`${page.rel}: ${alternate} hreflang does not match route counterpart`);
}
const expectedCount = indexedRoutes.length * LANGUAGES.length;
if (pages.length !== expectedCount) errors.push(`expected ${expectedCount} indexable pages, found ${pages.length}`);
for (const lang of LANGUAGES) {
  const count = pages.filter((page) => page.language === lang).length;
  if (count !== indexedRoutes.length) errors.push(`expected ${indexedRoutes.length} indexable ${lang} pages, found ${count}`);
}

const sitemap = fs.readFileSync(path.join(ROOT, "sitemap.xml"), "utf8");
const entries = [...sitemap.matchAll(/<url\b[^>]*>([\s\S]*?)<\/url>/g)];
const pageLocs = entries.map((entry) => decode(entry[1].match(/<loc>([^<]+)<\/loc>/)?.[1] ?? ""));
if (new Set(pageLocs).size !== pageLocs.length) errors.push("sitemap.xml: duplicate page URLs");
for (const [index, url] of pageLocs.entries()) {
  const page = pageByCanonical.get(url);
  if (!page?.indexable) errors.push(`sitemap.xml: not an indexable canonical page: ${url}`);
  const alternates = [...entries[index][1].matchAll(/<xhtml:link\b[^>]*>/gi)].map((m) => attrs(m[0]));
  if (alternates.length) {
    const expected = page ? languageMap(page) : {};
    if (alternates.length !== 4 || [...LANGUAGES, "x-default"].some((lang) => alternates.filter((a) => a.rel === "alternate" && a.hreflang === lang && a.href === expected[lang]).length !== 1)) errors.push(`sitemap.xml: incorrect hreflang group for ${url}`);
  }
}
if (JSON.stringify(pages.map((page) => page.canonical).sort()) !== JSON.stringify([...pageLocs].sort())) errors.push("sitemap.xml: URLs do not match indexable canonical pages");

const robots = fs.readFileSync(path.join(ROOT, "robots.txt"), "utf8");
if (!/^Sitemap:\s*https:\/\/www\.renoveerikodu\.ee\/sitemap\.xml\s*$/mi.test(robots)) errors.push("robots.txt: missing canonical sitemap URL");
if (/^Disallow:\s*\/(?:en|ru)(?:\/|\s*$)/mi.test(robots)) errors.push("robots.txt: localized public pages must be crawlable");

const htaccess = fs.readFileSync(path.join(ROOT, ".htaccess"), "utf8");
for (const required of [
  "RewriteRule ^(.+)\\.html$ https://www.renoveerikodu.ee/$1 [R=301,L,NE]",
  "RewriteCond %{HTTPS} !=on [OR]",
  "RewriteCond %{HTTP_HOST} !^www\\.renoveerikodu\\.ee$ [NC]",
  "RewriteRule ^ https://www.renoveerikodu.ee%{REQUEST_URI} [R=301,L,NE]"
]) if (!htaccess.includes(required)) errors.push(`.htaccess: missing rule: ${required}`);

// The owner requested this uploaded page be published byte-for-byte. Keep the
// exception bound to its exact bytes and exact known diagnostics; new issues fail.
const exception = JSON.parse(fs.readFileSync(path.join(ROOT, "scripts/seo-upload-exceptions.json"), "utf8"));
const uploadedHash = createHash("sha256").update(fs.readFileSync(path.join(ROOT, exception.file))).digest("hex");
const accepted = uploadedHash === exception.sha256 ? new Set(exception.diagnostics) : new Set();
const waived = [...new Set(errors)].filter((error) => accepted.has(error));
const blocking = errors.filter((error) => !accepted.has(error));
if (waived.length) console.warn(`Owner-supplied unchanged page: ${waived.length} known SEO warnings accepted for ${exception.file} (${uploadedHash}).\n${waived.join("\n")}`);
if (blocking.length) {
  console.error([...new Set(blocking)].join("\n"));
  process.exit(1);
}
console.log(`SEO check passed (${waived.length} explicit upload exceptions): ${pages.length} indexable pages (${indexedRoutes.length} per language), ${multilingualPages.length} multilingual pages, checked hreflang, selectors, assets/JSON-LD and ${pageLocs.length} sitemap URLs.`);
