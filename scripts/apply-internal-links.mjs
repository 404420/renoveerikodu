import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();

const additions = {
  "plaatimine.html": `
<section class="topical-links">
  <h2>Milliste töödega plaatimine tavaliselt seostub?</h2>
  <p>Vannitoa ja köögi plaatimine on sageli osa suuremast tööjärjekorrast. Enne plaati tuleb aluspind sirgeks ja niiskuskindlaks saada, mistõttu on paljudel objektidel vaja ka <a href="kipsitood.html">märgruumi seinte ja lagede ettevalmistust</a> või <a href="lammutustood.html">vana viimistluse eemaldamist</a>. Kui plaatimistöö on osa korteri tervikremondist, tasub hinnata ka <a href="maalritood.html">järgnevaid siseviimistluse töid</a> ning vaadata <a href="hinnakiri.html">plaatimistööde orienteeruvaid hindu</a>.</p>
</section>`,
  "maalritood.html": `
<section class="topical-links">
  <h2>Milliste töödega maalritööd tavaliselt seostuvad?</h2>
  <p>Maalritööd jõuavad tavaliselt lõppviimistluse etappi pärast seda, kui seinad, laed ja avad on ette valmistatud. Kui pind vajab ehituslikku parandust, algab töö tihti <a href="kipsitood.html">kipsplaadi ja karkassi korrigeerimisest</a>; dekoratiivsema tulemuse puhul võib maalritööle järgneda <a href="tapeetimine.html">tapeedi paigaldus või aktsentsein</a>. Eelarve võrdlemiseks aitab <a href="hinnakiri.html">siseviimistluse hinnakirja vaade</a>.</p>
</section>`,
  "ledlahendused.html": `
<section class="topical-links">
  <h2>Milliste töödega LED lahendused tavaliselt seostuvad?</h2>
  <p>LED valgustus annab parima tulemuse siis, kui see on planeeritud enne lõppviimistlust. Süvistatud valguslahendused vajavad sageli <a href="kipsitood.html">ripplae või karniisi ehitust</a>, seinte esiletõstmisel võib lahendus seostuda <a href="seinapaneelidepaigaldus.html">seinapaneelide paigaldusega</a> ning lõpptulemuse viimistleb <a href="maalritood.html">korrektne värvi- ja pahtlitöö</a>. Sarnast tehtud tööd näeb lehel <a href="led-valgustuse-paigaldus-tallinn.html">LED valgustuse paigaldus Tallinnas</a>.</p>
</section>`,
  "seinapaneelidepaigaldus.html": `
<section class="topical-links">
  <h2>Milliste töödega seinapaneelide paigaldus tavaliselt seostub?</h2>
  <p>Seinapaneelide paigaldus sõltub aluspinna sirgusest ja ruumi üldisest viimistlusest. Enne paneele võib vaja minna <a href="maalritood.html">seinte parandust ja toonitud taustapinda</a>, valgustatud lahenduste puhul tasub planeerida ka <a href="ledlahendused.html">LED valguse asukoht paneelide kõrval</a>. Kui sein on ebatasane või vajab konstruktsiooni, aitab <a href="kipsitood.html">kipsitöödega loodud sirge aluspind</a>.</p>
</section>`,
  "parketipaigaldus.html": `
<section class="topical-links">
  <h2>Milliste töödega parketi paigaldus tavaliselt seostub?</h2>
  <p>Parketi paigaldus tuleb ajastada pärast tolmuseid ja märgi töid. Enne uut põrandat võib olla vaja <a href="lammutustood.html">vana põrandakatte eemaldamist</a> või aluspinna korrigeerimist; pärast paigaldust lõpetavad ruumi sageli <a href="maalritood.html">seinte ja liistude viimistlustööd</a>. Hindade ja töömahu võrdlemiseks sobib <a href="hinnakiri.html">põrandatööde hinnakirja osa</a>, ning tehtud töö näitena aitab <a href="parketi-paigaldus-tallinn-korter.html">parketi paigaldus korteris Tallinnas</a>.</p>
</section>`,
  "tapeetimine.html": `
<section class="topical-links">
  <h2>Milliste töödega tapeetimine tavaliselt seostub?</h2>
  <p>Tapeetimine vajab siledat ja stabiilset aluspinda, seetõttu on enne paigaldust sageli oluline <a href="maalritood.html">pahteldus, lihvimine ja kruntimine</a>. Kui sein vajab ehituslikku sirgestamist, tuleb enne lahendada <a href="kipsitood.html">kipsplaadi või vaheseina töö</a>. Dekoratiivse terviklahenduse puhul võib tapeet toimida koos <a href="seinapaneelidepaigaldus.html">seinapaneelide või akustiliste paneelidega</a>.</p>
</section>`,
  "kipsitood.html": `
<section class="topical-links">
  <h2>Milliste töödega kipsitööd tavaliselt seostuvad?</h2>
  <p>Kipsitööd on paljudes remontides vaheetapp, mille peale ehitatakse lõppviimistlus. Märgruumides valmistab see ette <a href="plaatimine.html">plaatimisele sobiva aluspinna</a>, eluruumides liigub töö edasi <a href="maalritood.html">pahtelduse ja värvimise juurde</a>. Valgustusega lagede puhul seostub kipsitöö sageli ka <a href="ledlahendused.html">süvistatud LED lahenduste planeerimisega</a>.</p>
</section>`,
  "lammutustood.html": `
<section class="topical-links">
  <h2>Milliste töödega lammutustööd tavaliselt seostuvad?</h2>
  <p>Lammutustööd loovad remondile puhta lähtekoha, kuid neid ei tasu vaadata eraldi tööna. Pärast vana viimistluse eemaldamist liigub töö sageli <a href="kipsitood.html">uute seinte või lagede ehitusse</a>, vannitubades <a href="plaatimine.html">aluspinna ettevalmistuse ja plaatimiseni</a> ning põrandatel <a href="parketipaigaldus.html">uue parketi või laminaadi paigalduseni</a>. Terviktöö eelarvet aitab hinnata <a href="hinnakiri.html">renoveerimistööde hinnakiri</a>.</p>
</section>`,
  "tellingutepaigaldus.html": `
<section class="topical-links">
  <h2>Milliste töödega tellingute paigaldus tavaliselt seostub?</h2>
  <p>Tellingud on vajalikud siis, kui töö kvaliteet ja ohutus sõltuvad ligipääsust. Enamasti toetavad need <a href="fassaaditood.html">fassaadi parandust ja renoveerimist</a>, <a href="fassaadivarvimine.html">puit- või krohvfassaadi värvimist</a> ning kõrgematel objektidel ka <a href="katusetood.html">katuse hooldus- ja parandustöid</a>. Talvisel ajal võib sama ligipääsu planeerimine seostuda <a href="lumetood.html">katuse lumekoormuse eemaldamisega</a>.</p>
</section>`,
  "betoonitood.html": `
<section class="topical-links">
  <h2>Milliste töödega betoonitööd tavaliselt seostuvad?</h2>
  <p>Betoonitööd mõjutavad järgmiste kihtide vastupidavust, mistõttu tasub need siduda kogu tööjärjekorraga. Vundamendi ja sokli juures on seotud tööks sageli <a href="vundamenditood.html">vundamendi parandamine või hüdroisolatsioon</a>, siseruumides võib betoonpinna ettevalmistus liikuda edasi <a href="plaatimine.html">plaaditava põranda või seina lahenduseni</a>. Kui vana konstruktsioon vajab eemaldamist, tuleb enne hinnata <a href="lammutustood.html">lammutuse mahtu ja äravedu</a>.</p>
</section>`,
  "vundamenditood.html": `
<section class="topical-links">
  <h2>Milliste töödega vundamenditööd tavaliselt seostuvad?</h2>
  <p>Vundamenditööd seostuvad tihti hoone alumise osa niiskuskaitse, betooni ja fassaadi üleminekuga. Kui töö hõlmab konstruktsiooni parandust, on oluline siduda see <a href="betoonitood.html">betoonitööde ja aluspinna taastamisega</a>; hoone välisilme ja sokli kaitse puhul jätkub töö sageli <a href="fassaaditood.html">fassaadipinna paranduse või viimistlusega</a>. Enne uut lahendust võib vaja minna ka <a href="lammutustood.html">vana kahjustunud osa eemaldamist</a>.</p>
</section>`,
  "fassaaditood.html": `
<section class="topical-links">
  <h2>Milliste töödega fassaaditööd tavaliselt seostuvad?</h2>
  <p>Fassaaditööd koondavad mitu etappi: pesu, parandused, kruntimine, värvimine ja vajadusel detailide vahetus. Kui põhieesmärk on uus kaitsev värvikiht, on eraldi ülevaade lehel <a href="fassaadivarvimine.html">fassaadi värvimise tööjärjekord</a>. Kõrgematel majadel tuleb planeerida <a href="tellingutepaigaldus.html">ohutu ligipääs tellingutega</a>, sokli ja alumiste sõlmede puhul võivad seotud olla ka <a href="vundamenditood.html">vundamendi või sokli parandused</a>.</p>
</section>`,
  "fassaadivarvimine.html": `
<section class="topical-links">
  <h2>Milliste töödega fassaadi värvimine tavaliselt seostub?</h2>
  <p>Fassaadi värvimine on tavaliselt üks osa laiemast välitööde paketist. Kui pind vajab enne värvi parandamist, aitab <a href="fassaaditood.html">fassaaditööde terviklik käsitlus</a>; kõrgematel seintel tuleb läbi mõelda <a href="tellingutepaigaldus.html">ligipääs ja tööohutus</a>. Katuse servade, vihmaveesüsteemi või räästa juures võib värvimist olla mõistlik koordineerida ka <a href="katusetood.html">katuse hooldus- või parandustöödega</a>.</p>
</section>`,
  "katusetood.html": `
<section class="topical-links">
  <h2>Milliste töödega katusetööd tavaliselt seostuvad?</h2>
  <p>Katusetööd mõjutavad otseselt fassaadi, räästast ja hoone niiskuskaitset. Kui töö toimub kõrgemal või järsema kaldega pinnal, tuleb sageli planeerida <a href="tellingutepaigaldus.html">turvaline ligipääs ja tööplatvorm</a>. Katuse servade ja fassaadi kokkupuutekohas võib töö seostuda <a href="fassaaditood.html">välisseina paranduste või hooldusega</a>, talvisel ajal aga <a href="lumetood.html">katuse lume ja jää eemaldamisega</a>.</p>
</section>`,
  "lumetood.html": `
<section class="topical-links">
  <h2>Milliste töödega lumetööd tavaliselt seostuvad?</h2>
  <p>Lumetööd on hooajaline hooldus, mis aitab kaitsta katust, fassaadi ja hoone ümbrust. Kui lumi või jää koguneb katusele, on seotud tööks sageli <a href="katusetood.html">katuse seisukorra kontroll ja hooldus</a>; kõrgematel objektidel tuleb hinnata <a href="tellingutepaigaldus.html">ohutu ligipääsu vajadust</a>. Sulamisvee või jääkahjustuste korral võib hiljem vajalik olla ka <a href="fassaaditood.html">fassaadi ja sokli seisukorra ülevaatus</a>.</p>
</section>`
};

for (const [file, section] of Object.entries(additions)) {
  const filePath = path.join(ROOT, file);
  let html = fs.readFileSync(filePath, "utf8");
  if (html.includes('class="topical-links"')) continue;
  const marker = '<section class="service-related">';
  if (!html.includes(marker)) throw new Error(`${file} missing service-related section`);
  html = html.replace(marker, `${section}\n\n${marker}`);
  fs.writeFileSync(filePath, html, "utf8");
}

console.log(`Ensured contextual internal-link sections on ${Object.keys(additions).length} service pages.`);
