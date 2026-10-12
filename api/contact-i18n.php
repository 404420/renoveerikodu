<?php
declare(strict_types=1);

function rk_contact_language($value): string
{
    return is_string($value) && in_array($value, ['et', 'en', 'ru'], true) ? $value : 'et';
}

function rk_contact_text(string $key, ?string $language = null): string
{
    $texts = [
        'post_only' => ['et' => 'Lubatud on ainult POST-päring.', 'en' => 'Only POST requests are allowed.', 'ru' => 'Разрешены только POST-запросы.'],
        'config_missing' => ['et' => 'Serveri andmebaasi seadistus puudub.', 'en' => 'The server database configuration is unavailable.', 'ru' => 'Настройки базы данных сервера недоступны.'],
        'connection_missing' => ['et' => 'Serveri andmebaasi ühendus puudub.', 'en' => 'The server database connection is unavailable.', 'ru' => 'Соединение с базой данных сервера недоступно.'],
        'captcha_required' => ['et' => 'Palun kinnita, et sa ei ole robot.', 'en' => 'Please confirm that you are not a robot.', 'ru' => 'Подтвердите, что вы не робот.'],
        'captcha_failed' => ['et' => 'Captcha kontroll ebaõnnestus. Palun proovi uuesti.', 'en' => 'CAPTCHA verification failed. Please try again.', 'ru' => 'Проверка CAPTCHA не удалась. Попробуйте ещё раз.'],
        'not_sent' => ['et' => 'Päringut ei saadetud.', 'en' => 'The request was not sent.', 'ru' => 'Запрос не был отправлен.'],
        'name_required' => ['et' => 'Palun sisesta nimi.', 'en' => 'Please enter your name.', 'ru' => 'Укажите ваше имя.'],
        'email_invalid' => ['et' => 'Palun sisesta korrektne e-posti aadress.', 'en' => 'Please enter a valid email address.', 'ru' => 'Укажите корректный адрес электронной почты.'],
        'message_required' => ['et' => 'Palun sisesta sõnum.', 'en' => 'Please enter a message.', 'ru' => 'Введите сообщение.'],
        'upload_directory' => ['et' => 'Failide kausta loomine ebaõnnestus.', 'en' => 'The upload folder could not be created.', 'ru' => 'Не удалось создать папку для файлов.'],
        'upload_failed' => ['et' => 'Faili üleslaadimine ebaõnnestus.', 'en' => 'The file upload failed.', 'ru' => 'Не удалось загрузить файл.'],
        'file_large' => ['et' => 'Üks fail on liiga suur. Maksimaalne suurus on 10 MB.', 'en' => 'A file is too large. The maximum size is 10 MB.', 'ru' => 'Файл слишком большой. Максимальный размер — 10 МБ.'],
        'file_type' => ['et' => 'Failitüüp ei ole lubatud.', 'en' => 'This file type is not allowed.', 'ru' => 'Этот тип файла не разрешён.'],
        'file_save' => ['et' => 'Faili salvestamine ebaõnnestus.', 'en' => 'The file could not be saved.', 'ru' => 'Не удалось сохранить файл.'],
        'database_failed' => ['et' => 'Päring jõudis serverisse, aga andmebaasi salvestamine ebaõnnestus.', 'en' => 'Your request reached the server, but could not be saved. Please try again later.', 'ru' => 'Запрос поступил на сервер, но его не удалось сохранить. Попробуйте позже.'],
        'success' => ['et' => 'Aitäh! Päring on saadetud ja kinnituskiri saadeti teie e-postile.', 'en' => 'Thank you! Your request has been sent and a confirmation email has been sent to you.', 'ru' => 'Спасибо! Запрос отправлен, а подтверждение отправлено на вашу электронную почту.'],
        'auto_subject' => ['et' => 'Täname päringu eest', 'en' => 'Thank you for your enquiry', 'ru' => 'Спасибо за ваш запрос'],
        'auto_body' => [
            'et' => "Tere!\n\nTäname päringu eest. Oleme päringu kätte saanud ja vastame esimesel võimalusel.\n\nLugupidamisega\nRK Meistrid OÜ\ninfo@renoveerikodu.ee\n",
            'en' => "Hello!\n\nThank you for your enquiry. We have received your request and will reply as soon as possible.\n\nKind regards\nRK Meistrid OÜ\ninfo@renoveerikodu.ee\n",
            'ru' => "Здравствуйте!\n\nСпасибо за ваш запрос. Мы его получили и ответим при первой возможности.\n\nС уважением\nRK Meistrid OÜ\ninfo@renoveerikodu.ee\n",
        ],
    ];
    return $texts[$key][rk_contact_language($language ?? ($_POST['language'] ?? 'et'))] ?? $texts[$key]['et'] ?? '';
}
