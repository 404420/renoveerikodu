# Renoveeri Kodu PHP/MySQL paigaldus

GitHub Pages ei sobi selle projekti lõplikuks majutuseks, kui kontaktivorm, failide üleslaadimine ja admin paneel peavad töötama. GitHub Pages serveerib ainult staatilisi faile ning ei käivita PHP-d ega ühendu MySQL andmebaasiga.

Sobiv lahendus on tavaline PHP + MySQL veebimajutus, näiteks Zone, Veebimajutus.ee, Hostinger, Verpex cPanel või muu PHP 8.x ja MySQL/MariaDB toega pakett.

## Paigaldus

1. Laadi failid PHP-hostingu `public_html` kausta.
2. Loo hostingus MySQL andmebaas ja kasutaja.
3. Impordi phpMyAdminis fail `database.sql`.
4. Kopeeri `config.example.php` failiks `config.php`.
5. Täida `config.php` sees andmebaasi host, nimi, kasutaja ja parool.
6. Ava brauseris `/admin/setup-admin.php` ja loo esimene admin kasutaja.
7. Edaspidi logi sisse aadressil `/admin/`.

## Töötav andmevoog

- Avalehe ja kontaktilehe vormid saadavad päringu aadressile `/api/contact.php`.
- `api/contact.php` valideerib väljad, salvestab failid kausta `api/uploads/contact/` ja lisab päringu MySQL tabelisse `contacts`.
- Admin paneel `/admin/` loeb päringud samast `contacts` tabelist.
- Vana vormi aadress `submit-contact.php` töötab edasi ja suunab sama kontaktihändleri peale.

## Oluline

Ära pane päris `config.php` faili GitHubi. See sisaldab andmebaasi paroole. Fail on `.gitignore` all.

Kui vanad andmebaasi paroolid või reCAPTCHA saladused on kunagi GitHubi jõudnud, tuleb need hostingus välja vahetada.
