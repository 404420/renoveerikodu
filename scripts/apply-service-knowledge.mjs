import fs from "node:fs";
import path from "node:path";

const ROOT = process.cwd();

const sections = {
  "plaatimine.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo plaatimise kohta</h2>
  <h3>Materjalid</h3>
  <p>Plaatimise kvaliteet sõltub aluspinnast, niiskuskaitsest, plaadi tüübist, segust ja vuugimaterjalist. Märgruumis kasutame lahendust, kus hüdroisolatsioon, nurgalindid, läbiviikude tihendused ja sobiv plaatimissegu töötavad koos. Suureformaadilise plaadi puhul peab aluspind olema eriti tasane, sest väike kõverus jääb lõpptulemuses kiiresti näha.</p>
  <h3>Ajakulu</h3>
  <p>Väike köögi tagasein võib valmida lühikese ajaga, kuid vannituba vajab tavaliselt mitut etappi: vana pinna eemaldus, tasandus, hüdroisolatsiooni kuivamine, plaatimine, vuukimine ja silikoonimine. Kuivamisajad on osa kvaliteedist, mitte paus töökorralduses.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad aluspinna seisukord, plaatide mõõt, lõigete arv, kallete tegemine, nišid, dušinurga lahendus, hüdroisolatsiooni ulatus ja see, kas objekt asub elatud korteris või tühjal pinnal.</p>
  <h3>Levinud vead</h3>
  <p>Kõige sagedasemad vead on liiga kiire alustamine niiskel või ebatasasel pinnal, läbiviikude nõrk tihendamine, vale segu valik ning vuukide või silikooni tegemine enne pinna piisavat kuivamist.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne hinnapäringut tasub saata ruumi mõõdud, fotod, plaadi suurus ja info selle kohta, kas vana plaat jääb eemaldada või on pind juba ette valmistatud. Nii saab pakkumine olla täpsem ja tööjärjekord realistlikum.</p>
</section>`,
  "maalritood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo maalritööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Maalritööde puhul on oluline valida õige pahtel, krunt ja värv vastavalt ruumi kasutusele. Köögis, esikus ja lastetoas on pestav värv praktilisem kui väga matt toon; niiskemates ruumides tuleb jälgida, et värvisüsteem sobiks koormusega.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub kihtide arvust ja kuivamisest. Pahteldus, lihvimine, kruntimine ja kaks värvikihti ei ole üks tööpäev, kui pind vajab korralikku parandust. Kiirustamine jätab nähtavad lihvimisjäljed, varjud ja ebaühtlase läike.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad seina kõrgus, pinna seisukord, pragude parandused, nurkade ja lagede arv, värvitoonide vahetus ning see, kas mööbel ja põrandad vajavad kaitsmist.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on krundi vahele jätmine, liiga väike kuivamisaeg, vale valguse all kontrollimata pind ja odava värvi kasutamine ruumis, kus pind peab taluma pesemist.</p>
  <h3>Soovitused kliendile</h3>
  <p>Hea tulemuse jaoks vali toon näidisega samas valguses, kus ruumi päriselt kasutatakse. Kui seintele tulevad riiulid, liistud või paneelid, tasub nende asukohad enne lõppvärvi kokku leppida.</p>
</section>`,
  "ledlahendused.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo LED lahenduste kohta</h2>
  <h3>Materjalid</h3>
  <p>LED lahenduste puhul on tähtsad profiil, hajuti, toiteplokk, juhtimine ja valguse toon. Odav LED-riba võib anda ebaühtlase valgusjoone või hakata vilkuma, kui toiteplokk ja juhtimine ei sobi koormusega.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub sellest, kas valgustus paigaldatakse valmis pinnale või ehitatakse selle jaoks karniis, nišš või ripplagi. Kõige sujuvam on planeerida LED lahendus enne kipsi- ja maalritööde lõppu.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad valgusjoone pikkus, profiili tüüp, juhtimise lahendus, ligipääs kaablitele, toiteplokkide peitmine ja see, kas töö nõuab lisaks kipsi- või viimistlustööd.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on liiga nõrk toiteplokk, nähtavale jäävad ühendused, vale valguse temperatuur ja lahendus, mille hooldamiseks ei pääse hiljem toiteplokile ligi.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne töö algust tasub otsustada, kas valgus peab olema praktiline töövalgus, meeleoluvalgus või arhitektuurne detail. Sellest sõltub nii LED-i võimsus kui ka paigutus.</p>
</section>`,
  "seinapaneelidepaigaldus.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo seinapaneelide paigalduse kohta</h2>
  <h3>Materjalid</h3>
  <p>Seinapaneelide puhul tuleb arvestada paneeli tüüpi, kinnitust, aluspinna sirgust ja servade lõpetust. Akustilised paneelid, MDF-liistud ja dekoratiivpaneelid vajavad erinevat lõikamist ning erinevat kinnitust.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub lõigete arvust, pistikupesadest, nurkadest ja sellest, kas sein vajab enne parandamist või värvimist. Sirge aktsentsein valmib kiiremini kui mitme läbiviigu ja servaga lahendus.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad paneeli materjal, seina suurus, aluspinna seisukord, nähtavate servade arv, elektripunktid ja see, kas paneelide taha või kõrvale tuleb valgustus.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on ebatasase seina ignoreerimine, läbimõtlemata algusjoon, pistikupesade ümbruse lohakas lõige ja paneelide paigaldus enne tolmuste tööde lõppu.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne tellimist mõõda sein, märgi pistikupesad ja vali, kas paneel lõpeb lae, põranda või mööbli järgi. See aitab vältida ebamugavaid lõikeid ja visuaalselt juhuslikke servi.</p>
</section>`,
  "parketipaigaldus.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo parketi paigalduse kohta</h2>
  <h3>Materjalid</h3>
  <p>Parketi puhul on tähtis põrandakatte tüüp, alusmaterjal, niiskustõke ja liistude lahendus. Laminaat, laudparkett ja kalasabamustriga põrand käituvad erinevalt ning vajavad erinevat aluspinna täpsust.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub ruumi kujust, vana katte eemaldusest, aluspinna tasandamisest ja liistude arvust. Uus põrand tuleks paigaldada siis, kui märjad ja tolmused tööd on lõppenud.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad ruutmeetrid, aluspinna kõrguste erinevus, ukseavade lõiked, torude ümbrused, liistude tüüp ja see, kas vana põrand tuleb eemaldada ning ära vedada.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on paisumisvuugi puudumine, liiga niiske aluspind, vale alusmaterjal ja põranda paigaldus enne seda, kui ruumi niiskus on stabiliseerunud.</p>
  <h3>Soovitused kliendile</h3>
  <p>Too materjal objektile piisava varuga ja lase sellel ruumi tingimustega kohaneda. Kui võimalik, vali liistud ja üleminekuprofiilid enne paigalduspäeva.</p>
</section>`,
  "tapeetimine.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo tapeetimise kohta</h2>
  <h3>Materjalid</h3>
  <p>Tapeetimise tulemus sõltub tapeedi tüübist, liimist, aluspinna imavusest ja mustri kordusest. Fliis-, vinüül- ja pabertapeet ei käitu paigaldusel ühtemoodi.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu määrab seina ettevalmistus. Kui pind vajab pahteldamist, lihvimist ja kruntimist, tuleb arvestada kuivamisaegadega enne tapeedi paigaldust.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad mustri sobitamine, seinte arv, akna- ja ukseavad, nurkade seisukord ning see, kas vana tapeet tuleb eemaldada.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on vana liimi või lahtise tapeedi peale paigaldamine, krundi vahele jätmine ja mustri nihkumine, kui esimene paan ei ole täpselt loodis.</p>
  <h3>Soovitused kliendile</h3>
  <p>Osta tapeeti varuga, eriti mustriga tapeedi puhul. Säilita rullide partiinumbrid, sest erinevast partiist toon võib seinal erineda.</p>
</section>`,
  "kipsitood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo kipsitööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Kipsitöödel kasutatakse erinevaid plaate ja karkasse vastavalt ruumi koormusele. Märgruumis ei piisa tavalisest kipsist; vaja on niiskuskindlat lahendust ja õigeid liitekohti.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub karkassi keerukusest, plaatide arvust, avadest, vuukimisest ja kuivamisest. Ripplagi koos valgustusega võtab rohkem aega kui sirge vahesein.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad konstruktsiooni kõrgus, heliisolatsioon, niiskusnõuded, avad, tugevdused ja see, kas töö hõlmab ka pahteldust või ainult plaadi paigaldust.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on liiga hõre karkass, puuduvad tugevdused riiulite või kappide jaoks, läbimõtlemata valgustiaugud ja vuukide kiirustatud viimistlus.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne karkassi sulgemist tasub läbi mõelda elektri, valgustuse, riiulite, peeglite ja kappide kinnituskohad. Hiljem on neid parandada kallim.</p>
</section>`,
  "lammutustood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo lammutustööde kohta</h2>
  <h3>Materjalid ja jäätmed</h3>
  <p>Lammutustöödel tuleb eristada ehitusprahti, puitu, metalli, plaati, sanitaartehnikat ja võimalikke ohtlikke materjale. Õige sorteerimine teeb utiliseerimise selgemaks ja vähendab hilisemaid probleeme objektil.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub konstruktsiooni tüübist, korrusest, ligipääsust, prahi kogusest ja sellest, kas töö toimub elatud korteris või tühjal objektil.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad prahi äravedu, kaitsetööd, tolmu piiramine, kandvate konstruktsioonide vältimine, torustiku või elektri olemasolu ning parkimis- või liftiligipääs.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on lammutuse alustamine ilma kommunikatsioone kontrollimata, kandva seina valesti hindamine ja prahi liikumistee kaitsmata jätmine.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne lammutust tasub kokku leppida, mis jääb alles, mis utiliseeritakse ja millised pinnad vajavad kaitset. Kortermajas tuleb arvestada ka maja reeglite ja tööaegadega.</p>
</section>`,
  "tellingutepaigaldus.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo tellingute paigalduse kohta</h2>
  <h3>Materjalid ja lahendus</h3>
  <p>Tellingulahendus valitakse hoone kõrguse, pinnase, töö tüübi ja ligipääsu järgi. Oluline on stabiilne alus, korrektne ankurdamine ja ohutu liikumistee töötegijatele.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub tellingu kõrgusest, pikkusest, pinnase ettevalmistusest ja sellest, kas objekt asub kitsas hoovis, tänava ääres või ebatasasel pinnal.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad tellingu maht, rentimise aeg, transport, paigalduskeerukus, katuse või fassaadi erikuju ja vajadus kaitsevõrkude või lisaplatvormide järele.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on liiga kitsas tööplatvorm, nõrk alus, ebapiisav ankurdamine ja olukord, kus telling ei ulatu tegeliku tööalani.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne pakkumist saada fotod hoone kõigist külgedest, ligipääsust ja pinnasest. Nii saab planeerida ohutu lahenduse ega pea töö käigus tellingut ümber ehitama.</p>
</section>`,
  "betoonitood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo betoonitööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Betoonitöödel on oluline betooni klass, armeerimine, aluspinna tihendus, niiskuskaitse ja järelhooldus. Väiksem parandus ja uus valupind vajavad erinevat lahendust.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu ei piirdu valamisega. Arvestada tuleb ettevalmistuse, vormide, armeerimise, valamise, tasandamise ja kivinemisega enne järgmisi töid.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad betooni kogus, ligipääs, pumba või käsitsi töö vajadus, armeerimine, pinna viimistlus ja see, kas vana betoon tuleb eemaldada.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on nõrk või vajuv aluspind, valesti planeeritud kalle, puudulik armeerimine ja liiga kiire järgmise tööetapi alustamine enne piisavat kivinemist.</p>
  <h3>Soovitused kliendile</h3>
  <p>Enne töö tellimist tasub täpsustada, millist koormust pind kandma hakkab ja kas sellele tuleb plaat, parkett, hüdroisolatsioon või välitingimustes viimistlus.</p>
</section>`,
  "vundamenditood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo vundamenditööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Vundamenditöödel on tähtsad betoon, armeerimine, hüdroisolatsioon, drenaaž, soojustus ja sokli viimistlus. Õige lahendus sõltub pinnasest ja niiskuskoormusest.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub kaevetööde mahust, ligipääsust, pinnase seisukorrast, kuivamisest ja sellest, kas parandatakse olemasolevat vundamenti või ehitatakse uut osa.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad kaevamise vajadus, betooni ja armeerimise maht, niiskuskaitse ulatus, drenaaž, tagasitäide ja tööala ligipääs.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on niiskuse põhjuse eiramine, sokli viimistlemine enne hüdroisolatsiooni lahendamist ja vee äravoolu planeerimata jätmine.</p>
  <h3>Soovitused kliendile</h3>
  <p>Kui vundamendi juures on niiskus, soolajäljed või pragunemine, tasub saata fotod nii seest kui väljast. Ainult nähtava pinna parandamine ei pruugi põhjust lahendada.</p>
</section>`,
  "fassaaditood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo fassaaditööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Fassaaditöödel valitakse materjal hoone tüübi järgi: puit, krohv, sokkel ja liitekohad vajavad erinevat parandust, krunti ja viimistlust. Vale materjal võib kiirendada koorumist või niiskuskahjustust.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub ilmast, kuivamisest, kõrgusest, ligipääsust ja kahjustuste ulatusest. Välitöödel peab arvestama vihma, tuule, temperatuuri ja otsese päikesega.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad fassaadi pindala, kõrgus, tellingud, pesu, paranduste hulk, värvikihtide arv ja detailid nagu aknapaled, sokkel ja räästad.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on mustale või niiskele pinnale viimistlemine, pragude põhjuse parandamata jätmine ja erinevate materjalide liitekohtade tähelepanuta jätmine.</p>
  <h3>Soovitused kliendile</h3>
  <p>Saada päringuga fotod eri külgedest ja lähivaated kahjustustest. Nii saab eristada, kas vaja on hooldust, parandust, värvimist või suuremat renoveerimist.</p>
</section>`,
  "fassaadivarvimine.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo fassaadi värvimise kohta</h2>
  <h3>Materjalid</h3>
  <p>Fassaadivärv tuleb valida pinna järgi. Puitfassaad, krohv ja sokkel vajavad erinevat krunti, värvi ja ettevalmistust; oluline on ka varasema värvisüsteemi sobivus.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub pesust, kuivamisest, lahtise värvi eemaldamisest, kruntimisest ja ilmast. Hea värvimistöö eeldab kuiva pinda ja sobivat temperatuuri.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad pinna kõrgus, ligipääs, värvikihtide arv, parandustööd, akende ja detailide kaitsmine ning see, kas vaja on tellinguid.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on värvimine liiga niiske või kuuma pinnaga, pesu vahele jätmine ja vana lahtise värvi ebapiisav eemaldamine.</p>
  <h3>Soovitused kliendile</h3>
  <p>Vali toon prooviala järgi, mitte ainult värvikaardi järgi. Suurel pinnal võib toon paista heledam või intensiivsem kui väikesel näidisel.</p>
</section>`,
  "katusetood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo katusetööde kohta</h2>
  <h3>Materjalid</h3>
  <p>Katusetöödel sõltub materjal katuse tüübist: plekk, kivi, bituumen ja läbiviigud vajavad erinevat hooldust. Tähelepanu tuleb pöörata ka kinnitustele, tihenditele ja vee liikumisele.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub katuse kaldest, ligipääsust, ilmast, ohutuslahendusest ja kahjustuste ulatusest. Märja, jäise või tugeva tuulega katusel ei ole mõistlik kvaliteedi arvelt kiirustada.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad katuse pindala, kõrgus, turvavarustus, läbiviikude arv, vihmaveesüsteemid ja vajadus tellingu või tõstuki järele.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on väikeste lekete edasilükkamine, läbiviikude tihenduse kontrollimata jätmine ja katuse pesu tegemine viisil, mis kahjustab kattematerjali.</p>
  <h3>Soovitused kliendile</h3>
  <p>Saada fotod probleemkohast, kogu katusepinnast ja ligipääsust. Kui probleem ilmneb ainult vihmaga, kirjelda, kust vesi sisse tuleb ja millal see tekib.</p>
</section>`,
  "lumetood.html": `
<section class="service-knowledge">
  <h2>Praktiline tööinfo lumetööde kohta</h2>
  <h3>Vahendid ja ohutus</h3>
  <p>Lumetöödel kasutatakse vahendeid, mis ei kahjusta katusekatet, fassaadi ega vihmaveesüsteemi. Kõrgematel objektidel on oluline turvavarustus ja tööala piiramine.</p>
  <h3>Ajakulu</h3>
  <p>Ajakulu sõltub lume kogusest, jää olemasolust, katuse kaldest, ligipääsust ja sellest, kas puhastada tuleb ainult ohtlikud servad või kogu pind.</p>
  <h3>Hinnategurid</h3>
  <p>Hinda mõjutavad objekti kõrgus, lume maht, jää eemaldamine, ligipääs, töö kiireloomulisus ja ohutusmeetmed jalakäijate või autode kaitseks.</p>
  <h3>Levinud vead</h3>
  <p>Levinud vead on katusekatte lõhkumine metallist tööriistaga, lume lükkamine ohtlikku kohta ja jääpurikate eemaldamine ilma all oleva ala piiramiseta.</p>
  <h3>Soovitused kliendile</h3>
  <p>Kui lumi või jää ohustab sissepääsu, kõnniteed või parkimist, anna päringus teada täpne asukoht ja lisa fotod. Kiireloomulistel töödel on ligipääsuinfo eriti oluline.</p>
</section>`
};

for (const [file, section] of Object.entries(sections)) {
  const filePath = path.join(ROOT, file);
  let html = fs.readFileSync(filePath, "utf8");
  if (html.includes('class="service-knowledge"')) continue;
  const marker = '<section class="service-faq">';
  if (!html.includes(marker)) throw new Error(`${file} missing service-faq section`);
  html = html.replace(marker, `${section}\n\n${marker}`);
  fs.writeFileSync(filePath, html, "utf8");
}

console.log(`Ensured expert knowledge sections on ${Object.keys(sections).length} service pages.`);
