import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();
const BASE = "https://www.renoveerikodu.ee";
const ignored = new Set(["admin", "node_modules", ".git", "work", "outputs"]);
const errors = [];

function walk(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory() && ignored.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walk(full));
    else if (entry.isFile() && entry.name.endsWith(".html")) out.push(full);
  }
  return out;
}

function attrs(tag) {
  const result = {};
  for (const match of tag.matchAll(/([:\w-]+)\s*=\s*(["'])(.*?)\2/g)) result[match[1].toLowerCase()] = match[3];
  return result;
}

const htmlFiles = walk(ROOT);
const pageByPath = new Map();
const pages = [];

for (const file of htmlFiles) {
  const rel = path.relative(ROOT, file).replaceAll("\\", "/");
  const html = fs.readFileSync(file, "utf8");
  const metas = [...html.matchAll(/<meta\b[^>]*>/gi)].map((m) => attrs(m[0]));
  const robots = metas.find((a) => a.name?.toLowerCase() === "robots")?.content?.toLowerCase() ?? "";
  const canonicals = [...html.matchAll(/<link\b[^>]*>/gi)].map((m) => attrs(m[0]))
    .filter((a) => (a.rel ?? "").toLowerCase() === "canonical").map((a) => a.href);
  const indexable = robots.includes("index") && !robots.includes("noindex");
  const expected = rel === "index.html" ? `${BASE}/`
    : rel.endsWith("/index.html") ? `${BASE}/${rel.slice(0, -"index.html".length)}`
    : `${BASE}/${rel.replace(/\.html$/i, "")}`;

  if (indexable) {
    const title = html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1]?.trim();
    const description = metas.find((a) => a.name?.toLowerCase() === "description")?.content;
    const h1Count = (html.match(/<h1\b/gi) ?? []).length;
    if (!title) errors.push(`${rel}: missing title`);
    if (!description) errors.push(`${rel}: missing meta description`);
    if (h1Count !== 1) errors.push(`${rel}: expected one H1, found ${h1Count}`);
    if (canonicals.length !== 1) errors.push(`${rel}: expected one canonical, found ${canonicals.length}`);
    else if (canonicals[0] !== expected) errors.push(`${rel}: canonical must be ${expected}`);
    pages.push({ rel, html, canonical: expected });
  }
  if (canonicals.length === 1) pageByPath.set(rel, canonicals[0]);
}

function repoPagePath(pathname) {
  const clean = decodeURIComponent(pathname).replace(/^\/+/, "");
  const candidates = pathname.endsWith("/") ? [`${clean}index.html`]
    : /\.html$/i.test(clean) ? [clean] : [`${clean}.html`, `${clean}/index.html`];
  return candidates.find((candidate) => pageByPath.has(candidate));
}

for (const { rel, html } of pages) {
  const source = new URL(rel, `${BASE}/`);
  for (const match of html.matchAll(/<a\b[^>]*\bhref\s*=\s*(["'])(.*?)\1/gi)) {
    const href = match[2];
    if (/^(?:#|mailto:|tel:|javascript:|data:)/i.test(href)) continue;
    let url;
    try { url = new URL(href, source); } catch { continue; }
    if (!["renoveerikodu.ee", "www.renoveerikodu.ee"].includes(url.hostname)) continue;
    const target = repoPagePath(url.pathname);
    if (!target) continue;
    const expected = `${pageByPath.get(target)}${url.search}${url.hash}`;
    if (href !== expected) errors.push(`${rel}: internal page link must be direct: ${href} -> ${expected}`);
  }
}

const sitemap = fs.readFileSync(path.join(ROOT, "sitemap.xml"), "utf8");
const locs = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
const pageLocs = locs.filter((url) => !url.includes("/images/"));
if (new Set(pageLocs).size !== pageLocs.length) errors.push("sitemap.xml: duplicate page URLs");
for (const url of pageLocs) {
  if (!url.startsWith(`${BASE}/`)) errors.push(`sitemap.xml: non-canonical host or scheme: ${url}`);
  if (/\.html(?:$|[?#])/.test(url)) errors.push(`sitemap.xml: redirected .html URL: ${url}`);
}
const expectedLocs = pages.map((page) => page.canonical).sort();
const actualLocs = [...pageLocs].sort();
if (JSON.stringify(expectedLocs) !== JSON.stringify(actualLocs)) errors.push("sitemap.xml: URLs do not match indexable canonical pages");

const robots = fs.readFileSync(path.join(ROOT, "robots.txt"), "utf8");
if (!/^Sitemap:\s*https:\/\/www\.renoveerikodu\.ee\/sitemap\.xml\s*$/mi.test(robots)) errors.push("robots.txt: missing canonical sitemap URL");

const htaccess = fs.readFileSync(path.join(ROOT, ".htaccess"), "utf8");
for (const required of [
  "RewriteRule ^(.+)\\.html$ https://www.renoveerikodu.ee/$1 [R=301,L,NE]",
  "RewriteCond %{HTTPS} !=on [OR]",
  "RewriteCond %{HTTP_HOST} !^www\\.renoveerikodu\\.ee$ [NC]",
  "RewriteRule ^ https://www.renoveerikodu.ee%{REQUEST_URI} [R=301,L,NE]"
]) if (!htaccess.includes(required)) errors.push(`.htaccess: missing rule: ${required}`);

if (errors.length) {
  console.error(errors.join("\n"));
  process.exit(1);
}
console.log(`SEO check passed: ${pages.length} indexable pages, ${pageLocs.length} sitemap URLs.`);
