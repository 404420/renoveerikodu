import fs from "node:fs";
import path from "node:path";

const SITE = path.resolve(process.argv[2] ?? "_site");
const PREFIX = `/${(process.argv[3] ?? "/renoveerikodu/").replace(/^\/+|\/+$/g, "")}/`;
const errors = [];
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}
const files = walk(SITE);
function checkUrl(value, source) {
  if (/^(?:#|mailto:|tel:|data:|javascript:|https?:|\/\/)/i.test(value)) return;
  const url = new URL(value.replaceAll("&amp;", "&"), `https://preview.example${PREFIX}${source}`);
  if (/^\/(?:api|admin)(?:\/|$)/.test(url.pathname) || /^\/paneel(?:\.php)?\/?$/.test(url.pathname)) return;
  if (!url.pathname.startsWith(PREFIX)) { errors.push(`${source}: missing preview prefix: ${value}`); return; }
  const relative = decodeURIComponent(url.pathname.slice(PREFIX.length));
  const target = path.join(SITE, relative);
  if (/^(?:api|admin)\//.test(relative) || /^paneel(?:\.php)?\/?$/.test(relative)) return;
  if (!fs.existsSync(target) || (fs.statSync(target).isDirectory() && !fs.existsSync(path.join(target, "index.html")))) errors.push(`${source}: missing preview target: ${value}`);
}
let count = 0;
for (const file of files) {
  const relative = path.relative(SITE, file).replaceAll("\\", "/");
  if (/\.(?:php|sql|zip)$/.test(relative)) errors.push(`server-only file in preview: ${relative}`);
  if (!file.endsWith(".html")) continue;
  count++;
  const html = fs.readFileSync(file, "utf8").replace(/<!--[\s\S]*?-->/g, "");
  if (!/<meta\b[^>]*name=["']robots["'][^>]*content=["']noindex, nofollow["']/i.test(html)) errors.push(`${relative}: preview is indexable`);
  for (const match of html.matchAll(/<(a|img|script|link|source)\b[^>]*>/gi)) {
    if (match[1].toLowerCase() === "link" && /\brel\s*=\s*(["'])(?:canonical|alternate)\1/i.test(match[0])) continue;
    for (const attribute of match[0].matchAll(/\b(href|src|srcset)\s*=\s*(["'])(.*?)\2/gi)) {
      const values = attribute[1].toLowerCase() === "srcset" && !attribute[3].startsWith("data:") ? attribute[3].split(",").map((item) => item.trim().split(/\s+/)[0]) : [attribute[3]];
      for (const value of values) checkUrl(value, relative);
    }
  }
}
if (fs.readFileSync(path.join(SITE, "robots.txt"), "utf8").trim() !== "User-agent: *\nDisallow: /") errors.push("preview robots.txt must block all crawlers");
if (errors.length) { console.error([...new Set(errors)].join("\n")); process.exit(1); }
console.log(`Pages preview check passed: ${count} noindex pages, valid prefixed navigation and assets, no server-only files.`);
