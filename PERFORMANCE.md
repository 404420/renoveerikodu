# 13.09.2026 veebilehe uuendus

Alus: omaniku `13.09.rk_backup.zip`, ühendatud GitHubi varasema lähtekoodiga. Uuendatud on avalikud lehed, administraatori ja töötaja paneel ning API lähtekood. Säilitatud on varasemad arendusskriptid, seadistuse näidis ja GitHub Pagesi turvaline avalike failide pakkimine.

## Kontrollitud tulemused

Lighthouse'i kohalik test Chrome'is, mobiili vaikimisi aeglustusega, gzip-toega staatilises eelvaates. Need ei ole renoveerikodu.ee tootmisserveri uued PageSpeedi tulemused.

| Leht | Jõudlus | Ligipääsetavus | Parimad praktikad | SEO | Agentic browsing |
| --- | ---: | ---: | ---: | ---: | ---: |
| Avaleht, mobiil | 99 | 100 | 100 | 100 | 100 |
| Avaleht, arvuti | 100 | 100 | 100 | 100 | 100 |
| Kontakt, mobiil | 98 | 100 | 100 | 100 | 100 |

ZIP-i avalehe kohalik algtest: 74/82/96/100; LCP 9,2 s. Optimeeritud avalehe mobiilne LCP: ligikaudu 2,0 s. Algtesti parimate praktikate skoori mõjutas kohaliku staatilise eelvaate puuduv PHP endpoint. Tootmise skoorid sõltuvad ka hostingu vastuseajast, tihendusest, vahemälust ja väliste teenuste koormusest.

## Muudatused

- WebP pildid, väiksemad mobiili taustapildid, mitmes mõõdus logod ning piltide mõõtmed HTML-is.
- Esimene ekraan kuvatakse kohe, ilma kogu lehe laadimist ootava animatsioonita.
- Oluliste piltide eellaadimine ja prioriteedid; allpool nähtavat ala olevate piltide lazy loading.
- Minimeeritud CSS. Ikoonifont laaditakse ainult seda kasutavatel lehtedel, fondi kuvamine ei blokeeri teksti.
- Nuppude kontrast, mobiilne suumimine, vormiväljade ja ikoonlinkide nimed, klaviatuuriga kasutatav menüü ja hinnakalkulaator.
- Kontaktilehe vigane taustapildi aadress parandatud; mobiilis välditakse töölaua taustapildi lisalaadimist.
- Analytics laaditakse pärast lehe põhisisu; olemasolev sündmuste järjekord säilib. reCAPTCHA käivitub vormile lähenemisel või vormi kasutamisel; serveripoolset kontrolli ei muudeta.
- Apache/LiteSpeed tihenduse ja staatiliste failide vahemälu reeglid ning versioonitud CSS/JS viited.

25 avalikul lehel kontrolliti mobiili paigutust, pilte ja JavaScripti vigu. Axe'i WCAG A/AA automaatkontrollis ei tuvastatud rikkumisi. Kontrolliti menüüd ja alammenüüd klaviatuuriga, kalkulaatori vastust ning fookuse taastamist. Kontaktivormi brauseritest kasutas simuleeritud API ja CAPTCHA vastuseid; päris e-kirju ei saadetud. PHP/MySQL andmebaasivoogu selles staatilises eelvaates ei käivitatud.

## Paigaldus ja hooldus

GitHubi uuendus ei paigalda iseenesest faile praegusesse LiteSpeed/PHP majutusse. Tootmisserverisse tuleb viia uuendatud avalikud failid, `assets`, `images` ja `.htaccess`; serveripoolse versiooni uuendamisel ka ZIP-i API/admin/töötaja lähtekood. Säilita serveris olemasolev `config.php`, üleslaadimised ja andmebaas. Ära impordi olemasoleva andmebaasi peale kogu `database.sql` faili; skeemimuudatused tuleb rakendada kontrollitult, vastavalt olemasolevale andmebaasile.

`config.php`, klientide üleslaadimised, Google'i privaatvõtmed, logid, vanad admini koopiad ja varukoopia ZIP-id ei kuulu GitHubi. GitHub Pages avaldab ainult staatilise eelvaate ning ei käivita PHP-d.

CSS-i muutmise järel käivita `npm ci` ja `npm run build:css`. Genereeritud `.min.css` failid on lähtekoodis olemas, seega majutus ei vaja Node.js-i. Järgmise uuenduse puhul suurenda ka vastavaid CSS/JS versiooniparameetreid HTML-is. Pärast tootmisse paigaldamist tee uus mobiili ja arvuti PageSpeedi test.
