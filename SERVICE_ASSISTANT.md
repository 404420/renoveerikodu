# Renoveeri Kodu teenindusrobot

Avalikul lehel on nimi „Teenindusrobot”, inglise ja vene lehel vastav tõlge. Saatjanimi on kõigis keeltes „RK”. Avamisnupul on ainult vestlusmulli ikoon, nupu ligipääsetav nimi jääb ekraanilugejale alles. Oranž teema kasutab sama põhivärvi `#a84400` nagu „Võta ühendust” nupp. Abiline töötab kõigil 81 avalikul keeleversiooni lehel. Vestluse päis selgitab, et tegu on automaatse abilisega ning lõplikud hinnad ja tööajad kinnitab meeskond.

## Käitumine

- „Soovin hinnapakkumist” kuvab kõigi avalike põhiteenuste valiku ning vannitoa remondi, fassaadi värvimise ja katuse hoolduse. Loend on ühine kõigile lehtedele ja tõlgitud kolmes keeles; see ei sõltu konkreetse lehe lühendatud menüüst. Pikas loendis saab kerida.

- Kinnitatud vastused käsitlevad teenuseid, materjale, hinnas sisalduvat, garantiid, ajakava, maksmist, ettevalmistust, tööjärjekorda, märgruume, põrandakütet, jäätmeid, remonti ja kontaktandmeid.
- Arvutused kasutavad avaliku `hinnakiri.html` 25 ühikuhinnaga töö hinda. 3 näidispaketti on orientiirid, mitte põrandapinnaga korrutatavad ühikuhinnad. Avaliku hinnakirjata töö puhul küsitakse objekti andmeid.
- Eraldi kogustega tööde tööraha saab kokku liita. Ühise või vastuolulise koguse puhul küsitakse täpsustust. m², jm, tk ja komplekt on eraldi ühikud. Uus teenus ei päri eelmise töö kogust.
- Jätkusõnum „25 m²” seotakse eelneva üheselt määratud tööga. Materjalide jätkuküsimus võib näidata sama töö materjalidega alghinda ja arvestust, kui hinnakirjas on selleks eraldi hind.
- Korteri või toa pindala ei käsitleta automaatselt seinte ja lagede viimistluspinnana. Kandevkonstruktsioonide, niiskuskahjustuse ja avarii küsimused suunatakse pädevale abile.
- Bot ei broneeri aega, analüüsi fotosid, kinnita projektlahendusi ega saada ise kliendipäringut.

## Andmevoog ja piirid

Praegune majutus on staatiliste lehtede ning PHP kontaktivormi kombinatsioon. Abiline kasutab kohalikku intentide ja kinnitatud vastuste loogikat; keelemudeli API-t ega API võtmeid ei lisatud. Piiramatu vabavestlus ei kuulu selle lahenduse võimekusse. Tundmatute küsimuste korral pakutakse ühendust meeskonnaga, mitte oletuslikku vastust.

Vestlus on lehe mälus. Ainult „Koosta hinnapäring” valimisel salvestatakse viimase kuni 16 kliendisõnumi kirjeldus sama vahelehe `sessionStorage`-sse. Kontaktivormis saab kasutaja kokkuvõtte eraldi nupuga lisada; olemasolev tekst säilib. Kokkuvõte aegub 30 minutiga ja eemaldatakse pärast lisamist. Lõpliku päringu saadab kasutaja olemasoleva turvakontrolliga kontaktivormi kaudu. Vestluse enda jaoks serveripäringuid ei tehta.

Ühikuhinnad ja tõlked asuvad `assets/js/renoveeri-ai-chat.js` failis, käitumine ning uued nõuanded `assets/js/renoveeri-support.js` failis. Failinime säilitati olemasoleva integratsiooni jaoks; avalikud saatjanimed ei sisalda „AI”. Välimus asub `assets/css/renoveeri-support.css` failis. Avalikud lehed sisaldavad esmalt käitumise skripti, seejärel kataloogi skripti. Uus CSS laaditakse ühise skripti asukohast, nii et see töötab ka GitHub Pagesi eelvaate alamkaustas.

## Konkurentide avalike lahenduste vaatlus, 13.10.2026

Vaatlus puudutas avalikke nõustamis- ja hinnapäringuvooge; teiste ettevõtete privaatse roboti sisemist loogikat ei kontrollitud.

- [Ehitab.ee kalkulaator](https://ehitab.ee/kalkulaator/): objekt → töömaht → eelarve; eristab hinnas sisalduvat ja eraldi täpsustatavat. Sellest lähtudes küsib abiline ühikuid ja selgitab töömahu piire.
- [Remondikratt](https://remondikratt.ee/): juhendab saatma probleemi kirjelduse ja foto ning pakub otsest kontakti. Abiline viib kirjelduse kontaktivormi ja pakub telefonikontakti.
- [Prism korteri renoveerimine](https://prism.ee/korteri-renoveerimine/): seob hinna tööde ulatuse ja objekti ülevaatusega. Abiline eristab alghinda lõplikust pakkumisest ning ei anna siduvat tehnilist kinnitust vestluses.

Konkurentide hindu, maksetingimusi ega ajakavalubadusi ei kantud RK Meistrite vastustesse.

## Kontrollimine

`npm test` kontrollib 24 kliendistsenaariumi, hinnakirja vastavust ja kõigi keelelehtede skripte. `npm run check:seo` kontrollib lehtede linke, tõlkeid, varasid ja sitemap'i. Tõlked taastoodetakse käsuga `npm run build:languages` (Windowsis kasuta Python 3 UTF-8 režiimi).

Brauseris kontrolliti tööhinnangut, ühikuga jätkusõnumit, inglise ja vene vastuseid, vestluse kokkuvõtte lisamist olemasolevat teksti säilitades, Escape-klahvi ja mobiilipaigutust. Kohalik eelvaade ei käivita päris PHP/MySQL kontaktivormi: päringute edastamist tootmisandmebaasi ega meili ei katsetatud ja proovipäringuid ei saadetud.
