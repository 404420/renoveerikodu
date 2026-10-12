# Keeleversioonide hooldamine

Eesti lehed on algallikad. Inglise ja vene lehed on valmis staatilised HTML-failid;
neid saab indekseerida JavaScriptita. Kõigil lehtedel on oma canonical, vastastikused
et/en/ru/x-default hreflang-viited ja samal lehel keelt vahetav ET / EN / RU valik.
Sitemap sisaldab 26 lehte igas keeles. Privaatsuspoliitika kolm versiooni on noindex.

## Sisu muutmine

1. Muuda eesti HTML-lehe sisu.
2. Lisa või uuenda täpsed tekstivasted kataloogis `scripts/i18n/<leht>.json`.
   Võti on eestikeelne tekst ja väärtus on `[ingliskeelne, venekeelne]`.
   Korduvad tekstid, sealhulgas menüü, asuvad failis `common.json`.
3. Käivita `npm run build:languages` ja `npm run check:seo`.
   Ehitaja katkestab enne failide muutmist, kui mõni tõlge puudub.
4. Laadi hostingu veebijuure üles kõik muudetud failid, sealhulgas `en/`, `ru/`,
   ühised JS/CSS-varad ja sitemap. Genereeritud HTML kuulub reposse.

Keele URL-id on failis `scripts/i18n/routes.json`. Sisulinke suunatakse valitud
keele vastetele, pilte ning vormi API-t kasutatakse kõigis keeltes ühiselt.
Keelevalik ei suuna külastajat automaatselt ümber: valitud URL määrab keele.
Uue lehe lisamisel tuleb uuendada ka marsruute ning SEO-kontrolli lehtede arvu.

## Admini päringute tõlkimine

Vorm salvestab lehe keele, algne sõnum jääb muutmata. Admini venekeelse või
kirillitsat sisaldava päringu juures saab vajutada **Tõlgi eesti keelde**.
DeepL-i seadistus, cURL nõue ja olemasoleva andmebaasi lisaväljade migratsioon
on kirjeldatud failis `BACKEND_SETUP.md`. Tõlke võti asub ainult serveris.
Tõlge salvestatakse eraldi ning sama muutmata sõnumit uuesti teenusele ei saadeta.

GitHub Pages on ainult staatiline, noindex eelvaade. Päris kontaktivorm ja admin
vajavad PHP/MySQL hostingut; reposse salvestamine ei paigalda neid sinna automaatselt.
