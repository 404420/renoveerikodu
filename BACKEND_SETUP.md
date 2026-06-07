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

## Vercel ja live disain

Vercel võib näidata staatilist disaini, aga ta ei käivita selle projekti PHP backendit. Seega Vercelis saad kontrollida HTML/CSS/JS välimust, kuid kontaktivormi MySQL salvestus ja admin paneel töötavad õigesti PHP-hostingus.

## Turva

Kui vanad andmebaasi paroolid või reCAPTCHA saladused on kunagi GitHubi jõudnud, vaheta need Verpexis välja.
