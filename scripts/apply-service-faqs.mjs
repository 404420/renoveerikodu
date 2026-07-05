import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();

const additions = {
  "plaatimine.html": [
    ["Kas vana plaadi peale võib uue plaadi panna?", "Mõnikord on see tehniliselt võimalik, kuid ainult siis, kui vana plaat on tugevalt kinni, pind on sirge ja niiskuskaitse lahendus on kontrollitud. Märgruumis eelistame enamasti vana pinna avamist, sest peidetud kahjustused tuleb enne uut viimistlust välistada."],
    ["Kas plaatimistöö sisaldab vuukimist ja silikoonimist?", "Tavaliselt jah. Pakkumises täpsustame, kas hind sisaldab plaatimist, vuukimist, silikoonühendusi, hüdroisolatsiooni ja aluspinna ettevalmistust või ainult ühte tööetappi."],
    ["Millal saab plaaditud pinda kasutama hakata?", "See sõltub segust, vuugist, ruumi niiskusest ja tootja kuivamisajast. Põrandale astumise ja duši kasutamise aeg ei ole alati sama, seetõttu anname üleandmisel konkreetse juhise."],
    ["Kas klient peab plaadid ise ostma?", "Klient võib plaadid ise valida ja osta, kuid saame aidata koguse, varu ja tehnilise sobivuse hindamisel. Suureformaadilise või reljeefse plaadi puhul tasub sobivus enne ostu üle vaadata."]
  ],
  "maalritood.html": [
    ["Kas mööbel peab enne maalritöid ruumist väljas olema?", "Ideaalis võiks ruum olla võimalikult tühi. Kui mööblit ei saa eemaldada, tuleb see koondada, katta ja jätta tööks piisav ligipääs seintele ning lagedele."],
    ["Kas värvitooni saab valida töö käigus?", "Parem on toon enne töö algust kinnitada, sest värvi tellimine, prooviala ja kuivamisjärgne toon võivad ajagraafikut mõjutada. Vajadusel teeme proovipinna enne kogu seina värvimist."],
    ["Miks on kruntimine vajalik?", "Krunt ühtlustab pinna imavust ja parandab värvi naket. Ilma krundita võib lõpptulemus jääda laiguline või vajada rohkem värvikihte."],
    ["Kas parandate ka seinapraod?", "Jah, kuid oluline on aru saada, kas pragu on ainult viimistluskihis või liigub konstruktsioon. Liikuv pragu võib vajada teistsugust lahendust kui tavaline pahtliparandus."]
  ],
  "ledlahendused.html": [
    ["Kas LED valgustust saab lisada juba valmis remonditud ruumi?", "Saab, kuid lahendus sõltub kaablite ligipääsust ja sellest, kuhu saab peita toiteploki. Kõige puhtam tulemus sünnib siis, kui valgustus on planeeritud enne kipsi- ja maalritööde lõppu."],
    ["Kas LED-riba sobib põhivalgustuseks?", "Mõnikord sobib, kuid sageli toimib LED-riba paremini meeleolu- või kaudvalgusena. Põhivalgustuse jaoks tuleb arvestada ruumi suurust, valgusvoogu ja hajuti kvaliteeti."],
    ["Kuhu paigutatakse LED toiteplokk?", "Toiteplokk peab jääma ligipääsetavasse ja õhutatavasse kohta. Seda ei tohiks täielikult kinni ehitada nii, et hooldus või vahetus muutub hiljem võimatuks."],
    ["Kas LED valgustust saab ühendada dimmeriga?", "Jah, kui LED-riba, toiteplokk ja juhtimine on omavahel sobivad. Dimmer tuleb valida süsteemi järgi, mitte ainult lüliti välimuse järgi."]
  ],
  "seinapaneelidepaigaldus.html": [
    ["Kas sein peab enne paneelide paigaldust täiesti sirge olema?", "Mida suurem ja jäigem paneel, seda olulisem on sirge aluspind. Väiksem ebatasasus võib jääda varju, kuid kõver sein võib tekitada nähtavaid vahesid ja pinget paneelis."],
    ["Kas paneele saab paigaldada värvitud seinale?", "Saab, kui värvikiht on tugev ja pind puhas. Lahtine värv, tolm või niiskuskahjustus tuleb enne paigaldust kõrvaldada."],
    ["Kuidas lahendatakse pistikupesad paneelide sees?", "Pistikupesade asukohad mõõdetakse enne lõikamist. Vajadusel tuleb kaasata elektrik, eriti kui pesa asend või sügavus muutub."],
    ["Kas akustilised paneelid parandavad päriselt heli?", "Need aitavad vähendada kaja ja muuta ruumi kõla pehmemaks, kuid ei asenda täielikku heliisolatsiooni. Tulemus sõltub ruumi suurusest, pindadest ja paneelide kogusest."]
  ],
  "parketipaigaldus.html": [
    ["Kas vana põrand tuleb enne parketti eemaldada?", "See sõltub vana katte tüübist, kõrgusest ja stabiilsusest. Kui vana põrand liigub, kriuksub või on niiske, tuleb probleem enne uut katet lahendada."],
    ["Kas parketti saab paigaldada ebatasasele põrandale?", "Ei ole soovitatav. Ebatasane aluspind võib põhjustada lukusüsteemi purunemist, õõnsat heli ja põranda liikumist. Vajadusel tuleb aluspind tasandada."],
    ["Kui suur materjalivaru tuleks arvestada?", "Tavaliselt arvestatakse lõigete ja praagi jaoks varu. Lihtsa ruumi puhul võib varu olla väiksem, keerulise mustri või paljude nurkadega ruumis suurem."],
    ["Kas liistud paigaldatakse kohe pärast parketti?", "Tavaliselt jah, kui seinad on valmis ja värv kuivanud. Kui seinaviimistlus jätkub, võib liistud paigaldada hiljem, et neid mitte kahjustada."]
  ],
  "tapeetimine.html": [
    ["Kas vana tapeet tuleb alati eemaldada?", "Enamasti jah, eriti kui vana tapeet on lahti, määrdunud või mitmes kihis. Uus tapeet vajab tugevat ja ühtlase imavusega aluspinda."],
    ["Kas tapeeti saab panna värvitud seinale?", "Saab, kui värv on tugevalt kinni ja pind on sobivalt ette valmistatud. Läikiv või väga sile pind võib vajada matistamist ja kruntimist."],
    ["Miks tapeedi muster võib nihkuda?", "Põhjuseks võib olla vale algusjoon, ebaühtlane sein, mustri korduse arvestamata jätmine või tapeedi venimine paigaldusel."],
    ["Kas paigaldate ka fototapeeti?", "Jah, kuid fototapeet nõuab eriti täpset aluspinda ja paanide järjestust. Väike viga alguses võib kogu pildi nihkesse viia."]
  ],
  "kipsitood.html": [
    ["Kas kipsseina sisse saab hiljem raskeid kappe kinnitada?", "Saab, kui tugevduse kohad on enne seina sulgemist planeeritud. Ilma tugevduseta tuleb kasutada sobivaid kinnituslahendusi, kuid kõik koormused ei ole siis mõistlikud."],
    ["Kas kipsitööd sisaldavad ka pahteldust?", "See sõltub pakkumisest. Mõnikord tellitakse ainult karkass ja plaat, teinekord ka vuukimine, pahteldus ja värvivalmis pind."],
    ["Millal on vaja niiskuskindlat kipsi?", "Niiskuskindlat plaati kasutatakse ruumides, kus niiskuskoormus on suurem, näiteks vannitoas või tehnoruumis. Märgruumis ei asenda see siiski hüdroisolatsiooni."],
    ["Kas ripplagi vähendab ruumi kõrgust palju?", "Kõrguse kadu sõltub karkassist, valgustitest ja kommunikatsioonidest. Enne töö algust saab määrata minimaalse mõistliku langetuse."]
  ],
  "lammutustood.html": [
    ["Kas lammutustööde ajal saab korteris sees elada?", "Väiksema töö puhul võib see olla võimalik, kuid tolm, müra ja ligipääs muudavad selle ebamugavaks. Märgade ruumide või suurema lammutuse ajal on parem ruum vabastada."],
    ["Kas prahi äravedu kuulub töö sisse?", "See tuleb pakkumises eraldi kokku leppida. Prahi maht, korrus, lift ja parkimine mõjutavad äraveo hinda märgatavalt."],
    ["Kuidas piirate tolmu levikut?", "Kasutame katmist, vajadusel tsoonide eraldamist ja tööjärjekorda, mis vähendab tolmu liikumist teistesse ruumidesse. Täielikult tolmuvaba lammutus ei ole realistlik."],
    ["Kas kandvat seina võib lammutada?", "Kandva seina muutmine vajab projekti ja kooskõlastusi. Ilma konstruktsioonilise hinnanguta kandvat seina ei lammutata."]
  ],
  "tellingutepaigaldus.html": [
    ["Kui palju ruumi on tellingu jaoks vaja?", "See sõltub tellingu tüübist, kõrgusest ja töömahust. Vaja on stabiilset alust ning piisavat liikumisruumi paigalduseks ja tööks."],
    ["Kas tellinguid saab paigaldada ebatasasele pinnale?", "Saab, kui alus lahendatakse ohutult ja tootja nõuete järgi. Ebastabiilne pinnas või kitsas ligipääs tuleb enne üle vaadata."],
    ["Kas tellingu paigaldus sisaldab ka renti?", "See sõltub kokkuleppest. Pakkumises täpsustatakse paigaldus, demontaaž, transpordi ja rendiperioodi tingimused."],
    ["Millal on telling parem kui tõstuk?", "Telling on parem pikema töö ja suurema fassaadipinna puhul, kus tööala peab olema stabiilselt ligipääsetav. Tõstuk sobib sageli lühemaks või lokaalseks tööks."]
  ],
  "betoonitood.html": [
    ["Kas väikest betooniparandust saab teha ilma suurema ettevalmistuseta?", "Mõnikord saab, kuid vana pinna nakkuvus, niiskus ja pragude põhjus tuleb enne üle vaadata. Kehv ettevalmistus lühendab paranduse eluiga."],
    ["Millal võib betoonpinnale järgmise töö teha?", "See sõltub betooni paksusest, niiskusest, temperatuurist ja järgmisest viimistlusest. Plaatimine, hüdroisolatsioon või põrandakate vajavad erinevat kuivustaset."],
    ["Kas betoonitöödel on vaja armeerimist?", "Paljudel juhtudel jah, eriti koormust kandvate või pragunemisohuga pindade puhul. Armeerimise vajadus sõltub konstruktsioonist ja kasutusest."],
    ["Kas betoonpõrand peab olema kaldega?", "Märgades või välitingimustes jah, kui vesi peab liikuma äravoolu suunas. Kuivas siseruumis sõltub kalle ruumi kasutusest ja viimistlusest."]
  ],
  "vundamenditood.html": [
    ["Kuidas aru saada, kas vundament vajab parandust?", "Märgiks võivad olla praod, niiskus, soolajäljed, sokli lagunemine või põranda ja seina liite niiskus. Täpne põhjus selgub ülevaatusel."],
    ["Kas ainult sokli värvimisest piisab?", "Kui probleem on ainult visuaalne, võib piisata. Kui sokkel laguneb niiskuse tõttu, tuleb enne viimistlust lahendada niiskuse põhjus."],
    ["Kas vundamenditööd vajavad kaevamist?", "Mitte alati. Mõni parandus on lokaalne ja nähtaval pinnal, kuid hüdroisolatsiooni või drenaaži puhul võib kaevamine olla vältimatu."],
    ["Millal on vaja drenaaži?", "Drenaaži vajadus sõltub pinnasest, vee liikumisest ja hoone niiskusprobleemist. Seda ei tasu lisada automaatselt ilma põhjuse hindamiseta."]
  ],
  "fassaaditood.html": [
    ["Kas fassaadi saab parandada ainult kahjustunud kohtadest?", "Jah, kui kahjustus on lokaalne ja ülejäänud pind on stabiilne. Kui probleem on süsteemne, annab ainult kohtparandus lühiajalise tulemuse."],
    ["Millal tuleks fassaad enne värvimist pesta?", "Kui pinnal on mustus, sammal, tolm või lahtised osakesed, tuleb pesu teha enne krunti ja värvi. Pärast pesu peab pind saama kuivada."],
    ["Kas fassaaditöödeks on alati tellinguid vaja?", "Mitte alati. Madalal või lokaalsel tööl võib piisata muust ligipääsust, kuid kvaliteedi ja ohutuse jaoks on telling paljudel objektidel parim lahendus."],
    ["Kas teete ka sokli parandusi?", "Jah, kui töö seostub fassaadi või vundamendi nähtava osaga. Sokli niiskuskahjustuse puhul tuleb enne viimistlust põhjus üle vaadata."]
  ],
  "fassaadivarvimine.html": [
    ["Kas fassaadi võib värvida kohe pärast pesu?", "Ei. Pind peab enne kruntimist ja värvimist piisavalt kuivama. Kuivamisaeg sõltub ilmast, materjalist ja fassaadi seisukorrast."],
    ["Kas sama värv sobib puidule ja krohvile?", "Tavaliselt mitte. Puit, krohv ja sokkel vajavad erinevat värvisüsteemi ning vale toode võib põhjustada koorumist või niiskusprobleeme."],
    ["Kui tihti peaks puitfassaadi värvima?", "See sõltub ilmakaarest, varasemast värvist, puidu seisukorrast ja hooldusest. Päikese ja vihma käes olevad küljed kuluvad tavaliselt kiiremini."],
    ["Kas värvimise ajal peab ilm olema täiesti päikeseline?", "Ei. Liiga tugev päike võib olla isegi halb, sest värv kuivab pinnal liiga kiiresti. Oluline on kuiv, mõõdukas ja tootja nõuetele vastav ilm."]
  ],
  "katusetood.html": [
    ["Kas väikest lekkekohta saab parandada ilma kogu katust vahetamata?", "Sageli saab, kui kahjustus on lokaalne ja katuse üldseisukord on hea. Kui probleem kordub mitmes kohas, tuleb hinnata laiemat renoveerimisvajadust."],
    ["Kas katuse pesu võib katust kahjustada?", "Jah, kui kasutatakse vale survet või töövõtet. Pesu tuleb teha katusematerjali järgi, et mitte kahjustada pinda, kinnitusi ega kaitsekihti."],
    ["Kas katusetöid saab teha talvel?", "Mõningaid hooldus- ja avariitöid saab teha, kuid ilm, lumi, jää ja ohutus piiravad tööde ulatust. Suuremad tööd on mõistlik planeerida sobivamale ajale."],
    ["Kas kontrollite ka vihmaveerenne?", "Jah, katuse ülevaatusel on rennid ja vee liikumine oluline osa, sest ummistus või vale kalle võib tekitada fassaadi- ja niiskuskahjustusi."]
  ],
  "lumetood.html": [
    ["Millal tuleks lumi katuselt eemaldada?", "Lumi tuleks eemaldada siis, kui koormus muutub suureks, tekivad jääpurikad või lumi ohustab inimesi, autosid ja sissepääse. Ohtlikke olukordi ei tasu edasi lükata."],
    ["Kas lumetöödega võib katusekatet kahjustada?", "Vale tööriistaga jah. Kasutame töövõtteid, mis vähendavad katusekatte, rennide ja fassaadi kahjustamise riski."],
    ["Kas puhastate ka jääpurikaid?", "Jah, kui ligipääs ja ohutus seda võimaldavad. Enne töö algust tuleb piirata ala, kuhu lumi või jää võib kukkuda."],
    ["Kas lumetöid saab tellida ühekordselt?", "Jah. Võimalik on tellida ühekordne ohuolukorra lahendamine või korduv hooldus suurema lumesaju perioodil."]
  ]
};

for (const [file, faqs] of Object.entries(additions)) {
  const filePath = path.join(ROOT, file);
  let html = fs.readFileSync(filePath, "utf8");
  const sectionMatch = html.match(/<section class="service-faq">[\s\S]*?<\/section>/);
  if (!sectionMatch) throw new Error(`${file} missing service-faq section`);
  let section = sectionMatch[0];
  let changed = false;
  for (const [question, answer] of faqs) {
    if (section.includes(`<summary>${question}</summary>`)) continue;
    section = section.replace("</section>", `\n<details><summary>${question}</summary><p>${answer}</p></details>\n</section>`);
    changed = true;
  }
  if (changed) {
    html = html.replace(sectionMatch[0], section);
    fs.writeFileSync(filePath, html, "utf8");
  }
}

console.log(`Ensured useful FAQ expansions on ${Object.keys(additions).length} service pages.`);
