import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();
const SERVICE_FILES = [
  "plaatimine.html",
  "maalritood.html",
  "ledlahendused.html",
  "seinapaneelidepaigaldus.html",
  "parketipaigaldus.html",
  "tapeetimine.html",
  "kipsitood.html",
  "lammutustood.html",
  "tellingutepaigaldus.html",
  "betoonitood.html",
  "vundamenditood.html",
  "fassaaditood.html",
  "fassaadivarvimine.html",
  "katusetood.html",
  "lumetood.html"
];

const trustStrip = `<aside class="service-trust-strip" aria-label="Teenuse eelised">
  <strong>3-aastane garantii tehtud ehitus- ja viimistlustöödele</strong>
  <span>Selge hinnapakkumine</span>
  <span>Tallinn ja Harjumaa</span>
  <a href="kontakt.html#vorm">Saada töö kirjeldus ja fotod</a>
</aside>`;

const guaranteeFaq = `<details>
  <summary>Kas tehtud töödele kehtib garantii?</summary>
  <p>Jah. RK Meistrid OÜ tehtud ehitus- ja viimistlustöödele kehtib 3-aastane garantii. Konkreetse töö sisu ja kokkulepitud tingimused fikseeritakse hinnapakkumises.</p>
</details>`;

for (const file of SERVICE_FILES) {
  const filePath = path.join(ROOT, file);
  let html = fs.readFileSync(filePath, "utf8");
  // Temporary and preparatory services must not inherit the construction warranty.
  const safetyContent = {
    "lumetood.html": ["Kogenud meeskond ja kokkulepitud töökorraldus", "Kuidas korraldate lumetööde ohutuse?", "Enne lumekoristust hindame ligipääsu ja ohuala, lepime kokku töömahu ning korraldame töö nii, et kaitsta inimesi ja vara."],
    "tellingutepaigaldus.html": ["Korrektne ja turvaline teostus", "Kuidas tagate tellingute turvalise paigalduse?", "Enne paigaldust hindame aluspinda, ligipääsu ja töö kõrgust. Tellingute lahendus ja kasutustingimused lepitakse kokku vastavalt objektile."],
    "lammutustood.html": ["Korrektne ja turvaline teostus", "Kuidas tagate lammutustööde ohutuse?", "Enne lammutamist täpsustame eemaldatavad osad, kaitseme säilivad pinnad ning lepime kokku jäätmete käitlemise ja töökorralduse. Kandvate konstruktsioonide muutmine vajab projekti ja kooskõlastusi."]
  }[file];
  const serviceTrust = safetyContent
    ? trustStrip.replace("3-aastane garantii tehtud ehitus- ja viimistlustöödele", safetyContent[0])
    : trustStrip;
  const faqQuestion = safetyContent?.[1] ?? "Kas tehtud töödele kehtib garantii?";
  const serviceFaq = safetyContent
    ? `<details><summary>${faqQuestion}</summary><p>${safetyContent[2]}</p></details>`
    : guaranteeFaq;


  if (!html.includes('class="service-trust-strip"')) {
    const articleStart = html.search(/<article\b/i);
    const headerEnd = html.indexOf("</header>", articleStart);
    const h1End = html.search(/<\/h1>/i);
    const insertAt = articleStart >= 0 && headerEnd >= 0
      ? headerEnd + "</header>".length
      : h1End >= 0
        ? h1End + "</h1>".length
        : -1;
    if (insertAt < 0) throw new Error(`${file}: service heading not found`);
    html = `${html.slice(0, insertAt)}\n${serviceTrust}${html.slice(insertAt)}`;
  }

  if (!html.includes(faqQuestion)) {
    const faqMatch = html.match(/<section class="service-faq">[\s\S]*?<\/section>/i);
    if (faqMatch) {
      const enhancedFaq = faqMatch[0].replace(/<\/section>\s*$/i, `${serviceFaq}\n</section>`);
      html = html.replace(faqMatch[0], enhancedFaq);
    } else if (file === "ledlahendused.html") {
      const ledFaq = html.match(/<section class="led-section led-faq"[\s\S]*?<\/section>/i);
      if (!ledFaq) throw new Error(`${file}: LED FAQ section not found`);
      const enhancedLedFaq = ledFaq[0].replace(/<\/div><\/section>\s*$/i, `${serviceFaq}</div></section>`);
      html = html.replace(ledFaq[0], enhancedLedFaq);
    } else {
      throw new Error(`${file}: service FAQ not found`);
    }
  }

  fs.writeFileSync(filePath, html, "utf8");
}

const homePath = path.join(ROOT, "index.html");
let home = fs.readFileSync(homePath, "utf8");
if (!home.includes('class="service-trust-strip home-trust-strip"')) {
  const homeTrust = trustStrip
    .replace('class="service-trust-strip"', 'class="service-trust-strip home-trust-strip"')
    .replace('href="kontakt.html#vorm"', 'href="#contact"');
  const marker = '<p class="geo-entity-summary">';
  const markerAt = home.indexOf(marker);
  if (markerAt < 0) throw new Error("index.html: GEO entity summary not found");
  home = `${home.slice(0, markerAt)}${homeTrust}\n${home.slice(markerAt)}`;
  fs.writeFileSync(homePath, home, "utf8");
}

const changedSlugs = new Set([
  "",
  ...SERVICE_FILES.map((file) => file.replace(/\.html$/, "")),
  "vannitoa-plaatimine-tallinn",
  "parketi-paigaldus-tallinn-korter",
  "fassaadi-varvimine-tallinn-eramu",
  "katuse-pesu-ja-hooldus-tallinn",
  "led-valgustuse-paigaldus-tallinn"
]);
const sitemapPath = path.join(ROOT, "sitemap.xml");
let sitemap = fs.readFileSync(sitemapPath, "utf8");
sitemap = sitemap.replace(/<url>([\s\S]*?)<\/url>/g, (block) => {
  const loc = block.match(/<loc>https:\/\/www\.renoveerikodu\.ee\/([^<]*)<\/loc>/)?.[1] ?? null;
  if (loc === null || !changedSlugs.has(loc)) return block;
  return block.replace(/<lastmod>[^<]+<\/lastmod>/, "<lastmod>2026-10-05</lastmod>");
});
fs.writeFileSync(sitemapPath, sitemap, "utf8");

console.log(`Added trust and warranty content to ${SERVICE_FILES.length} service pages and the homepage.`);
