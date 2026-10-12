# Backend ja MySQL ühendamise juhend

See projekt kasutab kontaktivormi jaoks PHP backendit ja MySQL andmebaasi. GitHub ja Vercel sobivad disaini eelvaateks, aga PHP + MySQL osa peab töötama PHP-hostingus, näiteks Verpexis.

## Mis on valmis

- Avalehe ja kontaktilehe vormid saadavad päringu aadressile `/api/contact.php`.
- Backend salvestab päringu MySQL tabelisse `contacts`.
- Failid salvestatakse kausta `api/uploads/contact/`.
- Admin login asub aadressil `/admin/login.php`.
- Admin paneel asub aadressil `/admin/`.
- Esimese admin kasutaja loomine käib aadressil `/admin/setup-admin.php`.
- Vana vormi aadress `submit-contact.php` töötab edasi ja suunab sama backend faili peale.

## 1. Laadi failid PHP-hostingusse

Laadi kogu repo sisu Verpexi `public_html` kausta või selle domeeni document root kausta.

Oluline: ära kasuta GitHub Pagesi PHP/MySQL osa jaoks. GitHub Pages ei käivita PHP-d.

## 2. Loo MySQL andmebaas

Verpexis/cPanelis:

1. Ava `MySQL Databases`.
2. Loo uus andmebaas, näiteks `renoveerikodu`.
3. Loo uus andmebaasi kasutaja.
4. Anna kasutajale selle andmebaasi õigused.
5. Pane kirja:
   - database host, tavaliselt `localhost`;
   - database name;
   - database user;
   - database password.

## 3. Impordi tabelid phpMyAdminis

1. Ava phpMyAdmin.
2. Vali loodud andmebaas.
3. Ava `Import`.
4. Vali repo fail `database.sql`.
5. Käivita import.

See loob tabelid:

- `contacts` - kontaktivormi päringud;
- `admin_users` - admin kasutajad.

## 4. Loo serveris config.php

Serveris kopeeri:

```text
config.example.php
```

uueks failiks:

```text
config.php
```

Täida `config.php` sees need read päris Verpexi MySQL andmetega:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', ' sinu_andmebaasi_nimi ');
define('DB_USER', ' sinu_andmebaasi_kasutaja ');
define('DB_PASS', ' sinu_andmebaasi_parool ');
```

Ära pane päris `config.php` faili GitHubi. See on `.gitignore` all.

## 5. Kontrolli upload kausta

Backend loob upload kausta ise:

```text
api/uploads/contact/
```

Kui failide üleslaadimine annab serveris vea, loo see kaust käsitsi ja anna sellele kirjutamisõigus.

## 6. Loo esimene admin kasutaja

Ava brauseris:

```text
https://www.renoveerikodu.ee/admin/setup-admin.php
```

Sisesta admin kasutajanimi ja vähemalt 10 märgiga parool.

Kui admin on loodud, sulgub setup automaatselt. Edaspidi kasuta:

```text
https://www.renoveerikodu.ee/admin/
```

## 7. Testi kontaktivormi

1. Ava avaleht või kontaktileht.
2. Täida kontaktivorm.
3. Lisa soovi korral fail.
4. Saada vorm.
5. Ava `/admin/`.
6. Kontrolli, et päring ja fail on nähtavad.

## Keeled ja venekeelsete päringute automaatne tõlge

Kontaktivorm saadab lehe keele (`et`, `en` või `ru`); server lubab ainult need kolm väärtust. Vana vormi või tundmatu keele puhul kasutatakse `et`. Adminis on vormi keel nähtav ning kliendi kinnituskiri ja vormi vastused on valitud keeles.

Olemasoleva andmebaasi puhul käivita enne PHP failide uuendamist phpMyAdminis `admin/migrations/20261012_contact_languages.sql`. Vali esmalt õige andmebaas. Migratsiooni võib uuesti käivitada ka osaliselt uuendatud andmebaasis: see lisab ainult puuduvad veerud. Uues andmebaasis on väljad juba `database.sql` failis. Kui andmebaasi kasutajal on `ALTER TABLE` õigus, lisavad PHP failid puuduva veeru automaatselt; käsitsi migratsiooni järel ei ole seda õigust vaja. `message`, `source`, vanad päringud ja nende manused jäävad alles.

Automaatseks tõlkimiseks on vaja **DeepL API Free või DeepL API Pro** kontot ja selle API võtit. Tavalise DeepL Pro tõlkija tellimusest API jaoks ei piisa. Lisa serveri privaatsesse `config.php` faili:

```php
define('DEEPL_API_KEY', 'serverisse_lisatav_api_voti');
define('DEEPL_API_PLAN', 'free'); // DeepL API Pro korral 'pro'
```

Võid kasutada ka serveri keskkonnamuutujaid `DEEPL_API_KEY` ja `DEEPL_API_PLAN`; `config.example.php` loeb neid. Võtit ei tohi lisada GitHubi ega avaliku lehe HTML-i/JavaScripti. PHP peab olema vähemalt 8.0 koos cURL ja PDO MySQL laiendustega. Server vajab HTTPS väljapääsu API Free korral aadressile `api-free.deepl.com:443` või API Pro korral `api.deepl.com:443`.

Admini päringute vaates ilmub vene vormikeelega või kirillitsat sisaldava sõnumi juurde **„Tõlgi eesti keelde”**. Vajutus saadab sõnumi DeepL-ile, mis tuvastab lähtekeele automaatselt ja tõlgib eesti keelde. Tõlkimine nõuab sisselogitud `admin` või `superadmin` kasutajat ning kehtivat CSRF tunnust. Algne sõnum säilib; tõlge, allika räsi, teenuse nimi ja UTC aeg salvestatakse eraldi. Korduv avamine kasutab salvestatud tõlget. Tõlget tasub enne pakkumise koostamist kontrollida.

Kui võti, laiendus või andmebaasiväljad puuduvad, näitab admin eestikeelset seadistusjuhist. Kontaktivorm ja algsete päringute vaatamine töötavad ilma DeepL võtmeta. Tõlketeenuse vea või limiidi korral säilib algne sõnum ning toimingut saab hiljem korrata.

Kontrolli pärast paigaldamist vene ja inglise vormi, kinnituskirja keelt ning vene päringu tõlkenuppu adminis. PHP abil saab võrguühenduseta abifunktsioonide ja turvakontrollide teste käivitada käsuga `php tests/contact-translation.php`.

## Vercel ja live disain

Vercel võib näidata staatilist disaini, aga ta ei käivita selle projekti PHP backendit. Seega Vercelis saad kontrollida HTML/CSS/JS välimust, kuid kontaktivormi MySQL salvestus ja admin paneel töötavad õigesti PHP-hostingus.

## Turva

Kui vanad andmebaasi paroolid või reCAPTCHA saladused on kunagi GitHubi jõudnud, vaheta need Verpexis välja.
