import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();
const SITE = path.resolve(ROOT, process.argv[2] ?? "_site");
const PREFIX = `/${(process.argv[3] ?? "/renoveerikodu/").replace(/^\/+|\/+$/g, "")}/`;
const BASE = "https://www.renoveerikodu.ee";
if (SITE === ROOT || !fs.existsSync(SITE)) throw new Error("Pass an existing staged directory, never the source repository.");

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}
const files = walk(SITE);
const publicFiles = new Set(files.map((file) => path.relative(SITE, file).replaceAll("\\", "/")));
const publicDirectories = new Set([...publicFiles].flatMap((file) => {
  const parts = file.split("/");
  return parts.slice(0, -1).map((_, index) => `${parts.slice(0, index + 1).join("/")}/`);
}));
const pages = new Map();
for (const file of publicFiles) if (file.endsWith(".html")) {
  const clean = file === "index.html" ? "/" : file.endsWith("/index.html") ? `/${file.slice(0, -10)}` : `/${file.slice(0, -5)}`;
  const preview = clean.endsWith("/") ? clean.slice(1) : file;
  pages.set(clean, preview);
  pages.set(`/${file}`, preview);
}

function rewriteUrl(value, source) {
  if (!value || /^(?:#|mailto:|tel:|data:|javascript:)/i.test(value) || value.startsWith(PREFIX)) return value;
  let url;
  try { url = new URL(value.replaceAll("&amp;", "&"), `${BASE}/${source}`); } catch { return value; }
  if (!["www.renoveerikodu.ee", "renoveerikodu.ee"].includes(url.hostname)) return value;
  const clean = decodeURIComponent(url.pathname);
  const relative = pages.get(clean) ?? (publicFiles.has(clean.slice(1)) || publicDirectories.has(clean.slice(1)) ? clean.slice(1) : undefined);
  return relative === undefined ? value : `${PREFIX}${relative}${url.search.replaceAll("&", "&amp;")}${url.hash}`;
}

function rewriteJs(text, source) {
  // Shared route tables and dynamic menu links also need Pages' repository prefix.
  return text.replace(/(["'])(\/(?!\/)[^"'\n]*|https?:\/\/(?:www\.)?renoveerikodu\.ee[^"'\n]*)\1/g,
    (match, quote, value) => `${quote}${rewriteUrl(value, source)}${quote}`);
}

for (const file of files) {
  const relative = path.relative(SITE, file).replaceAll("\\", "/");
  if (file.endsWith(".html")) {
    let html = fs.readFileSync(file, "utf8");
    let hasRobots = false;
    html = html.replace(/<meta\b[^>]*>/gi, (tag) => {
      if (!/\bname\s*=\s*(["'])robots\1/i.test(tag)) return tag;
      hasRobots = true;
      return '<meta name="robots" content="noindex, nofollow">';
    });
    if (!hasRobots) html = html.replace(/<\/head>/i, '<meta name="robots" content="noindex, nofollow">\n</head>');
    html = html.replace(/<(a|img|script|link|source)\b[^>]*>/gi, (tag, name) => {
      if (name.toLowerCase() === "link" && /\brel\s*=\s*(["'])(?:canonical|alternate)\1/i.test(tag)) return tag;
      return tag.replace(/\b(href|src|srcset)\s*=\s*(["'])(.*?)\2/gi, (attribute, key, quote, value) => {
        const rewritten = key.toLowerCase() === "srcset" && !value.startsWith("data:")
          ? value.split(",").map((item) => item.trim().replace(/^(\S+)/, (url) => rewriteUrl(url, relative))).join(", ")
          : rewriteUrl(value, relative);
        return `${key}=${quote}${rewritten}${quote}`;
      });
    });
    html = html.replace(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi, (match, attributes, body) =>
      /application\/ld\+json/i.test(attributes) ? match : `<script${attributes}>${rewriteJs(body, relative)}</script>`);
    fs.writeFileSync(file, html);
  } else if (file.endsWith(".css")) {
    const css = fs.readFileSync(file, "utf8").replace(/url\(\s*(["']?)(\/[^)'"\s]+)\1\s*\)/gi,
      (match, quote, url) => `url(${quote}${rewriteUrl(url, relative)}${quote})`);
    fs.writeFileSync(file, css);
  } else if (file.endsWith(".js")) {
    fs.writeFileSync(file, rewriteJs(fs.readFileSync(file, "utf8"), relative));
  } else if (file.endsWith(".webmanifest")) {
    const manifest = JSON.parse(fs.readFileSync(file, "utf8"));
    for (const icon of manifest.icons ?? []) icon.src = rewriteUrl(icon.src, relative);
    fs.writeFileSync(file, JSON.stringify(manifest));
  }
}
fs.writeFileSync(path.join(SITE, "robots.txt"), "User-agent: *\nDisallow: /\n");
console.log(`Prepared ${pages.size / 2} HTML pages for the unindexed ${PREFIX} Pages preview.`);
