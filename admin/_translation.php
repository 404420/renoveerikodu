<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_contact-schema.php';

final class RKTranslationException extends RuntimeException
{
    public string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
        // Ainult ohutu veakood; võtmeid, sõnumeid ja teenuse vastuseid ei logita.
        parent::__construct($reason);
    }
}

function rk_translation_settings(): array
{
    $key = defined('DEEPL_API_KEY') ? (string)DEEPL_API_KEY : (string)(getenv('DEEPL_API_KEY') ?: '');
    $plan = defined('DEEPL_API_PLAN') ? (string)DEEPL_API_PLAN : (string)(getenv('DEEPL_API_PLAN') ?: 'free');
    return ['key' => trim($key), 'plan' => strtolower(trim($plan))];
}

function rk_translation_endpoint(array $settings): string
{
    $endpoints = [
        'free' => 'https://api-free.deepl.com/v2/translate',
        'pro' => 'https://api.deepl.com/v2/translate',
    ];
    if (!isset($endpoints[$settings['plan'] ?? ''])) {
        throw new RKTranslationException('configuration');
    }
    return $endpoints[$settings['plan']];
}

function rk_translation_configured(): bool
{
    $settings = rk_translation_settings();
    return $settings['key'] !== '' && strlen($settings['key']) <= 512 && !preg_match('/[\r\n]/', $settings['key'])
        && in_array($settings['plan'], ['free', 'pro'], true) && function_exists('curl_init');
}

function rk_contact_needs_translation(array $contact): bool
{
    return ($contact['language'] ?? 'et') === 'ru'
        || preg_match('/[А-Яа-яЁё]/u', (string)($contact['message'] ?? '')) === 1;
}

function rk_contact_translation_cached(array $contact): bool
{
    return trim((string)($contact['message_et'] ?? '')) !== ''
        && is_string($contact['translation_source_hash'] ?? null)
        && hash_equals(hash('sha256', (string)($contact['message'] ?? '')), $contact['translation_source_hash']);
}

function rk_deepl_transport(string $url, string $key, string $body): array
{
    if (!function_exists('curl_init')) {
        throw new RKTranslationException('unavailable');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RKTranslationException('unavailable');
    }
    $response = '';
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key, 'Content-Type: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
            if (strlen($response) + strlen($chunk) > 262144) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($ok === false) {
        throw new RKTranslationException('service');
    }
    return ['status' => $status, 'body' => $response];
}

// Transport on süstitav ainult serveri PHP koodist, et teste teha võrgu ja võtmeta.
function rk_translate_message(string $message, ?array $settings = null, ?callable $transport = null): string
{
    $settings = $settings ?? rk_translation_settings();
    $key = (string)($settings['key'] ?? '');
    if ($key === '') {
        throw new RKTranslationException('unavailable');
    }
    if (strlen($key) > 512 || preg_match('/[\r\n]/', $key)) {
        throw new RKTranslationException('configuration');
    }
    $url = rk_translation_endpoint($settings);
    if (trim($message) === '' || strlen($message) > 60000 || preg_match('//u', $message) !== 1) {
        throw new RKTranslationException('message');
    }
    // Automaatne lähtkeel sobib ka segakeelsetele sõnumitele. Eesmärk on alati eesti keel.
    $body = json_encode(['text' => [$message], 'target_lang' => 'ET'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $response = ($transport ?? 'rk_deepl_transport')($url, $key, $body);
    $status = (int)($response['status'] ?? 0);
    if ($status === 429) {
        throw new RKTranslationException('busy');
    }
    if ($status === 456) {
        throw new RKTranslationException('quota');
    }
    if ($status === 401 || $status === 403) {
        throw new RKTranslationException('configuration');
    }
    if ($status !== 200) {
        throw new RKTranslationException('service');
    }
    $data = json_decode((string)($response['body'] ?? ''), true);
    $text = $data['translations'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '' || strlen($text) > 65535 || preg_match('//u', $text) !== 1) {
        throw new RKTranslationException('service');
    }
    return $text;
}

function rk_translation_notice(string $reason): string
{
    return [
        'success' => 'Päringu eestikeelne tõlge on salvestatud. Algne sõnum säilib.',
        'cached' => 'Selle päringu eestikeelne tõlge on juba salvestatud.',
        'unavailable' => 'Automaatne tõlge pole seadistatud. Lisa serverisse DeepL API võti ja kontrolli PHP cURL laiendust.',
        'configuration' => 'Tõlketeenuse seadistus vajab kontrollimist. Kontrolli serveris DeepL API võtit ja Free/Pro paketti.',
        'busy' => 'Tõlketeenus on hõivatud. Proovi mõne aja pärast uuesti.',
        'quota' => 'DeepL tõlkelimiit on täis. Kontrolli DeepL konto kasutusmahtu.',
        'message' => 'Seda sõnumit ei saa automaatselt tõlkida. Sõnum peab olema kehtiv tekst ja kuni 60 000 baiti.',
        'missing' => 'Päringut ei leitud.',
        'ineligible' => 'See päring ei ole märgitud venekeelseks ega sisalda venekeelset teksti.',
        'changed' => 'Algne sõnum muutus tõlkimise ajal. Laadi päring uuesti ja proovi veel kord.',
        'schema' => 'Tõlke salvestusväljad puuduvad. Käivita kontaktide keele ja tõlke andmebaasimigratsioon.',
        'service' => 'Automaatne tõlge ebaõnnestus. Algne sõnum säilib. Proovi hiljem uuesti.',
    ][$reason] ?? 'Automaatne tõlge ebaõnnestus. Proovi hiljem uuesti.';
}

// Käivitaja peab enne kontrollima panel.full õigust ja CSRF tunnust.
function rk_translate_contact(PDO $db, int $id, ?callable $translator = null): string
{
    $contact = rk_sql($db, 'SELECT * FROM contacts WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$contact) {
        return 'missing';
    }
    if (rk_contact_translation_cached($contact)) {
        return 'cached';
    }
    if (!rk_contact_needs_translation($contact)) {
        return 'ineligible';
    }
    $original = (string)$contact['message'];
    $translated = ($translator ?? 'rk_translate_message')($original);
    $updated = rk_sql($db, 'UPDATE contacts SET message_et = ?, translation_source_hash = ?, translated_at = UTC_TIMESTAMP(), translation_provider = ? WHERE id = ? AND BINARY message = BINARY ?',
        [$translated, hash('sha256', $original), 'DeepL', $id, $original]);
    return $updated->rowCount() > 0 ? 'success' : 'changed';
}
