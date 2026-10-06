import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();

const servicePages = {
  "plaatimine.html": "plaatimistöid",
  "maalritood.html": "maalritöid ja siseviimistlust",
  "ledlahendused.html": "LED-valgustuse ja kaudvalguse lahendusi",
  "seinapaneelidepaigaldus.html": "dekoratiivsete ja akustiliste seinapaneelide paigaldust",
  "parketipaigaldus.html": "parketi ja laminaatpõrandate paigaldust",
  "tapeetimine.html": "tapeetimist ja seinte ettevalmistust",
  "kipsitood.html": "kipsitöid, vaheseinu ja lagede lahendusi",
  "lammutustood.html": "siseruumide lammutustöid ja demontaaži",
  "tellingutepaigaldus.html": "tellingute paigaldust ehitus- ja fassaaditöödeks",
  "betoonitood.html": "betoonitöid ja aluspinna ettevalmistust",
  "vundamenditood.html": "vundamendi ehituse, paranduse ja hüdroisolatsiooniga seotud töid",
  "fassaaditood.html": "fassaadi renoveerimist, parandust, pesu ja viimistlust",
  "fassaadivarvimine.html": "puit- ja krohvfassaadide värvimist",
  "katusetood.html": "katuse hooldust, pesu, parandusi ja renoveerimistöid",
  "lumetood.html": "katuste, hoovide ja hoonete ümbruse lumetöid"
};

const projectPages = {
  "vannitoa-plaatimine-tallinn.html": "<strong>RK Meistrid OÜ</strong> teeb vannitoa plaatimist ja märgruumide viimistlust Tallinnas ning Harjumaal. See näide selgitab, kuidas planeerime aluspinna ettevalmistuse, hüdroisolatsiooni, kallete kontrolli, plaatimise, vuukimise ja silikoonimise.",
  "fassaadi-varvimine-tallinn-eramu.html": "<strong>RK Meistrid OÜ</strong> teeb fassaadi värvimise ja muid renoveerimistöid Tallinnas ning Harjumaal. Selle näite kaudu saab hinnata, kuidas planeerime puitfassaadi puhastuse, parandused, kruntimise, värvimise ja ligipääsu enne töö alustamist.",
  "katuse-pesu-ja-hooldus-tallinn.html": "<strong>RK Meistrid OÜ</strong> teeb katuse hoolduse, pesu ja seotud renoveerimistöid Tallinnas ning Harjumaal. See näide selgitab, kuidas hindame katuse seisukorda, puhastame katusepinda ja vaatame üle kohad, mis võivad hiljem vajada parandust.",
  "led-valgustuse-paigaldus-tallinn.html": "<strong>RK Meistrid OÜ</strong> paigaldab LED-valgustust ja kaudvalguse lahendusi Tallinnas ning Harjumaal. Selle näite kaudu saab vaadata, kuidas planeerime valguse eesmärgi, profiilid, kaabelduse, toiteploki asukoha ja lõppviimistluse."
};

function read(file) {
  return fs.readFileSync(path.join(ROOT, file), "utf8");
}

function write(file, html) {
  fs.writeFileSync(path.join(ROOT, file), html);
}

function preserveSeoSnapshot(html) {
  return {
    title: (html.match(/<title>[\s\S]*?<\/title>/i) || [""])[0],
    metaDescription: (html.match(/<meta\s+name=["']description["'][^>]*>/i) || [""])[0],
    canonical: (html.match(/<link\s+rel=["']canonical["'][^>]*>/i) || [""])[0],
    h1: (html.match(/<h1[^>]*>[\s\S]*?<\/h1>/i) || [""])[0]
  };
}

function assertSeoPreserved(file, before, after) {
  const current = preserveSeoSnapshot(after);
  for (const key of Object.keys(before)) {
    if (before[key] !== current[key]) {
      throw new Error(`${file}: ${key} changed during final GEO refinement`);
    }
  }
}

function insertOnce(html, marker, insertion, label) {
  if (html.includes(label)) return html;
  if (!html.includes(marker)) return html;
  return html.replace(marker, `${marker}\n${insertion}`);
}

let changed = 0;

for (const [file, servicePhrase] of Object.entries(servicePages)) {
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary"><strong>RK Meistrid OÜ</strong> pakub ${servicePhrase} Tallinnas ja Harjumaal. Päringu hindamisel lähtume objekti seisukorrast, tööjärjekorrast, materjalidest ja realistlikust ajakavast.</p>`;
  const next = html.includes("geo-entity-summary")
    ? html
    : html.replace(/(<section class="service-intro">[\s\S]*?<div class="center-image">[\s\S]*?<\/div>)/, `$1\n\t\t\t\t\t${insertion}`);
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

for (const [file, projectPhrase] of Object.entries(projectPages)) {
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary">${projectPhrase}</p>`;
  let next = html;
  if (!next.includes("geo-entity-summary")) {
    next = next.replace(/(<article id="main">\s*<header>[\s\S]*?<\/header>)/, `$1\n\t\t\t\t\t\t<section class="wrapper style5 geo-project-context"><div class="inner">${insertion}</div></section>`);
  }
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

{
  const file = "index.html";
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary"><strong>RK Meistrid OÜ</strong> teostab renoveerimis- ja ehitustöid Tallinnas ning Harjumaal. Teeme siseviimistlust, plaatimist, põrandatöid, kipsitöid, fassaaditöid ja muid renoveerimistöid.</p>`;
  const next = insertOnce(html, `<p class="business-lead">Kvaliteetsed ehitus- ja renoveerimislahendused, mis kestavad ajas.</p>`, insertion, "geo-entity-summary");
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

{
  const file = "hinnakiri.html";
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary"><strong>RK Meistrid OÜ</strong> hinnakirjas on orienteeruvad hinnad Tallinnas ja Harjumaal tehtavatele renoveerimis- ja ehitustöödele. Hinnad on abiks esmase eelarve hindamisel, kuid lõplik pakkumine sõltub objekti seisukorrast ja töömahust.</p>`;
  const next = insertOnce(html, `<h2>Hinnad alates</h2>`, insertion, "geo-entity-summary");
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

{
  const file = "tehtudtood.html";
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary"><strong>RK Meistrid OÜ</strong> tehtud tööde näited aitavad hinnata ettevõtte praktilist kogemust renoveerimis-, siseviimistlus-, fassaadi-, katuse- ja paigaldustöödel Tallinnas ning Harjumaal.</p>`;
  const next = insertOnce(html, `<h2>Valik tehtud töödest</h2>`, insertion, "geo-entity-summary");
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

{
  const file = "kontakt.html";
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const insertion = `<p class="geo-entity-summary">Saada meile oma töö kirjeldus ja fotod, et saaksime koostada hinnapakkumise. Teeme renoveerimis- ja ehitustöid Tallinnas ja Harjumaal.</p>`;
  const next = insertOnce(html, `<p>juhatus ja kontaktivorm</p>`, insertion, "geo-entity-summary");
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

{
  const file = "privaatsuspoliitika.html";
  let html = read(file);
  const beforeSeo = preserveSeoSnapshot(html);
  const next = html.replace("<html>", "<html lang=\"et\">");
  assertSeoPreserved(file, beforeSeo, next);
  if (next !== html) {
    write(file, next);
    changed += 1;
  }
}

console.log(`Applied final GEO refinements to ${changed} pages.`);
