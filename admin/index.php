<?php
require __DIR__ . '/_bootstrap.php';

/* ================================================================
   EMAIL CAMPAIGN SYSTEM
   - SMTP / PHP mail transport
   - campaigns, templates, queue, test sends
   - open/click tracking, unsubscribe
   - batch worker usable from admin or cron
   ================================================================ */
function email_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        transport VARCHAR(16) NOT NULL DEFAULT 'smtp',
        smtp_host VARCHAR(190) NULL,
        smtp_port INT NOT NULL DEFAULT 587,
        smtp_encryption VARCHAR(16) NOT NULL DEFAULT 'tls',
        smtp_username VARCHAR(190) NULL,
        smtp_password TEXT NULL,
        sender_name VARCHAR(190) NOT NULL DEFAULT 'RK Meistrid OÜ',
        sender_email VARCHAR(190) NOT NULL DEFAULT 'info@renoveerikodu.ee',
        reply_to VARCHAR(190) NULL,
        batch_size INT NOT NULL DEFAULT 25,
        cron_token VARCHAR(80) NOT NULL,
        tracking_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $settingsCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_email_settings')->fetchColumn();
    if ($settingsCount === 0) {
        $token = bin2hex(random_bytes(24));
        $stmt = $pdo->prepare("INSERT INTO admin_email_settings (id,cron_token,reply_to) VALUES (1,:token,'info@renoveerikodu.ee')");
        $stmt->execute([':token'=>$token]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_campaigns (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '',
        preheader VARCHAR(255) NULL,
        html_body LONGTEXT NULL,
        text_body LONGTEXT NULL,
        sender_name VARCHAR(190) NULL,
        sender_email VARCHAR(190) NULL,
        reply_to VARCHAR(190) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        scheduled_at DATETIME NULL,
        queued_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_campaign_status (status),
        INDEX idx_campaign_scheduled (scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_recipients (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        email VARCHAR(254) NOT NULL,
        token VARCHAR(64) NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        sent_at DATETIME NULL,
        opened_at DATETIME NULL,
        clicked_at DATETIME NULL,
        unsubscribed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_campaign_email (campaign_id,email),
        UNIQUE KEY uq_recipient_token (token),
        INDEX idx_recipient_queue (campaign_id,status),
        INDEX idx_recipient_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        recipient_id BIGINT UNSIGNED NULL,
        event_type VARCHAR(24) NOT NULL,
        event_data TEXT NULL,
        ip_hash VARCHAR(64) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_campaign (campaign_id,event_type),
        INDEX idx_event_recipient (recipient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_templates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NULL,
        html_body LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ((int)$pdo->query('SELECT COUNT(*) FROM admin_email_templates')->fetchColumn() === 0) {
        $tpl = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#17202a"><h1 style="color:#087b65">RK Meistrid OÜ</h1><p>Tere!</p><p>Kirjuta siia kampaania sisu.</p><p><a href="https://renoveerikodu.ee" style="display:inline-block;background:#087b65;color:#fff;padding:12px 18px;text-decoration:none;border-radius:5px">Vaata lähemalt</a></p><p style="color:#667085;font-size:13px">RK Meistrid OÜ · renoveerikodu.ee</p></div>';
        $stmt=$pdo->prepare('INSERT INTO admin_email_templates (name,subject,html_body) VALUES (?,?,?)');
        $stmt->execute(['Lihtne uudiskiri','RK Meistrid OÜ uudised',$tpl]);
    }
}

function email_settings(PDO $pdo): array
{
    $row = $pdo->query('SELECT * FROM admin_email_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function email_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'renoveerikodu.ee');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    return $scheme . '://' . $host . $script;
}

function email_b64url_encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function email_b64url_decode(string $value): string|false
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return base64_decode($value, true);
}

function email_smtp_read($fp): string
{
    $result = '';
    while (($line = fgets($fp, 515)) !== false) {
        $result .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $result;
}
function email_smtp_expect($fp, array $codes): string
{
    $response = email_smtp_read($fp);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP: ' . trim($response));
    return $response;
}
function email_smtp_cmd($fp, string $command, array $codes): string
{
    fwrite($fp, $command . "\r\n");
    return email_smtp_expect($fp, $codes);
}
function email_encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
function email_plain_from_html(string $html): string
{
    $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
    $text = preg_replace('/<\/p\s*>/i', "\n\n", (string)$text);
    return trim(html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function email_send_message(array $settings, string $to, string $subject, string $html, string $text = ''): void
{
    $fromName = trim((string)($settings['sender_name'] ?? 'RK Meistrid OÜ'));
    $fromEmail = trim((string)($settings['sender_email'] ?? ''));
    $replyTo = trim((string)($settings['reply_to'] ?? $fromEmail));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Vigane adressaat: ' . $to);
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Saatja e-post ei ole seadistatud.');
    if ($text === '') $text = email_plain_from_html($html);

    $boundary = '=_rk_' . bin2hex(random_bytes(12));
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . email_encode_header($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/^www\./','',(string)($_SERVER['HTTP_HOST'] ?? 'renoveerikodu.ee')) . '>',
    ];
    $body = '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
          . '--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
          . '--'.$boundary."--\r\n";

    if (($settings['transport'] ?? 'smtp') === 'mail') {
        $ok = mail($to, email_encode_header($subject), $body, implode("\r\n", $headers));
        if (!$ok) throw new RuntimeException('PHP mail() ei suutnud kirja saata.');
        return;
    }

    $host = trim((string)($settings['smtp_host'] ?? ''));
    $port = (int)($settings['smtp_port'] ?? 587);
    $encryption = (string)($settings['smtp_encryption'] ?? 'tls');
    if ($host === '') throw new RuntimeException('SMTP host ei ole seadistatud.');
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) throw new RuntimeException('SMTP ühendus ebaõnnestus: ' . $errstr . ' (' . $errno . ')');
    stream_set_timeout($fp, 20);
    try {
        email_smtp_expect($fp, [220]);
        $ehlo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        email_smtp_cmd($fp, 'EHLO ' . $ehlo, [250]);
        if ($encryption === 'tls') {
            email_smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS aktiveerimine ebaõnnestus.');
            email_smtp_cmd($fp, 'EHLO ' . $ehlo, [250]);
        }
        $username = (string)($settings['smtp_username'] ?? '');
        $password = (string)($settings['smtp_password'] ?? '');
        if ($username !== '') {
            email_smtp_cmd($fp, 'AUTH LOGIN', [334]);
            email_smtp_cmd($fp, base64_encode($username), [334]);
            email_smtp_cmd($fp, base64_encode($password), [235]);
        }
        email_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        email_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250,251]);
        email_smtp_cmd($fp, 'DATA', [354]);
        $message = implode("\r\n", $headers) . "\r\nSubject: " . email_encode_header($subject) . "\r\nTo: <" . $to . ">\r\n\r\n" . $body;
        $message = preg_replace('/(?m)^\./', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        email_smtp_expect($fp, [250]);
        @email_smtp_cmd($fp, 'QUIT', [221]);
    } finally { fclose($fp); }
}

function email_track_html(PDO $pdo, array $campaign, array $recipient, array $settings): string
{
    $html = (string)($campaign['html_body'] ?? '');
    $base = email_base_url();
    $rid = (int)$recipient['id'];
    $token = (string)$recipient['token'];
    $unsubscribe = $base . '?email_public=unsubscribe&rid=' . $rid . '&token=' . rawurlencode($token);
    $html = str_replace(['{{email}}','{{unsubscribe_url}}'], [htmlspecialchars((string)$recipient['email'],ENT_QUOTES,'UTF-8'),htmlspecialchars($unsubscribe,ENT_QUOTES,'UTF-8')], $html);

    if (!empty($settings['tracking_enabled'])) {
        $html = preg_replace_callback("/href=([\"'])(https?:\/\/[^\"']+)\\1/i", function($m) use ($base,$rid,$token) {
            if (str_contains($m[2], 'email_public=unsubscribe')) return $m[0];
            $u = $base . '?email_public=click&rid=' . $rid . '&token=' . rawurlencode($token) . '&u=' . rawurlencode(email_b64url_encode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            return 'href=' . $m[1] . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . $m[1];
        }, $html) ?? $html;
        $pixel = $base . '?email_public=open&rid=' . $rid . '&token=' . rawurlencode($token);
        $html .= '<img src="' . htmlspecialchars($pixel,ENT_QUOTES,'UTF-8') . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px">';
    }
    if (!str_contains($html, 'email_public=unsubscribe')) {
        $html .= '<div style="max-width:640px;margin:24px auto 0;padding-top:14px;border-top:1px solid #e5e7eb;color:#667085;font:12px Arial,sans-serif">Saad selle kirja, sest oled liitunud RK Meistrid OÜ uudiskirjaga. <a href="'.htmlspecialchars($unsubscribe,ENT_QUOTES,'UTF-8').'">Loobu uudiskirjast</a>.</div>';
    }
    return $html;
}

function email_event(PDO $pdo, int $campaignId, ?int $recipientId, string $type, ?string $data = null): void
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $stmt=$pdo->prepare('INSERT INTO admin_email_events (campaign_id,recipient_id,event_type,event_data,ip_hash) VALUES (?,?,?,?,?)');
    $stmt->execute([$campaignId,$recipientId,$type,$data,$ip !== '' ? hash('sha256',$ip) : null]);
}

function email_process_batch(PDO $pdo, int $campaignId = 0, ?int $limit = null): array
{
    $settings = email_settings($pdo);
    $batch = max(1, min(200, $limit ?? (int)($settings['batch_size'] ?? 25)));
    $where = "c.status IN ('queued','sending') AND (c.scheduled_at IS NULL OR c.scheduled_at <= NOW())";
    $params=[];
    if ($campaignId > 0) { $where .= ' AND c.id=:cid'; $params[':cid']=$campaignId; }
    $stmt=$pdo->prepare("SELECT r.*, c.subject,c.preheader,c.html_body,c.text_body,c.sender_name AS c_sender_name,c.sender_email AS c_sender_email,c.reply_to AS c_reply_to,c.name AS campaign_name FROM admin_email_recipients r JOIN admin_email_campaigns c ON c.id=r.campaign_id WHERE $where AND r.status='pending' ORDER BY r.id ASC LIMIT $batch");
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent=0;$failed=0;
    foreach($rows as $r){
        $cid=(int)$r['campaign_id'];
        $campaign=['html_body'=>$r['html_body']];
        $sendSettings=$settings;
        if(trim((string)$r['c_sender_name'])!=='')$sendSettings['sender_name']=$r['c_sender_name'];
        if(trim((string)$r['c_sender_email'])!=='')$sendSettings['sender_email']=$r['c_sender_email'];
        if(trim((string)$r['c_reply_to'])!=='')$sendSettings['reply_to']=$r['c_reply_to'];
        try{
            $html=email_track_html($pdo,$campaign,$r,$settings);
            $text=(string)($r['text_body'] ?? '');
            if($text==='')$text=email_plain_from_html($html);
            $text=str_replace(['{{email}}'],[(string)$r['email']],$text);
            email_send_message($sendSettings,(string)$r['email'],(string)$r['subject'],$html,$text);
            $u=$pdo->prepare("UPDATE admin_email_recipients SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=NOW() WHERE id=?");$u->execute([(int)$r['id']]);
            $pdo->prepare("UPDATE admin_email_campaigns SET status='sending' WHERE id=? AND status='queued'")->execute([$cid]);
            email_event($pdo,$cid,(int)$r['id'],'sent');$sent++;
        }catch(Throwable $e){
            $u=$pdo->prepare("UPDATE admin_email_recipients SET status=IF(attempts>=2,'failed','pending'),attempts=attempts+1,last_error=? WHERE id=?");$u->execute([mb_substr($e->getMessage(),0,1000),(int)$r['id']]);
            email_event($pdo,$cid,(int)$r['id'],'failed',mb_substr($e->getMessage(),0,1000));$failed++;
        }
    }
    $campaignIds=array_values(array_unique(array_map(fn($r)=>(int)$r['campaign_id'],$rows)));
    if($campaignId>0 && !in_array($campaignId,$campaignIds,true))$campaignIds[]=$campaignId;
    foreach($campaignIds as $cid){
        $p=$pdo->prepare("SELECT COUNT(*) FROM admin_email_recipients WHERE campaign_id=? AND status='pending'");$p->execute([$cid]);
        if((int)$p->fetchColumn()===0){$pdo->prepare("UPDATE admin_email_campaigns SET status='completed',completed_at=NOW() WHERE id=? AND status IN ('queued','sending')")->execute([$cid]);}
    }
    return ['sent'=>$sent,'failed'=>$failed,'processed'=>count($rows)];
}

$pdo = admin_db();
ensure_newsletter_subscribers_table($pdo);
// Kontaktide lisaväljad emailikampaaniate CRM-vaate jaoks.
foreach ([
    'name' => "VARCHAR(190) NULL",
    'phone' => "VARCHAR(80) NULL",
    'company' => "VARCHAR(190) NULL",
    'notes' => "TEXT NULL",
    'cleaned_at' => "DATETIME NULL",
    'verification_source' => "VARCHAR(32) NULL",
    'verification_status' => "VARCHAR(64) NULL"
] as $column => $definition) {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM newsletter_subscribers LIKE " . $pdo->quote($column))->fetchAll();
        if (!$columns) {
            $pdo->exec("ALTER TABLE newsletter_subscribers ADD COLUMN `$column` $definition");
        }
    } catch (Throwable $e) {
        // Kui majutus ei luba ALTER TABLE käske, jääb ülejäänud admin siiski tööle.
    }
}
email_ensure_schema($pdo);

// Kontaktide listid / segmendid.
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_segments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    source_scope VARCHAR(24) NOT NULL DEFAULT 'all',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_segment_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_email_segment_members (
    segment_id INT UNSIGNED NOT NULL,
    subscriber_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (segment_id, subscriber_id),
    INDEX idx_segment_member_subscriber (subscriber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Public tracking / unsubscribe / cron endpoints. Need no admin session.
if (isset($_GET['email_public'])) {
    $action=(string)$_GET['email_public'];
    if ($action === 'cron') {
        $settings=email_settings($pdo);
        if (!hash_equals((string)($settings['cron_token'] ?? ''),(string)($_GET['token'] ?? ''))) { http_response_code(403); exit('Forbidden'); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(email_process_batch($pdo),JSON_UNESCAPED_UNICODE); exit;
    }
    $rid=(int)($_GET['rid'] ?? 0);$token=(string)($_GET['token'] ?? '');
    $st=$pdo->prepare('SELECT * FROM admin_email_recipients WHERE id=?');$st->execute([$rid]);$recipient=$st->fetch(PDO::FETCH_ASSOC);
    if(!$recipient || !hash_equals((string)$recipient['token'],$token)){http_response_code(404);exit('Not found');}
    $cid=(int)$recipient['campaign_id'];
    if($action==='open'){
        if(empty($recipient['opened_at'])){$pdo->prepare('UPDATE admin_email_recipients SET opened_at=NOW() WHERE id=?')->execute([$rid]);email_event($pdo,$cid,$rid,'open');}
        header('Content-Type: image/gif');header('Cache-Control: no-store, no-cache, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');exit;
    }
    if($action==='click'){
        $decoded=email_b64url_decode((string)($_GET['u'] ?? ''));
        if($decoded===false || !preg_match('#^https?://#i',$decoded)){http_response_code(400);exit('Bad URL');}
        if(empty($recipient['clicked_at']))$pdo->prepare('UPDATE admin_email_recipients SET clicked_at=NOW() WHERE id=?')->execute([$rid]);
        email_event($pdo,$cid,$rid,'click',$decoded);header('Location: '.$decoded,true,302);exit;
    }
    if($action==='unsubscribe'){
        $email=(string)$recipient['email'];
        $up=$pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed',unsubscribed_at=NOW(),updated_at=NOW() WHERE LOWER(email)=LOWER(?)");$up->execute([$email]);
        $pdo->prepare('UPDATE admin_email_recipients SET unsubscribed_at=COALESCE(unsubscribed_at,NOW()) WHERE id=?')->execute([$rid]);
        email_event($pdo,$cid,$rid,'unsubscribe');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="et"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Uudiskirjast loobumine</title><body style="font-family:Arial;background:#f5f7fb;padding:40px"><div style="max-width:560px;margin:auto;background:#fff;padding:30px;border-radius:10px"><h1 style="color:#087b65">Uudiskirjast loobumine</h1><p>E-posti aadress <strong>'.htmlspecialchars($email,ENT_QUOTES,'UTF-8').'</strong> on uudiskirjast eemaldatud.</p></div></body></html>';exit;
    }
    http_response_code(400);exit('Bad request');
}

require_admin();
ensure_admin_objects_table($pdo);

// Tööpäevade mooduli tabel. CREATE TABLE IF NOT EXISTS teeb paigalduse automaatselt.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_workdays (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        work_date DATE NOT NULL,
        worker_name VARCHAR(190) NOT NULL,
        object_id INT UNSIGNED NULL,
        object_name VARCHAR(190) NOT NULL,
        address VARCHAR(255) NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        break_minutes INT UNSIGNED NOT NULL DEFAULT 0,
        work_type VARCHAR(190) NULL,
        notes TEXT NULL,
        mileage_km DECIMAL(10,2) NOT NULL DEFAULT 0,
        hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_type VARCHAR(20) NOT NULL DEFAULT 'hourly',
        piece_quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
        piece_unit VARCHAR(30) NOT NULL DEFAULT 'm²',
        piece_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        piece_pricing_mode VARCHAR(20) NOT NULL DEFAULT 'unit',
        piece_fixed_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT 'confirmed',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_work_date (work_date),
        INDEX idx_worker_name (worker_name),
        INDEX idx_object_id (object_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Lisa uued tükitöö veerud ka olemasolevale tabelile ilma andmeid kaotamata.
$workdayColumns = $pdo->query("SHOW COLUMNS FROM admin_workdays")->fetchAll(PDO::FETCH_COLUMN);
$workdayColumnSql = [
    'payment_type' => "ALTER TABLE admin_workdays ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'hourly' AFTER hourly_rate",
    'piece_quantity' => "ALTER TABLE admin_workdays ADD COLUMN piece_quantity DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER payment_type",
    'piece_unit' => "ALTER TABLE admin_workdays ADD COLUMN piece_unit VARCHAR(30) NOT NULL DEFAULT 'm²' AFTER piece_quantity",
    'piece_rate' => "ALTER TABLE admin_workdays ADD COLUMN piece_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER piece_unit",
    'piece_pricing_mode' => "ALTER TABLE admin_workdays ADD COLUMN piece_pricing_mode VARCHAR(20) NOT NULL DEFAULT 'unit' AFTER piece_rate",
    'piece_fixed_price' => "ALTER TABLE admin_workdays ADD COLUMN piece_fixed_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER piece_pricing_mode",
];
foreach ($workdayColumnSql as $column => $sql) {
    if (!in_array($column, $workdayColumns, true)) {
        $pdo->exec($sql);
    }
}

// Tööliste register. Tabel luuakse automaatselt ning olemasolevaid andmeid ei puututa.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_workers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        contact VARCHAR(190) NULL,
        role_skills VARCHAR(255) NULL,
        experience VARCHAR(120) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'free',
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_worker_status (status),
        INDEX idx_worker_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


// Hinnakirja moodul. Hinnad on adminis muudetavad ning esmane nimekiri täidetakse automaatselt.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_price_list (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(20) NOT NULL DEFAULT 'service',
        category VARCHAR(120) NOT NULL,
        name VARCHAR(255) NOT NULL,
        unit VARCHAR(40) NULL,
        price_from DECIMAL(10,2) NOT NULL DEFAULT 0,
        material_price_from DECIMAL(10,2) NULL,
        description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_price_category (category), INDEX idx_price_type (item_type), INDEX idx_price_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$priceListCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_price_list')->fetchColumn();
if ($priceListCount === 0) {
    $priceSeed = [
        ['package','Näidispaketid','1-toalise korteri värskendus','pakett',2900,null,'Pahteldus, värvimine, liistud ja väiksemad parandused.',10],
        ['package','Näidispaketid','Vannitoa tervikremont','pakett',4900,null,'Ettevalmistus, hüdroisolatsioon, plaatimine ja lõppviimistlus.',20],
        ['package','Näidispaketid','Kogu korteri siseviimistlus','pakett',8900,null,'Kips, pahtel, värv või tapeet, põrandad ja detailid.',30],
        ['service','Siseviimistlus','Kipsplaadi paigaldus','m²',16,null,null,100],
        ['service','Siseviimistlus','Pahteldus ja lihvimine','m²',9,null,null,110],
        ['service','Siseviimistlus','Kruntimine','m²',3.5,null,null,120],
        ['service','Siseviimistlus','Värvimine, 2 kihti','m²',7.5,null,null,130],
        ['service','Plaatimistööd','Seinapinna tasandus kuni 10 mm','m²',19,29,null,200],
        ['service','Plaatimistööd','Põranda tasandus ja kallete valamine','m²',19,29,null,210],
        ['service','Plaatimistööd','Hüdroisolatsiooni paigaldus','m²',14,24,null,220],
        ['service','Plaatimistööd','Seinte ja põranda plaatimine','m²',49,59,null,230],
        ['service','Plaatimistööd','Plaaditud põrandaliist','jm',19,null,'Koos materjaliga: kokkuleppel',240],
        ['service','Plaatimistööd','Köögitausta plaatimine','komplekt',349,null,'Koos materjaliga: kokkuleppel',250],
        ['service','Põrandad ja lammutus','Laudparketi paigaldus','m²',12,null,null,300],
        ['service','Põrandad ja lammutus','Laminaatparketi paigaldus','m²',9,null,null,310],
        ['service','Põrandad ja lammutus','Põrandaliistu paigaldus','jm',5,null,null,320],
        ['service','Põrandad ja lammutus','Mittekandva seina lammutus','m²',35,null,null,330],
        ['service','Sanitaar ja vannituba','Segisti paigaldus','tk',59,null,null,400],
        ['service','Sanitaar ja vannituba','Dušisegisti paigaldus','tk',69,null,null,410],
        ['service','Sanitaar ja vannituba','Valamu paigaldus','tk',59,null,null,420],
        ['service','Sanitaar ja vannituba','WC poti paigaldus','tk',79,null,null,430],
        ['service','Sanitaar ja vannituba','Dušinurga paigaldus','tk',139,null,null,440],
        ['service','Fassaad ja välistööd','Fassaadi pesu või puhastus','m²',6,null,null,500],
        ['service','Fassaad ja välistööd','Fassaadi krunt ja värv','m²',14,null,null,510],
        ['service','Fassaad ja välistööd','Puitfassaadi paigaldus','m²',39,null,null,520],
        ['service','Fassaad ja välistööd','Krohvfassaadi süsteem','m²',49,null,null,530],
    ];
    $seedStmt=$pdo->prepare('INSERT INTO admin_price_list (item_type,category,name,unit,price_from,material_price_from,description,sort_order) VALUES (?,?,?,?,?,?,?,?)');
    foreach($priceSeed as $priceRow){$seedStmt->execute($priceRow);}
}

// Varasemate hinnapakkumiste põhjal lisatud praktilised hinnad.
// Lisame ainult puuduvad read, et adminis käsitsi muudetud hindu mitte üle kirjutada.
$historyPriceSeed = [
    ['service','Fassaad ja välistööd','Fassaadi pesu (varasem pakkumine)','m²',3.60,null,'Varasemates pakkumistes kasutatud tööraha näidishind.',600],
    ['service','Fassaad ja välistööd','Katuse pesu samblast','m²',3.00,null,'Varasem pakkumine.',610],
    ['service','Fassaad ja välistööd','Samblatõrje','m²',1.50,null,'Varasem pakkumine.',620],
    ['service','Fassaad ja välistööd','Puitfassaadi vana lahtise värvi eemaldus','m²',5.00,null,'Varasem pakkumine.',630],
    ['service','Fassaad ja välistööd','Puitfassaadi kruntimine','m²',7.00,null,'Varasem pakkumine.',640],
    ['service','Fassaad ja välistööd','Puitfassaadi värvimine 2x','m²',14.00,null,'Varasem pakkumine.',650],
    ['service','Fassaad ja välistööd','Pindade ettevalmistus / puhastus enne värvimist','m²',3.00,null,'Varasem pakkumine.',660],
    ['service','Fassaad ja välistööd','Laudise värvimine 2x','m²',15.00,null,'Varasem pakkumine.',670],
    ['service','Fassaad ja välistööd','Tuulekasti värvimine 2x','m²',15.00,null,'Varasem pakkumine.',680],
    ['service','Fassaad ja välistööd','Katkise laudise väljavahetamine','komplekt',150.00,null,'Varasemas pakkumises sisaldas materjali ja tööd.',690],
    ['service','Fassaad ja välistööd','Sokli värvimine 2x','komplekt',600.00,null,'Varasem pakkumine; tegelik hind sõltub mahust.',700],
    ['service','Fassaad ja välistööd','Katmistööd','komplekt',150.00,null,'Varasem pakkumine.',710],
    ['service','Logistika ja lisatööd','Tellingu transport objektile ja tagasi','komplekt',100.00,null,'Varasem pakkumine.',720],
    ['service','Logistika ja lisatööd','Ehitusmaterjalide transport','komplekt',100.00,null,'Varasemates pakkumistes 100–200 € sõltuvalt objektist.',730],
    ['service','Logistika ja lisatööd','Ehitusprügi utiliseerimine','komplekt',100.00,null,'Varasemates pakkumistes 80–250 € sõltuvalt mahust.',740],
    ['service','Logistika ja lisatööd','Lisatöö tunnitasu','h',25.00,null,'Varasema pakkumise märkuses kasutatud lisatöö tunnitasu.',750],

    ['service','Siseviimistlus','Tapeedi eemaldamine','m²',5.00,null,'Varasem pakkumine.',800],
    ['service','Siseviimistlus','Lauspahteldus 2x','m²',14.00,null,'Varasemates pakkumistes 14–15 €/m².',810],
    ['service','Siseviimistlus','Lauspahteldus 1x','m²',25.00,null,'Varasema pakkumise hind; sisaldas pahtli maksumust.',820],
    ['service','Siseviimistlus','Kruntimine 1x (varasem pakkumine)','m²',4.50,null,'Varasemates pakkumistes 4,50–5,00 €/m².',830],
    ['service','Siseviimistlus','Värvimine 2x (varasem pakkumine)','m²',9.00,null,'Varasemates pakkumistes 9–10 €/m².',840],
    ['service','Siseviimistlus','Põranda katmine / kaitsmine','m²',3.00,null,'Varasem pakkumine.',850],
    ['service','Siseviimistlus','Aknapalede viimistlus','jm',20.00,null,'Varasem pakkumine.',860],
    ['service','Siseviimistlus','Sisemise aknalaua paigaldus / viimistlus','jm',26.00,null,'Varasem pakkumine.',870],
    ['service','Siseviimistlus','Kipsplaatide vuukide täitmine ja vuugilindi paigaldus','jm',3.00,null,'Varasem pakkumine.',880],
    ['service','Siseviimistlus','Vaheseinte ehitus vastavalt projektile','m²',28.00,null,'Varasem suure objekti pakkumine; karkass + kips + vill.',890],
    ['service','Siseviimistlus','Sirge lae ehitus','m²',32.00,null,'Varasem pakkumine.',900],
    ['service','Siseviimistlus','Kaldlae / kõrge elutoa laepinna ehitus','m²',36.00,null,'Varasem pakkumine.',910],
    ['service','Siseviimistlus','Lavatsi / trepi aluse lae ehitus','m²',32.00,null,'Varasem pakkumine.',920],
    ['service','Siseviimistlus','Siseukse paigaldus','tk',85.00,null,'Varasem pakkumine.',930],
    ['service','Siseviimistlus','Ukse piirdeliistude paigaldus','jm',9.00,null,'Varasem pakkumine.',940],

    ['service','Põrandad ja lammutus','Põranda katmine (komplekt)','komplekt',40.00,null,'Varasem pakkumine; väikese objekti katmistöö.',1000],
    ['service','Põrandad ja lammutus','Armatuuri paigaldus','m²',17.00,null,'Varasem pakkumine.',1010],
    ['service','Põrandad ja lammutus','Betooni valamine','m²',30.00,null,'Varasem pakkumine; pumba vajadus võib hinda muuta.',1020],
    ['service','Põrandad ja lammutus','Ettevalmistustööd enne betooni / armatuuri','m²',15.00,null,'Varasem pakkumine.',1030],
    ['service','Põrandad ja lammutus','Kuivade ruumide põrandakatte paigaldus','m²',14.50,null,'Varasem pakkumine.',1040],

    ['service','Sanitaar ja vannituba','Valamu ja kapi eemaldus','komplekt',50.00,null,'Varasem vannitoa pakkumine.',1100],
    ['service','Sanitaar ja vannituba','WC poti eemaldus','komplekt',40.00,null,'Varasem vannitoa pakkumine.',1110],
    ['service','Sanitaar ja vannituba','Vanade plaatide eemaldus','komplekt',250.00,null,'Varasem vannitoa pakkumine.',1120],
    ['service','Sanitaar ja vannituba','Klaasseina / klaasukse lammutus','komplekt',35.00,null,'Varasem vannitoa pakkumine.',1130],
    ['service','Sanitaar ja vannituba','Ripplae lammutus','komplekt',100.00,null,'Varasem vannitoa pakkumine.',1140],
    ['service','Sanitaar ja vannituba','Dušinurga lokaalse kalde / valu eemaldus','komplekt',150.00,null,'Varasem vannitoa pakkumine.',1150],
    ['service','Sanitaar ja vannituba','Vana põrandasoojustuse eemaldus','komplekt',250.00,null,'Varasem vannitoa pakkumine.',1160],
    ['service','Sanitaar ja vannituba','Põrandavalu enne uue põrandasoojustuse paigaldust','komplekt',120.00,null,'Varasem vannitoa pakkumine.',1170],
    ['service','Sanitaar ja vannituba','Kips-karkass seinad','m²',50.00,null,'Varasem vannitoa pakkumine.',1180],
    ['service','Sanitaar ja vannituba','Uue dušitrapi paigaldus','komplekt',150.00,null,'Varasem vannitoa pakkumine.',1190],
    ['service','Sanitaar ja vannituba','Dušinurga kallete valamine','komplekt',200.00,null,'Varasem vannitoa pakkumine.',1200],
    ['service','Sanitaar ja vannituba','Hüdroisolatsiooni paigaldus (varasem pakkumine)','m²',25.00,null,'Varasemates pakkumistes kasutatud hind.',1210],
    ['service','Sanitaar ja vannituba','Vannitoa plaatimine','m²',85.00,null,'Varasem vannitoa pakkumine.',1220],
    ['service','Sanitaar ja vannituba','Vuukimine','m²',10.00,null,'Varasem suure objekti pakkumine.',1230],
    ['service','Sanitaar ja vannituba','Silikoonimine ja vuukimine','komplekt',400.00,null,'Varasem vannitoa pakkumine.',1240],
    ['service','Sanitaar ja vannituba','Silikoonimine','komplekt',180.00,null,'Varasem suure objekti pakkumine.',1250],
    ['service','Sanitaar ja vannituba','Ripplae ehitus vannitoas','komplekt',240.00,null,'Varasem vannitoa pakkumine.',1260],
    ['service','Sanitaar ja vannituba','Uue valgusti paigaldus','tk',15.00,null,'Varasem vannitoa pakkumine.',1270],
    ['service','Sanitaar ja vannituba','Ventilaatori paigaldus','tk',25.00,null,'Varasem vannitoa pakkumine.',1280],
    ['service','Sanitaar ja vannituba','Valamu ja kapi paigaldus','komplekt',120.00,null,'Varasem vannitoa pakkumine.',1290],
    ['service','Sanitaar ja vannituba','Dušisegisti paigaldus (varasem pakkumine)','tk',100.00,null,'Varasemates pakkumistes kasutatud hind.',1300],
    ['service','Sanitaar ja vannituba','Dušinurga seinte paigaldus','komplekt',165.00,null,'Varasem vannitoa pakkumine.',1310],
    ['service','Sanitaar ja vannituba','Torutööd / äravoolutorustiku paigaldus','komplekt',1100.00,null,'Varasem suure objekti pakkumine.',1320],
    ['service','Sanitaar ja vannituba','Märgruumide kruntimine','m²',3.00,null,'Varasem suure objekti pakkumine.',1330],
    ['service','Sanitaar ja vannituba','Märgruumide hüdroisolatsioon','m²',12.00,null,'Varasem suure objekti pakkumine.',1340],
    ['service','Sanitaar ja vannituba','Märgruumide plaatimine','m²',75.00,null,'Varasem suure objekti pakkumine.',1350],

    ['service','Elekter','Elektritööd (väiksem komplekt)','komplekt',160.00,null,'Varasem vannitoa pakkumine.',1400],
    ['service','Elekter','Valgustite paigaldus + kaabli vedamine (2 valgustit)','komplekt',80.00,null,'Varasem pakkumine.',1410],
];
$historyExistsStmt = $pdo->prepare('SELECT id FROM admin_price_list WHERE category = ? AND name = ? LIMIT 1');
$historyInsertStmt = $pdo->prepare('INSERT INTO admin_price_list (item_type,category,name,unit,price_from,material_price_from,description,sort_order) VALUES (?,?,?,?,?,?,?,?)');
foreach ($historyPriceSeed as $priceRow) {
    $historyExistsStmt->execute([$priceRow[1], $priceRow[2]]);
    if (!$historyExistsStmt->fetchColumn()) {
        $historyInsertStmt->execute($priceRow);
    }
}

// Hinnapakkumiste mustandid. Iga päring võib omada ühte aktiivset hinnapakkumist.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_quotes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_id INT UNSIGNED NOT NULL,
        quote_number VARCHAR(60) NOT NULL,
        title VARCHAR(255) NOT NULL,
        client_name VARCHAR(190) NULL,
        client_email VARCHAR(190) NULL,
        client_phone VARCHAR(120) NULL,
        object_address VARCHAR(255) NULL,
        valid_days INT UNSIGNED NOT NULL DEFAULT 14,
        work_time VARCHAR(120) NOT NULL DEFAULT 'Kokkuleppel',
        offer_type VARCHAR(190) NOT NULL DEFAULT 'Esialgne, päringu põhjal',
        duration VARCHAR(120) NULL,
        scope_text TEXT NULL,
        work_items_json LONGTEXT NULL,
        material_items_json LONGTEXT NULL,
        terms_text LONGTEXT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_quote_request (request_id),
        INDEX idx_quote_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Eraldi saatmise staatus hinnapakkumiste nimekirja jaoks.
$quoteSendStatusColumn = $pdo->query("SHOW COLUMNS FROM admin_quotes LIKE 'send_status'")->fetch();
if (!$quoteSendStatusColumn) {
    $pdo->exec("ALTER TABLE admin_quotes ADD COLUMN send_status VARCHAR(16) NOT NULL DEFAULT 'unsent' AFTER status, ADD INDEX idx_quote_send_status (send_status)");
}

// Käsitsi koostatud hinnapakkumine ei pea olema seotud päringuga.
$quoteRequestColumn = $pdo->query("SHOW COLUMNS FROM admin_quotes LIKE 'request_id'")->fetch();
if ($quoteRequestColumn && strtoupper((string)($quoteRequestColumn['Null'] ?? 'NO')) !== 'YES') {
    $pdo->exec("ALTER TABLE admin_quotes MODIFY request_id INT UNSIGNED NULL");
}

function quote_decimal($value): float
{
    return max(0, (float) str_replace(',', '.', trim((string) $value)));
}

// Annab käsitsi loodud nimeta hinnapakkumisele unikaalse nime: Mustand, Mustand_2, Mustand_3 ...
function quote_next_draft_name(PDO $pdo, int $excludeId = 0): string
{
    $sql = "SELECT client_name FROM admin_quotes WHERE client_name REGEXP '^Mustand(_[0-9]+)?$'";
    $params = [];
    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $used = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $name = (string) $name;
        if ($name === 'Mustand') {
            $used[1] = true;
        } elseif (preg_match('/^Mustand_(\d+)$/', $name, $match)) {
            $used[(int) $match[1]] = true;
        }
    }

    $number = 1;
    while (isset($used[$number])) {
        $number++;
    }

    return $number === 1 ? 'Mustand' : 'Mustand_' . $number;
}

function quote_detect_area(string $text): float
{
    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:m²|m2|ruutmeet)/ui', $text, $m)) {
        return quote_decimal($m[1]);
    }
    return 0.0;
}

function quote_auto_work_items(string $message): array
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
    $area = quote_detect_area($message);
    $q = $area > 0 ? $area : 0;
    $items = [];
    $add = static function(string $description, float $qty, string $unit, float $rate) use (&$items): void {
        foreach ($items as $item) if ($item['description'] === $description) return;
        $items[] = ['description'=>$description,'qty'=>$qty,'unit'=>$unit,'rate'=>$rate];
    };

    // Hinnad lähtuvad kasutaja saadetud RK Meistrite näidispakkumiste tööraha tabelitest.
    if (str_contains($text, 'laminaat')) {
        $add('Vana laminaadi ja põrandaliistude eemaldamine', $q, 'm²', 5.00);
        $add('Aluspõranda korrastamine ujuva laminaadi paigalduseks', $q, 'm²', 25.00);
        $add('Ujuva laminaadi paigaldus alusvaibale', $q, 'm²', 13.00);
    }
    if (str_contains($text, 'liist')) $add('Põrandaliistude paigaldus', 0, 'jm', 9.00);
    if (str_contains($text, 'tapeet')) $add('Vana tapeedi eemaldamine ning tapeediliimi jääkide puhastamine', $q, 'm²', 5.00);
    if (str_contains($text, 'paht')) $add('Seinte lauspahteldus 2 kihti ja lihvimine', $q, 'm²', 14.00);
    if (str_contains($text, 'värv') && !str_contains($text, 'fassaad')) {
        $add('Seinte kruntimine enne värvimist', $q, 'm²', 4.00);
        $add('Seinte värvimine 2 kihti', $q, 'm²', 8.00);
    }
    if (str_contains($text, 'fassaad') || str_contains($text, 'survepes') || str_contains($text, 'fassaadi pes')) {
        $add('Ettevalmistus, tööala tähistus ja pindade kaitsmine', 1, 'kompl', 350.00);
        $add('Survepesu: krohvitud fassaad ja sokkel', $q, 'm²', 3.50);
        $add('Vana värvikihi nakkekatse, lahtiste osade eemaldus ja ettevalmistus', $q, 'm²', 2.50);
        $add('Nakkekrundi paigaldus', $q, 'm²', 5.00);
        $add('Fassaadivärvi paigaldus 2 kihti', $q, 'm²', 12.00);
    }
    if (!$items) $items[] = ['description'=>'Tööd vastavalt kliendi päringule','qty'=>1,'unit'=>'kompl','rate'=>0];
    return $items;
}

function quote_default_terms(): string
{
    return "• Lõplikud mahud kontrollitakse enne tööde alustamist objektil.
"
        . "• Varjatud kahjustused ja tavapärasest mahukamad lisatööd kooskõlastatakse tellijaga enne teostamist.
"
        . "• Tellija tagab tööalale vaba ligipääsu ning vee ja elektri kasutamise võimaluse, kui töö seda vajab.
"
        . "• Materjalide lõplik valik ja toonid kooskõlastatakse enne tellimist.
"
        . "• Teostatud töödele kehtib 2-aastane garantii; materjalidele tootja tingimused.
"
        . "• Pakkumise koostamise ajal käibemaksu ei lisandu. Kui arveldamise ajaks tekib käibemaksukohustus, lisandub kehtiv käibemaks.";
}

function workday_hours(string $start, string $end, int $breakMinutes = 0): float
{
    $startTs = strtotime('2000-01-01 ' . $start);
    $endTs = strtotime('2000-01-01 ' . $end);
    if ($startTs === false || $endTs === false) return 0.0;
    if ($endTs < $startTs) $endTs += 86400; // öövahetus
    return max(0, (($endTs - $startTs) / 3600) - (max(0, $breakMinutes) / 60));
}


$emailFlash = '';
$emailError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    $current=email_settings($pdo);
    $password=(string)($_POST['smtp_password'] ?? '');
    if($password==='')$password=(string)($current['smtp_password'] ?? '');
    $stmt=$pdo->prepare("UPDATE admin_email_settings SET transport=?,smtp_host=?,smtp_port=?,smtp_encryption=?,smtp_username=?,smtp_password=?,sender_name=?,sender_email=?,reply_to=?,batch_size=?,tracking_enabled=? WHERE id=1");
    $stmt->execute([
        in_array((string)($_POST['transport']??'smtp'),['smtp','mail'],true)?(string)$_POST['transport']:'smtp',
        trim((string)($_POST['smtp_host']??'')),max(1,(int)($_POST['smtp_port']??587)),
        in_array((string)($_POST['smtp_encryption']??'tls'),['tls','ssl','none'],true)?(string)$_POST['smtp_encryption']:'tls',
        trim((string)($_POST['smtp_username']??'')),$password,trim((string)($_POST['sender_name']??'')),trim((string)($_POST['sender_email']??'')),trim((string)($_POST['reply_to']??'')),
        max(1,min(200,(int)($_POST['batch_size']??25))),isset($_POST['tracking_enabled'])?1:0
    ]);
    header('Location: index.php?view=email-settings&saved=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_campaign'])) {
    $id=(int)($_POST['campaign_id']??0);
    $name=trim((string)($_POST['name']??''));$subject=trim((string)($_POST['subject']??''));
    if($name==='')$name='Kampaania '.date('d.m.Y H:i');
    $values=[
        ':name'=>$name,':subject'=>$subject,':preheader'=>trim((string)($_POST['preheader']??'')),':html_body'=>(string)($_POST['html_body']??''),':text_body'=>(string)($_POST['text_body']??''),
        ':sender_name'=>trim((string)($_POST['sender_name']??'')),':sender_email'=>trim((string)($_POST['sender_email']??'')),':reply_to'=>trim((string)($_POST['reply_to']??'')),
        ':scheduled_at'=>trim((string)($_POST['scheduled_at']??''))!==''?str_replace('T',' ',trim((string)$_POST['scheduled_at'])).':00':null
    ];
    if($id>0){$values[':id']=$id;$stmt=$pdo->prepare('UPDATE admin_email_campaigns SET name=:name,subject=:subject,preheader=:preheader,html_body=:html_body,text_body=:text_body,sender_name=:sender_name,sender_email=:sender_email,reply_to=:reply_to,scheduled_at=:scheduled_at WHERE id=:id');$stmt->execute($values);}
    else{$stmt=$pdo->prepare('INSERT INTO admin_email_campaigns (name,subject,preheader,html_body,text_body,sender_name,sender_email,reply_to,scheduled_at) VALUES (:name,:subject,:preheader,:html_body,:text_body,:sender_name,:sender_email,:reply_to,:scheduled_at)');$stmt->execute($values);$id=(int)$pdo->lastInsertId();}
    header('Location: index.php?view=email-campaign&campaign_id='.$id.'&saved=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email_campaign'])) {
    $id=(int)($_POST['campaign_id']??0);
    if($id>0){$pdo->beginTransaction();try{$pdo->prepare('DELETE FROM admin_email_events WHERE campaign_id=?')->execute([$id]);$pdo->prepare('DELETE FROM admin_email_recipients WHERE campaign_id=?')->execute([$id]);$pdo->prepare('DELETE FROM admin_email_campaigns WHERE id=?')->execute([$id]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}}
    header('Location: index.php?view=email-campaigns&deleted=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['queue_email_campaign'])) {
    $id=(int)($_POST['campaign_id']??0);
    $c=$pdo->prepare('SELECT * FROM admin_email_campaigns WHERE id=?');$c->execute([$id]);$campaign=$c->fetch(PDO::FETCH_ASSOC);
    if(!$campaign || trim((string)$campaign['subject'])==='' || trim((string)$campaign['html_body'])===''){header('Location:index.php?view=email-campaign&campaign_id='.$id.'&queue_error=1');exit;}
    $emails=$pdo->query("SELECT DISTINCT LOWER(TRIM(email)) email FROM newsletter_subscribers WHERE status='subscribed' AND email IS NOT NULL AND email<>''")->fetchAll(PDO::FETCH_COLUMN);
    $ins=$pdo->prepare("INSERT IGNORE INTO admin_email_recipients (campaign_id,email,token,status) VALUES (?,?,?,'pending')");
    foreach($emails as $email){if(filter_var($email,FILTER_VALIDATE_EMAIL))$ins->execute([$id,$email,bin2hex(random_bytes(24))]);}
    $pdo->prepare("UPDATE admin_email_campaigns SET status='queued',queued_at=NOW(),completed_at=NULL WHERE id=?")->execute([$id]);
    header('Location:index.php?view=email-campaign&campaign_id='.$id.'&queued=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pause_email_campaign'])) {
    $id=(int)($_POST['campaign_id']??0);$pdo->prepare("UPDATE admin_email_campaigns SET status='paused' WHERE id=?")->execute([$id]);header('Location:index.php?view=email-campaign&campaign_id='.$id.'&paused=1');exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resume_email_campaign'])) {
    $id=(int)($_POST['campaign_id']??0);$pdo->prepare("UPDATE admin_email_campaigns SET status='queued' WHERE id=?")->execute([$id]);header('Location:index.php?view=email-campaign&campaign_id='.$id.'&queued=1');exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email_batch'])) {
    $id=(int)($_POST['campaign_id']??0);
    try{$r=email_process_batch($pdo,$id);header('Location:index.php?view=email-campaign&campaign_id='.$id.'&batch_sent='.$r['sent'].'&batch_failed='.$r['failed']);}catch(Throwable $e){header('Location:index.php?view=email-campaign&campaign_id='.$id.'&send_error='.rawurlencode($e->getMessage()));}exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    $id=(int)($_POST['campaign_id']??0);$to=trim((string)($_POST['test_email']??''));
    $c=$pdo->prepare('SELECT * FROM admin_email_campaigns WHERE id=?');$c->execute([$id]);$campaign=$c->fetch(PDO::FETCH_ASSOC);
    try{if(!$campaign)throw new RuntimeException('Kampaaniat ei leitud.');$settings=email_settings($pdo);foreach(['sender_name','sender_email','reply_to'] as $k){if(trim((string)($campaign[$k]??''))!=='')$settings[$k]=$campaign[$k];}email_send_message($settings,$to,'TEST: '.(string)$campaign['subject'],(string)$campaign['html_body'],(string)$campaign['text_body']);header('Location:index.php?view=email-campaign&campaign_id='.$id.'&test_sent=1');}catch(Throwable $e){header('Location:index.php?view=email-campaign&campaign_id='.$id.'&test_error='.rawurlencode($e->getMessage()));}exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_template'])) {
    $id=(int)($_POST['template_id']??0);$name=trim((string)($_POST['name']??''));if($name==='')$name='Uus mall';
    if($id>0){$st=$pdo->prepare('UPDATE admin_email_templates SET name=?,subject=?,html_body=? WHERE id=?');$st->execute([$name,(string)($_POST['subject']??''),(string)($_POST['html_body']??''),$id]);}
    else{$st=$pdo->prepare('INSERT INTO admin_email_templates (name,subject,html_body) VALUES (?,?,?)');$st->execute([$name,(string)($_POST['subject']??''),(string)($_POST['html_body']??'')]);}
    header('Location:index.php?view=email-templates&saved=1');exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email_template'])) {$id=(int)($_POST['template_id']??0);if($id>0)$pdo->prepare('DELETE FROM admin_email_templates WHERE id=?')->execute([$id]);header('Location:index.php?view=email-templates&deleted=1');exit;}


function email_contact_normalize_header(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(['ä','ö','ü','õ','š','ž'], ['a','o','u','o','s','z'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
    return $value;
}

function email_contact_header_key(string $value): ?string
{
    $h = email_contact_normalize_header($value);
    $map = [
        'nimi'=>'name','name'=>'name','fullname'=>'name','taisnimi'=>'name','kontaktisik'=>'name',
        'email'=>'email','epost'=>'email','epostiaadress'=>'email','mail'=>'email','emailaddress'=>'email',
        'telefon'=>'phone','phone'=>'phone','tel'=>'phone','mobiil'=>'phone','mobile'=>'phone',
        'ettevote'=>'company','company'=>'company','firma'=>'company','organisation'=>'company','organization'=>'company',
        'markused'=>'notes','markus'=>'notes','notes'=>'notes','comment'=>'notes','kommentaar'=>'notes',
        'staatus'=>'status','status'=>'status'
    ];
    return $map[$h] ?? null;
}

function email_contact_parse_status(string $value): string
{
    $v = mb_strtolower(trim($value), 'UTF-8');
    if (in_array($v, ['unsubscribed','loobunud','loobuja','inactive','ei','0'], true)) return 'unsubscribed';
    return 'subscribed';
}

function email_contact_rows_from_delimited(string $raw, ?string $delimiter = null): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $lines = preg_split('/\R/u', trim($raw)) ?: [];
    if (!$lines) return [];
    if ($delimiter === null) {
        $sample = implode("\n", array_slice($lines, 0, 5));
        $candidates = ["\t", ';', ',', '|'];
        $delimiter = "\t";
        $best = -1;
        foreach ($candidates as $d) {
            $score = substr_count($sample, $d);
            if ($score > $best) { $best = $score; $delimiter = $d; }
        }
    }
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $cells = str_getcsv($line, $delimiter);
        $rows[] = array_map(static fn($v) => trim((string)$v), $cells);
    }
    return $rows;
}

function email_contact_rows_from_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('Serveris puudub PHP ZipArchive laiendus, mida .xlsx import vajab.');
    if (!function_exists('simplexml_load_string')) throw new RuntimeException('Serveris puudub PHP SimpleXML laiendus, mida .xlsx import vajab.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('XLSX faili ei õnnestunud avada.');
    try {
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml) {
                $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($xml->xpath('//x:si') ?: [] as $si) {
                    $parts = [];
                    foreach ($si->xpath('.//x:t') ?: [] as $t) $parts[] = (string)$t;
                    $shared[] = implode('', $parts);
                }
            }
        }
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if ($workbookXml !== false && $relsXml !== false) {
            $wb = simplexml_load_string($workbookXml); $rels = simplexml_load_string($relsXml);
            if ($wb && $rels) {
                $wb->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $wb->registerXPathNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $first = ($wb->xpath('//x:sheets/x:sheet') ?: [])[0] ?? null;
                if ($first) {
                    $attrs = $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $rid = (string)($attrs['id'] ?? '');
                    $rels->registerXPathNamespace('r','http://schemas.openxmlformats.org/package/2006/relationships');
                    foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
                        if ((string)$rel['Id'] === $rid) {
                            $target = ltrim((string)$rel['Target'], '/');
                            $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . preg_replace('#^\.\./#', '', $target);
                            break;
                        }
                    }
                }
            }
        }
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) throw new RuntimeException('XLSX esimese töölehe andmeid ei leitud.');
        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet) throw new RuntimeException('XLSX töölehte ei õnnestunud lugeda.');
        $sheet->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $row) {
            $out = [];
            foreach ($row->xpath('./x:c') ?: [] as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $letters = $m[1] ?? 'A'; $idx = 0;
                for ($i=0; $i<strlen($letters); $i++) $idx = $idx*26 + (ord($letters[$i])-64);
                $idx--;
                $type = (string)$cell['t'];
                $value = '';
                if ($type === 'inlineStr') {
                    $parts=[]; foreach ($cell->xpath('.//x:t') ?: [] as $t) $parts[]=(string)$t; $value=implode('',$parts);
                } else {
                    $v = ($cell->xpath('./x:v') ?: [])[0] ?? null;
                    $raw = $v ? (string)$v : '';
                    $value = $type === 's' ? ($shared[(int)$raw] ?? '') : $raw;
                }
                $out[$idx] = trim($value);
            }
            if ($out) {
                $max=max(array_keys($out)); $normalized=[];
                for($i=0;$i<=$max;$i++) $normalized[]=$out[$i]??'';
                $rows[]=$normalized;
            }
        }
        return $rows;
    } finally { $zip->close(); }
}

function email_contact_map_rows(array $rows): array
{
    if (!$rows) return [];
    $headerMap = [];
    foreach ($rows[0] as $i => $cell) { $key=email_contact_header_key((string)$cell); if($key!==null && !isset($headerMap[$key])) $headerMap[$key]=$i; }
    $hasHeader = isset($headerMap['email']);
    $dataRows = $hasHeader ? array_slice($rows,1) : $rows;
    $result=[];
    foreach($dataRows as $row){
        if(!array_filter($row, static fn($v)=>trim((string)$v)!=='')) continue;
        $item=['name'=>'','email'=>'','phone'=>'','company'=>'','notes'=>'','status'=>'subscribed'];
        if($hasHeader){ foreach($headerMap as $key=>$i){$item[$key]=trim((string)($row[$i]??''));} }
        else {
            $emailIndex=null; foreach($row as $i=>$cell){if(filter_var(trim((string)$cell),FILTER_VALIDATE_EMAIL)){$emailIndex=$i;break;}}
            if($emailIndex===null) continue;
            $item['email']=trim((string)$row[$emailIndex]);
            $others=[]; foreach($row as $i=>$cell){if($i!==$emailIndex && trim((string)$cell)!=='')$others[]=trim((string)$cell);}
            $item['name']=$others[0]??''; $item['phone']=$others[1]??''; $item['company']=$others[2]??''; $item['notes']=$others[3]??'';
        }
        if(isset($item['status'])) $item['status']=email_contact_parse_status((string)$item['status']);
        if(filter_var($item['email'],FILTER_VALIDATE_EMAIL)) $result[]=$item;
    }
    return $result;
}

function email_contact_import(PDO $pdo, array $items, bool $reactivate = false): array
{
    $stats=['added'=>0,'updated'=>0,'skipped'=>0,'invalid'=>0];
    $find=$pdo->prepare('SELECT id,status FROM newsletter_subscribers WHERE LOWER(email)=LOWER(?) LIMIT 1');
    $insert=$pdo->prepare("INSERT INTO newsletter_subscribers (email,name,phone,company,notes,status,subscribed_at,unsubscribed_at,source,created_at,updated_at) VALUES (?,?,?,?,?,'subscribed',NOW(),NULL,'admin-import',NOW(),NOW())");
    $update=$pdo->prepare("UPDATE newsletter_subscribers SET name=COALESCE(NULLIF(?,''),name),phone=COALESCE(NULLIF(?,''),phone),company=COALESCE(NULLIF(?,''),company),notes=COALESCE(NULLIF(?,''),notes),updated_at=NOW() WHERE id=?");
    $react=$pdo->prepare("UPDATE newsletter_subscribers SET status='subscribed',subscribed_at=COALESCE(subscribed_at,NOW()),unsubscribed_at=NULL,updated_at=NOW() WHERE id=?");
    $seen=[];
    foreach($items as $item){
        $email=mb_strtolower(trim((string)($item['email']??'')),'UTF-8');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$stats['invalid']++;continue;}
        if(isset($seen[$email])){$stats['skipped']++;continue;} $seen[$email]=true;
        $find->execute([$email]); $existing=$find->fetch(PDO::FETCH_ASSOC);
        if($existing){
            $update->execute([(string)($item['name']??''),(string)($item['phone']??''),(string)($item['company']??''),(string)($item['notes']??''),(int)$existing['id']]);
            if($reactivate && $existing['status']==='unsubscribed') $react->execute([(int)$existing['id']]);
            $stats['updated']++;
        } else {
            $insert->execute([$email,trim((string)($item['name']??''))?:null,trim((string)($item['phone']??''))?:null,trim((string)($item['company']??''))?:null,trim((string)($item['notes']??''))?:null]);
            $stats['added']++;
        }
    }
    return $stats;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_email_contacts'])) {
    try {
        $mode=(string)($_POST['import_mode']??'paste'); $rows=[];
        if($mode==='paste'){
            $raw=(string)($_POST['contact_paste']??'');
            if(trim($raw)==='') throw new RuntimeException('Kleebi vähemalt üks kontakt.');
            $rows=email_contact_rows_from_delimited($raw);
            // If pasted content is a plain email list, preserve it too.
            if(count($rows)===1 && count($rows[0])===1 && preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',$raw,$matches)) $rows=array_map(static fn($e)=>[$e],$matches[0]);
        } else {
            if(empty($_FILES['contact_file']) || (int)($_FILES['contact_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Vali CSV või XLSX fail.');
            $file=$_FILES['contact_file']; if((int)$file['size']>8*1024*1024) throw new RuntimeException('Fail on liiga suur. Maksimum on 8 MB.');
            $ext=mb_strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION),'UTF-8');
            if($ext==='csv'){$raw=file_get_contents((string)$file['tmp_name']);if($raw===false)throw new RuntimeException('CSV faili ei õnnestunud lugeda.');$rows=email_contact_rows_from_delimited($raw);}
            elseif($ext==='xlsx'){$rows=email_contact_rows_from_xlsx((string)$file['tmp_name']);}
            else throw new RuntimeException('Lubatud failiformaadid on .csv ja .xlsx.');
        }
        $items=email_contact_map_rows($rows); if(!$items) throw new RuntimeException('Ühtegi korrektse e-posti aadressiga kontakti ei leitud.');
        $stats=email_contact_import($pdo,$items,!empty($_POST['reactivate_unsubscribed']));
        $query=http_build_query(['view'=>'email-contacts','imported'=>1]+$stats); header('Location:index.php?'.$query);exit;
    } catch(Throwable $e){header('Location:index.php?view=email-contacts&import_error='.rawurlencode($e->getMessage()));exit;}
}


function email_cleaned_map_rows(array $rows): array
{
    if (!$rows) return [];
    $headers=[];
    foreach($rows[0] as $i=>$cell){
        $h=mb_strtolower(trim((string)$cell),'UTF-8');
        $h=preg_replace('/[^a-z0-9õäöü]+/u','_',$h);
        if(in_array($h,['email','e_mail','email_address','emailaddress','address'],true)) $headers['email']=$i;
        if(in_array($h,['status','result','validation_status','email_status','sub_status'],true)) $headers['status']=$i;
    }
    $hasHeader=isset($headers['email']);
    $data=$hasHeader?array_slice($rows,1):$rows;
    $out=[];
    foreach($data as $row){
        $email='';$status='valid';
        if($hasHeader){$email=trim((string)($row[$headers['email']]??'')); if(isset($headers['status']))$status=trim((string)($row[$headers['status']]??'valid'));}
        else{foreach($row as $cell){$v=trim((string)$cell);if(filter_var($v,FILTER_VALIDATE_EMAIL)){$email=$v;break;}} if(isset($row[1]))$status=trim((string)$row[1]);}
        if(filter_var($email,FILTER_VALIDATE_EMAIL))$out[]=['email'=>mb_strtolower($email,'UTF-8'),'status'=>mb_strtolower($status,'UTF-8')];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_cleaned_contacts'])) {
    try {
        if(empty($_FILES['cleaned_file']) || (int)($_FILES['cleaned_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Vali NeverBounce või ZeroBounce CSV/XLSX fail.');
        $file=$_FILES['cleaned_file']; if((int)$file['size']>12*1024*1024) throw new RuntimeException('Fail on liiga suur. Maksimum on 12 MB.');
        $ext=mb_strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION),'UTF-8');
        if($ext==='csv'){$raw=file_get_contents((string)$file['tmp_name']);if($raw===false)throw new RuntimeException('CSV faili ei õnnestunud lugeda.');$rows=email_contact_rows_from_delimited($raw);}
        elseif($ext==='xlsx'){$rows=email_contact_rows_from_xlsx((string)$file['tmp_name']);}
        else throw new RuntimeException('Lubatud failiformaadid on .csv ja .xlsx.');
        $items=email_cleaned_map_rows($rows); if(!$items) throw new RuntimeException('Failist ei leitud e-posti aadresse.');
        $source=in_array((string)($_POST['verification_source']??''),['neverbounce','zerobounce','other'],true)?(string)$_POST['verification_source']:'other';
        $includeCatch=!empty($_POST['include_catchall']);
        $valid=['valid','deliverable','verified','ok']; if($includeCatch){$valid=array_merge($valid,['catchall','catch-all','catch_all']);}
        $find=$pdo->prepare('SELECT id FROM newsletter_subscribers WHERE LOWER(email)=LOWER(?) LIMIT 1');
        $insert=$pdo->prepare("INSERT INTO newsletter_subscribers (email,status,subscribed_at,source,created_at,updated_at,cleaned_at,verification_source,verification_status) VALUES (?,'subscribed',NOW(),'verification-import',NOW(),NOW(),NOW(),?,?)");
        $update=$pdo->prepare('UPDATE newsletter_subscribers SET cleaned_at=NOW(),verification_source=?,verification_status=?,updated_at=NOW() WHERE id=?');
        $accepted=0;$rejected=0;$added=0;
        foreach($items as $item){$st=trim((string)$item['status']); if(!in_array($st,$valid,true)){$rejected++;continue;} $find->execute([$item['email']]);$id=(int)($find->fetchColumn()?:0);if($id){$update->execute([$source,$st,$id]);}else{$insert->execute([$item['email'],$source,$st]);$added++;}$accepted++;}
        header('Location:index.php?view=email-contacts&contact_tab=cleaned&cleaned_imported=1&accepted='.$accepted.'&rejected='.$rejected.'&clean_added='.$added);exit;
    } catch(Throwable $e){header('Location:index.php?view=email-contacts&contact_tab=cleaned&clean_error='.rawurlencode($e->getMessage()));exit;}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_email_segment'])) {
    $name=trim((string)($_POST['segment_name']??''));$ids=array_values(array_unique(array_filter(array_map('intval',$_POST['contact_ids']??[]))));
    if($name===''){header('Location:index.php?view=email-contacts&segment_error='.rawurlencode('Sisesta segmendi nimi.'));exit;}
    $scope=in_array((string)($_POST['segment_scope']??'all'),['all','cleaned'],true)?(string)$_POST['segment_scope']:'all';
    $pdo->beginTransaction();try{$st=$pdo->prepare('INSERT INTO admin_email_segments (name,source_scope) VALUES (?,?)');$st->execute([$name,$scope]);$sid=(int)$pdo->lastInsertId();$mem=$pdo->prepare('INSERT IGNORE INTO admin_email_segment_members (segment_id,subscriber_id) VALUES (?,?)');foreach($ids as $id)$mem->execute([$sid,$id]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
    header('Location:index.php?view=email-contacts&contact_tab=segment&segment_id='.$sid.'&segment_saved=1');exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contacts_to_segment'])) {
    $sid=(int)($_POST['segment_id']??0);$ids=array_values(array_unique(array_filter(array_map('intval',$_POST['contact_ids']??[]))));
    if($sid>0){$mem=$pdo->prepare('INSERT IGNORE INTO admin_email_segment_members (segment_id,subscriber_id) VALUES (?,?)');foreach($ids as $id)$mem->execute([$sid,$id]);}
    header('Location:index.php?view=email-contacts&contact_tab=segment&segment_id='.$sid.'&members_added='.count($ids));exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_contacts_from_segment'])) {
    $sid=(int)($_POST['segment_id']??0);$ids=array_values(array_unique(array_filter(array_map('intval',$_POST['contact_ids']??[]))));
    if($sid>0 && $ids){$del=$pdo->prepare('DELETE FROM admin_email_segment_members WHERE segment_id=? AND subscriber_id=?');foreach($ids as $id)$del->execute([$sid,$id]);}
    header('Location:index.php?view=email-contacts&contact_tab=segment&segment_id='.$sid.'&members_removed='.count($ids));exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email_segment'])) {
    $sid=(int)($_POST['segment_id']??0);if($sid>0){$pdo->beginTransaction();try{$pdo->prepare('DELETE FROM admin_email_segment_members WHERE segment_id=?')->execute([$sid]);$pdo->prepare('DELETE FROM admin_email_segments WHERE id=?')->execute([$sid]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}}
    header('Location:index.php?view=email-contacts&contact_tab=segment&segment_deleted=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_contact'])) {
    $id=(int)($_POST['contact_id']??0);
    $email=trim((string)($_POST['email']??''));
    $name=trim((string)($_POST['name']??''));
    $phone=trim((string)($_POST['phone']??''));
    $company=trim((string)($_POST['company']??''));
    $notes=trim((string)($_POST['notes']??''));
    $status=in_array((string)($_POST['status']??'subscribed'),['subscribed','unsubscribed'],true)?(string)$_POST['status']:'subscribed';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { header('Location:index.php?view=email-contacts&error=email'); exit; }
    try {
        if($id>0){
            $stmt=$pdo->prepare("UPDATE newsletter_subscribers SET email=?,name=?,phone=?,company=?,notes=?,status=?,subscribed_at=CASE WHEN ?='subscribed' THEN COALESCE(subscribed_at,NOW()) ELSE subscribed_at END,unsubscribed_at=CASE WHEN ?='unsubscribed' THEN NOW() ELSE NULL END,updated_at=NOW() WHERE id=?");
            $stmt->execute([$email,$name?:null,$phone?:null,$company?:null,$notes?:null,$status,$status,$status,$id]);
        } else {
            $find=$pdo->prepare('SELECT id FROM newsletter_subscribers WHERE LOWER(email)=LOWER(?) LIMIT 1');$find->execute([$email]);$existing=(int)($find->fetchColumn()?:0);
            if($existing>0){
                $stmt=$pdo->prepare("UPDATE newsletter_subscribers SET name=?,phone=?,company=?,notes=?,status=?,subscribed_at=CASE WHEN ?='subscribed' THEN COALESCE(subscribed_at,NOW()) ELSE subscribed_at END,unsubscribed_at=CASE WHEN ?='unsubscribed' THEN NOW() ELSE NULL END,updated_at=NOW() WHERE id=?");
                $stmt->execute([$name?:null,$phone?:null,$company?:null,$notes?:null,$status,$status,$status,$existing]);
            } else {
                $stmt=$pdo->prepare("INSERT INTO newsletter_subscribers (email,name,phone,company,notes,status,subscribed_at,unsubscribed_at,source,created_at,updated_at) VALUES (?,?,?,?,?,?,CASE WHEN ?='subscribed' THEN NOW() ELSE NULL END,CASE WHEN ?='unsubscribed' THEN NOW() ELSE NULL END,'admin-contact',NOW(),NOW())");
                $stmt->execute([$email,$name?:null,$phone?:null,$company?:null,$notes?:null,$status,$status,$status]);
            }
        }
        header('Location:index.php?view=email-contacts&saved=1');exit;
    } catch(Throwable $e){ header('Location:index.php?view=email-contacts&error=save');exit; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email_contact'])) {
    $id=(int)($_POST['contact_id']??0);if($id>0){$pdo->prepare('DELETE FROM newsletter_subscribers WHERE id=?')->execute([$id]);}
    header('Location:index.php?view=email-contacts&deleted=1');exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_subscribers'])) {
    $raw=(string)($_POST['emails']??'');$parts=preg_split('/[\s,;]+/',trim($raw))?:[];$added=0;
    $find=$pdo->prepare('SELECT id,status FROM newsletter_subscribers WHERE LOWER(email)=LOWER(?) LIMIT 1');
    foreach(array_unique($parts) as $email){$email=trim($email);if(!filter_var($email,FILTER_VALIDATE_EMAIL))continue;$find->execute([$email]);$existing=$find->fetch(PDO::FETCH_ASSOC);if($existing){$pdo->prepare("UPDATE newsletter_subscribers SET status='subscribed',subscribed_at=COALESCE(subscribed_at,NOW()),unsubscribed_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$existing['id']]);}else{$pdo->prepare("INSERT INTO newsletter_subscribers (email,status,subscribed_at,source,created_at,updated_at) VALUES (?,'subscribed',NOW(),'admin-import',NOW(),NOW())")->execute([$email]);}$added++;}
    header('Location:index.php?view=subscribers&imported='.$added);exit;
}

$view = $_GET['view'] ?? 'requests';
if (!in_array($view, ['requests', 'quotes', 'objects', 'calculations', 'price-list', 'workdays', 'workers', 'quote', 'statistics', 'email-campaigns', 'email-campaign', 'email-templates', 'email-settings', 'email-contacts', 'subscribers', 'unsubscribers'], true)) {
    $view = 'requests';
}

$viewTitles = [
    'requests' => 'Päringud',
    'quotes' => 'Hinnapakkumised',
    'objects' => 'Objektid',
    'calculations' => 'Arvestused',
    'price-list' => 'Hinnakiri',
    'workdays' => 'Tööpäevad',
    'workers' => 'Töölised',
    'quote' => 'Hinnapakkumine',
    'statistics' => 'Lehe statistika',
    'email-campaigns' => 'Email kampaaniad',
    'email-campaign' => 'Email kampaania',
    'email-templates' => 'Emaili mallid',
    'email-settings' => 'Emaili seaded',
    'email-contacts' => 'Kontaktid',
    'subscribers' => 'Subscriberid',
    'unsubscribers' => 'Unsubscriberid',
];

$quoteFormError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quote'])) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $quoteNumber = trim((string) ($_POST['quote_number'] ?? ''));
    $title = trim((string) ($_POST['quote_title'] ?? ''));
    $clientName = trim((string) ($_POST['client_name'] ?? ''));
    $clientEmail = trim((string) ($_POST['client_email'] ?? ''));
    $clientPhone = trim((string) ($_POST['client_phone'] ?? ''));
    $objectAddress = trim((string) ($_POST['object_address'] ?? ''));
    $validDays = max(1, (int) ($_POST['valid_days'] ?? 14));
    $workTime = trim((string) ($_POST['work_time'] ?? 'Kokkuleppel'));
    $offerType = trim((string) ($_POST['offer_type'] ?? 'Esialgne, päringu põhjal'));
    $duration = trim((string) ($_POST['duration'] ?? ''));
    $scopeText = trim((string) ($_POST['scope_text'] ?? ''));
    $termsText = trim((string) ($_POST['terms_text'] ?? ''));
    $status = in_array((string)($_POST['quote_status'] ?? 'draft'), ['draft','ready','sent','accepted'], true) ? (string)$_POST['quote_status'] : 'draft';

    $workItems = [];
    $descs = $_POST['work_desc'] ?? [];
    foreach ($descs as $i => $desc) {
        $desc = trim((string)$desc); if ($desc === '') continue;
        $workItems[] = ['description'=>$desc,'qty'=>quote_decimal($_POST['work_qty'][$i] ?? 0),'unit'=>trim((string)($_POST['work_unit'][$i] ?? 'kompl')),'rate'=>quote_decimal($_POST['work_rate'][$i] ?? 0)];
    }
    $materialItems = [];
    $mdescs = $_POST['material_desc'] ?? [];
    foreach ($mdescs as $i => $desc) {
        $desc = trim((string)$desc); if ($desc === '') continue;
        $materialItems[] = ['description'=>$desc,'qty'=>quote_decimal($_POST['material_qty'][$i] ?? 0),'unit'=>trim((string)($_POST['material_unit'][$i] ?? 'tk')),'rate'=>quote_decimal($_POST['material_rate'][$i] ?? 0)];
    }

    $quoteId = (int) ($_POST['quote_id'] ?? 0);

    // Käsitsi koostatud hinnapakkumise võib salvestada ka enne tellija sisestamist.
    // Sellisel juhul saab ta nime Mustand, Mustand_2 jne, et pakkumine ei läheks kaduma.
    if ($clientName === '') {
        if ($quoteId > 0) {
            $currentDraftStmt = $pdo->prepare('SELECT client_name FROM admin_quotes WHERE id = :id LIMIT 1');
            $currentDraftStmt->execute([':id' => $quoteId]);
            $currentDraftName = trim((string) $currentDraftStmt->fetchColumn());
            if (preg_match('/^Mustand(?:_\d+)?$/', $currentDraftName)) {
                $clientName = $currentDraftName;
            } else {
                $clientName = quote_next_draft_name($pdo, $quoteId);
            }
        } else {
            $clientName = quote_next_draft_name($pdo);
        }
    }

    if ($title === '') {
        $title = 'Hinnapakkumine';
    }

    if ($quoteNumber === '') {
        $quoteNumber = 'RK-' . date('dmy') . '-' . str_pad((string)(((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM admin_quotes')->fetchColumn()) % 100), 2, '0', STR_PAD_LEFT);
    }

    if ($title === '') {
        $quoteFormError = 'Pakkumise pealkiri on kohustuslik.';
    } else {
        $params = [':request_id'=>$requestId > 0 ? $requestId : null,':quote_number'=>$quoteNumber,':title'=>$title,':client_name'=>$clientName,':client_email'=>$clientEmail!==''?$clientEmail:null,':client_phone'=>$clientPhone!==''?$clientPhone:null,':object_address'=>$objectAddress!==''?$objectAddress:null,':valid_days'=>$validDays,':work_time'=>$workTime,':offer_type'=>$offerType,':duration'=>$duration!==''?$duration:null,':scope_text'=>$scopeText!==''?$scopeText:null,':work_items'=>json_encode($workItems,JSON_UNESCAPED_UNICODE),':material_items'=>json_encode($materialItems,JSON_UNESCAPED_UNICODE),':terms_text'=>$termsText!==''?$termsText:null,':status'=>$status];
        if ($quoteId > 0) {
            $params[':id'] = $quoteId;
            $stmt = $pdo->prepare("UPDATE admin_quotes SET request_id=:request_id,quote_number=:quote_number,title=:title,client_name=:client_name,client_email=:client_email,client_phone=:client_phone,object_address=:object_address,valid_days=:valid_days,work_time=:work_time,offer_type=:offer_type,duration=:duration,scope_text=:scope_text,work_items_json=:work_items,material_items_json=:material_items,terms_text=:terms_text,status=:status WHERE id=:id");
            $stmt->execute($params);
            $savedQuoteId = $quoteId;
        } elseif ($requestId > 0) {
            $stmt = $pdo->prepare("INSERT INTO admin_quotes (request_id,quote_number,title,client_name,client_email,client_phone,object_address,valid_days,work_time,offer_type,duration,scope_text,work_items_json,material_items_json,terms_text,status) VALUES (:request_id,:quote_number,:title,:client_name,:client_email,:client_phone,:object_address,:valid_days,:work_time,:offer_type,:duration,:scope_text,:work_items,:material_items,:terms_text,:status) ON DUPLICATE KEY UPDATE quote_number=VALUES(quote_number),title=VALUES(title),client_name=VALUES(client_name),client_email=VALUES(client_email),client_phone=VALUES(client_phone),object_address=VALUES(object_address),valid_days=VALUES(valid_days),work_time=VALUES(work_time),offer_type=VALUES(offer_type),duration=VALUES(duration),scope_text=VALUES(scope_text),work_items_json=VALUES(work_items_json),material_items_json=VALUES(material_items_json),terms_text=VALUES(terms_text),status=VALUES(status)");
            $stmt->execute($params);
            $find = $pdo->prepare('SELECT id FROM admin_quotes WHERE request_id=:request_id LIMIT 1');
            $find->execute([':request_id'=>$requestId]);
            $savedQuoteId = (int)$find->fetchColumn();
        } else {
            $stmt = $pdo->prepare("INSERT INTO admin_quotes (request_id,quote_number,title,client_name,client_email,client_phone,object_address,valid_days,work_time,offer_type,duration,scope_text,work_items_json,material_items_json,terms_text,status) VALUES (NULL,:quote_number,:title,:client_name,:client_email,:client_phone,:object_address,:valid_days,:work_time,:offer_type,:duration,:scope_text,:work_items,:material_items,:terms_text,:status)");
            $manualParams = $params; unset($manualParams[':request_id']);
            $stmt->execute($manualParams);
            $savedQuoteId = (int)$pdo->lastInsertId();
        }
        header('Location: index.php?view=quote&quote_id=' . $savedQuoteId . '&saved=1'); exit;
    }
}

// Kustuta hinnapakkumine hinnapakkumiste nimekirjast.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_quote'])) {
    $quoteId = (int) ($_POST['quote_id'] ?? 0);
    if ($quoteId > 0) {
        $stmt = $pdo->prepare('DELETE FROM admin_quotes WHERE id = :id');
        $stmt->execute([':id' => $quoteId]);
    }
    header('Location: index.php?view=quotes&deleted=1');
    exit;
}

// Muuda hinnapakkumise saatmise staatust otse nimekirjast.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quote_send_status'])) {
    $quoteId = (int) ($_POST['quote_id'] ?? 0);
    $sendStatus = (string) ($_POST['send_status'] ?? 'unsent');
    if (!in_array($sendStatus, ['unsent', 'sent'], true)) {
        $sendStatus = 'unsent';
    }
    if ($quoteId > 0) {
        $stmt = $pdo->prepare('UPDATE admin_quotes SET send_status = :send_status WHERE id = :id');
        $stmt->execute([':send_status' => $sendStatus, ':id' => $quoteId]);
    }
    header('Location: index.php?view=quotes&status_saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $pdo->prepare('SELECT file_path FROM contacts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        foreach (decode_files($row['file_path'] ?? null) as $file) {
            $relative = ltrim(file_path_from_entry($file), '/');
            if (str_starts_with($relative, 'api/uploads/') || str_starts_with($relative, 'uploads/')) {
                $absolute = realpath(__DIR__ . '/../' . $relative);
                $root = realpath(__DIR__ . '/..');
                if ($absolute && $root && str_starts_with($absolute, $root) && is_file($absolute)) {
                    unlink($absolute);
                }
            }
        }
    }

    $delete = $pdo->prepare('DELETE FROM contacts WHERE id = :id');
    $delete->execute([':id' => $id]);
    header('Location: index.php?view=requests');
    exit;
}

$objectFormError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_object'])) {
    $objectId = (int) ($_POST['object_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $startAt = trim((string) ($_POST['start_at'] ?? ''));
    $expectedEndDate = trim((string) ($_POST['expected_end_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($name === '' || $address === '') {
        $objectFormError = 'Objekti nimi ja aadress on kohustuslikud.';
    } else {
        $startAtSql = $startAt !== '' ? str_replace('T', ' ', $startAt) . (strlen($startAt) === 16 ? ':00' : '') : null;
        $expectedEndDateSql = $expectedEndDate !== '' ? $expectedEndDate : null;

        if ($objectId > 0) {
            $stmt = $pdo->prepare("
                UPDATE admin_objects
                SET name = :name,
                    address = :address,
                    phone = :phone,
                    start_at = :start_at,
                    expected_end_date = :expected_end_date,
                    notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone !== '' ? $phone : null,
                ':start_at' => $startAtSql,
                ':expected_end_date' => $expectedEndDateSql,
                ':notes' => $notes !== '' ? $notes : null,
                ':id' => $objectId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO admin_objects (name, address, phone, start_at, expected_end_date, notes)
                VALUES (:name, :address, :phone, :start_at, :expected_end_date, :notes)
            ");
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone !== '' ? $phone : null,
                ':start_at' => $startAtSql,
                ':expected_end_date' => $expectedEndDateSql,
                ':notes' => $notes !== '' ? $notes : null,
            ]);
        }

        header('Location: index.php?view=objects&saved=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_object'])) {
    $objectId = (int) ($_POST['object_id'] ?? 0);
    if ($objectId > 0) {
        $stmt = $pdo->prepare('DELETE FROM admin_objects WHERE id = :id');
        $stmt->execute([':id' => $objectId]);
    }

    header('Location: index.php?view=objects&deleted=1');
    exit;
}


$workdayFormError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workday'])) {
    $workdayId = (int) ($_POST['workday_id'] ?? 0);
    $workDate = trim((string) ($_POST['work_date'] ?? ''));
    $workerName = trim((string) ($_POST['worker_name'] ?? ''));
    $objectId = (int) ($_POST['object_id'] ?? 0);
    $objectName = trim((string) ($_POST['object_name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));
    $breakMinutes = max(0, (int) ($_POST['break_minutes'] ?? 0));
    $workType = trim((string) ($_POST['work_type'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $mileageKm = max(0, (float) str_replace(',', '.', (string) ($_POST['mileage_km'] ?? '0')));
    $hourlyRate = max(0, (float) str_replace(',', '.', (string) ($_POST['hourly_rate'] ?? '0')));
    $paymentType = (string) ($_POST['payment_type'] ?? 'hourly');
    if (!in_array($paymentType, ['hourly', 'piece'], true)) $paymentType = 'hourly';
    $pieceQuantity = max(0, (float) str_replace(',', '.', (string) ($_POST['piece_quantity'] ?? '0')));
    $pieceUnit = trim((string) ($_POST['piece_unit'] ?? 'm²'));
    if ($pieceUnit === '') $pieceUnit = 'm²';
    $pieceRate = max(0, (float) str_replace(',', '.', (string) ($_POST['piece_rate'] ?? '0')));
    $piecePricingMode = (string) ($_POST['piece_pricing_mode'] ?? 'unit');
    if (!in_array($piecePricingMode, ['unit', 'fixed'], true)) $piecePricingMode = 'unit';
    $pieceFixedPrice = max(0, (float) str_replace(',', '.', (string) ($_POST['piece_fixed_price'] ?? '0')));
    $status = (string) ($_POST['status'] ?? 'confirmed');
    if (!in_array($status, ['pending', 'draft', 'confirmed', 'paid'], true)) $status = 'confirmed';

    if ($objectId > 0) {
        $objectStmt = $pdo->prepare('SELECT name, address FROM admin_objects WHERE id = :id');
        $objectStmt->execute([':id' => $objectId]);
        $selectedObject = $objectStmt->fetch();
        if ($selectedObject) {
            if ($objectName === '') $objectName = (string) $selectedObject['name'];
            if ($address === '') $address = (string) $selectedObject['address'];
        }
    }

    if ($workDate === '' || $workerName === '' || $objectName === '' || $startTime === '' || $endTime === '') {
        $workdayFormError = 'Kuupäev, töötaja, objekt ning tööpäeva algus ja lõpp on kohustuslikud.';
    } elseif (workday_hours($startTime, $endTime, $breakMinutes) <= 0) {
        $workdayFormError = 'Tööpäeva pikkus peab pärast pausi arvestamist olema suurem kui 0 tundi.';
    } else {
        $params = [
            ':work_date' => $workDate,
            ':worker_name' => $workerName,
            ':object_id' => $objectId > 0 ? $objectId : null,
            ':object_name' => $objectName,
            ':address' => $address !== '' ? $address : null,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':break_minutes' => $breakMinutes,
            ':work_type' => $workType !== '' ? $workType : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':mileage_km' => $mileageKm,
            ':hourly_rate' => $hourlyRate,
            ':payment_type' => $paymentType,
            ':piece_quantity' => $pieceQuantity,
            ':piece_unit' => $pieceUnit,
            ':piece_rate' => $pieceRate,
            ':piece_pricing_mode' => $piecePricingMode,
            ':piece_fixed_price' => $pieceFixedPrice,
            ':status' => $status,
        ];

        if ($workdayId > 0) {
            $params[':id'] = $workdayId;
            $stmt = $pdo->prepare("UPDATE admin_workdays SET
                work_date=:work_date, worker_name=:worker_name, object_id=:object_id,
                object_name=:object_name, address=:address, start_time=:start_time,
                end_time=:end_time, break_minutes=:break_minutes, work_type=:work_type,
                notes=:notes, mileage_km=:mileage_km, hourly_rate=:hourly_rate, payment_type=:payment_type,
                piece_quantity=:piece_quantity, piece_unit=:piece_unit, piece_rate=:piece_rate,
                piece_pricing_mode=:piece_pricing_mode, piece_fixed_price=:piece_fixed_price, status=:status
                WHERE id=:id");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO admin_workdays
                (work_date, worker_name, object_id, object_name, address, start_time, end_time,
                 break_minutes, work_type, notes, mileage_km, hourly_rate, payment_type, piece_quantity, piece_unit, piece_rate,
                 piece_pricing_mode, piece_fixed_price, status)
                VALUES (:work_date,:worker_name,:object_id,:object_name,:address,:start_time,:end_time,
                        :break_minutes,:work_type,:notes,:mileage_km,:hourly_rate,:payment_type,:piece_quantity,:piece_unit,:piece_rate,
                        :piece_pricing_mode,:piece_fixed_price,:status)");
            $stmt->execute($params);
        }
        header('Location: index.php?view=workdays&saved=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_confirm_workday'])) {
    $workdayId = (int) ($_POST['workday_id'] ?? 0);
    if ($workdayId > 0) {
        $stmt = $pdo->prepare("UPDATE admin_workdays SET status='confirmed' WHERE id=:id AND status='pending'");
        $stmt->execute([':id' => $workdayId]);
    }
    header('Location: index.php?view=workdays&quick_confirmed=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workday'])) {
    $workdayId = (int) ($_POST['workday_id'] ?? 0);
    if ($workdayId > 0) {
        $stmt = $pdo->prepare('DELETE FROM admin_workdays WHERE id = :id');
        $stmt->execute([':id' => $workdayId]);
    }
    header('Location: index.php?view=workdays&deleted=1');
    exit;
}

$priceListFormError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_price_item'])) {
    $priceId=(int)($_POST['price_id']??0); $itemType=in_array((string)($_POST['item_type']??'service'),['service','package'],true)?(string)$_POST['item_type']:'service';
    $category=trim((string)($_POST['category']??'')); $name=trim((string)($_POST['name']??'')); $unit=trim((string)($_POST['unit']??''));
    $priceFrom=max(0,(float)str_replace(',','.',(string)($_POST['price_from']??'0'))); $materialRaw=trim((string)($_POST['material_price_from']??'')); $materialPrice=$materialRaw===''?null:max(0,(float)str_replace(',','.',$materialRaw));
    $description=trim((string)($_POST['description']??'')); $sortOrder=(int)($_POST['sort_order']??0);
    if($category===''||$name===''){$priceListFormError='Kategooria ja nimetus on kohustuslikud.';} else {
        $p=[':item_type'=>$itemType,':category'=>$category,':name'=>$name,':unit'=>$unit!==''?$unit:null,':price_from'=>$priceFrom,':material_price_from'=>$materialPrice,':description'=>$description!==''?$description:null,':sort_order'=>$sortOrder];
        if($priceId>0){$p[':id']=$priceId;$stmt=$pdo->prepare('UPDATE admin_price_list SET item_type=:item_type,category=:category,name=:name,unit=:unit,price_from=:price_from,material_price_from=:material_price_from,description=:description,sort_order=:sort_order WHERE id=:id');}
        else{$stmt=$pdo->prepare('INSERT INTO admin_price_list (item_type,category,name,unit,price_from,material_price_from,description,sort_order) VALUES (:item_type,:category,:name,:unit,:price_from,:material_price_from,:description,:sort_order)');}
        $stmt->execute($p); header('Location: index.php?view=price-list&saved=1'); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_price_item'])) {$priceId=(int)($_POST['price_id']??0);if($priceId>0){$stmt=$pdo->prepare('DELETE FROM admin_price_list WHERE id=:id');$stmt->execute([':id'=>$priceId]);}header('Location: index.php?view=price-list&deleted=1');exit;}

$workerFormError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_worker'])) {
    $workerId = (int) ($_POST['worker_id'] ?? 0);
    $workerName = trim((string) ($_POST['name'] ?? ''));
    $workerContact = trim((string) ($_POST['contact'] ?? ''));
    $workerRoleSkills = trim((string) ($_POST['role_skills'] ?? ''));
    $workerExperience = trim((string) ($_POST['experience'] ?? ''));
    $workerStatus = (string) ($_POST['worker_status'] ?? 'free');
    $workerNotes = trim((string) ($_POST['notes'] ?? ''));
    if (!in_array($workerStatus, ['busy', 'waiting', 'free'], true)) $workerStatus = 'free';

    if ($workerName === '') {
        $workerFormError = 'Töölise nimi on kohustuslik.';
    } else {
        $params = [
            ':name' => $workerName,
            ':contact' => $workerContact !== '' ? $workerContact : null,
            ':role_skills' => $workerRoleSkills !== '' ? $workerRoleSkills : null,
            ':experience' => $workerExperience !== '' ? $workerExperience : null,
            ':status' => $workerStatus,
            ':notes' => $workerNotes !== '' ? $workerNotes : null,
        ];
        if ($workerId > 0) {
            $params[':id'] = $workerId;
            $stmt = $pdo->prepare("UPDATE admin_workers SET name=:name, contact=:contact, role_skills=:role_skills, experience=:experience, status=:status, notes=:notes WHERE id=:id");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO admin_workers (name, contact, role_skills, experience, status, notes) VALUES (:name,:contact,:role_skills,:experience,:status,:notes)");
            $stmt->execute($params);
        }
        header('Location: index.php?view=workers&saved=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_worker'])) {
    $workerId = (int) ($_POST['worker_id'] ?? 0);
    if ($workerId > 0) {
        $stmt = $pdo->prepare('DELETE FROM admin_workers WHERE id = :id');
        $stmt->execute([':id' => $workerId]);
    }
    header('Location: index.php?view=workers&deleted=1');
    exit;
}

function ga_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function ga_http_post(string $url, array $payload, bool $formEncoded = false, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Serveris puudub PHP cURL laiendus. Palu veebimajutajal see aktiveerida.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Google Analyticsi ühenduse loomine ebaõnnestus.');
    }

    $body = $formEncoded ? http_build_query($payload) : json_encode($payload, JSON_UNESCAPED_SLASHES);
    $requestHeaders = $headers;
    $requestHeaders[] = $formEncoded
        ? 'Content-Type: application/x-www-form-urlencoded'
        : 'Content-Type: application/json';

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $requestHeaders,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google Analyticsi võrguühendus ebaõnnestus: ' . $curlError);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Google Analytics tagastas vigase vastuse.');
    }

    if ($status < 200 || $status >= 300) {
        $message = $decoded['error']['message'] ?? $decoded['error_description'] ?? 'Tundmatu API viga.';
        throw new RuntimeException('Google Analytics API: ' . $message);
    }

    return $decoded;
}

function ga_access_token(string $credentialsPath): string
{
    static $accessToken = null;
    if (is_string($accessToken) && $accessToken !== '') {
        return $accessToken;
    }

    if (!is_readable($credentialsPath)) {
        throw new RuntimeException('Google Analyticsi JSON-võtit ei leitud: admin/private-google/renoveerikodu-analytics.json');
    }
    if (!function_exists('openssl_sign')) {
        throw new RuntimeException('Serveris puudub PHP OpenSSL laiendus.');
    }

    $credentials = json_decode((string) file_get_contents($credentialsPath), true);
    if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
        throw new RuntimeException('Google Analyticsi JSON-võti ei ole korrektne Service Account võti.');
    }

    $now = time();
    $header = ga_base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = ga_base64url((string) json_encode([
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now - 30,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));
    $unsignedJwt = $header . '.' . $claims;

    $signature = '';
    if (!openssl_sign($unsignedJwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Google Service Account võtmega allkirjastamine ebaõnnestus.');
    }

    $tokenResponse = ga_http_post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $unsignedJwt . '.' . ga_base64url($signature),
    ], true);

    $accessToken = (string) ($tokenResponse['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google ei tagastanud ligipääsutunnust.');
    }

    return $accessToken;
}

function ga_report(string $accessToken, string $propertyId, array $request, bool $realtime = false): array
{
    $method = $realtime ? 'runRealtimeReport' : 'runReport';
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($propertyId) . ':' . $method;
    return ga_http_post($url, $request, false, ['Authorization: Bearer ' . $accessToken]);
}

function ga_rows(array $report): array
{
    $dimensionNames = array_map(static fn(array $item): string => (string) ($item['name'] ?? ''), $report['dimensionHeaders'] ?? []);
    $metricNames = array_map(static fn(array $item): string => (string) ($item['name'] ?? ''), $report['metricHeaders'] ?? []);
    $result = [];

    foreach ($report['rows'] ?? [] as $row) {
        $values = [];
        foreach ($dimensionNames as $index => $name) {
            $values[$name] = (string) ($row['dimensionValues'][$index]['value'] ?? '');
        }
        foreach ($metricNames as $index => $name) {
            $values[$name] = (float) ($row['metricValues'][$index]['value'] ?? 0);
        }
        $result[] = $values;
    }

    return $result;
}

function ga_first_metric(array $report, string $metricName): float
{
    $rows = ga_rows($report);
    return (float) ($rows[0][$metricName] ?? 0);
}

function ga_number(float $value, int $decimals = 0): string
{
    return number_format($value, $decimals, ',', ' ');
}

function ga_duration(float $seconds): string
{
    $seconds = max(0, (int) round($seconds));
    $minutes = intdiv($seconds, 60);
    $remainingSeconds = $seconds % 60;
    return $minutes > 0 ? $minutes . ' min ' . $remainingSeconds . ' sek' : $remainingSeconds . ' sek';
}

function fetch_cheapest_tallinn_95(): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Serveris puudub PHP cURL laiendus.');
    }
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('Serveris puudub PHP DOM laiendus.');
    }

    $sourceUrl = 'https://autoportaal.ee/et/kutusehinnad/tallinn?fuel_id=1&location_id=1&distance=10&brand_id=&seconds=86400';
    $ch = curl_init($sourceUrl);
    if ($ch === false) {
        throw new RuntimeException('Kütusehindade ühenduse loomine ebaõnnestus.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'RenoveeriKodu admin fuel price widget/1.0',
        CURLOPT_HTTPHEADER => ['Accept-Language: et-EE,et;q=0.9'],
    ]);

    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($html === false) {
        throw new RuntimeException('Autoportaali ühendus ebaõnnestus: ' . $curlError);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Autoportaal tagastas HTTP vea ' . $status . '.');
    }

    $previousLibxmlState = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . (string) $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    if (!$loaded) {
        throw new RuntimeException('Autoportaali hinnalehte ei õnnestunud lugeda.');
    }

    $xpath = new DOMXPath($document);
    $headings = $xpath->query('//h2[contains(normalize-space(string(.)), "Bensiin 95")]');
    if (!$headings || $headings->length === 0) {
        throw new RuntimeException('Bensiin 95 hinnatabelit ei leitud.');
    }

    $tables = $xpath->query('following::table[1]', $headings->item(0));
    if (!$tables || $tables->length === 0) {
        throw new RuntimeException('Bensiin 95 hinnatabelit ei leitud.');
    }

    $rows = $xpath->query('.//tr[td]', $tables->item(0));
    $cheapest = null;
    foreach ($rows ?: [] as $row) {
        $cells = $xpath->query('./td', $row);
        $cellValues = [];
        foreach ($cells ?: [] as $cell) {
            $value = preg_replace('/\s+/u', ' ', trim((string) $cell->textContent));
            if ($value !== '') {
                $cellValues[] = $value;
            }
        }
        if (count($cellValues) < 2) {
            continue;
        }

        $price = null;
        $priceIndex = null;
        for ($index = count($cellValues) - 1; $index >= 0; $index--) {
            if (preg_match('/(?:^|\s)(\d+[\.,]\d{3})(?:\s|€|$)/u', $cellValues[$index], $match)) {
                $price = (float) str_replace(',', '.', $match[1]);
                $priceIndex = $index;
                break;
            }
        }
        if ($price === null || $price <= 0) {
            continue;
        }

        $station = $cellValues[0];
        $distance = '';
        $updated = '';
        foreach ($cellValues as $index => $value) {
            if ($index === $priceIndex || $index === 0) {
                continue;
            }
            if ($distance === '' && preg_match('/\b\d+(?:[\.,]\d+)?\s*km\b/ui', $value)) {
                $distance = $value;
            } elseif ($updated === '' && preg_match('/täna|eile|tund|min|päev|\d{1,2}[\.\/-]\d{1,2}/ui', $value)) {
                $updated = $value;
            }
        }

        if ($cheapest === null || $price < $cheapest['price']) {
            $cheapest = [
                'station' => $station,
                'price' => $price,
                'distance' => $distance,
                'updated' => $updated,
                'sourceUrl' => $sourceUrl,
            ];
        }
    }

    if ($cheapest === null) {
        throw new RuntimeException('Autoportaali tabelist ei leitud kehtivat Bensiin 95 hinda.');
    }

    return $cheapest;
}

$requests = [];
$quotes = [];
$objects = [];
$workdays = [];
$workers = [];
$quote = null;
$quoteRequest = null;
$quoteRequestIds = [];
$workerCounts = ['all'=>0,'busy'=>0,'waiting'=>0,'free'=>0];
$workdaySummary = ['count'=>0,'hours'=>0.0,'cost'=>0.0,'mileage'=>0.0];
$workdayWorkers = [];
$subscribers = [];
$unsubscribers = [];
$analyticsError = '';
$analyticsData = [];
$analyticsDays = in_array((string) ($_GET['days'] ?? '30'), ['7', '30', '90'], true)
    ? (string) ($_GET['days'] ?? '30')
    : '30';
$fuelPriceInfo = [];
$fuelPriceError = '';

$emailCampaigns = [];
$emailCampaign = null;
$emailCampaignStats = [];
$emailTemplates = [];
$emailSettings = email_settings($pdo);
$emailTemplates = $pdo->query('SELECT * FROM admin_email_templates ORDER BY updated_at DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
if ($view === 'email-campaigns') {
    $emailCampaigns = $pdo->query("SELECT c.*,
        (SELECT COUNT(*) FROM admin_email_recipients r WHERE r.campaign_id=c.id) recipient_count,
        (SELECT COUNT(*) FROM admin_email_recipients r WHERE r.campaign_id=c.id AND r.status='sent') sent_count,
        (SELECT COUNT(*) FROM admin_email_recipients r WHERE r.campaign_id=c.id AND r.opened_at IS NOT NULL) open_count,
        (SELECT COUNT(*) FROM admin_email_recipients r WHERE r.campaign_id=c.id AND r.clicked_at IS NOT NULL) click_count
        FROM admin_email_campaigns c ORDER BY c.created_at DESC,c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($view === 'email-campaign') {
    $campaignId=(int)($_GET['campaign_id']??0);
    if($campaignId>0){$st=$pdo->prepare('SELECT * FROM admin_email_campaigns WHERE id=?');$st->execute([$campaignId]);$emailCampaign=$st->fetch(PDO::FETCH_ASSOC)?:null;}
    if($emailCampaign){
        $st=$pdo->prepare("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='sent') sent, SUM(status='failed') failed, SUM(opened_at IS NOT NULL) opened, SUM(clicked_at IS NOT NULL) clicked, SUM(unsubscribed_at IS NOT NULL) unsubscribed FROM admin_email_recipients WHERE campaign_id=?");$st->execute([(int)$emailCampaign['id']]);$emailCampaignStats=$st->fetch(PDO::FETCH_ASSOC)?:[];
    }
}
$emailContacts = [];
$emailSegments = [];
$activeEmailSegment = null;
$cleanedLastAt = null;
if ($view === 'email-contacts') {
    $emailSegments=$pdo->query("SELECT s.*,COUNT(m.subscriber_id) member_count FROM admin_email_segments s LEFT JOIN admin_email_segment_members m ON m.segment_id=s.id GROUP BY s.id ORDER BY s.created_at DESC,s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $contactTab=in_array((string)($_GET['contact_tab']??'all'),['all','cleaned','segment'],true)?(string)$_GET['contact_tab']:'all';
    $segmentId=(int)($_GET['segment_id']??0);
    if($contactTab==='segment' && $segmentId>0){$st=$pdo->prepare('SELECT * FROM admin_email_segments WHERE id=?');$st->execute([$segmentId]);$activeEmailSegment=$st->fetch(PDO::FETCH_ASSOC)?:null;if(!$activeEmailSegment)$contactTab='all';}
    $q=trim((string)($_GET['q']??''));
    $sql="SELECT ns.id,ns.email,ns.status,ns.cleaned_at,ns.verification_source,ns.verification_status,
        (SELECT COUNT(*) FROM admin_email_recipients r WHERE LOWER(r.email)=LOWER(ns.email) AND r.sent_at IS NOT NULL) AS sent_count,
        (SELECT MAX(r.sent_at) FROM admin_email_recipients r WHERE LOWER(r.email)=LOWER(ns.email) AND r.sent_at IS NOT NULL) AS last_sent_at,
        (SELECT c.name FROM admin_email_recipients r JOIN admin_email_campaigns c ON c.id=r.campaign_id WHERE LOWER(r.email)=LOWER(ns.email) AND r.sent_at IS NOT NULL ORDER BY r.sent_at DESC LIMIT 1) AS last_campaign
        FROM newsletter_subscribers ns";
    $params=[];
    if($contactTab==='segment'){$sql.=' INNER JOIN admin_email_segment_members sm ON sm.subscriber_id=ns.id AND sm.segment_id=:segment_id';$params[':segment_id']=$segmentId;}
    $sql.=' WHERE 1=1';
    if($contactTab==='cleaned')$sql.=' AND ns.cleaned_at IS NOT NULL';
    if($q!==''){$sql.=' AND ns.email LIKE :q';$params[':q']='%'.$q.'%';}
    $sql.=' ORDER BY LOWER(ns.email) ASC';
    $st=$pdo->prepare($sql);$st->execute($params);$emailContacts=$st->fetchAll(PDO::FETCH_ASSOC);
    $cleanedLastAt=$pdo->query('SELECT MAX(cleaned_at) FROM newsletter_subscribers WHERE cleaned_at IS NOT NULL')->fetchColumn()?:null;
}
$subscriberCount = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'")->fetchColumn();
$unsubscriberCount = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'unsubscribed'")->fetchColumn();

if ($view === 'requests') {
    $requests = $pdo->query('SELECT * FROM contacts ORDER BY created_at DESC')->fetchAll();
    $quoteRequestIds = array_map('intval', $pdo->query('SELECT request_id FROM admin_quotes')->fetchAll(PDO::FETCH_COLUMN));
 } elseif ($view === 'quotes') {
    $quotes = $pdo->query("SELECT q.*, c.created_at AS request_created_at FROM admin_quotes q LEFT JOIN contacts c ON c.id = q.request_id ORDER BY q.updated_at DESC, q.id DESC")->fetchAll();
 } elseif ($view === 'quote') {
    $requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
    $quoteId = (int) ($_GET['quote_id'] ?? $_POST['quote_id'] ?? 0);
    if ($quoteId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM admin_quotes WHERE id = :id LIMIT 1');
        $stmt->execute([':id'=>$quoteId]);
        $quote = $stmt->fetch();
        if ($quote && !empty($quote['request_id'])) {
            $requestId = (int)$quote['request_id'];
            $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id');
            $stmt->execute([':id'=>$requestId]);
            $quoteRequest = $stmt->fetch();
        }
    } elseif ($requestId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id');
        $stmt->execute([':id'=>$requestId]);
        $quoteRequest = $stmt->fetch();
        if ($quoteRequest) {
            $stmt = $pdo->prepare('SELECT * FROM admin_quotes WHERE request_id = :id LIMIT 1');
            $stmt->execute([':id'=>$requestId]);
            $quote = $stmt->fetch();
            if (!$quote) {
                $created = !empty($quoteRequest['created_at']) ? strtotime((string)$quoteRequest['created_at']) : time();
                $quote = [
                    'id'=>0,'request_id'=>$requestId,
                    'quote_number'=>'RK-' . date('dmy', $created ?: time()) . '-' . str_pad((string)($requestId % 100), 2, '0', STR_PAD_LEFT),
                    'title'=>'Ehitus- ja renoveerimistööd',
                    'client_name'=>(string)($quoteRequest['name'] ?? ''),
                    'client_email'=>(string)($quoteRequest['email'] ?? ''),
                    'client_phone'=>(string)($quoteRequest['phone'] ?? ''),
                    'object_address'=>(string)($quoteRequest['address'] ?? ''),
                    'valid_days'=>14,'work_time'=>'Kokkuleppel','offer_type'=>'Esialgne, päringu ja lisatud info põhjal','duration'=>'',
                    'scope_text'=>'Pakkumine on koostatud tellija päringu põhjal. ' . trim((string)($quoteRequest['message'] ?? '')),
                    'work_items_json'=>json_encode(quote_auto_work_items((string)($quoteRequest['message'] ?? '')), JSON_UNESCAPED_UNICODE),
                    'material_items_json'=>json_encode([['description'=>'Materjalid vastavalt lõplikult kooskõlastatud töömahule','qty'=>1,'unit'=>'kompl','rate'=>0]], JSON_UNESCAPED_UNICODE),
                    'terms_text'=>quote_default_terms(),'status'=>'draft',
                ];
            }
        }
    } elseif (isset($_GET['new'])) {
        $nextNumber = 'RK-' . date('dmy') . '-' . str_pad((string)(((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM admin_quotes')->fetchColumn()) % 100), 2, '0', STR_PAD_LEFT);
        $quote = [
            'id'=>0,'request_id'=>null,'quote_number'=>$nextNumber,'title'=>'Ehitus- ja renoveerimistööd','client_name'=>'','client_email'=>'','client_phone'=>'','object_address'=>'',
            'valid_days'=>14,'work_time'=>'Kokkuleppel','offer_type'=>'Esialgne hinnapakkumine','duration'=>'','scope_text'=>'',
            'work_items_json'=>json_encode([['description'=>'','qty'=>1,'unit'=>'kompl','rate'=>0]], JSON_UNESCAPED_UNICODE),
            'material_items_json'=>json_encode([['description'=>'','qty'=>1,'unit'=>'kompl','rate'=>0]], JSON_UNESCAPED_UNICODE),
            'terms_text'=>quote_default_terms(),'status'=>'draft'
        ];
    }
 } elseif ($view === 'objects') {
    $objects = $pdo->query('SELECT * FROM admin_objects ORDER BY created_at DESC, id DESC')->fetchAll();
} elseif ($view === 'workdays') {
    $objects = $pdo->query('SELECT * FROM admin_objects ORDER BY name ASC, id DESC')->fetchAll();
    $workdayWorkers = $pdo->query("SELECT DISTINCT worker_name FROM admin_workdays WHERE worker_name <> '' ORDER BY worker_name ASC")->fetchAll(PDO::FETCH_COLUMN);

    $filterWorker = trim((string) ($_GET['worker'] ?? ''));
    $filterObject = trim((string) ($_GET['object'] ?? ''));
    $filterStatus = trim((string) ($_GET['status'] ?? ''));
    $filterFrom = trim((string) ($_GET['from'] ?? ''));
    $filterTo = trim((string) ($_GET['to'] ?? ''));
    $filterSearch = trim((string) ($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if ($filterWorker !== '') { $where[] = 'worker_name = :worker'; $params[':worker'] = $filterWorker; }
    if ($filterObject !== '') { $where[] = 'object_name = :object'; $params[':object'] = $filterObject; }
    if (in_array($filterStatus, ['pending','draft','confirmed','paid'], true)) { $where[] = 'status = :status'; $params[':status'] = $filterStatus; }
    if ($filterFrom !== '') { $where[] = 'work_date >= :from'; $params[':from'] = $filterFrom; }
    if ($filterTo !== '') { $where[] = 'work_date <= :to'; $params[':to'] = $filterTo; }
    if ($filterSearch !== '') {
        $where[] = '(worker_name LIKE :search OR object_name LIKE :search OR address LIKE :search OR work_type LIKE :search OR notes LIKE :search)';
        $params[':search'] = '%' . $filterSearch . '%';
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare('SELECT * FROM admin_workdays' . $sqlWhere . ' ORDER BY work_date DESC, start_time DESC, id DESC');
    $stmt->execute($params);
    $workdays = $stmt->fetchAll();

    foreach ($workdays as $wd) {
        $hours = workday_hours((string) $wd['start_time'], (string) $wd['end_time'], (int) $wd['break_minutes']);
        $workdaySummary['count']++;
        $workdaySummary['hours'] += $hours;
        $workdaySummary['cost'] += (($wd['payment_type'] ?? 'hourly') === 'piece')
            ? (((($wd['piece_pricing_mode'] ?? 'unit') === 'fixed')
                ? (float)($wd['piece_fixed_price'] ?? 0)
                : ((float)($wd['piece_quantity'] ?? 0) * (float)($wd['piece_rate'] ?? 0))))
            : ($hours * (float) $wd['hourly_rate']);
        $workdaySummary['mileage'] += (float) $wd['mileage_km'];
    }
} elseif ($view === 'price-list') {
    $priceListItems = $pdo->query("SELECT * FROM admin_price_list ORDER BY CASE WHEN item_type='package' THEN 0 ELSE 1 END, sort_order ASC, category ASC, name ASC")->fetchAll();
} elseif ($view === 'workers') {
    $workers = $pdo->query("SELECT * FROM admin_workers ORDER BY FIELD(status, 'free', 'waiting', 'busy'), name ASC, id DESC")->fetchAll();
    $workerCounts['all'] = count($workers);
    foreach ($workers as $worker) {
        $st = (string) ($worker['status'] ?? 'free');
        if (isset($workerCounts[$st])) $workerCounts[$st]++;
    }
} elseif ($view === 'calculations') {
    try {
        $cachedFuelPrice = $_SESSION['fuel_price_tallinn_95'] ?? null;
        $refreshFuelPrice = isset($_GET['fuel_refresh']);
        if (!$refreshFuelPrice && is_array($cachedFuelPrice) && (int) ($cachedFuelPrice['fetchedAt'] ?? 0) > time() - 1800) {
            $fuelPriceInfo = $cachedFuelPrice;
        } else {
            $fuelPriceInfo = fetch_cheapest_tallinn_95();
            $fuelPriceInfo['fetchedAt'] = time();
            $_SESSION['fuel_price_tallinn_95'] = $fuelPriceInfo;
        }
    } catch (Throwable $error) {
        $fuelPriceError = $error->getMessage();
    }
} elseif ($view === 'subscribers') {
    $subscribers = $pdo->query("
        SELECT id, email, status, subscribed_at, unsubscribed_at, source, created_at, updated_at
        FROM newsletter_subscribers
        WHERE status = 'subscribed'
        ORDER BY COALESCE(updated_at, subscribed_at, created_at) DESC, id DESC
    ")->fetchAll();
} elseif ($view === 'unsubscribers') {
    $unsubscribers = $pdo->query("
        SELECT id, email, status, subscribed_at, unsubscribed_at, source, created_at, updated_at
        FROM newsletter_subscribers
        WHERE status = 'unsubscribed'
        ORDER BY COALESCE(unsubscribed_at, updated_at, created_at) DESC, id DESC
    ")->fetchAll();
} elseif ($view === 'statistics') {
    try {
        $credentialsPath = __DIR__ . '/private-google/renoveerikodu-analytics.json';
        $propertyId = '527723533';
        $startDate = $analyticsDays . 'daysAgo';
        $cacheKey = 'ga4_' . $propertyId . '_' . $analyticsDays;
        $refreshRequested = isset($_GET['refresh']);
        $cached = $_SESSION['analytics_cache'][$cacheKey] ?? null;

        if (!$refreshRequested && is_array($cached) && (int) ($cached['fetchedAt'] ?? 0) > time() - 300) {
            $analyticsData = $cached;
        } else {
            $token = ga_access_token($credentialsPath);
            $dateRange = [['startDate' => $startDate, 'endDate' => 'today']];

            $overview = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'metrics' => array_map(static fn(string $name): array => ['name' => $name], [
                    'activeUsers', 'newUsers', 'sessions', 'screenPageViews',
                    'averageSessionDuration', 'engagementRate', 'bounceRate', 'keyEvents',
                ]),
            ]);
            $trend = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'date']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
                'limit' => 100,
            ]);
            $pages = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'pagePath']],
                'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers'], ['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => 10,
            ]);
            $sources = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers'], ['name' => 'screenPageViews']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 10,
            ]);
            $devices = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'deviceCategory']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
                'limit' => 10,
            ]);
            $countries = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'country']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
                'limit' => 10,
            ]);
            $events = ga_report($token, $propertyId, [
                'dateRanges' => $dateRange,
                'dimensions' => [['name' => 'eventName']],
                'metrics' => [['name' => 'eventCount'], ['name' => 'keyEvents']],
                'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
                'limit' => 10,
            ]);
            $realtime = ga_report($token, $propertyId, [
                'metrics' => [['name' => 'activeUsers']],
            ], true);

            $analyticsData = [
                'overview' => $overview,
                'trend' => ga_rows($trend),
                'pages' => ga_rows($pages),
                'sources' => ga_rows($sources),
                'devices' => ga_rows($devices),
                'countries' => ga_rows($countries),
                'events' => ga_rows($events),
                'realtimeUsers' => ga_first_metric($realtime, 'activeUsers'),
                'fetchedAt' => time(),
            ];
            $_SESSION['analytics_cache'][$cacheKey] = $analyticsData;
        }
    } catch (Throwable $error) {
        $analyticsError = $error->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - <?= h($viewTitles[$view] ?? 'Admin') ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
body{background:#f5f7fb;font-family:Arial,sans-serif;margin:0;padding:24px;color:#17202a}.topbar{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#fff;padding:18px 20px;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:20px}.topbar h1{font-size:24px;margin:0}.topbar-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.admin-tabs{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.admin-tab{border:1px solid #cfd8e3;border-radius:4px;color:#17202a;padding:9px 12px;text-decoration:none;background:#fff}.admin-tab.active{background:#17202a;border-color:#17202a;color:#fff}.admin-tab-count{color:#697386;font-size:13px;margin-left:4px}.admin-tab.active .admin-tab-count{color:#d7dde5}.logout,.button-delete{background:#d9534f;color:#fff;border:0;border-radius:4px;padding:9px 12px;text-decoration:none;cursor:pointer}.table-wrap{overflow:auto;background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06)}table{border-collapse:collapse;width:100%;min-width:960px}th,td{border-bottom:1px solid #e7ebf0;padding:10px;text-align:left;vertical-align:top}th{background:#f1f4f8}.message{max-width:320px;white-space:pre-wrap}.preview-img{width:120px;height:90px;object-fit:cover;display:block;margin:0 0 6px;border:1px solid #d7dde5;border-radius:6px;background:#eef2f7}.lightbox-item{display:block;width:max-content}.lightbox-item:hover .preview-img{border-color:#17202a;box-shadow:0 4px 14px rgba(0,0,0,.16)}.admin-lightbox{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(9,15,23,.88);padding:28px}.admin-lightbox.open{display:flex}.lightbox-panel{position:relative;display:flex;align-items:center;justify-content:center;width:100%;height:100%;max-width:1180px;max-height:92vh}.lightbox-image{max-width:100%;max-height:82vh;object-fit:contain;border-radius:6px;background:#111;box-shadow:0 16px 50px rgba(0,0,0,.45)}.lightbox-close,.lightbox-prev,.lightbox-next{position:absolute;border:0;border-radius:4px;background:rgba(255,255,255,.92);color:#17202a;cursor:pointer;font-size:24px;line-height:1}.lightbox-close{top:0;right:0;padding:10px 14px}.lightbox-prev,.lightbox-next{top:50%;transform:translateY(-50%);padding:16px 18px}.lightbox-prev{left:0}.lightbox-next{right:0}.lightbox-prev:disabled,.lightbox-next:disabled{opacity:.28;cursor:not-allowed}.lightbox-caption{position:absolute;left:0;right:0;bottom:0;color:#fff;text-align:center;font-size:15px;padding:10px 48px}.muted{color:#697386}.file-link{display:block;margin-bottom:6px;max-width:180px;overflow-wrap:anywhere}.actions{white-space:nowrap}.calculator-wrap{background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);padding:22px;max-width:760px}.calculator-title{margin:0 0 6px;font-size:22px}.calculator-subtitle{margin:0 0 22px;color:#697386;line-height:1.45}.calculator-grid{display:grid;grid-template-columns:1fr 180px;gap:14px 18px;align-items:center}.calculator-grid label{font-weight:700}.calculator-label-info{display:block;color:#98a2b3;font-size:12px;font-weight:400;line-height:1.35;margin-top:4px}.calculator-input-wrap{display:flex;align-items:center;border:1px solid #cfd8e3;border-radius:5px;background:#fff;overflow:hidden}.calculator-input-wrap input{width:100%;min-width:0;border:0;padding:11px 12px;font:inherit;outline:none}.calculator-input-wrap span{flex:0 0 auto;border-left:1px solid #e1e6ec;background:#f6f8fb;color:#697386;padding:11px 10px;font-size:14px}.calculator-results{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}.calculator-result{background:#f1f4f8;border:1px solid #e0e6ed;border-radius:7px;padding:16px}.calculator-result-label{display:block;color:#697386;font-size:13px;margin-bottom:6px}.calculator-result-value{font-size:25px;font-weight:700}.calculator-actions{display:flex;justify-content:flex-end;margin-top:16px}.button-reset{background:#fff;color:#d9534f;border:1px solid #d9534f;border-radius:4px;padding:9px 12px;cursor:pointer;font:inherit}.objects-wrap{background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);padding:22px}.objects-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.objects-title{margin:0 0 6px;font-size:22px}.objects-subtitle{margin:0;color:#697386}.object-add-toggle{border:1px solid #17202a;background:#17202a;color:#fff;border-radius:5px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}.object-saved,.object-error{margin:0 0 14px;padding:10px 12px;border-radius:6px}.object-saved{border:1px solid #b7e4c7;background:#f0fff4;color:#246b3a}.object-error{border:1px solid #f1b5b2;background:#fff4f3;color:#a61b1b}.object-add-panel{display:none;margin:0 0 18px;padding:16px;border:1px solid #d9e2ec;border-radius:8px;background:#f8fafc}.object-add-panel.open{display:block}.object-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.object-field{display:flex;flex-direction:column;gap:6px}.object-field.full{grid-column:1/-1}.object-field label{font-size:13px;font-weight:700;color:#344054}.object-field input,.object-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfd8e3;border-radius:5px;padding:10px 11px;font:inherit;background:#fff;color:#17202a}.object-field textarea{min-height:100px;resize:vertical}.object-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.object-list{display:flex;flex-direction:column;gap:10px}.object-card{border:1px solid #d9e2ec;border-radius:8px;background:#fff;overflow:hidden}.object-card summary{display:flex;align-items:center;justify-content:space-between;gap:14px;list-style:none;cursor:pointer;padding:15px 16px;font-weight:700;background:#f8fafc}.object-card summary::-webkit-details-marker{display:none}.object-summary-main{display:flex;align-items:center;min-width:0;gap:10px}.object-chevron{display:inline-block;width:9px;height:9px;border-right:2px solid #667085;border-bottom:2px solid #667085;transform:rotate(45deg);transition:transform .18s ease;flex:0 0 auto;margin-top:-4px}.object-card[open] .object-chevron{transform:rotate(225deg);margin-top:4px}.object-summary-text{min-width:0}.object-summary-name{display:block;color:#17202a;overflow-wrap:anywhere}.object-summary-address{display:block;color:#697386;font-size:13px;font-weight:400;margin-top:3px;overflow-wrap:anywhere}.object-card-body{padding:16px}.object-card .object-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.object-phone-link{color:#17202a;text-decoration:none}.waze-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#33ccff;color:#083344;border:0;border-radius:5px;padding:10px 13px;text-decoration:none;font-weight:700;white-space:nowrap}.waze-button:hover{filter:brightness(.96)}.object-save{background:#17202a;color:#fff;border:0;border-radius:5px;padding:10px 14px;cursor:pointer;font:inherit}.object-delete{background:#fff;color:#d9534f;border:1px solid #d9534f;border-radius:5px;padding:10px 14px;cursor:pointer;font:inherit}.object-nav-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.object-empty{padding:18px;border:1px dashed #cfd8e3;border-radius:8px;color:#697386;text-align:center}@media(max-width:700px){body{padding:12px}.topbar{align-items:flex-start;flex-direction:column}.topbar-actions{align-items:flex-start;flex-direction:column}.admin-lightbox{padding:16px}.lightbox-prev,.lightbox-next{padding:12px 14px}.lightbox-caption{padding:10px 40px}}
</style>
<style>
.calculations-layout{display:grid;grid-template-columns:minmax(0,760px) minmax(300px,390px);gap:18px;align-items:start}.calculations-layout .calculator-wrap{max-width:none}.fuel-live-card{background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);padding:22px;border:1px solid #e2e8f0}.fuel-live-badge{display:inline-flex;align-items:center;gap:7px;color:#246b3a;background:#f0fff4;border:1px solid #b7e4c7;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700}.fuel-live-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:#2da44e}.fuel-live-title{font-size:20px;margin:14px 0 5px}.fuel-live-subtitle{margin:0 0 18px;color:#697386;line-height:1.45}.fuel-live-price{display:flex;align-items:flex-end;gap:6px;margin-bottom:14px}.fuel-live-price strong{font-size:36px;line-height:1;color:#17202a}.fuel-live-price span{color:#697386;font-weight:700}.fuel-live-station{font-weight:700;line-height:1.45;margin-bottom:10px}.fuel-live-meta{display:flex;gap:8px;flex-wrap:wrap;color:#697386;font-size:13px}.fuel-live-meta span{background:#f6f8fb;border-radius:5px;padding:6px 8px}.fuel-live-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:18px}.fuel-live-button{display:flex;align-items:center;justify-content:center;border:1px solid #17202a;border-radius:5px;background:#17202a;color:#fff;padding:10px 11px;text-decoration:none;font-weight:700;cursor:pointer;font:inherit}.fuel-live-button.secondary{background:#fff;color:#17202a}.fuel-live-source{display:block;color:#98a2b3;font-size:12px;line-height:1.45;margin-top:14px}.fuel-live-error{border:1px solid #f1b5b2;background:#fff4f3;color:#8a1c1c;border-radius:6px;padding:12px;line-height:1.45}.fuel-live-error strong{display:block;margin-bottom:4px}
.admin-menu-group{position:relative}
.admin-menu-group>summary{display:flex;align-items:center;gap:8px;list-style:none;cursor:pointer;user-select:none}
.admin-menu-group>summary::-webkit-details-marker{display:none}
.admin-menu-group>summary::after{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-4px 1px 0 3px;transition:transform .18s ease}
.admin-menu-group[open]>summary::after{transform:rotate(225deg);margin-top:4px}
.admin-submenu{position:absolute;z-index:20;top:calc(100% + 8px);left:0;display:flex;min-width:210px;flex-direction:column;gap:6px;padding:8px;background:#fff;border:1px solid #d9e2ec;border-radius:7px;box-shadow:0 12px 30px rgba(16,24,40,.16)}
.admin-submenu .admin-tab{display:flex;align-items:center;justify-content:space-between;white-space:nowrap}
.price-wrap{background:#fff;border-radius:8px;box-shadow:0 6px 22px rgba(0,0,0,.06);padding:22px}.price-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.price-head h2{margin:0 0 6px;font-size:22px}.price-head p{margin:0;color:#697386}.price-add-toggle{background:#17202a;color:#fff;border:0;border-radius:5px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}.price-intro{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.price-package{border:1px solid #cfe4dd;background:#f3fbf8;border-radius:9px;padding:17px}.price-package span{display:block;color:#667085;font-size:12px;margin-bottom:6px}.price-package strong{display:block;font-size:19px;margin-bottom:8px}.price-package-price{font-size:25px;font-weight:800;color:#08745b}.price-package p{margin:7px 0 0;color:#667085;font-size:13px;line-height:1.4}.price-panel{display:none;border:1px solid #d9e2ec;background:#f8fafc;border-radius:8px;padding:16px;margin-bottom:18px}.price-panel.open{display:block}.price-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.price-field{display:flex;flex-direction:column;gap:6px}.price-field.full{grid-column:1/-1}.price-field label{font-size:12px;font-weight:700;color:#344054}.price-field input,.price-field select,.price-field textarea{box-sizing:border-box;width:100%;border:1px solid #cfd8e3;border-radius:5px;padding:10px 11px;background:#fff;font:inherit;color:#17202a}.price-field textarea{min-height:80px;resize:vertical}.price-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}.price-save{background:#17202a;color:#fff;border:0;border-radius:5px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}.price-delete{background:#fff;color:#d9534f;border:1px solid #d9534f;border-radius:5px;padding:9px 12px;font:inherit;cursor:pointer}.price-alert{padding:11px 13px;border-radius:6px;margin-bottom:14px}.price-alert.ok{background:#f0fff4;border:1px solid #b7e4c7;color:#246b3a}.price-alert.error{background:#fff4f3;border:1px solid #f1b5b2;color:#a61b1b}.price-category{margin-top:20px}.price-category h3{margin:0 0 10px;font-size:18px}.price-edit-card{border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;margin-bottom:8px}.price-edit-card summary{list-style:none;cursor:pointer;padding:12px 14px;background:#f8fafc}.price-edit-card summary::-webkit-details-marker{display:none}.price-summary{display:grid;grid-template-columns:minmax(260px,2fr) 90px 120px 160px 25px;gap:12px;align-items:center}.price-edit-body{padding:14px}.price-money{font-weight:800;white-space:nowrap}.price-note{color:#667085;font-size:12px}.price-chevron{width:8px;height:8px;border-right:2px solid #667085;border-bottom:2px solid #667085;transform:rotate(45deg)}.price-edit-card[open] .price-chevron{transform:rotate(225deg)}
.price-search-box{margin:0 0 20px;border:1px solid #d9e2ec;border-radius:10px;background:#f8fafc;padding:16px}.price-search-title{font-size:15px;font-weight:800;margin:0 0 7px}.price-search-help{margin:0 0 12px;color:#667085;font-size:13px}.price-search-input-wrap{position:relative}.price-search-input{box-sizing:border-box;width:100%;border:1px solid #b8c4d1;border-radius:8px;background:#fff;padding:13px 44px 13px 14px;font:inherit;font-size:16px;color:#17202a;outline:none}.price-search-input:focus{border-color:#17202a;box-shadow:0 0 0 3px rgba(23,32,42,.08)}.price-search-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#667085;font-size:18px;pointer-events:none}.price-search-results{display:none;margin-top:10px;border:1px solid #d9e2ec;border-radius:8px;background:#fff;overflow:hidden}.price-search-results.open{display:block}.price-search-result{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:12px 14px;border-bottom:1px solid #edf1f5;cursor:pointer}.price-search-result:last-child{border-bottom:0}.price-search-result:hover,.price-search-result.active{background:#f3f7fa}.price-search-main{min-width:0}.price-search-name{display:block;font-weight:800;color:#17202a;overflow-wrap:anywhere}.price-search-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:4px;color:#667085;font-size:12px}.price-search-price{text-align:right;white-space:nowrap}.price-search-price strong{display:block;font-size:17px}.price-search-price span{display:block;color:#667085;font-size:12px;margin-top:3px}.price-search-empty{padding:14px;color:#667085;text-align:center}.price-search-match{background:#fff1a8;border-radius:2px;padding:0 1px}.price-search-tip{margin-top:9px;color:#98a2b3;font-size:12px}
.analytics-main{background:transparent;box-shadow:none;overflow:visible}
.analytics-wrap{display:flex;flex-direction:column;gap:18px}
.analytics-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;background:#fff;border-radius:8px;padding:18px 20px;box-shadow:0 6px 22px rgba(0,0,0,.06)}
.analytics-toolbar h2{margin:0 0 5px;font-size:22px}.analytics-toolbar p{margin:0;color:#697386}
.analytics-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.analytics-period{display:flex;gap:6px}.analytics-period a,.analytics-refresh{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cfd8e3;border-radius:5px;background:#fff;color:#17202a;padding:9px 11px;text-decoration:none;font-weight:700}.analytics-period a.active{background:#17202a;border-color:#17202a;color:#fff}.analytics-refresh{color:#246b3a;border-color:#9fd5ae}
.analytics-error{padding:16px 18px;border:1px solid #f1b5b2;border-radius:8px;background:#fff4f3;color:#8a1c1c;line-height:1.5}.analytics-error strong{display:block;margin-bottom:5px}
.analytics-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.analytics-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:17px;box-shadow:0 5px 18px rgba(16,24,40,.05)}.analytics-card.realtime{border-color:#b7e4c7;background:#f0fff4}.analytics-card-label{display:block;color:#697386;font-size:13px;margin-bottom:8px}.analytics-card-value{font-size:27px;line-height:1;font-weight:700;color:#17202a}.analytics-card-note{display:block;color:#98a2b3;font-size:12px;margin-top:7px}
.analytics-panel{background:#fff;border-radius:8px;padding:20px;box-shadow:0 6px 22px rgba(0,0,0,.06)}.analytics-panel-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px}.analytics-panel h3{margin:0;font-size:18px}.analytics-panel-subtitle{color:#697386;font-size:13px;margin-top:4px}.analytics-chart-wrap{position:relative;width:100%;height:300px}.analytics-chart-wrap canvas{display:block;width:100%;height:100%}.analytics-chart-legend{display:flex;gap:16px;flex-wrap:wrap;color:#667085;font-size:13px}.analytics-chart-legend span{display:inline-flex;align-items:center;gap:6px}.analytics-chart-legend i{display:inline-block;width:10px;height:10px;border-radius:50%}
.analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.analytics-table-wrap{overflow:auto}.analytics-table{min-width:520px}.analytics-table th,.analytics-table td{padding:10px 8px}.analytics-table th{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#667085}.analytics-table td:first-child{max-width:300px;overflow-wrap:anywhere}.analytics-empty{padding:18px;text-align:center;color:#697386}.analytics-updated{font-size:12px;color:#98a2b3}

.workdays-wrap{background:transparent;box-shadow:none;padding:0}.workdays-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;background:#fff;border-radius:8px;padding:20px 22px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:14px}.workdays-head h2{margin:0 0 5px;font-size:22px}.workdays-head p{margin:0;color:#697386}.workday-add{border:0;border-radius:6px;background:#17202a;color:#fff;padding:11px 15px;font:inherit;font-weight:700;cursor:pointer;white-space:nowrap}.workday-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.workday-stat{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;box-shadow:0 5px 18px rgba(16,24,40,.05)}.workday-stat span{display:block;color:#697386;font-size:12px;margin-bottom:6px}.workday-stat strong{font-size:24px;line-height:1.1}.workday-form-panel{display:none;background:#fff;border-radius:8px;padding:20px 22px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:14px}.workday-form-panel.open{display:block}.workday-form-title{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.workday-form-title h3{margin:0;font-size:18px}.workday-close{border:0;background:transparent;color:#697386;font-size:24px;cursor:pointer}.workday-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.workday-field{display:flex;flex-direction:column;gap:6px}.workday-field.span2{grid-column:span 2}.workday-field.full{grid-column:1/-1}.workday-field label{font-size:12px;font-weight:700;color:#344054}.workday-field input,.workday-field select,.workday-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfd8e3;border-radius:6px;padding:10px 11px;font:inherit;background:#fff;color:#17202a}.workday-field textarea{min-height:92px;resize:vertical}.workday-live-calc{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:11px 12px;font-size:13px;color:#667085}.workday-live-calc strong{color:#17202a;font-size:16px}.workday-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.workday-save{border:0;border-radius:6px;background:#17202a;color:#fff;padding:10px 15px;font:inherit;font-weight:700;cursor:pointer}.workday-cancel{border:1px solid #cfd8e3;border-radius:6px;background:#fff;color:#344054;padding:10px 15px;font:inherit;cursor:pointer}.workday-alert{padding:12px 14px;border-radius:7px;margin-bottom:14px}.workday-alert.ok{background:#f0fff4;border:1px solid #b7e4c7;color:#246b3a}.workday-alert.error{background:#fff4f3;border:1px solid #f1b5b2;color:#8a1c1c}.workday-filters{display:grid;grid-template-columns:minmax(180px,1.4fr) repeat(5,minmax(120px,1fr)) auto;gap:8px;background:#fff;border-radius:8px;padding:12px;box-shadow:0 6px 22px rgba(0,0,0,.06);margin-bottom:14px}.workday-filters input,.workday-filters select{min-width:0;border:1px solid #cfd8e3;border-radius:6px;padding:9px 10px;font:inherit;background:#fff}.workday-filter-button{border:0;border-radius:6px;background:#17202a;color:#fff;padding:9px 13px;font:inherit;font-weight:700;cursor:pointer}.workday-clear{display:flex;align-items:center;justify-content:center;border:1px solid #cfd8e3;border-radius:6px;color:#344054;text-decoration:none;padding:9px 11px;background:#fff}.workday-list{display:flex;flex-direction:column;gap:10px}.workday-card{background:#fff;border:1px solid #dfe5ec;border-radius:9px;box-shadow:0 5px 18px rgba(16,24,40,.04);overflow:hidden}.workday-card summary{list-style:none;cursor:pointer;padding:14px 16px}.workday-card summary::-webkit-details-marker{display:none}.workday-card-main{display:grid;grid-template-columns:150px minmax(160px,.9fr) minmax(230px,1.3fr) minmax(180px,.9fr) 130px 110px 28px;gap:14px;align-items:center}.workday-date strong,.workday-person strong,.workday-object strong{display:block}.workday-date span,.workday-person span,.workday-object span{display:block;color:#697386;font-size:12px;margin-top:4px}.workday-payment-summary{min-width:0}.workday-payment-summary strong{display:block;font-size:13px}.workday-payment-summary span{display:block;color:#697386;font-size:12px;margin-top:4px;white-space:normal}.workday-payment-summary.piece strong{color:#7a2e0e}.workday-payment-summary.hourly strong{color:#344054}.workday-time{font-weight:700}.workday-hours{font-size:18px;font-weight:700;text-align:right}.workday-status{display:inline-flex;align-items:center;width:max-content;border-radius:999px;padding:5px 8px;font-size:11px;font-weight:700;margin-top:5px}.workday-status.confirmed{background:#ecfdf3;color:#027a48}.workday-status.pending{background:#fff7ed;color:#c2410c}.workday-status.draft{background:#f2f4f7;color:#667085}.workday-status.paid{background:#ecfdf3;color:#027a48}.workday-quick-confirm{display:inline-block;margin:0}.workday-status-button{border:1px solid #fed7aa;cursor:pointer;font:inherit;font-size:11px;font-weight:700}.workday-status-button:hover{background:#ffedd5;border-color:#fdba74}.workday-status-button:focus{outline:2px solid #fb923c;outline-offset:2px}.workday-chevron{width:8px;height:8px;border-right:2px solid #667085;border-bottom:2px solid #667085;transform:rotate(45deg);transition:.18s}.workday-card[open] .workday-chevron{transform:rotate(225deg)}.workday-detail{border-top:1px solid #edf1f5;padding:16px;background:#fcfdff}.workday-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.workday-meta span{background:#f2f4f7;border-radius:999px;padding:6px 9px;font-size:12px;color:#475467}.workday-delete{border:1px solid #d9534f;border-radius:6px;background:#fff;color:#d9534f;padding:10px 14px;font:inherit;cursor:pointer}.workday-empty{background:#fff;border:1px dashed #cfd8e3;border-radius:8px;padding:32px;text-align:center;color:#697386}.workday-address-link{color:#175cd3;text-decoration:none}.workday-edit-heading{font-size:13px;font-weight:700;color:#344054;margin:0 0 12px}
.worker-wrap{display:flex;flex-direction:column;gap:14px}.worker-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;background:#fff;border-radius:8px;padding:20px 22px;box-shadow:0 6px 22px rgba(0,0,0,.06)}.worker-head h2{margin:0 0 5px;font-size:22px}.worker-head p{margin:0;color:#697386}.worker-add-toggle{border:0;border-radius:6px;background:#17202a;color:#fff;padding:11px 15px;font:inherit;font-weight:700;cursor:pointer}.worker-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.worker-stat{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:15px;box-shadow:0 5px 18px rgba(16,24,40,.05)}.worker-stat span{display:block;font-size:12px;color:#697386;margin-bottom:6px}.worker-stat strong{font-size:23px}.worker-panel{display:none;background:#fff;border-radius:8px;padding:20px 22px;box-shadow:0 6px 22px rgba(0,0,0,.06)}.worker-panel.open{display:block}.worker-panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.worker-panel-head h3{margin:0;font-size:18px}.worker-close{border:0;background:transparent;color:#667085;font-size:24px;cursor:pointer}.worker-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.worker-field{display:flex;flex-direction:column;gap:6px}.worker-field.full{grid-column:1/-1}.worker-field label{font-size:12px;font-weight:700;color:#344054}.worker-field input,.worker-field select,.worker-field textarea{width:100%;box-sizing:border-box;border:1px solid #cfd8e3;border-radius:6px;padding:10px 11px;font:inherit;background:#fff}.worker-field textarea{min-height:85px;resize:vertical}.worker-form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.worker-save{border:0;border-radius:6px;background:#17202a;color:#fff;padding:10px 15px;font:inherit;font-weight:700;cursor:pointer}.worker-alert{padding:12px 14px;border-radius:7px}.worker-alert.ok{background:#f0fff4;border:1px solid #b7e4c7;color:#246b3a}.worker-alert.error{background:#fff4f3;border:1px solid #f1b5b2;color:#8a1c1c}.worker-list{display:flex;flex-direction:column;gap:10px}.worker-card{background:#fff;border:1px solid #dfe5ec;border-radius:9px;box-shadow:0 5px 18px rgba(16,24,40,.04);overflow:hidden}.worker-card summary{list-style:none;cursor:pointer;padding:15px 16px}.worker-card summary::-webkit-details-marker{display:none}.worker-card-row{display:grid;grid-template-columns:minmax(160px,1fr) minmax(180px,1fr) minmax(220px,1.3fr) minmax(120px,.7fr) 120px 24px;gap:16px;align-items:center}.worker-main strong{display:block;font-size:16px}.worker-sub{display:block;color:#697386;font-size:12px;margin-top:4px}.worker-col-label{display:block;color:#98a2b3;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}.worker-status{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;width:max-content}.worker-status.busy{background:#fef3f2;color:#b42318;border:1px solid #fecdca}.worker-status.waiting{background:#eff8ff;color:#175cd3;border:1px solid #b2ddff}.worker-status.free{background:#ecfdf3;color:#027a48;border:1px solid #abefc6}.worker-chevron{width:8px;height:8px;border-right:2px solid #667085;border-bottom:2px solid #667085;transform:rotate(45deg);transition:.18s}.worker-card[open] .worker-chevron{transform:rotate(225deg)}.worker-detail{border-top:1px solid #edf1f5;padding:16px;background:#fcfdff}.worker-delete{border:1px solid #d9534f;border-radius:6px;background:#fff;color:#d9534f;padding:10px 14px;font:inherit;cursor:pointer;margin-top:10px}.worker-empty{background:#fff;border:1px dashed #cfd8e3;border-radius:8px;padding:32px;text-align:center;color:#697386}.worker-contact-link{color:#175cd3;text-decoration:none}
@media(max-width:760px){
  .worker-head{padding:16px;flex-direction:column}.worker-add-toggle{width:100%}.worker-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.worker-panel{padding:15px}.worker-grid{grid-template-columns:1fr}.worker-field.full{grid-column:auto}.worker-form-actions{flex-direction:column}.worker-save{width:100%}.worker-card-row{grid-template-columns:1fr auto;gap:10px}.worker-main,.worker-contact,.worker-role,.worker-exp,.worker-status-wrap{grid-column:1}.worker-status-wrap{grid-row:auto}.worker-chevron{grid-column:2;grid-row:1/6;align-self:center}.worker-detail{padding:14px}
  .workdays-head{padding:16px;flex-direction:column}.workday-add{width:100%}.workday-summary{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.workday-stat{padding:13px}.workday-stat strong{font-size:21px}.workday-form-panel{padding:15px}.workday-grid{grid-template-columns:1fr}.workday-field.span2,.workday-field.full{grid-column:auto}.workday-form-actions{flex-direction:column}.workday-save,.workday-cancel{width:100%}.workday-filters{grid-template-columns:1fr}.workday-filter-button,.workday-clear{width:100%;box-sizing:border-box}.workday-card-main{grid-template-columns:1fr auto;gap:9px}.workday-date,.workday-person,.workday-object,.workday-payment-summary,.workday-time{grid-column:1}.workday-hours{grid-column:2;grid-row:1/6;align-self:center}.workday-chevron{grid-column:2;grid-row:5;justify-self:end}.workday-detail{padding:14px}
  .calculations-layout{grid-template-columns:1fr;gap:14px}.fuel-live-card{padding:16px}.fuel-live-price strong{font-size:32px}.fuel-live-actions{grid-template-columns:1fr}
  body{background:#eef2f7;padding:10px;color:#101828}
  .topbar{position:static;align-items:stretch;gap:14px;padding:16px;border-radius:8px;margin-bottom:14px}
  .topbar h1{font-size:24px;line-height:1.1}
  .topbar-actions{align-items:stretch;width:100%}
  .admin-tabs{display:grid;grid-template-columns:1fr;gap:8px;width:100%}
  .admin-tab{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;font-size:16px}
  .admin-menu-group{width:100%}
  .admin-menu-group>summary{box-sizing:border-box;justify-content:space-between;width:100%}
  .admin-submenu{position:static;box-sizing:border-box;min-width:0;width:100%;margin-top:8px;padding:8px;box-shadow:none;background:#f8fafc}
  .admin-submenu .admin-tab{background:#fff}
  .analytics-toolbar{padding:15px}.analytics-filters{width:100%}.analytics-period{display:grid;grid-template-columns:repeat(3,1fr);width:100%}.analytics-period a{padding:11px 8px}.analytics-refresh{width:100%;box-sizing:border-box}
  .analytics-cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.analytics-card{padding:14px}.analytics-card-value{font-size:23px}.analytics-grid{grid-template-columns:1fr;gap:14px}.analytics-panel{padding:15px}.analytics-chart-wrap{height:250px}.analytics-table{display:table;min-width:520px}.analytics-table thead{display:table-header-group}.analytics-table tbody{display:table-row-group}.analytics-table tr{display:table-row;border:0;box-shadow:none;margin:0}.analytics-table th,.analytics-table td{display:table-cell}.analytics-table td::before{display:none}
  .logout{display:block;text-align:center;width:100%;box-sizing:border-box;padding:12px 14px}
  .table-wrap{overflow:visible;background:transparent;border-radius:0;box-shadow:none}
  table{display:block;width:100%;min-width:0;border-collapse:separate}
  thead{display:none}
  tbody{display:block}
  tr{display:block;background:#fff;border:1px solid #d9e2ec;border-radius:8px;box-shadow:0 8px 24px rgba(16,24,40,.07);margin:0 0 14px;overflow:hidden}
  td{display:grid;grid-template-columns:96px minmax(0,1fr);gap:10px;align-items:start;border-bottom:1px solid #edf1f5;padding:11px 14px;font-size:15px;line-height:1.35;overflow-wrap:anywhere}
  td::before{content:attr(data-label);color:#667085;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
  td:last-child{border-bottom:0}
  td.message{display:block;max-width:none;white-space:pre-wrap;font-size:16px;line-height:1.45}
  td.message::before{display:block;margin-bottom:7px}
  td[data-label="Failid"]{display:block}
  td[data-label="Failid"]::before{display:block;margin-bottom:8px}
  td[data-label="Failid"]{overflow-x:auto;white-space:nowrap;padding-bottom:6px}
  .preview-img{width:104px;height:82px;margin:0 8px 8px 0}
  .lightbox-item{display:inline-block;vertical-align:top}
  .file-link{display:inline-block;max-width:124px;font-size:13px;margin:0 8px 10px 0;vertical-align:top;white-space:normal}
  .actions{display:block;white-space:normal;padding:12px 14px}
  .actions::before{display:none}
  .actions form{margin:0}
  .button-delete{width:100%;padding:12px 14px;font-size:16px}
  .muted{font-size:14px}
  .calculator-wrap{padding:16px;max-width:none}
  .calculator-title{font-size:21px}
  .calculator-grid{grid-template-columns:1fr;gap:7px}
  .calculator-grid label{margin-top:8px}
  .calculator-input-wrap input{padding:13px 12px;font-size:16px}
  .calculator-input-wrap span{padding:13px 10px}
  .calculator-results{grid-template-columns:1fr;margin-top:18px}
  .calculator-actions{justify-content:stretch}
  .button-reset{width:100%;padding:12px 14px;font-size:16px}
  .objects-wrap{padding:14px;background:#fff;box-shadow:none}
  .objects-head{flex-direction:column;align-items:stretch}
  .objects-title{font-size:21px}
  .object-add-toggle{width:100%;padding:12px 14px}
  .object-form-grid,.object-card .object-form-grid{grid-template-columns:1fr}
  .object-field.full{grid-column:auto}
  .object-card summary{padding:14px}
  .object-card-body{padding:14px}
  .object-form-actions{flex-direction:column}
  .waze-button,.object-save,.object-delete{width:100%;box-sizing:border-box;text-align:center;padding:12px 14px;font-size:16px}
  .object-nav-row{align-items:stretch;flex-direction:column}
  .admin-lightbox{padding:14px}
  .lightbox-image{max-height:76vh}
  .lightbox-close{top:4px;right:4px}
  .lightbox-prev,.lightbox-next{padding:12px 14px}
  .lightbox-caption{font-size:13px;padding:10px 40px}
}
</style>

<style>
.request-quote-button{display:inline-flex;align-items:center;justify-content:center;background:#087b65;color:#fff;border:0;border-radius:5px;padding:9px 12px;text-decoration:none;font-weight:700;margin-bottom:7px;white-space:nowrap}.request-quote-button:hover{filter:brightness(.95)}
.quote-list-body{display:grid;grid-template-columns:minmax(140px,.7fr) minmax(180px,1fr) minmax(220px,1.2fr) minmax(220px,1.25fr) auto auto;gap:16px;align-items:center}.quote-contact-block{line-height:1.45}.quote-contact-block a{color:inherit;text-decoration:none}.quote-contact-block a:hover{text-decoration:underline}.quote-delete-form{margin:0}.quote-delete-button{background:#fff;color:#d9534f;border:1px solid #d9534f;border-radius:5px;padding:10px 14px;cursor:pointer;font:inherit;font-weight:700;white-space:nowrap}.quote-delete-button:hover{background:#fff4f3}
.quote-send-form{margin:0;display:flex;align-items:center}.quote-send-wrap{position:relative;display:inline-flex}.quote-send-wrap::after{content:"";position:absolute;right:12px;top:50%;width:6px;height:6px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:translateY(-65%) rotate(45deg);pointer-events:none}.quote-send-select{appearance:none;-webkit-appearance:none;border:1px solid transparent;border-radius:999px;padding:8px 31px 8px 12px;font:inherit;font-size:13px;font-weight:800;cursor:pointer;outline:none}.quote-send-select.sent{background:#eaf8ef;color:#24753a;border-color:#a9dfba}.quote-send-select.unsent{background:#fff4e5;color:#b45309;border-color:#f5c98a}.quote-status-saved{margin-bottom:10px;padding:9px 12px;border:1px solid #b7e4c7;background:#f0fff4;color:#246b3a;border-radius:6px;font-size:14px}
@media(max-width:900px){.quote-list-body{grid-template-columns:1fr 1fr}.quote-list-title,.quote-contact-block{grid-column:1/-1}.quote-send-form,.quote-send-wrap,.quote-send-select{width:100%}.quote-list-body .object-save,.quote-delete-button{width:100%;box-sizing:border-box}.quote-delete-form{width:100%}}
.quote-shell{background:#fff;border-radius:10px;box-shadow:0 6px 22px rgba(0,0,0,.06);overflow:hidden}.quote-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e3e9ef;position:sticky;top:0;z-index:10}.quote-toolbar-left,.quote-toolbar-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.quote-toolbar a,.quote-toolbar button{border:1px solid #cfd8e3;background:#fff;color:#17202a;border-radius:5px;padding:9px 12px;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.quote-toolbar .quote-save{background:#087b65;border-color:#087b65;color:#fff}.quote-toolbar .quote-print{background:#17202a;border-color:#17202a;color:#fff}.quote-status{border:1px solid #cfd8e3;border-radius:5px;padding:9px 10px;background:#fff;font:inherit}.quote-paper{max-width:1120px;margin:22px auto;padding:34px 38px 50px;background:#fff;color:#1f2933}.quote-brandline{display:flex;justify-content:space-between;gap:18px;border-bottom:2px solid #087b65;padding-bottom:10px;margin-bottom:30px;color:#667085;font-size:13px}.quote-brandline strong{color:#087b65;letter-spacing:.03em}.quote-kicker{font-size:38px;line-height:1;font-weight:800;color:#087b65;margin:0 0 5px}.quote-title-input{width:100%;border:0;border-bottom:1px dashed transparent;outline:none;font-size:22px;font-weight:800;color:#087b65;padding:2px 0 8px;background:transparent}.quote-title-input:hover,.quote-title-input:focus{border-color:#9ccfc4}.quote-meta{width:100%;border-collapse:collapse;min-width:0;margin:10px 0 28px}.quote-meta td{border:1px solid #bdd5cf;padding:0;background:#fff}.quote-meta .label{width:120px;background:#e7f2ef;color:#166052;font-weight:800;padding:10px}.quote-edit{width:100%;box-sizing:border-box;border:0;background:transparent;padding:10px;font:inherit;color:#17202a;outline:none}.quote-edit:focus{box-shadow:inset 0 0 0 2px #69b9a9}.quote-section{margin-top:24px}.quote-section h2{color:#087b65;font-size:22px;margin:0 0 8px}.quote-textarea{width:100%;box-sizing:border-box;min-height:100px;border:1px solid #d6e2df;border-radius:5px;padding:11px;font:inherit;line-height:1.45;resize:vertical}.quote-total-banner{display:flex;justify-content:space-between;gap:16px;background:#087b65;color:#fff;padding:11px 14px;margin:22px 0 10px;font-size:17px;font-weight:800}.quote-total-banner strong{font-size:20px}.quote-table-wrap{overflow-x:auto}.quote-items{width:100%;border-collapse:collapse;min-width:760px}.quote-items th{background:#087b65;color:#fff;padding:8px;text-align:left}.quote-items td{border:1px solid #c7d9d5;padding:0;background:#fff}.quote-items input,.quote-items select{width:100%;box-sizing:border-box;border:0;padding:9px 8px;font:inherit;background:transparent;outline:none}.quote-items input:focus,.quote-items select:focus{box-shadow:inset 0 0 0 2px #69b9a9}.quote-items .num{text-align:right}.quote-items .sum-cell{text-align:right;padding:9px 8px;font-weight:700;white-space:nowrap}.quote-remove-row{border:0;background:transparent;color:#c43d38;font-size:19px;cursor:pointer;width:100%;padding:6px}.quote-add-row{margin-top:8px;border:1px dashed #73ad9f;background:#f4fbf9;color:#087b65;border-radius:5px;padding:8px 11px;font-weight:700;cursor:pointer}.quote-subtotals{margin-left:auto;width:min(460px,100%);margin-top:12px}.quote-subtotals-row{display:flex;justify-content:space-between;gap:20px;padding:8px 10px;border-bottom:1px solid #dbe5e2}.quote-subtotals-row.total{background:#e7f2ef;font-weight:800;font-size:17px}.quote-request-source{margin:18px 0;padding:13px 15px;border-left:4px solid #087b65;background:#f6faf9;color:#475467;line-height:1.45}.quote-saved{margin:14px 18px 0;padding:10px 12px;border:1px solid #b7e4c7;background:#f0fff4;color:#246b3a;border-radius:6px}.quote-error{margin:14px 18px 0;padding:10px 12px;border:1px solid #f1b5b2;background:#fff4f3;color:#8a1c1c;border-radius:6px}.quote-signature{margin-top:26px;line-height:1.45}.quote-footer{border-top:1px solid #ced9d6;margin-top:50px;padding-top:8px;color:#697386;font-size:12px;display:flex;justify-content:space-between;gap:10px}
@media(max-width:760px){.quote-toolbar{position:static;align-items:stretch;flex-direction:column}.quote-toolbar-left,.quote-toolbar-right{width:100%;display:grid;grid-template-columns:1fr}.quote-paper{margin:0;padding:20px 14px}.quote-kicker{font-size:30px}.quote-meta,.quote-meta tbody,.quote-meta tr,.quote-meta td{display:block;width:100%}.quote-meta tr{margin-bottom:8px}.quote-meta .label{box-sizing:border-box;width:100%;padding:7px 10px}.quote-brandline{flex-direction:column}.request-quote-button{width:100%;box-sizing:border-box}}
@media print{body{background:#fff;padding:0}.topbar,.quote-toolbar,.quote-saved,.quote-error,.quote-add-row,.quote-remove-row,.request-quote-button{display:none!important}.table-wrap{box-shadow:none;background:#fff}.quote-shell{box-shadow:none}.quote-paper{max-width:none;margin:0;padding:12mm 10mm}.quote-edit,.quote-textarea,.quote-items input,.quote-items select{box-shadow:none!important}.quote-textarea{border:0;padding:0;resize:none}.quote-title-input{border:0}.quote-items{min-width:0}.quote-footer{position:relative}}
</style>
<style>@media(max-width:760px){.price-wrap{padding:14px}.price-head{flex-direction:column}.price-add-toggle{width:100%}.price-intro{grid-template-columns:1fr}.price-form-grid{grid-template-columns:1fr}.price-field[style]{grid-column:auto!important}.price-summary{grid-template-columns:1fr 70px 100px 24px}.price-summary>span:nth-child(4){grid-column:1/-1}}</style>

<style>
.email-wrap{background:#fff;border-radius:9px;padding:22px;box-shadow:0 6px 22px rgba(0,0,0,.06)}.email-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.email-head h2{margin:0 0 5px}.email-head p{margin:0;color:#697386}.email-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #17202a;background:#17202a;color:#fff;border-radius:5px;padding:10px 13px;text-decoration:none;font-weight:700;cursor:pointer;font:inherit}.email-btn.secondary{background:#fff;color:#17202a}.email-btn.green{background:#087b65;border-color:#087b65}.email-btn.danger{background:#fff;color:#c43d38;border-color:#d9534f}.email-flash{padding:10px 12px;border-radius:6px;margin-bottom:14px;background:#f0fff4;border:1px solid #b7e4c7;color:#246b3a}.email-error{padding:10px 12px;border-radius:6px;margin-bottom:14px;background:#fff4f3;border:1px solid #f1b5b2;color:#8a1c1c}.email-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:14px 0 20px}.email-stat{border:1px solid #e1e7ed;background:#f8fafc;border-radius:7px;padding:13px}.email-stat span{display:block;color:#697386;font-size:12px;margin-bottom:5px}.email-stat strong{font-size:22px}.email-campaign-list{display:flex;flex-direction:column;gap:9px}.email-campaign-card{display:grid;grid-template-columns:minmax(220px,1.3fr) 120px repeat(4,90px) auto;gap:12px;align-items:center;border:1px solid #d9e2ec;border-radius:8px;padding:14px}.email-campaign-name strong{display:block}.email-campaign-name small{color:#697386}.email-status{display:inline-flex;width:max-content;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800}.email-status.draft{background:#eef2f7;color:#475467}.email-status.queued,.email-status.sending{background:#fff4e5;color:#b45309}.email-status.completed{background:#eaf8ef;color:#24753a}.email-status.paused{background:#eaf2ff;color:#2457a6}.email-editor-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.7fr);gap:18px}.email-panel{border:1px solid #d9e2ec;border-radius:8px;padding:16px;background:#fff}.email-panel h3{margin:0 0 14px}.email-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.email-field{display:flex;flex-direction:column;gap:5px}.email-field.full{grid-column:1/-1}.email-field label{font-weight:700;font-size:13px;color:#344054}.email-field input,.email-field select,.email-field textarea{box-sizing:border-box;width:100%;border:1px solid #cfd8e3;border-radius:5px;padding:10px;font:inherit}.email-field textarea{min-height:110px;resize:vertical}.email-designer{min-height:330px;border:1px solid #cfd8e3;border-radius:6px;padding:16px;overflow:auto;background:#fff}.email-designer:focus{outline:2px solid #9ccfc4}.email-designer-toolbar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}.email-designer-toolbar button{border:1px solid #cfd8e3;background:#fff;border-radius:4px;padding:7px 9px;cursor:pointer}.email-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.email-progress{height:10px;border-radius:999px;background:#edf1f5;overflow:hidden}.email-progress>span{display:block;height:100%;background:#087b65}.email-code{font-family:monospace;font-size:12px;background:#f6f8fb;border:1px solid #d9e2ec;padding:10px;border-radius:5px;overflow-wrap:anywhere}.email-template-card{border:1px solid #d9e2ec;border-radius:8px;padding:14px;margin-bottom:10px}.email-template-preview{border:1px solid #edf1f5;background:#fff;max-height:180px;overflow:auto;padding:10px;margin:10px 0}.subscriber-tools{background:#fff;border:1px solid #d9e2ec;border-radius:8px;padding:14px;margin-bottom:14px}.subscriber-tools textarea{width:100%;box-sizing:border-box;min-height:90px;border:1px solid #cfd8e3;border-radius:5px;padding:10px;font:inherit}.email-help{color:#697386;font-size:13px;line-height:1.5}.email-help code{background:#f1f4f8;padding:2px 4px;border-radius:3px}.email-setting-note{background:#fff8e8;border:1px solid #f0d49c;padding:11px;border-radius:6px;color:#7a4b00;margin-bottom:14px}.contact-import-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:16px}.contact-import-box{border:1px solid #d9e2ec;border-radius:8px;padding:15px;background:#f8fafc}.contact-import-box h3{margin:0 0 5px}.contact-import-box p{margin:0 0 12px;color:#697386;font-size:13px;line-height:1.45}.contact-import-box textarea{width:100%;box-sizing:border-box;min-height:150px;border:1px solid #cfd8e3;border-radius:6px;padding:10px;font:inherit;background:#fff}.contact-import-drop{display:flex;flex-direction:column;justify-content:center;min-height:150px;border:1px dashed #98a2b3;border-radius:7px;background:#fff;padding:16px;text-align:center}.contact-import-drop input{margin:10px auto}.contact-import-columns{font-size:12px;color:#697386;background:#fff;border:1px solid #e3e8ef;border-radius:5px;padding:8px;margin-top:10px}.contact-import-check{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#475467;margin-top:10px}.contact-import-check input{margin-top:2px}.contact-import-result{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.contact-import-result span{background:#f0fff4;border:1px solid #b7e4c7;color:#246b3a;border-radius:999px;padding:6px 10px;font-size:13px;font-weight:700}@media(max-width:760px){.contact-import-grid{grid-template-columns:1fr}}
@media(max-width:1000px){.email-stats{grid-template-columns:repeat(3,1fr)}.email-editor-grid{grid-template-columns:1fr}.email-campaign-card{grid-template-columns:1fr 1fr}.email-campaign-name{grid-column:1/-1}}@media(max-width:650px){.email-wrap{padding:14px}.email-head{flex-direction:column}.email-form-grid{grid-template-columns:1fr}.email-field.full{grid-column:auto}.email-stats{grid-template-columns:repeat(2,1fr)}.email-campaign-card{grid-template-columns:1fr}.email-campaign-name{grid-column:auto}.email-actions>.email-btn{width:100%;box-sizing:border-box}}

.email-contact-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}.email-contact-toolbar input,.email-contact-toolbar select{border:1px solid #cfd8e3;border-radius:5px;padding:10px 11px;font:inherit;background:#fff}.email-contact-toolbar input{min-width:260px;flex:1}.email-contact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.email-contact-card{border:1px solid #d9e2ec;border-radius:8px;background:#fff;padding:16px}.email-contact-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}.email-contact-name{font-size:17px;font-weight:700}.email-contact-meta{color:#667085;font-size:13px;margin-top:4px}.email-contact-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700}.email-contact-status.subscribed{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.email-contact-status.unsubscribed{background:#fff4ed;color:#b54708;border:1px solid #ffd6ae}.email-contact-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.email-contact-form .full{grid-column:1/-1}.email-contact-form input,.email-contact-form select,.email-contact-form textarea{width:100%;box-sizing:border-box;border:1px solid #cfd8e3;border-radius:5px;padding:9px 10px;font:inherit}.email-contact-form textarea{min-height:70px;resize:vertical}.email-contact-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px;flex-wrap:wrap}@media(max-width:760px){.email-contact-grid{grid-template-columns:1fr}.email-contact-form{grid-template-columns:1fr}.email-contact-form .full{grid-column:auto}.email-contact-toolbar input{min-width:0;width:100%}}
.contact-clean-shell{display:grid;grid-template-columns:230px minmax(0,1fr);gap:18px;align-items:start}.contact-side{background:#fff;border:1px solid #e3e8ef;border-radius:10px;padding:12px}.contact-side-title{font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#667085;padding:6px 8px}.contact-nav-link{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border-radius:7px;color:#17202a;text-decoration:none;font-weight:700}.contact-nav-link.active{background:#17202a;color:#fff}.contact-nav-link small{font-weight:600;opacity:.7}.contact-segment-list{margin-top:8px;border-top:1px solid #edf1f5;padding-top:8px}.contact-main{min-width:0}.contact-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px}.contact-list-head h3{margin:0}.contact-list-meta{color:#667085;font-size:13px}.contact-search{display:flex;gap:8px;min-width:min(100%,420px)}.contact-search input{flex:1;min-width:0;border:1px solid #cfd8e3;border-radius:7px;padding:10px 12px;font:inherit}.contact-bulkbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #e3e8ef;border-radius:8px;padding:9px 10px;margin-bottom:9px}.contact-bulkbar select,.contact-bulkbar input{border:1px solid #cfd8e3;border-radius:6px;padding:8px 9px;font:inherit}.contact-sheet{border:1px solid #dce3eb;border-radius:9px;overflow:hidden;background:#fff}.contact-sheet-row{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:10px;align-items:center;min-height:43px;padding:0 12px;border-bottom:1px solid #edf1f5}.contact-sheet-row:last-child{border-bottom:0}.contact-sheet-row.sent{background:#fff7f7}.contact-sheet-email{font-size:14px;overflow-wrap:anywhere}.contact-sheet-row.sent .contact-sheet-email{color:#c73535;font-weight:700}.contact-row-info{display:flex;gap:7px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.contact-pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 7px;font-size:11px;font-weight:800;white-space:nowrap}.contact-pill.sent{background:#fee4e2;color:#b42318}.contact-pill.clean{background:#ecfdf3;color:#067647}.contact-pill.unsub{background:#fff4ed;color:#b54708}.contact-empty{padding:28px;text-align:center;color:#667085}.contact-import-details{margin-top:16px;border:1px solid #e3e8ef;border-radius:9px;background:#fff;overflow:hidden}.contact-import-details>summary{cursor:pointer;font-weight:800;padding:13px 15px;background:#f8fafc}.contact-import-details>div{padding:15px}.clean-upload-card{border:1px solid #b7e4c7;background:#f6fffa;border-radius:9px;padding:15px;margin-bottom:14px}.segment-create{display:grid;grid-template-columns:minmax(180px,1fr) auto;gap:8px;margin-top:10px}.segment-create input{min-width:0;border:1px solid #cfd8e3;border-radius:6px;padding:9px 10px;font:inherit}.contact-select-all{accent-color:#17202a}@media(max-width:820px){.contact-clean-shell{grid-template-columns:1fr}.contact-side{display:flex;gap:6px;overflow-x:auto}.contact-side-title,.contact-segment-list{display:contents}.contact-nav-link{flex:0 0 auto}.contact-sheet-row{grid-template-columns:30px minmax(0,1fr)}.contact-row-info{grid-column:2;justify-content:flex-start;padding-bottom:8px}.contact-bulkbar{align-items:stretch}.contact-bulkbar>*{width:100%;box-sizing:border-box}.segment-create{grid-template-columns:1fr}}

</style>
</head>
<body>
<header class="topbar">
  <div>
    <h1><?= h($viewTitles[$view] ?? 'Admin') ?></h1>
    <div class="muted">Sisse logitud: <?= h($_SESSION['username'] ?? '') ?></div>
  </div>
  <div class="topbar-actions">
    <nav class="admin-tabs" aria-label="Admin vaated">
      <a class="admin-tab <?= $view === 'requests' ? 'active' : '' ?>" href="index.php?view=requests">Päringud</a>
      <a class="admin-tab <?= in_array($view, ['quotes','quote'], true) ? 'active' : '' ?>" href="index.php?view=quotes">Hinnapakkumised</a>
      <a class="admin-tab <?= $view === 'price-list' ? 'active' : '' ?>" href="index.php?view=price-list">Hinnakiri</a>
      <a class="admin-tab <?= $view === 'workdays' ? 'active' : '' ?>" href="index.php?view=workdays">Tööpäevad</a>
      <a class="admin-tab <?= $view === 'workers' ? 'active' : '' ?>" href="index.php?view=workers">Töölised</a>
      <details class="admin-menu-group" <?= in_array($view, ['calculations','statistics','email-campaigns','email-campaign','email-templates','email-settings','email-contacts','subscribers','unsubscribers'], true) ? 'open' : '' ?>>
        <summary class="admin-tab <?= in_array($view, ['calculations','statistics','email-campaigns','email-campaign','email-templates','email-settings','email-contacts','subscribers','unsubscribers'], true) ? 'active' : '' ?>">Muu</summary>
        <div class="admin-submenu">
          <a class="admin-tab <?= $view === 'calculations' ? 'active' : '' ?>" href="index.php?view=calculations">Arvestused</a>
          <a class="admin-tab <?= $view === 'statistics' ? 'active' : '' ?>" href="index.php?view=statistics">Lehe statistika</a>
          <div style="padding:7px 10px 3px;color:#667085;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em">Email kampaania</div>
          <a class="admin-tab <?= in_array($view,['email-campaigns','email-campaign'],true) ? 'active' : '' ?>" href="index.php?view=email-campaigns">Kampaaniad</a>
          <a class="admin-tab <?= $view === 'email-templates' ? 'active' : '' ?>" href="index.php?view=email-templates">Mallid</a>
          <a class="admin-tab <?= $view === 'email-contacts' ? 'active' : '' ?>" href="index.php?view=email-contacts">Kontaktid <span class="admin-tab-count"><?= $subscriberCount + $unsubscriberCount ?></span></a>
          <a class="admin-tab <?= $view === 'subscribers' ? 'active' : '' ?>" href="index.php?view=subscribers">Subscriberid <span class="admin-tab-count"><?= $subscriberCount ?></span></a>
          <a class="admin-tab <?= $view === 'unsubscribers' ? 'active' : '' ?>" href="index.php?view=unsubscribers">Unsubscriberid <span class="admin-tab-count"><?= $unsubscriberCount ?></span></a>
          <a class="admin-tab <?= $view === 'email-settings' ? 'active' : '' ?>" href="index.php?view=email-settings">Seaded / SMTP</a>
        </div>
      </details>
    </nav>
    <a href="logout.php" class="logout">Logi välja</a>
  </div>
</header>

<main class="<?= $view === 'statistics' ? 'analytics-main' : 'table-wrap' ?>">
<?php if ($view === 'requests'): ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Nimi</th>
      <th>Email</th>
      <th>Telefon</th>
      <th>Aadress</th>
      <th>Sõnum</th>
      <th>Failid</th>
      <th>Allikas</th>
      <th>Kuupäev</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$requests): ?>
    <tr><td colspan="10" class="muted">Päringuid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($requests as $row): ?>
    <tr>
      <td data-label="ID"><?= (int) $row['id'] ?></td>
      <td data-label="Nimi"><?= h($row['name']) ?></td>
      <td data-label="Email"><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td data-label="Telefon"><?= h($row['phone'] ?? '') ?></td>
      <td data-label="Aadress"><?= h($row['address'] ?? '') ?></td>
      <td data-label="Sõnum" class="message"><?= h($row['message']) ?></td>
      <td data-label="Failid">
        <?php $files = decode_files($row['file_path'] ?? null); ?>
        <?php if (!$files): ?>
          <span class="muted">-</span>
        <?php endif; ?>
        <?php foreach ($files as $file): ?>
          <?php
          $path = file_path_from_entry($file);
          $label = file_label_from_entry($file);
          $url = upload_url($path);
          ?>
          <?php if ($path !== '' && is_preview_image($path)): ?>
            <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" class="lightbox-item" data-request-id="' . (int) $row['id'] . '" data-label="' . h($label) . '"><img ' . 'sr' . 'c="' . h($url) . '" alt="' . h($label) . '" class="preview-img" loading="lazy"></a>'; ?>
          <?php endif; ?>
          <?php if ($path !== ''): ?>
            <?php echo '<a ' . 'hr' . 'ef="' . h($url) . '" target="_blank" rel="noopener" class="file-link">' . h($label) . '</a>'; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </td>
      <td data-label="Allikas"><?= h($row['source'] ?? '') ?></td>
      <td data-label="Kuupäev"><?= h($row['created_at']) ?></td>
      <td class="actions">
        <a class="request-quote-button" href="index.php?view=quote&amp;request_id=<?= (int)$row['id'] ?>"><?= in_array((int)$row['id'], $quoteRequestIds, true) ? 'Ava hinnapakkumine' : 'Genereeri hinnapakkumine' ?></a>
        <form method="post" onsubmit="return confirm('Kustutan selle päringu?');">
          <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>">
          <button type="submit" class="button-delete">Kustuta</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php elseif ($view === 'quotes'): ?>
<section class="objects-wrap" aria-labelledby="quotesTitle">
  <div class="objects-head">
    <div>
      <h2 class="objects-title" id="quotesTitle">Hinnapakkumised</h2>
      <p class="objects-subtitle">Kõik salvestatud hinnapakkumiste mustandid ja valmis pakkumised ühes kohas.</p>
    </div>
    <a class="object-add-toggle" style="text-decoration:none;text-align:center" href="index.php?view=quote&amp;new=1">+ Koosta hinnapakkumine</a>
  </div>
  <div class="object-list">
    <?php if (!$quotes): ?>
      <div class="object-empty">Hinnapakkumisi ei ole veel. Vajuta „+ Koosta hinnapakkumine“ või genereeri pakkumine Päringud lehelt.</div>
    <?php endif; ?>
    <?php if (isset($_GET['status_saved'])): ?>
      <div class="quote-status-saved">Staatus on uuendatud.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="quote-status-saved">Hinnapakkumine on kustutatud.</div>
    <?php endif; ?>
    <?php foreach ($quotes as $quoteRow): ?>
      <?php $sendStatus = (string)($quoteRow['send_status'] ?? 'unsent'); ?>
      <div class="object-card">
        <div class="object-card-body quote-list-body">
          <div><strong><?= h($quoteRow['quote_number'] ?? '') ?></strong><div class="muted" style="margin-top:4px"><?= preg_match('/^Mustand(?:_\d+)?$/', (string)($quoteRow['client_name'] ?? '')) ? 'Salvestatud mustand' : 'Hinnapakkumine' ?></div></div>
          <div><strong><?= h($quoteRow['client_name'] ?? '') ?></strong><div class="muted" style="margin-top:4px"><?= h($quoteRow['title'] ?? '') ?></div></div>
          <div class="quote-list-title"><span class="muted" style="display:block;font-size:12px;margin-bottom:3px">Aadress</span><strong><?= h(($quoteRow['object_address'] ?? '') !== '' ? $quoteRow['object_address'] : '—') ?></strong></div>
          <div class="quote-contact-block"><span class="muted" style="display:block;font-size:12px;margin-bottom:3px">Kontakt</span><?php if (!empty($quoteRow['client_email'])): ?><a href="mailto:<?= h($quoteRow['client_email']) ?>"><?= h($quoteRow['client_email']) ?></a><?php else: ?><span class="muted">E-post puudub</span><?php endif; ?><?php if (!empty($quoteRow['client_phone'])): ?><div style="margin-top:3px"><a href="tel:<?= h(preg_replace('/[^\d+]/', '', (string)$quoteRow['client_phone'])) ?>"><?= h($quoteRow['client_phone']) ?></a></div><?php endif; ?></div>
          <form method="post" action="index.php?view=quotes" class="quote-send-form">
            <input type="hidden" name="update_quote_send_status" value="1">
            <input type="hidden" name="quote_id" value="<?= (int)$quoteRow['id'] ?>">
            <div class="quote-send-wrap">
              <select class="quote-send-select <?= $sendStatus === 'sent' ? 'sent' : 'unsent' ?>" name="send_status" onchange="this.form.submit()" aria-label="Hinnapakkumise saatmise staatus">
                <option value="unsent" <?= $sendStatus !== 'sent' ? 'selected' : '' ?>>Saatmata</option>
                <option value="sent" <?= $sendStatus === 'sent' ? 'selected' : '' ?>>Saadetud</option>
              </select>
            </div>
          </form>
          <a class="object-save" style="text-decoration:none;text-align:center" href="index.php?view=quote&amp;quote_id=<?= (int)$quoteRow['id'] ?>">Ava / muuda</a>
          <form method="post" action="index.php?view=quotes" class="quote-delete-form" onsubmit="return confirm('Kas kustutan selle hinnapakkumise jäädavalt?');">
            <input type="hidden" name="delete_admin_quote" value="1">
            <input type="hidden" name="quote_id" value="<?= (int)$quoteRow['id'] ?>">
            <button type="submit" class="quote-delete-button">Kustuta</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif ($view === 'quote'): ?>
<?php if (!$quote): ?>
<section class="objects-wrap"><div class="object-error">Hinnapakkumist ei leitud. <a href="index.php?view=quotes">Tagasi hinnapakkumistesse</a></div></section>
<?php else: ?>
<?php
$quoteWorkItems = json_decode((string)($quote['work_items_json'] ?? '[]'), true); if (!is_array($quoteWorkItems)) $quoteWorkItems=[];
$quoteMaterialItems = json_decode((string)($quote['material_items_json'] ?? '[]'), true); if (!is_array($quoteMaterialItems)) $quoteMaterialItems=[];
?>
<section class="quote-shell">
  <?php if (isset($_GET['saved'])): ?><div class="quote-saved">Hinnapakkumise mustand on salvestatud.</div><?php endif; ?>
  <?php if ($quoteFormError !== ''): ?><div class="quote-error"><?= h($quoteFormError) ?></div><?php endif; ?>
  <form method="post" action="index.php?view=quote" id="quoteEditorForm">
    <input type="hidden" name="save_quote" value="1"><input type="hidden" name="request_id" value="<?= (int)($quote['request_id'] ?? 0) ?>"><input type="hidden" name="quote_id" value="<?= (int)($quote['id'] ?? 0) ?>">
    <div class="quote-toolbar">
      <div class="quote-toolbar-left"><a href="index.php?view=requests">← Päringud</a><strong>Visuaalne hinnapakkumise editor</strong></div>
      <div class="quote-toolbar-right">
        <select class="quote-status" name="quote_status"><option value="draft" <?= ($quote['status']??'draft')==='draft'?'selected':'' ?>>Mustand</option><option value="ready" <?= ($quote['status']??'')==='ready'?'selected':'' ?>>Valmis</option><option value="sent" <?= ($quote['status']??'')==='sent'?'selected':'' ?>>Saadetud</option><option value="accepted" <?= ($quote['status']??'')==='accepted'?'selected':'' ?>>Kinnitatud</option></select>
        <button type="button" class="quote-print" id="quotePrint">Prindi / PDF</button><button type="submit" class="quote-save">Salvesta mustand</button>
      </div>
    </div>
    <article class="quote-paper">
      <div class="quote-brandline"><strong>RK MEISTRID OÜ</strong><span>Ehitus- ja renoveerimistööd Tallinnas ning Harjumaal</span><span>+372 5551 5783 &nbsp;|&nbsp; renoveerikodu.ee</span></div>
      <div class="quote-kicker">HINNAPAKKUMINE</div>
      <input class="quote-title-input" name="quote_title" value="<?= h($quote['title'] ?? '') ?>" placeholder="Pakkumise pealkiri">
      <table class="quote-meta"><tbody>
        <tr><td class="label">Pakkumise nr</td><td><input class="quote-edit" name="quote_number" value="<?= h($quote['quote_number'] ?? '') ?>"></td><td class="label">Kuupäev</td><td><input class="quote-edit" value="<?= h(date('d.m.Y')) ?>" readonly></td></tr>
        <tr><td class="label">Tellija</td><td><input class="quote-edit" name="client_name" value="<?= h($quote['client_name'] ?? '') ?>"></td><td class="label">Objekt</td><td><input class="quote-edit" name="object_address" value="<?= h($quote['object_address'] ?? '') ?>"></td></tr>
        <tr><td class="label">Kontakt</td><td><input class="quote-edit" name="client_email" value="<?= h($quote['client_email'] ?? '') ?>" placeholder="e-post"><input class="quote-edit" name="client_phone" value="<?= h($quote['client_phone'] ?? '') ?>" placeholder="telefon"></td><td class="label">Töövõtja</td><td><input class="quote-edit" value="RK Meistrid OÜ" readonly></td></tr>
        <tr><td class="label">Kehtivus</td><td><input class="quote-edit" type="number" min="1" name="valid_days" value="<?= (int)($quote['valid_days'] ?? 14) ?>"></td><td class="label">Tööde aeg</td><td><input class="quote-edit" name="work_time" value="<?= h($quote['work_time'] ?? 'Kokkuleppel') ?>"></td></tr>
        <tr><td class="label">Pakkumise liik</td><td><input class="quote-edit" name="offer_type" value="<?= h($quote['offer_type'] ?? '') ?>"></td><td class="label">Kestus</td><td><input class="quote-edit" name="duration" value="<?= h($quote['duration'] ?? '') ?>" placeholder="nt 8–12 tööpäeva"></td></tr>
      </tbody></table>

      <section class="quote-section"><h2>Pakkumise alus ja tööpiir</h2><textarea class="quote-textarea" name="scope_text"><?= h($quote['scope_text'] ?? '') ?></textarea></section>
      <?php if ($quoteRequest): ?><div class="quote-request-source"><strong>Algne kliendipäring:</strong><br><?= nl2br(h($quoteRequest['message'] ?? '')) ?></div><?php endif; ?>

      <div class="quote-total-banner"><span>PAKKUMISE KOGUSUMMA</span><strong id="quoteGrandTotal">0,00 €</strong></div>
      <section class="quote-section"><h2>1. TÖÖRAHA</h2><div class="quote-table-wrap"><table class="quote-items" id="quoteWorkTable"><thead><tr><th style="width:48px">Nr</th><th>Tööetapp</th><th style="width:110px">Maht</th><th style="width:100px">Ühik</th><th style="width:125px">Ühikhind</th><th style="width:125px">Summa</th><th style="width:44px"></th></tr></thead><tbody>
      <?php foreach ($quoteWorkItems as $i=>$item): ?><tr><td class="sum-cell js-row-number"><?= $i+1 ?></td><td><input name="work_desc[]" value="<?= h($item['description']??'') ?>"></td><td><input class="num js-qty" name="work_qty[]" value="<?= h((string)($item['qty']??0)) ?>"></td><td><input name="work_unit[]" value="<?= h($item['unit']??'m²') ?>"></td><td><input class="num js-rate" name="work_rate[]" value="<?= h((string)($item['rate']??0)) ?>"></td><td class="sum-cell js-line-total">0,00 €</td><td><button class="quote-remove-row" type="button" title="Eemalda">×</button></td></tr><?php endforeach; ?>
      </tbody></table></div><button class="quote-add-row" type="button" data-target="quoteWorkTable">+ Lisa töö rida</button></section>

      <section class="quote-section"><h2>2. MATERJALID JA LOGISTIKA</h2><div class="quote-table-wrap"><table class="quote-items" id="quoteMaterialTable"><thead><tr><th style="width:48px">Nr</th><th>Materjal / kulu</th><th style="width:110px">Kogus</th><th style="width:100px">Ühik</th><th style="width:125px">Ühikhind</th><th style="width:125px">Summa</th><th style="width:44px"></th></tr></thead><tbody>
      <?php foreach ($quoteMaterialItems as $i=>$item): ?><tr><td class="sum-cell js-row-number"><?= $i+1 ?></td><td><input name="material_desc[]" value="<?= h($item['description']??'') ?>"></td><td><input class="num js-qty" name="material_qty[]" value="<?= h((string)($item['qty']??0)) ?>"></td><td><input name="material_unit[]" value="<?= h($item['unit']??'tk') ?>"></td><td><input class="num js-rate" name="material_rate[]" value="<?= h((string)($item['rate']??0)) ?>"></td><td class="sum-cell js-line-total">0,00 €</td><td><button class="quote-remove-row" type="button" title="Eemalda">×</button></td></tr><?php endforeach; ?>
      </tbody></table></div><button class="quote-add-row" type="button" data-target="quoteMaterialTable">+ Lisa materjali rida</button></section>

      <div class="quote-subtotals"><div class="quote-subtotals-row"><span>Tööraha</span><strong id="quoteWorkTotal">0,00 €</strong></div><div class="quote-subtotals-row"><span>Materjalid ja logistika</span><strong id="quoteMaterialTotal">0,00 €</strong></div><div class="quote-subtotals-row total"><span>Kogusumma</span><strong id="quoteBottomTotal">0,00 €</strong></div></div>
      <section class="quote-section"><h2>3. PAKKUMISE SISU JA TINGIMUSED</h2><textarea class="quote-textarea" name="terms_text" style="min-height:210px"><?= h($quote['terms_text'] ?? '') ?></textarea></section>
      <div class="quote-signature">Lugupidamisega<br><strong>Hans Suurväli</strong><br>RK Meistrid OÜ<br>info@renoveerikodu.ee | +372 5551 5783<br>www.renoveerikodu.ee</div>
      <div class="quote-footer"><span>RK Meistrid OÜ &nbsp;|&nbsp; registrikood 16541086 &nbsp;|&nbsp; Hane 2, 13418 Tallinn</span><span>Hinnapakkumine</span></div>
    </article>
  </form>
</section>
<?php endif; ?>
<?php elseif ($view === 'objects'): ?>
<section class="objects-wrap" aria-labelledby="objectsTitle">
  <div class="objects-head">
    <div>
      <h2 class="objects-title" id="objectsTitle">Objektid</h2>
      <p class="objects-subtitle">Vajuta objekti nimele, et avada kontakt, ajakava, märkused ja navigeerimine.</p>
    </div>
    <button type="button" class="object-add-toggle" id="objectAddToggle" aria-expanded="false" aria-controls="objectAddPanel">+ Lisa objekt</button>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="object-saved">Objekti andmed on salvestatud.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="object-saved">Objekt on kustutatud.</div>
  <?php endif; ?>

  <?php if ($objectFormError !== ''): ?>
    <div class="object-error"><?= h($objectFormError) ?></div>
  <?php endif; ?>

  <div class="object-add-panel <?= $objectFormError !== '' ? 'open' : '' ?>" id="objectAddPanel">
    <form method="post" action="index.php?view=objects">
      <input type="hidden" name="save_admin_object" value="1">
      <div class="object-form-grid">
        <div class="object-field">
          <label for="newObjectName">Objekti nimi</label>
          <input type="text" id="newObjectName" name="name" placeholder="Näiteks Sõudebaasi tee 15" required>
        </div>
        <div class="object-field">
          <label for="newObjectPhone">Kontakttelefon</label>
          <input type="tel" id="newObjectPhone" name="phone" placeholder="+372 ...">
        </div>
        <div class="object-field full">
          <label for="newObjectAddress">Aadress</label>
          <input type="text" id="newObjectAddress" name="address" placeholder="Sisesta täielik aadress" required>
        </div>
        <div class="object-field">
          <label for="newObjectStart">Objekti algusaeg</label>
          <input type="datetime-local" id="newObjectStart" name="start_at">
        </div>
        <div class="object-field">
          <label for="newObjectEnd">Eeldatav lõppkuupäev</label>
          <input type="date" id="newObjectEnd" name="expected_end_date">
        </div>
        <div class="object-field full">
          <label for="newObjectNotes">Märkused</label>
          <textarea id="newObjectNotes" name="notes" placeholder="Lisa märkused..."></textarea>
        </div>
      </div>
      <div class="object-form-actions">
        <button type="submit" class="object-save">Lisa objekt</button>
      </div>
    </form>
  </div>

  <div class="object-list">
    <?php if (!$objects): ?>
      <div class="object-empty">Objekte ei ole veel. Vajuta „+ Lisa objekt”.</div>
    <?php endif; ?>

    <?php foreach ($objects as $object): ?>
      <?php
        $startValue = '';
        if (!empty($object['start_at'])) {
            $startValue = date('Y-m-d\TH:i', strtotime((string) $object['start_at']));
        }
        $phoneHref = preg_replace('/[^\d+]/', '', (string) ($object['phone'] ?? ''));
        $wazeUrl = 'https://waze.com/ul?q=' . rawurlencode((string) $object['address']) . '&navigate=yes';
      ?>
      <details class="object-card">
        <summary>
          <span class="object-summary-main">
            <span class="object-chevron" aria-hidden="true"></span>
            <span class="object-summary-text">
              <span class="object-summary-name"><?= h($object['name']) ?></span>
              <span class="object-summary-address"><?= h($object['address']) ?></span>
            </span>
          </span>
          <span class="muted">Ava</span>
        </summary>

        <div class="object-card-body">
          <form method="post" action="index.php?view=objects">
            <input type="hidden" name="save_admin_object" value="1">
            <input type="hidden" name="object_id" value="<?= (int) $object['id'] ?>">

            <div class="object-form-grid">
              <div class="object-field">
                <label for="objectName<?= (int) $object['id'] ?>">Objekti nimi</label>
                <input type="text" id="objectName<?= (int) $object['id'] ?>" name="name" value="<?= h($object['name']) ?>" required>
              </div>

              <div class="object-field">
                <label for="objectPhone<?= (int) $object['id'] ?>">Kontakttelefon</label>
                <input type="tel" id="objectPhone<?= (int) $object['id'] ?>" name="phone" value="<?= h($object['phone'] ?? '') ?>">
                <?php if (!empty($object['phone'])): ?>
                  <a class="object-phone-link" href="tel:<?= h($phoneHref) ?>"><?= h($object['phone']) ?></a>
                <?php endif; ?>
              </div>

              <div class="object-field full">
                <label for="objectAddress<?= (int) $object['id'] ?>">Aadress</label>
                <input type="text" id="objectAddress<?= (int) $object['id'] ?>" name="address" value="<?= h($object['address']) ?>" required>
              </div>

              <div class="object-field">
                <label for="objectStart<?= (int) $object['id'] ?>">Objekti algusaeg</label>
                <input type="datetime-local" id="objectStart<?= (int) $object['id'] ?>" name="start_at" value="<?= h($startValue) ?>">
              </div>

              <div class="object-field">
                <label for="objectEnd<?= (int) $object['id'] ?>">Eeldatav lõppkuupäev</label>
                <input type="date" id="objectEnd<?= (int) $object['id'] ?>" name="expected_end_date" value="<?= h($object['expected_end_date'] ?? '') ?>">
              </div>

              <div class="object-field full">
                <label for="objectNotes<?= (int) $object['id'] ?>">Märkused</label>
                <textarea id="objectNotes<?= (int) $object['id'] ?>" name="notes" placeholder="Lisa märkused..."><?= h($object['notes'] ?? '') ?></textarea>
              </div>

              <div class="object-field full">
                <label>Navigeeri</label>
                <div class="object-nav-row">
                  <a class="waze-button" href="<?= h($wazeUrl) ?>" target="_blank" rel="noopener">Waze</a>
                  <span class="muted">Waze kasutab objekti aadressi automaatselt.</span>
                </div>
              </div>
            </div>

            <div class="object-form-actions">
              <button type="submit" class="object-save">Salvesta muudatused</button>
            </div>
          </form>

          <form method="post" action="index.php?view=objects" onsubmit="return confirm('Kustutan selle objekti?');" style="margin-top:10px">
            <input type="hidden" name="delete_admin_object" value="1">
            <input type="hidden" name="object_id" value="<?= (int) $object['id'] ?>">
            <button type="submit" class="object-delete">Kustuta objekt</button>
          </form>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif ($view === 'workdays'): ?>
<section class="workdays-wrap" aria-labelledby="workdaysTitle">
  <div class="workdays-head">
    <div>
      <h2 id="workdaysTitle">Tööpäevad</h2>
      <p>Lisa tööpäev, seosta objektiga ning jälgi automaatselt töötunde, kulu ja läbisõitu.</p>
    </div>
    <button type="button" class="workday-add" id="workdayAddToggle">+ Lisa tööpäev</button>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="workday-alert ok">Tööpäev on salvestatud.</div>
  <?php elseif (isset($_GET['quick_confirmed'])): ?>
    <div class="workday-alert ok">Tööpäev on kinnitatud.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="workday-alert ok">Tööpäev on kustutatud.</div>
  <?php endif; ?>
  <?php if ($workdayFormError !== ''): ?>
    <div class="workday-alert error"><?= h($workdayFormError) ?></div>
  <?php endif; ?>

  <div class="workday-summary">
    <div class="workday-stat"><span>Kirjeid filtris</span><strong><?= (int) $workdaySummary['count'] ?></strong></div>
    <div class="workday-stat"><span>Töötunde kokku</span><strong><?= number_format((float) $workdaySummary['hours'], 1, ',', ' ') ?> h</strong></div>
    <div class="workday-stat"><span>Tööjõukulu</span><strong><?= number_format((float) $workdaySummary['cost'], 2, ',', ' ') ?> €</strong></div>
    <div class="workday-stat"><span>Läbisõit</span><strong><?= number_format((float) $workdaySummary['mileage'], 1, ',', ' ') ?> km</strong></div>
  </div>

  <div class="workday-form-panel <?= $workdayFormError !== '' ? 'open' : '' ?>" id="workdayAddPanel">
    <div class="workday-form-title">
      <h3>Uus tööpäev</h3>
      <button type="button" class="workday-close" id="workdayClose" aria-label="Sulge">×</button>
    </div>
    <form method="post" action="index.php?view=workdays" class="workday-form js-workday-form">
      <input type="hidden" name="save_workday" value="1">
      <div class="workday-grid">
        <div class="workday-field">
          <label for="wdDate">Kuupäev *</label>
          <input type="date" id="wdDate" name="work_date" value="<?= h($_POST['work_date'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="workday-field">
          <label for="wdWorker">Töötaja *</label>
          <input type="text" id="wdWorker" name="worker_name" list="workdayWorkers" value="<?= h($_POST['worker_name'] ?? '') ?>" placeholder="Näiteks Karl" required>
          <datalist id="workdayWorkers">
            <?php foreach ($workdayWorkers as $worker): ?><option value="<?= h($worker) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="workday-field span2">
          <label for="wdObjectSelect">Vali olemasolev objekt</label>
          <select id="wdObjectSelect" name="object_id" class="js-object-select">
            <option value="0">— Sisestan käsitsi —</option>
            <?php foreach ($objects as $object): ?>
              <option value="<?= (int) $object['id'] ?>" data-name="<?= h($object['name']) ?>" data-address="<?= h($object['address']) ?>" <?= (int)($_POST['object_id'] ?? 0)===(int)$object['id']?'selected':'' ?>><?= h($object['name']) ?> — <?= h($object['address']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="workday-field span2">
          <label for="wdObjectName">Objekti nimetus *</label>
          <input type="text" id="wdObjectName" name="object_name" class="js-object-name" value="<?= h($_POST['object_name'] ?? '') ?>" placeholder="Näiteks Sõudebaasi tee 15" required>
        </div>
        <div class="workday-field span2">
          <label for="wdAddress">Aadress</label>
          <input type="text" id="wdAddress" name="address" class="js-object-address" value="<?= h($_POST['address'] ?? '') ?>" placeholder="Täielik aadress">
        </div>

        <div class="workday-field">
          <label for="wdStart">Tööpäeva algus *</label>
          <input type="time" id="wdStart" name="start_time" class="js-start-time" value="<?= h($_POST['start_time'] ?? '08:00') ?>" required>
        </div>
        <div class="workday-field">
          <label for="wdEnd">Tööpäeva lõpp *</label>
          <input type="time" id="wdEnd" name="end_time" class="js-end-time" value="<?= h($_POST['end_time'] ?? '17:00') ?>" required>
        </div>
        <div class="workday-field">
          <label for="wdBreak">Paus (ei lähe töötundide sisse)</label>
          <input type="number" id="wdBreak" name="break_minutes" class="js-break-minutes" min="0" step="5" value="<?= h($_POST['break_minutes'] ?? '30') ?>">
        </div>
        <div class="workday-field">
          <label for="wdStatus">Staatus</label>
          <select id="wdStatus" name="status">
            <option value="pending">Ootab kinnitamist</option>
            <option value="confirmed">Kinnitatud</option>
            <option value="draft">Mustand</option>
            <option value="paid">Makstud</option>
          </select>
        </div>

        <div class="workday-field span2">
          <label for="wdType">Tehtud töö / töö liik</label>
          <input type="text" id="wdType" name="work_type" value="<?= h($_POST['work_type'] ?? '') ?>" placeholder="Näiteks põranda paigaldus, lammutus...">
        </div>
        <div class="workday-field">
          <label for="wdPaymentType">Tasustamise viis</label>
          <select id="wdPaymentType" name="payment_type" class="js-payment-type">
            <option value="hourly" <?= ($_POST['payment_type'] ?? 'hourly') === 'hourly' ? 'selected' : '' ?>>Tunnitöö</option>
            <option value="piece" <?= ($_POST['payment_type'] ?? '') === 'piece' ? 'selected' : '' ?>>Tükitöö</option>
          </select>
        </div>
        <div class="workday-field js-hourly-fields">
          <label for="wdRate">Tunnihind</label>
          <input type="number" id="wdRate" name="hourly_rate" class="js-hourly-rate" min="0" step="0.01" value="<?= h($_POST['hourly_rate'] ?? '0') ?>" placeholder="€/h">
        </div>
        <div class="workday-field js-piece-fields">
          <label for="wdPiecePricingMode">Tükitöö hinna tüüp</label>
          <select id="wdPiecePricingMode" name="piece_pricing_mode" class="js-piece-pricing-mode">
            <option value="unit" <?= ($_POST['piece_pricing_mode'] ?? 'unit') === 'unit' ? 'selected' : '' ?>>Kogus × ühikuhind</option>
            <option value="fixed" <?= ($_POST['piece_pricing_mode'] ?? '') === 'fixed' ? 'selected' : '' ?>>Kokkulepitud komplekthind</option>
          </select>
        </div>
        <div class="workday-field js-piece-fields js-piece-unit-fields">
          <label for="wdPieceQuantity">Tükitöö kogus</label>
          <input type="number" id="wdPieceQuantity" name="piece_quantity" class="js-piece-quantity" min="0" step="0.01" value="<?= h($_POST['piece_quantity'] ?? '0') ?>" placeholder="Näiteks 150">
        </div>
        <div class="workday-field js-piece-fields js-piece-unit-fields">
          <label for="wdPieceUnit">Ühik</label>
          <select id="wdPieceUnit" name="piece_unit" class="js-piece-unit">
            <?php $selectedUnit = (string)($_POST['piece_unit'] ?? 'm²'); ?>
            <option value="m²" <?= $selectedUnit === 'm²' ? 'selected' : '' ?>>m²</option>
            <option value="jm" <?= $selectedUnit === 'jm' ? 'selected' : '' ?>>jm</option>
            <option value="tk" <?= $selectedUnit === 'tk' ? 'selected' : '' ?>>tk</option>
            <option value="m³" <?= $selectedUnit === 'm³' ? 'selected' : '' ?>>m³</option>
          </select>
        </div>
        <div class="workday-field js-piece-fields js-piece-unit-fields">
          <label for="wdPieceRate">Hind ühiku kohta</label>
          <input type="number" id="wdPieceRate" name="piece_rate" class="js-piece-rate" min="0" step="0.01" value="<?= h($_POST['piece_rate'] ?? '0') ?>" placeholder="Näiteks 2.00 € / m²">
        </div>
        <div class="workday-field js-piece-fields js-piece-fixed-fields">
          <label for="wdPieceFixedPrice">Kokkulepitud komplekthind</label>
          <input type="number" id="wdPieceFixedPrice" name="piece_fixed_price" class="js-piece-fixed-price" min="0" step="0.01" value="<?= h($_POST['piece_fixed_price'] ?? '0') ?>" placeholder="Näiteks 150.00 €">
        </div>
        <div class="workday-field">
          <label for="wdMileage">Läbisõit</label>
          <input type="number" id="wdMileage" name="mileage_km" min="0" step="0.1" value="<?= h($_POST['mileage_km'] ?? '0') ?>" placeholder="km">
        </div>

        <div class="workday-field full">
          <div class="workday-live-calc">Arvestus: <strong class="js-hours-preview">0,0 h</strong><span>•</span> <span class="js-payment-preview">tunnitöö</span><span>•</span> töötasu <strong class="js-cost-preview">0,00 €</strong></div>
        </div>
        <div class="workday-field full">
          <label for="wdNotes">Märkused</label>
          <textarea id="wdNotes" name="notes" placeholder="Materjalid, tehtud töö, probleemid, järgmised sammud..."><?= h($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="workday-form-actions">
        <button type="button" class="workday-cancel" id="workdayCancel">Tühista</button>
        <button type="submit" class="workday-save">Salvesta tööpäev</button>
      </div>
    </form>
  </div>

  <form method="get" class="workday-filters">
    <input type="hidden" name="view" value="workdays">
    <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Otsi töötajat, objekti, märkust...">
    <select name="worker"><option value="">Kõik töötajad</option><?php foreach ($workdayWorkers as $worker): ?><option value="<?= h($worker) ?>" <?= ($_GET['worker'] ?? '')===$worker?'selected':'' ?>><?= h($worker) ?></option><?php endforeach; ?></select>
    <select name="object"><option value="">Kõik objektid</option><?php foreach ($objects as $object): ?><option value="<?= h($object['name']) ?>" <?= ($_GET['object'] ?? '')===$object['name']?'selected':'' ?>><?= h($object['name']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">Kõik staatused</option><option value="pending" <?= ($_GET['status'] ?? '')==='pending'?'selected':'' ?>>Ootab kinnitamist</option><option value="confirmed" <?= ($_GET['status'] ?? '')==='confirmed'?'selected':'' ?>>Kinnitatud</option><option value="draft" <?= ($_GET['status'] ?? '')==='draft'?'selected':'' ?>>Mustand</option><option value="paid" <?= ($_GET['status'] ?? '')==='paid'?'selected':'' ?>>Makstud</option></select>
    <input type="date" name="from" value="<?= h($_GET['from'] ?? '') ?>" title="Alates">
    <input type="date" name="to" value="<?= h($_GET['to'] ?? '') ?>" title="Kuni">
    <button class="workday-filter-button" type="submit">Filtreeri</button>
    <a class="workday-clear" href="index.php?view=workdays">Nulli</a>
  </form>

  <div class="workday-list">
    <?php if (!$workdays): ?>
      <div class="workday-empty">Tööpäevi ei leitud. Vajuta „+ Lisa tööpäev”.</div>
    <?php endif; ?>
    <?php foreach ($workdays as $wd): ?>
      <?php
        $hours = workday_hours((string)$wd['start_time'], (string)$wd['end_time'], (int)$wd['break_minutes']);
        $isPiece = (($wd['payment_type'] ?? 'hourly') === 'piece');
        $piecePricingMode = (string)($wd['piece_pricing_mode'] ?? 'unit');
        $isFixedPiece = $isPiece && $piecePricingMode === 'fixed';
        $cost = $isPiece
            ? ($isFixedPiece ? (float)($wd['piece_fixed_price'] ?? 0) : ((float)($wd['piece_quantity'] ?? 0) * (float)($wd['piece_rate'] ?? 0)))
            : ($hours * (float)$wd['hourly_rate']);
        $statusLabel = ['pending'=>'Ootab kinnitamist','confirmed'=>'Kinnitatud','draft'=>'Mustand','paid'=>'Makstud'][$wd['status']] ?? 'Kinnitatud';
        $mapsUrl = !empty($wd['address']) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$wd['address']) : '';
      ?>
      <details class="workday-card">
        <summary>
          <div class="workday-card-main">
            <div class="workday-date"><strong><?= h(date('d.m.Y', strtotime((string)$wd['work_date']))) ?></strong><span><?= h(date('l', strtotime((string)$wd['work_date']))) ?></span></div>
            <div class="workday-person"><strong><?= h($wd['worker_name']) ?></strong><span><?= h($wd['work_type'] ?: 'Tööpäev') ?></span></div>
            <div class="workday-object"><strong><?= h($wd['object_name']) ?></strong><span><?= h($wd['address'] ?: 'Aadress puudub') ?></span></div>
            <?php if ($isPiece): ?>
              <div class="workday-payment-summary piece">
                <strong><?= $isFixedPiece ? 'Tükitöö · Komplekt' : 'Tükitöö' ?></strong>
                <?php if ($isFixedPiece): ?>
                  <span>Kokkulepitud hind <?= number_format($cost, 2, ',', ' ') ?> €</span>
                <?php else: ?>
                  <span><?= number_format((float)($wd['piece_quantity'] ?? 0), 2, ',', ' ') ?> <?= h($wd['piece_unit'] ?? 'm²') ?> · <?= number_format((float)($wd['piece_rate'] ?? 0), 2, ',', ' ') ?> €/<?= h($wd['piece_unit'] ?? 'm²') ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="workday-payment-summary hourly">
                <strong>Tunnitöö</strong>
                <span><?= number_format((float)($wd['hourly_rate'] ?? 0), 2, ',', ' ') ?> €/h</span>
              </div>
            <?php endif; ?>
            <div class="workday-time">
              <?= h(substr((string)$wd['start_time'],0,5)) ?>–<?= h(substr((string)$wd['end_time'],0,5)) ?>
              <?php if (($wd['status'] ?? '') === 'pending'): ?>
                <form method="post" action="index.php?view=workdays" class="workday-quick-confirm js-workday-quick-confirm">
                  <input type="hidden" name="quick_confirm_workday" value="1">
                  <input type="hidden" name="workday_id" value="<?= (int)$wd['id'] ?>">
                  <button type="submit" class="workday-status pending workday-status-button" title="Vajuta, et kinnitada tööpäev kohe"><?= h($statusLabel) ?></button>
                </form>
              <?php else: ?>
                <div class="workday-status <?= h($wd['status']) ?>"><?= h($statusLabel) ?></div>
              <?php endif; ?>
            </div>
            <div class="workday-hours"><?= $isPiece ? number_format($cost, 2, ',', ' ') . ' €' : number_format($hours, 1, ',', ' ') . ' h' ?></div>
            <span class="workday-chevron" aria-hidden="true"></span>
          </div>
        </summary>
        <div class="workday-detail">
          <div class="workday-meta">
            <span>Paus: <?= (int)$wd['break_minutes'] ?> min · töötundidest maha arvestatud</span>
            <?php if ($isPiece): ?>
              <?php if ($isFixedPiece): ?>
                <span>Tükitöö komplekthind: <?= number_format($cost,2,',',' ') ?> €</span>
              <?php else: ?>
                <span>Tükitöö: <?= number_format((float)($wd['piece_quantity'] ?? 0),2,',',' ') ?> <?= h($wd['piece_unit'] ?? 'm²') ?> × <?= number_format((float)($wd['piece_rate'] ?? 0),2,',',' ') ?> €</span>
              <?php endif; ?>
            <?php else: ?>
              <span>Tunnihind: <?= number_format((float)$wd['hourly_rate'],2,',',' ') ?> €</span>
            <?php endif; ?>
            <span>Töötasu: <?= number_format($cost,2,',',' ') ?> €</span>
            <span>Läbisõit: <?= number_format((float)$wd['mileage_km'],1,',',' ') ?> km</span>
            <?php if ($mapsUrl !== ''): ?><a class="workday-address-link" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener">Ava kaart ↗</a><?php endif; ?>
          </div>
          <?php if (!empty($wd['notes'])): ?><p style="white-space:pre-wrap;margin:0 0 16px;color:#475467"><?= h($wd['notes']) ?></p><?php endif; ?>
          <p class="workday-edit-heading">Muuda kirjet</p>
          <form method="post" action="index.php?view=workdays" class="workday-form js-workday-form">
            <input type="hidden" name="save_workday" value="1"><input type="hidden" name="workday_id" value="<?= (int)$wd['id'] ?>">
            <div class="workday-grid">
              <div class="workday-field"><label>Kuupäev</label><input type="date" name="work_date" value="<?= h($wd['work_date']) ?>" required></div>
              <div class="workday-field"><label>Töötaja</label><input type="text" name="worker_name" list="workdayWorkers" value="<?= h($wd['worker_name']) ?>" required></div>
              <div class="workday-field span2"><label>Olemasolev objekt</label><select name="object_id" class="js-object-select"><option value="0">— Käsitsi —</option><?php foreach ($objects as $object): ?><option value="<?= (int)$object['id'] ?>" data-name="<?= h($object['name']) ?>" data-address="<?= h($object['address']) ?>" <?= (int)$wd['object_id']===(int)$object['id']?'selected':'' ?>><?= h($object['name']) ?></option><?php endforeach; ?></select></div>
              <div class="workday-field span2"><label>Objekti nimetus</label><input type="text" name="object_name" class="js-object-name" value="<?= h($wd['object_name']) ?>" required></div>
              <div class="workday-field span2"><label>Aadress</label><input type="text" name="address" class="js-object-address" value="<?= h($wd['address'] ?? '') ?>"></div>
              <div class="workday-field"><label>Algus</label><input type="time" name="start_time" class="js-start-time" value="<?= h(substr((string)$wd['start_time'],0,5)) ?>" required></div>
              <div class="workday-field"><label>Lõpp</label><input type="time" name="end_time" class="js-end-time" value="<?= h(substr((string)$wd['end_time'],0,5)) ?>" required></div>
              <div class="workday-field"><label>Paus (min, ei lähe töötundide sisse)</label><input type="number" name="break_minutes" class="js-break-minutes" min="0" value="<?= (int)$wd['break_minutes'] ?>"></div>
              <div class="workday-field"><label>Staatus</label><select name="status"><option value="pending" <?= $wd['status']==='pending'?'selected':'' ?>>Ootab kinnitamist</option><option value="confirmed" <?= $wd['status']==='confirmed'?'selected':'' ?>>Kinnitatud</option><option value="draft" <?= $wd['status']==='draft'?'selected':'' ?>>Mustand</option><option value="paid" <?= $wd['status']==='paid'?'selected':'' ?>>Makstud</option></select></div>
              <div class="workday-field span2"><label>Tehtud töö</label><input type="text" name="work_type" value="<?= h($wd['work_type'] ?? '') ?>"></div>
              <div class="workday-field"><label>Tasustamise viis</label><select name="payment_type" class="js-payment-type"><option value="hourly" <?= ($wd['payment_type'] ?? 'hourly')==='hourly'?'selected':'' ?>>Tunnitöö</option><option value="piece" <?= ($wd['payment_type'] ?? '')==='piece'?'selected':'' ?>>Tükitöö</option></select></div>
              <div class="workday-field js-hourly-fields"><label>Tunnihind</label><input type="number" name="hourly_rate" class="js-hourly-rate" min="0" step="0.01" value="<?= h($wd['hourly_rate']) ?>"></div>
              <div class="workday-field js-piece-fields"><label>Tükitöö hinna tüüp</label><select name="piece_pricing_mode" class="js-piece-pricing-mode"><option value="unit" <?= ($wd['piece_pricing_mode'] ?? 'unit')==='unit'?'selected':'' ?>>Kogus × ühikuhind</option><option value="fixed" <?= ($wd['piece_pricing_mode'] ?? '')==='fixed'?'selected':'' ?>>Kokkulepitud komplekthind</option></select></div>
              <div class="workday-field js-piece-fields js-piece-unit-fields"><label>Tükitöö kogus</label><input type="number" name="piece_quantity" class="js-piece-quantity" min="0" step="0.01" value="<?= h($wd['piece_quantity'] ?? 0) ?>"></div>
              <div class="workday-field js-piece-fields js-piece-unit-fields"><label>Ühik</label><select name="piece_unit" class="js-piece-unit"><option value="m²" <?= ($wd['piece_unit'] ?? 'm²')==='m²'?'selected':'' ?>>m²</option><option value="jm" <?= ($wd['piece_unit'] ?? '')==='jm'?'selected':'' ?>>jm</option><option value="tk" <?= ($wd['piece_unit'] ?? '')==='tk'?'selected':'' ?>>tk</option><option value="m³" <?= ($wd['piece_unit'] ?? '')==='m³'?'selected':'' ?>>m³</option></select></div>
              <div class="workday-field js-piece-fields js-piece-unit-fields"><label>Hind ühiku kohta</label><input type="number" name="piece_rate" class="js-piece-rate" min="0" step="0.01" value="<?= h($wd['piece_rate'] ?? 0) ?>"></div>
              <div class="workday-field js-piece-fields js-piece-fixed-fields"><label>Kokkulepitud komplekthind</label><input type="number" name="piece_fixed_price" class="js-piece-fixed-price" min="0" step="0.01" value="<?= h($wd['piece_fixed_price'] ?? 0) ?>" placeholder="Näiteks 150.00 €"></div>
              <div class="workday-field"><label>Läbisõit km</label><input type="number" name="mileage_km" min="0" step="0.1" value="<?= h($wd['mileage_km']) ?>"></div>
              <div class="workday-field full"><div class="workday-live-calc">Arvestus: <strong class="js-hours-preview">0,0 h</strong><span>•</span> töötasu <strong class="js-cost-preview">0,00 €</strong></div></div>
              <div class="workday-field full"><label>Märkused</label><textarea name="notes"><?= h($wd['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="workday-form-actions"><button type="submit" class="workday-save">Salvesta muudatused</button></div>
          </form>
          <form method="post" action="index.php?view=workdays" onsubmit="return confirm('Kustutan selle tööpäeva?');" style="margin-top:10px"><input type="hidden" name="delete_workday" value="1"><input type="hidden" name="workday_id" value="<?= (int)$wd['id'] ?>"><button type="submit" class="workday-delete">Kustuta tööpäev</button></form>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif ($view === 'price-list'): ?>
<section class="price-wrap" aria-labelledby="priceListTitle">
<div class="price-head"><div><h2 id="priceListTitle">Hinnakiri</h2><p>Orienteeruvad alghinnad Tallinnas ja Harjumaal. Kõiki ridu saad siin muuta, lisada või eemaldada.</p></div><button type="button" class="price-add-toggle" id="priceAddToggle">+ Lisa hinnakirja rida</button></div>
<?php if(isset($_GET['saved'])):?><div class="price-alert ok">Hinnakirja muudatus salvestatud.</div><?php endif;?><?php if(isset($_GET['deleted'])):?><div class="price-alert ok">Hinnakirja rida eemaldatud.</div><?php endif;?><?php if($priceListFormError!==''):?><div class="price-alert error"><?=h($priceListFormError)?></div><?php endif;?>
<div class="price-search-box" role="search" aria-label="Hinnakirja otsing">
  <div class="price-search-title">Otsi hinda</div>
  <p class="price-search-help">Sisesta töö või teenuse nimi. Näitan kohe lähimaid vasteid, ühikut ja hinnakirja hinda.</p>
  <div class="price-search-input-wrap">
    <input class="price-search-input" id="priceQuickSearch" type="search" autocomplete="off" placeholder="Näiteks: pahteldus, fassaadi pesu, plaatimine, laminaat..." aria-controls="priceQuickResults" aria-expanded="false">
    <span class="price-search-icon" aria-hidden="true">⌕</span>
  </div>
  <div class="price-search-results" id="priceQuickResults" role="listbox"></div>
  <div class="price-search-tip">Vihje: võid kirjutada ka osa nimetusest, näiteks „värv“, „kips“, „wc“ või „fassaad“.</div>
</div>
<div class="price-panel <?= $priceListFormError!==''?'open':'' ?>" id="priceAddPanel"><form method="post" action="index.php?view=price-list"><input type="hidden" name="save_price_item" value="1"><div class="price-form-grid"><div class="price-field"><label>Tüüp</label><select name="item_type"><option value="service">Töö / teenus</option><option value="package">Näidispakett</option></select></div><div class="price-field"><label>Kategooria *</label><input name="category" required></div><div class="price-field" style="grid-column:span 2"><label>Nimetus *</label><input name="name" required></div><div class="price-field"><label>Ühik</label><input name="unit" placeholder="m², jm, tk, komplekt"></div><div class="price-field"><label>Hind alates (€)</label><input type="number" step="0.01" min="0" name="price_from" value="0"></div><div class="price-field"><label>Koos materjaliga alates (€)</label><input type="number" step="0.01" min="0" name="material_price_from"></div><div class="price-field"><label>Järjekord</label><input type="number" name="sort_order" value="600"></div><div class="price-field full"><label>Kirjeldus / märkus</label><textarea name="description"></textarea></div></div><div class="price-form-actions"><button class="price-save" type="submit">Lisa hinnakirja</button></div></form></div>
<?php $packages=array_values(array_filter($priceListItems??[],static fn($r)=>($r['item_type']??'service')==='package'));?><div class="price-intro"><?php foreach($packages as $pkg):?><div class="price-package"><span>Näidispakett</span><strong><?=h($pkg['name'])?></strong><div class="price-package-price">al <?=number_format((float)$pkg['price_from'],0,',',' ')?> €</div><?php if(!empty($pkg['description'])):?><p><?=h($pkg['description'])?></p><?php endif;?></div><?php endforeach;?></div>
<?php $services=array_values(array_filter($priceListItems??[],static fn($r)=>($r['item_type']??'service')==='service'));$categories=[];foreach($services as $r)$categories[(string)$r['category']][]=$r;?>
<?php foreach($categories as $category=>$rows):?><div class="price-category"><h3><?=h($category)?></h3><?php foreach($rows as $row):?><details class="price-edit-card"><summary><div class="price-summary"><strong><?=h($row['name'])?></strong><span><?=h($row['unit']??'—')?></span><span class="price-money">al <?=number_format((float)$row['price_from'],2,',',' ')?> €</span><span><?php if($row['material_price_from']!==null):?><strong>materjaliga al <?=number_format((float)$row['material_price_from'],2,',',' ')?> €</strong><?php elseif(!empty($row['description'])):?><span class="price-note"><?=h($row['description'])?></span><?php else:?><span class="muted">—</span><?php endif;?></span><span class="price-chevron"></span></div></summary><div class="price-edit-body"><form method="post" action="index.php?view=price-list"><input type="hidden" name="save_price_item" value="1"><input type="hidden" name="price_id" value="<?=(int)$row['id']?>"><input type="hidden" name="item_type" value="service"><div class="price-form-grid"><div class="price-field"><label>Kategooria</label><input name="category" value="<?=h($row['category'])?>" required></div><div class="price-field" style="grid-column:span 2"><label>Nimetus</label><input name="name" value="<?=h($row['name'])?>" required></div><div class="price-field"><label>Ühik</label><input name="unit" value="<?=h($row['unit']??'')?>"></div><div class="price-field"><label>Hind alates (€)</label><input type="number" step="0.01" min="0" name="price_from" value="<?=h((string)$row['price_from'])?>"></div><div class="price-field"><label>Koos materjaliga alates (€)</label><input type="number" step="0.01" min="0" name="material_price_from" value="<?=$row['material_price_from']!==null?h((string)$row['material_price_from']):''?>"></div><div class="price-field"><label>Järjekord</label><input type="number" name="sort_order" value="<?=(int)$row['sort_order']?>"></div><div class="price-field full"><label>Märkus</label><textarea name="description"><?=h($row['description']??'')?></textarea></div></div><div class="price-form-actions"><button class="price-save" type="submit">Salvesta</button></div></form><form method="post" action="index.php?view=price-list" onsubmit="return confirm('Kas eemaldan selle hinnakirja rea?');" style="margin-top:8px"><input type="hidden" name="delete_price_item" value="1"><input type="hidden" name="price_id" value="<?=(int)$row['id']?>"><button class="price-delete" type="submit">Eemalda</button></form></div></details><?php endforeach;?></div><?php endforeach;?>
</section>
<?php elseif ($view === 'workers'): ?>
<section class="worker-wrap" aria-labelledby="workersTitle">
  <div class="worker-head">
    <div>
      <h2 id="workersTitle">Töölised</h2>
      <p>Halda töötajaid, kontaktandmeid, pädevusi, kogemust ja hetke saadavust.</p>
    </div>
    <button type="button" class="worker-add-toggle" id="workerAddToggle">+ Lisa tööline</button>
  </div>

  <div class="worker-stats">
    <div class="worker-stat"><span>Töölisi kokku</span><strong><?= (int)$workerCounts['all'] ?></strong></div>
    <div class="worker-stat"><span>Vaba</span><strong><?= (int)$workerCounts['free'] ?></strong></div>
    <div class="worker-stat"><span>Ootel</span><strong><?= (int)$workerCounts['waiting'] ?></strong></div>
    <div class="worker-stat"><span>Hõivatud</span><strong><?= (int)$workerCounts['busy'] ?></strong></div>
  </div>

  <?php if (isset($_GET['saved'])): ?><div class="worker-alert ok">Töölise andmed on salvestatud.</div><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><div class="worker-alert ok">Tööline on eemaldatud.</div><?php endif; ?>
  <?php if ($workerFormError !== ''): ?><div class="worker-alert error"><?= h($workerFormError) ?></div><?php endif; ?>

  <div class="worker-panel <?= $workerFormError !== '' ? 'open' : '' ?>" id="workerAddPanel">
    <div class="worker-panel-head"><h3>Uus tööline</h3><button type="button" class="worker-close" id="workerAddClose" aria-label="Sulge">×</button></div>
    <form method="post" action="index.php?view=workers">
      <input type="hidden" name="save_worker" value="1">
      <div class="worker-grid">
        <div class="worker-field"><label>Töölise nimi *</label><input type="text" name="name" placeholder="Näiteks Hannes" required></div>
        <div class="worker-field"><label>Kontakt</label><input type="text" name="contact" placeholder="+372 5... või email"></div>
        <div class="worker-field"><label>Pädevused / amet</label><input type="text" name="role_skills" placeholder="Näiteks üldehitaja, fassaad, plaatimine"></div>
        <div class="worker-field"><label>Kogemus valdkonnas</label><input type="text" name="experience" placeholder="Näiteks 7 aastat"></div>
        <div class="worker-field"><label>Staatus</label><select name="worker_status"><option value="free">Vaba</option><option value="waiting">Ootel</option><option value="busy">Hõivatud</option></select></div>
        <div class="worker-field full"><label>Märkused</label><textarea name="notes" placeholder="Lisainfo, load, sertifikaadid, eelistused..."></textarea></div>
      </div>
      <div class="worker-form-actions"><button class="worker-save" type="submit">Lisa tööline</button></div>
    </form>
  </div>

  <div class="worker-list">
    <?php if (!$workers): ?><div class="worker-empty">Töölisi ei ole veel. Vajuta „+ Lisa tööline”.</div><?php endif; ?>
    <?php foreach ($workers as $worker): ?>
      <?php
        $workerStatus = (string)($worker['status'] ?? 'free');
        $workerStatusText = ['busy'=>'Hõivatud','waiting'=>'Ootel','free'=>'Vaba'][$workerStatus] ?? 'Vaba';
        $contact = trim((string)($worker['contact'] ?? ''));
        $contactHref = '';
        if ($contact !== '') {
            $contactHref = str_contains($contact, '@') ? 'mailto:' . $contact : 'tel:' . preg_replace('/[^\d+]/', '', $contact);
        }
      ?>
      <details class="worker-card">
        <summary>
          <div class="worker-card-row">
            <div class="worker-main"><strong><?= h($worker['name']) ?></strong><span class="worker-sub">Tööline #<?= (int)$worker['id'] ?></span></div>
            <div class="worker-contact"><span class="worker-col-label">Kontakt</span><?php if ($contact !== ''): ?><a class="worker-contact-link" href="<?= h($contactHref) ?>"><?= h($contact) ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></div>
            <div class="worker-role"><span class="worker-col-label">Pädevused / amet</span><?= h($worker['role_skills'] ?? '—') ?></div>
            <div class="worker-exp"><span class="worker-col-label">Kogemus</span><?= h($worker['experience'] ?? '—') ?></div>
            <div class="worker-status-wrap"><span class="worker-status <?= h($workerStatus) ?>"><?= h($workerStatusText) ?></span></div>
            <span class="worker-chevron" aria-hidden="true"></span>
          </div>
        </summary>
        <div class="worker-detail">
          <form method="post" action="index.php?view=workers">
            <input type="hidden" name="save_worker" value="1"><input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>">
            <div class="worker-grid">
              <div class="worker-field"><label>Töölise nimi *</label><input type="text" name="name" value="<?= h($worker['name']) ?>" required></div>
              <div class="worker-field"><label>Kontakt</label><input type="text" name="contact" value="<?= h($worker['contact'] ?? '') ?>"></div>
              <div class="worker-field"><label>Pädevused / amet</label><input type="text" name="role_skills" value="<?= h($worker['role_skills'] ?? '') ?>"></div>
              <div class="worker-field"><label>Kogemus valdkonnas</label><input type="text" name="experience" value="<?= h($worker['experience'] ?? '') ?>"></div>
              <div class="worker-field"><label>Staatus</label><select name="worker_status"><option value="free" <?= $workerStatus==='free'?'selected':'' ?>>Vaba</option><option value="waiting" <?= $workerStatus==='waiting'?'selected':'' ?>>Ootel</option><option value="busy" <?= $workerStatus==='busy'?'selected':'' ?>>Hõivatud</option></select></div>
              <div class="worker-field full"><label>Märkused</label><textarea name="notes"><?= h($worker['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="worker-form-actions"><button class="worker-save" type="submit">Salvesta muudatused</button></div>
          </form>
          <form method="post" action="index.php?view=workers" onsubmit="return confirm('Kas eemaldan selle töölise?');"><input type="hidden" name="delete_worker" value="1"><input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>"><button type="submit" class="worker-delete">Eemalda tööline</button></form>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif ($view === 'calculations'): ?>
<div class="calculations-layout">
<section class="calculator-wrap" aria-labelledby="fuelCalculatorTitle">
  <h2 class="calculator-title" id="fuelCalculatorTitle">Kütuse kalkulaator</h2>
  <p class="calculator-subtitle">Sisesta teekonna pikkus, sõiduki keskmine kütusekulu ja kütuse liitrihind. Kütuse kogus ning maksumus arvutatakse automaatselt.</p>

  <div class="calculator-grid">
    <label for="fuelDistance">Teekonna pikkus</label>
    <div class="calculator-input-wrap">
      <input type="number" id="fuelDistance" min="0" step="0.1" inputmode="decimal" value="1200">
      <span>km</span>
    </div>

    <label for="fuelConsumption">Keskmine kütusekulu<span class="calculator-label-info">Skoda Octavia 1.0 TSI 85 kW ametlik tehasekulu: linn 5,4 · maantee 4,2–4,4 · keskmine 4,6–4,8 l/100 km</span></label>
    <div class="calculator-input-wrap">
      <input type="number" id="fuelConsumption" min="0" step="0.1" inputmode="decimal" value="5.7">
      <span>l / 100 km</span>
    </div>

    <label for="fuelPrice">Kütuseühiku hind</label>
    <div class="calculator-input-wrap">
      <input type="number" id="fuelPrice" min="0" step="0.01" inputmode="decimal" value="1.30">
      <span>€ / l</span>
    </div>
  </div>

  <div class="calculator-results" aria-live="polite">
    <div class="calculator-result">
      <span class="calculator-result-label">Kütuse kogus</span>
      <strong class="calculator-result-value" id="fuelAmount">68,40 l</strong>
    </div>
    <div class="calculator-result">
      <span class="calculator-result-label">Kütuse maksumus</span>
      <strong class="calculator-result-value" id="fuelCost">88,92 €</strong>
    </div>
  </div>

  <div class="calculator-actions">
    <button type="button" class="button-reset" id="fuelReset">Tühjenda väljad</button>
  </div>
</section>

<aside class="fuel-live-card" aria-labelledby="fuelLiveTitle">
  <span class="fuel-live-badge">Bensiin 95 · Tallinn</span>
  <h2 class="fuel-live-title" id="fuelLiveTitle">Tallinna odavaim hind</h2>
  <p class="fuel-live-subtitle">Viimase 24 tunni jooksul uuendatud hinnad kuni 10 km raadiuses.</p>

  <?php if ($fuelPriceError !== ''): ?>
    <div class="fuel-live-error" role="alert">
      <strong>Hinda ei õnnestunud laadida.</strong>
      <?= h($fuelPriceError) ?>
    </div>
    <div class="fuel-live-actions">
      <a class="fuel-live-button secondary" href="index.php?view=calculations&amp;fuel_refresh=1">Proovi uuesti</a>
      <a class="fuel-live-button" href="https://autoportaal.ee/et/kutusehinnad/tallinn?fuel_id=1&amp;location_id=1&amp;distance=10&amp;brand_id=&amp;seconds=86400" target="_blank" rel="noopener noreferrer">Ava Autoportaal</a>
    </div>
  <?php else: ?>
    <div class="fuel-live-price">
      <strong><?= number_format((float) ($fuelPriceInfo['price'] ?? 0), 3, ',', ' ') ?></strong>
      <span>€/l</span>
    </div>
    <div class="fuel-live-station"><?= h($fuelPriceInfo['station'] ?? 'Tankla') ?></div>
    <div class="fuel-live-meta">
      <?php if (!empty($fuelPriceInfo['distance'])): ?><span><?= h($fuelPriceInfo['distance']) ?></span><?php endif; ?>
      <?php if (!empty($fuelPriceInfo['updated'])): ?><span>Uuendus: <?= h($fuelPriceInfo['updated']) ?></span><?php endif; ?>
      <span>Kontrollitud <?= h(date('H:i', (int) ($fuelPriceInfo['fetchedAt'] ?? time()))) ?></span>
    </div>
    <div class="fuel-live-actions">
      <button type="button" class="fuel-live-button" id="useCheapestFuelPrice" data-price="<?= h((string) ($fuelPriceInfo['price'] ?? '')) ?>">Kasuta kalkulaatoris</button>
      <a class="fuel-live-button secondary" href="index.php?view=calculations&amp;fuel_refresh=1">Värskenda</a>
    </div>
    <a class="fuel-live-source" href="<?= h($fuelPriceInfo['sourceUrl'] ?? 'https://autoportaal.ee/et/kutusehinnad/tallinn') ?>" target="_blank" rel="noopener noreferrer">Allikas: Autoportaal.ee · hinnad võivad muutuda ja ei pruugi sisaldada kliendisoodustusi.</a>
  <?php endif; ?>
</aside>
</div>
<?php elseif ($view === 'statistics'): ?>
<?php
  $overviewReport = is_array($analyticsData['overview'] ?? null) ? $analyticsData['overview'] : [];
  $activeUsers = ga_first_metric($overviewReport, 'activeUsers');
  $newUsers = ga_first_metric($overviewReport, 'newUsers');
  $sessions = ga_first_metric($overviewReport, 'sessions');
  $pageViews = ga_first_metric($overviewReport, 'screenPageViews');
  $averageSessionDuration = ga_first_metric($overviewReport, 'averageSessionDuration');
  $engagementRate = ga_first_metric($overviewReport, 'engagementRate') * 100;
  $bounceRate = ga_first_metric($overviewReport, 'bounceRate') * 100;
  $keyEvents = ga_first_metric($overviewReport, 'keyEvents');
  $trendRows = $analyticsData['trend'] ?? [];
  $chartRows = [];
  foreach ($trendRows as $trendRow) {
      $rawDate = (string) ($trendRow['date'] ?? '');
      $dateLabel = $rawDate;
      if (preg_match('/^\d{8}$/', $rawDate)) {
          $dateLabel = substr($rawDate, 6, 2) . '.' . substr($rawDate, 4, 2);
      }
      $chartRows[] = [
          'date' => $dateLabel,
          'users' => (int) ($trendRow['activeUsers'] ?? 0),
          'sessions' => (int) ($trendRow['sessions'] ?? 0),
          'views' => (int) ($trendRow['screenPageViews'] ?? 0),
      ];
  }
?>
<section class="analytics-wrap" aria-labelledby="analyticsTitle">
  <div class="analytics-toolbar">
    <div>
      <h2 id="analyticsTitle">Google Analytics ülevaade</h2>
      <p>Renoveeri Kodu · GA4 Property 527723533</p>
    </div>
    <div class="analytics-filters">
      <div class="analytics-period" aria-label="Vali periood">
        <a class="<?= $analyticsDays === '7' ? 'active' : '' ?>" href="index.php?view=statistics&amp;days=7">7 päeva</a>
        <a class="<?= $analyticsDays === '30' ? 'active' : '' ?>" href="index.php?view=statistics&amp;days=30">30 päeva</a>
        <a class="<?= $analyticsDays === '90' ? 'active' : '' ?>" href="index.php?view=statistics&amp;days=90">90 päeva</a>
      </div>
      <a class="analytics-refresh" href="index.php?view=statistics&amp;days=<?= h($analyticsDays) ?>&amp;refresh=1">Värskenda</a>
    </div>
  </div>

  <?php if ($analyticsError !== ''): ?>
    <div class="analytics-error" role="alert">
      <strong>Google Analyticsi andmeid ei õnnestunud laadida.</strong>
      <?= h($analyticsError) ?>
    </div>
  <?php else: ?>
    <div class="analytics-cards">
      <div class="analytics-card realtime">
        <span class="analytics-card-label">Aktiivsed praegu</span>
        <strong class="analytics-card-value"><?= ga_number((float) ($analyticsData['realtimeUsers'] ?? 0)) ?></strong>
        <span class="analytics-card-note">Viimase 30 minuti jooksul</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Aktiivsed kasutajad</span>
        <strong class="analytics-card-value"><?= ga_number($activeUsers) ?></strong>
        <span class="analytics-card-note"><?= h($analyticsDays) ?> päeva</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Uued kasutajad</span>
        <strong class="analytics-card-value"><?= ga_number($newUsers) ?></strong>
        <span class="analytics-card-note"><?= h($analyticsDays) ?> päeva</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Sessioonid</span>
        <strong class="analytics-card-value"><?= ga_number($sessions) ?></strong>
        <span class="analytics-card-note"><?= h($analyticsDays) ?> päeva</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Lehevaatamised</span>
        <strong class="analytics-card-value"><?= ga_number($pageViews) ?></strong>
        <span class="analytics-card-note"><?= h($analyticsDays) ?> päeva</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Keskmine sessioon</span>
        <strong class="analytics-card-value"><?= h(ga_duration($averageSessionDuration)) ?></strong>
        <span class="analytics-card-note">Keskmine kestus</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Kaasatuse määr</span>
        <strong class="analytics-card-value"><?= ga_number($engagementRate, 1) ?>%</strong>
        <span class="analytics-card-note">Kaasatud sessioonide osakaal</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Põrkemäär</span>
        <strong class="analytics-card-value"><?= ga_number($bounceRate, 1) ?>%</strong>
        <span class="analytics-card-note">Mittekaasatud sessioonid</span>
      </div>
      <div class="analytics-card">
        <span class="analytics-card-label">Võtmesündmused</span>
        <strong class="analytics-card-value"><?= ga_number($keyEvents) ?></strong>
        <span class="analytics-card-note">GA4 key events</span>
      </div>
    </div>

    <div class="analytics-panel">
      <div class="analytics-panel-head">
        <div>
          <h3>Külastuste trend</h3>
          <div class="analytics-panel-subtitle">Päevade kaupa valitud perioodil</div>
        </div>
        <div class="analytics-chart-legend" aria-hidden="true">
          <span><i style="background:#2563eb"></i>Kasutajad</span>
          <span><i style="background:#10b981"></i>Sessioonid</span>
          <span><i style="background:#f59e0b"></i>Vaatamised</span>
        </div>
      </div>
      <?php if ($chartRows): ?>
        <div class="analytics-chart-wrap"><canvas id="analyticsTrend" aria-label="Külastuste trendi graafik"></canvas></div>
      <?php else: ?>
        <div class="analytics-empty">Valitud perioodil pole trendiandmeid.</div>
      <?php endif; ?>
    </div>

    <div class="analytics-grid">
      <div class="analytics-panel">
        <div class="analytics-panel-head"><div><h3>Populaarsemad lehed</h3><div class="analytics-panel-subtitle">Lehevaatamiste järgi</div></div></div>
        <div class="analytics-table-wrap">
          <table class="analytics-table">
            <thead><tr><th>Leht</th><th>Vaatamised</th><th>Kasutajad</th><th>Sessioonid</th></tr></thead>
            <tbody>
            <?php foreach ($analyticsData['pages'] ?? [] as $row): ?>
              <tr><td><?= h($row['pagePath'] ?: '/') ?></td><td><?= ga_number((float) $row['screenPageViews']) ?></td><td><?= ga_number((float) $row['activeUsers']) ?></td><td><?= ga_number((float) $row['sessions']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($analyticsData['pages'])): ?><tr><td colspan="4" class="analytics-empty">Andmed puuduvad.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="analytics-panel">
        <div class="analytics-panel-head"><div><h3>Liikluse kanalid</h3><div class="analytics-panel-subtitle">Kust külastajad saabusid</div></div></div>
        <div class="analytics-table-wrap">
          <table class="analytics-table">
            <thead><tr><th>Kanal</th><th>Sessioonid</th><th>Kasutajad</th><th>Vaatamised</th></tr></thead>
            <tbody>
            <?php foreach ($analyticsData['sources'] ?? [] as $row): ?>
              <tr><td><?= h($row['sessionDefaultChannelGroup'] ?: 'Määramata') ?></td><td><?= ga_number((float) $row['sessions']) ?></td><td><?= ga_number((float) $row['activeUsers']) ?></td><td><?= ga_number((float) $row['screenPageViews']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($analyticsData['sources'])): ?><tr><td colspan="4" class="analytics-empty">Andmed puuduvad.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="analytics-panel">
        <div class="analytics-panel-head"><div><h3>Seadmed</h3><div class="analytics-panel-subtitle">Telefon, arvuti ja tahvel</div></div></div>
        <div class="analytics-table-wrap">
          <table class="analytics-table">
            <thead><tr><th>Seade</th><th>Kasutajad</th><th>Sessioonid</th></tr></thead>
            <tbody>
            <?php foreach ($analyticsData['devices'] ?? [] as $row): ?>
              <tr><td><?= h($row['deviceCategory'] ?: 'Määramata') ?></td><td><?= ga_number((float) $row['activeUsers']) ?></td><td><?= ga_number((float) $row['sessions']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($analyticsData['devices'])): ?><tr><td colspan="3" class="analytics-empty">Andmed puuduvad.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="analytics-panel">
        <div class="analytics-panel-head"><div><h3>Riigid</h3><div class="analytics-panel-subtitle">Kasutajate asukoha järgi</div></div></div>
        <div class="analytics-table-wrap">
          <table class="analytics-table">
            <thead><tr><th>Riik</th><th>Kasutajad</th><th>Sessioonid</th></tr></thead>
            <tbody>
            <?php foreach ($analyticsData['countries'] ?? [] as $row): ?>
              <tr><td><?= h($row['country'] ?: 'Määramata') ?></td><td><?= ga_number((float) $row['activeUsers']) ?></td><td><?= ga_number((float) $row['sessions']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($analyticsData['countries'])): ?><tr><td colspan="3" class="analytics-empty">Andmed puuduvad.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="analytics-panel">
        <div class="analytics-panel-head"><div><h3>Sündmused</h3><div class="analytics-panel-subtitle">Kasutajate tegevused veebilehel</div></div></div>
        <div class="analytics-table-wrap">
          <table class="analytics-table">
            <thead><tr><th>Sündmus</th><th>Kordi</th><th>Võtmesündmused</th></tr></thead>
            <tbody>
            <?php foreach ($analyticsData['events'] ?? [] as $row): ?>
              <tr><td><?= h($row['eventName'] ?: 'Määramata') ?></td><td><?= ga_number((float) $row['eventCount']) ?></td><td><?= ga_number((float) $row['keyEvents']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($analyticsData['events'])): ?><tr><td colspan="3" class="analytics-empty">Andmed puuduvad.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="analytics-updated">Viimati uuendatud: <?= h(date('d.m.Y H:i', (int) ($analyticsData['fetchedAt'] ?? time()))) ?> · andmeid hoitakse 5 minutit vahemälus</div>
  <?php endif; ?>
</section>
<?php elseif ($view === 'email-campaigns'): ?>
<section class="email-wrap">
  <div class="email-head"><div><h2>Email kampaaniad</h2><p>Koosta, ajasta, saada ja jälgi uudiskirju. Saadetakse ainult aktiivsetele subscriberitele.</p></div><a class="email-btn green" href="index.php?view=email-campaign">+ Uus kampaania</a></div>
  <?php if(isset($_GET['deleted'])):?><div class="email-flash">Kampaania on kustutatud.</div><?php endif;?>
  <div class="email-stats">
    <div class="email-stat"><span>Kampaaniaid</span><strong><?= count($emailCampaigns) ?></strong></div>
    <div class="email-stat"><span>Subscriberid</span><strong><?= $subscriberCount ?></strong></div>
    <div class="email-stat"><span>Saadetud kokku</span><strong><?= array_sum(array_map(fn($c)=>(int)$c['sent_count'],$emailCampaigns)) ?></strong></div>
    <div class="email-stat"><span>Avamisi</span><strong><?= array_sum(array_map(fn($c)=>(int)$c['open_count'],$emailCampaigns)) ?></strong></div>
    <div class="email-stat"><span>Klikke</span><strong><?= array_sum(array_map(fn($c)=>(int)$c['click_count'],$emailCampaigns)) ?></strong></div>
    <div class="email-stat"><span>Transport</span><strong style="font-size:15px"><?= h(strtoupper((string)($emailSettings['transport']??'smtp'))) ?></strong></div>
  </div>
  <div class="email-campaign-list">
  <?php if(!$emailCampaigns):?><div class="object-empty">Kampaaniaid ei ole veel. Vajuta „+ Uus kampaania”.</div><?php endif;?>
  <?php foreach($emailCampaigns as $c): $total=max(0,(int)$c['recipient_count']); $sent=(int)$c['sent_count']; ?>
    <div class="email-campaign-card">
      <div class="email-campaign-name"><strong><?= h($c['name']) ?></strong><small><?= h($c['subject']) ?> · <?= h(date('d.m.Y H:i',strtotime((string)$c['created_at']))) ?></small></div>
      <span class="email-status <?= h($c['status']) ?>"><?= h(['draft'=>'Mustand','queued'=>'Järjekorras','sending'=>'Saatmisel','completed'=>'Valmis','paused'=>'Pausil'][$c['status']]??$c['status']) ?></span>
      <div><small class="muted">Adressaate</small><br><strong><?= $total ?></strong></div>
      <div><small class="muted">Saadetud</small><br><strong><?= $sent ?></strong></div>
      <div><small class="muted">Avatud</small><br><strong><?= (int)$c['open_count'] ?></strong></div>
      <div><small class="muted">Klikid</small><br><strong><?= (int)$c['click_count'] ?></strong></div>
      <a class="email-btn secondary" href="index.php?view=email-campaign&amp;campaign_id=<?= (int)$c['id'] ?>">Ava</a>
    </div>
  <?php endforeach;?>
  </div>
</section>

<?php elseif ($view === 'email-campaign'): ?>
<?php
  $settings=$emailSettings;
  $isNew=!$emailCampaign;
  $templateId=(int)($_GET['template_id']??0);$chosenTemplate=null;
  if($isNew && $templateId>0){foreach($emailTemplates as $t){if((int)$t['id']===$templateId){$chosenTemplate=$t;break;}}}
  $c=$emailCampaign ?: ['id'=>0,'name'=>'','subject'=>$chosenTemplate['subject']??'','preheader'=>'','html_body'=>$chosenTemplate['html_body']??'','text_body'=>'','sender_name'=>$settings['sender_name']??'RK Meistrid OÜ','sender_email'=>$settings['sender_email']??'info@renoveerikodu.ee','reply_to'=>$settings['reply_to']??'','scheduled_at'=>null,'status'=>'draft'];
  $total=(int)($emailCampaignStats['total']??0);$sent=(int)($emailCampaignStats['sent']??0);$pct=$total>0?min(100,round($sent/$total*100)):0;
?>
<section class="email-wrap">
  <div class="email-head"><div><h2><?= $isNew?'Uus email kampaania':'Kampaania: '.h($c['name']) ?></h2><p>Visuaalne editor, testkiri, ajastus, järjekord ja statistika.</p></div><a class="email-btn secondary" href="index.php?view=email-campaigns">← Kampaaniad</a></div>
  <?php if(isset($_GET['saved'])):?><div class="email-flash">Kampaania salvestatud.</div><?php endif;?>
  <?php if(isset($_GET['queued'])):?><div class="email-flash">Kampaania on saatmisjärjekorras. Worker saadab kirju partiidena.</div><?php endif;?>
  <?php if(isset($_GET['test_sent'])):?><div class="email-flash">Testkiri saadetud.</div><?php endif;?>
  <?php if(isset($_GET['test_error'])):?><div class="email-error">Testkirja viga: <?= h((string)$_GET['test_error']) ?></div><?php endif;?>
  <?php if(isset($_GET['send_error'])):?><div class="email-error">Saatmise viga: <?= h((string)$_GET['send_error']) ?></div><?php endif;?>
  <?php if(isset($_GET['queue_error'])):?><div class="email-error">Enne järjekorda panemist sisesta vähemalt teema ja kirja sisu.</div><?php endif;?>
  <?php if(isset($_GET['batch_sent'])):?><div class="email-flash">Partii töödeldud: saadetud <?= (int)$_GET['batch_sent'] ?>, ebaõnnestus <?= (int)($_GET['batch_failed']??0) ?>.</div><?php endif;?>
  <?php if(!$isNew):?>
  <div class="email-stats">
    <div class="email-stat"><span>Adressaate</span><strong><?= $total ?></strong></div><div class="email-stat"><span>Ootel</span><strong><?= (int)($emailCampaignStats['pending']??0) ?></strong></div><div class="email-stat"><span>Saadetud</span><strong><?= $sent ?></strong></div><div class="email-stat"><span>Avatud</span><strong><?= (int)($emailCampaignStats['opened']??0) ?></strong></div><div class="email-stat"><span>Klikid</span><strong><?= (int)($emailCampaignStats['clicked']??0) ?></strong></div><div class="email-stat"><span>Loobunud</span><strong><?= (int)($emailCampaignStats['unsubscribed']??0) ?></strong></div>
  </div>
  <?php if($total>0):?><div class="email-progress" title="<?= $pct ?>%"><span style="width:<?= $pct ?>%"></span></div><div class="muted" style="margin:5px 0 18px"><?= $pct ?>% saatmisest tehtud</div><?php endif;?>
  <?php endif;?>
  <form method="post" id="emailCampaignForm">
    <input type="hidden" name="save_email_campaign" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"><textarea name="html_body" id="emailHtmlBody" hidden><?= h($c['html_body']??'') ?></textarea>
    <div class="email-editor-grid">
      <div class="email-panel">
        <h3>Kampaania sisu</h3>
        <div class="email-form-grid">
          <div class="email-field"><label>Kampaania nimi</label><input name="name" value="<?= h($c['name']??'') ?>" placeholder="Nt Augusti pakkumine"></div>
          <div class="email-field"><label>Mall</label><select id="emailTemplateSelect"><option value="">— Vali mall —</option><?php foreach($emailTemplates as $t):?><option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach;?></select></div>
          <div class="email-field full"><label>Emaili teema *</label><input id="emailSubject" name="subject" value="<?= h($c['subject']??'') ?>" required placeholder="Nt Fassaaditööd enne sügist"></div>
          <div class="email-field full"><label>Preheader / eelvaate tekst</label><input name="preheader" value="<?= h($c['preheader']??'') ?>" placeholder="Tekst, mida postkast näitab teema kõrval"></div>
          <div class="email-field full"><label>Visuaalne sisu</label>
            <div class="email-designer-toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="formatBlock" data-value="h2">H2</button><button type="button" data-cmd="insertUnorderedList">• Nimekiri</button><button type="button" id="emailInsertLink">🔗 Link</button><button type="button" id="emailInsertButton">+ CTA nupp</button></div>
            <div class="email-designer" id="emailDesigner" contenteditable="true"><?= $c['html_body']??'' ?></div>
            <div class="email-help">Dünaamilised väljad: <code>{{email}}</code> ja <code>{{unsubscribe_url}}</code>. Kui loobumislink puudub, lisatakse see automaatselt kirja lõppu.</div>
          </div>
          <div class="email-field full"><label>Plain-text versioon (soovi korral)</label><textarea name="text_body" placeholder="Kui jätad tühjaks, tehakse HTML-ist automaatselt tekstiversioon."><?= h($c['text_body']??'') ?></textarea></div>
        </div>
        <div class="email-actions"><button class="email-btn green" type="submit">Salvesta</button></div>
      </div>
      <aside class="email-panel">
        <h3>Saatmine</h3>
        <div class="email-form-grid">
          <div class="email-field full"><label>Saatja nimi</label><input name="sender_name" value="<?= h($c['sender_name']??'') ?>"></div>
          <div class="email-field full"><label>Saatja e-post</label><input type="email" name="sender_email" value="<?= h($c['sender_email']??'') ?>"></div>
          <div class="email-field full"><label>Reply-to</label><input type="email" name="reply_to" value="<?= h($c['reply_to']??'') ?>"></div>
          <div class="email-field full"><label>Ajastatud aeg</label><input type="datetime-local" name="scheduled_at" value="<?= !empty($c['scheduled_at'])?h(date('Y-m-d\TH:i',strtotime((string)$c['scheduled_at']))):'' ?>"><div class="email-help">Tühjana alustab worker saatmist kohe pärast järjekorda panemist.</div></div>
        </div>
        <?php if(!$isNew):?>
          <hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0">
          <div class="email-field"><label>Testkiri</label></div>
          <div class="email-actions"><input form="emailTestForm" name="test_email" type="email" required placeholder="test@email.ee" style="flex:1;min-width:180px;border:1px solid #cfd8e3;border-radius:5px;padding:10px"><button form="emailTestForm" class="email-btn secondary" type="submit">Saada test</button></div>
          <div class="email-actions">
          <?php if(in_array($c['status'],['draft','completed','paused'],true)):?><button form="emailQueueForm" class="email-btn green" type="submit" onclick="return confirm('Panen kõik aktiivsed subscriberid saatmisjärjekorda?')"><?= $c['status']==='paused'?'Jätka saatmist':'Pane saatmisjärjekorda' ?></button><?php endif;?>
          <?php if(in_array($c['status'],['queued','sending'],true)):?><button form="emailBatchForm" class="email-btn green" type="submit">Saada järgmine partii</button><button form="emailPauseForm" class="email-btn secondary" type="submit">Paus</button><?php endif;?>
          </div>
          <div class="email-help" style="margin-top:12px">Saatmine käib partiidena (praegu <?= (int)($settings['batch_size']??25) ?> kirja korraga), et server ei läheks timeout'i. Automaatseks saatmiseks seadista cron Emaili seadete lehel.</div>
        <?php endif;?>
      </aside>
    </div>
  </form>
  <?php if(!$isNew):?>
  <form method="post" id="emailTestForm"><input type="hidden" name="send_test_email" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"></form>
  <form method="post" id="emailQueueForm"><input type="hidden" name="<?= $c['status']==='paused'?'resume_email_campaign':'queue_email_campaign' ?>" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"></form>
  <form method="post" id="emailBatchForm"><input type="hidden" name="send_email_batch" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"></form>
  <form method="post" id="emailPauseForm"><input type="hidden" name="pause_email_campaign" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"></form>
  <form method="post" onsubmit="return confirm('Kustutan kampaania ja kogu selle statistika?')" style="margin-top:16px"><input type="hidden" name="delete_email_campaign" value="1"><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"><button class="email-btn danger" type="submit">Kustuta kampaania</button></form>
  <?php endif;?>
</section>
<script>
(function(){
 const designer=document.getElementById('emailDesigner'), hidden=document.getElementById('emailHtmlBody'), form=document.getElementById('emailCampaignForm'), subject=document.getElementById('emailSubject');
 if(!designer||!hidden)return; function sync(){hidden.value=designer.innerHTML;} designer.addEventListener('input',sync);form.addEventListener('submit',sync);
 document.querySelectorAll('.email-designer-toolbar [data-cmd]').forEach(b=>b.addEventListener('click',()=>{document.execCommand(b.dataset.cmd,false,b.dataset.value||null);designer.focus();sync();}));
 document.getElementById('emailInsertLink')?.addEventListener('click',()=>{const u=prompt('Lingi URL:','https://');if(u){document.execCommand('createLink',false,u);sync();}});
 document.getElementById('emailInsertButton')?.addEventListener('click',()=>{const text=prompt('Nupu tekst:','Vaata lähemalt');const u=text?prompt('Nupu URL:','https://renoveerikodu.ee'):null;if(text&&u){document.execCommand('insertHTML',false,'<p><a href="'+u.replace(/"/g,'&quot;')+'" style="display:inline-block;background:#087b65;color:#fff;padding:12px 18px;border-radius:5px;text-decoration:none;font-weight:bold">'+text.replace(/</g,'&lt;')+'</a></p>');sync();}});
 const templates=<?= json_encode(array_map(fn($t)=>['id'=>(int)$t['id'],'subject'=>$t['subject'],'html_body'=>$t['html_body']],$emailTemplates),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
 document.getElementById('emailTemplateSelect')?.addEventListener('change',function(){if(!this.value)return;const t=templates.find(x=>String(x.id)===this.value);if(!t)return;if((designer.innerText.trim()||subject.value.trim())&&!confirm('Asendan praeguse sisu valitud malliga?'))return;designer.innerHTML=t.html_body||'';subject.value=t.subject||'';sync();});
})();
</script>

<?php elseif ($view === 'email-contacts'): ?>
<section class="email-wrap">
  <div class="email-head"><div><h2>Kontaktid</h2><p>Clean kontaktivaade, segmendid, puhastatud listid ja saatmisajalugu.</p></div><div class="email-actions"><a class="email-btn green" href="#contactImport">+ Impordi kontakte</a></div></div>
  <?php if(isset($_GET['import_error'])):?><div class="email-error"><?= h((string)$_GET['import_error']) ?></div><?php endif;?>
  <?php if(isset($_GET['clean_error'])):?><div class="email-error"><?= h((string)$_GET['clean_error']) ?></div><?php endif;?>
  <?php if(isset($_GET['imported'])):?><div class="contact-import-result"><span>Lisatud <?= (int)($_GET['added']??0) ?></span><span>Uuendatud <?= (int)($_GET['updated']??0) ?></span><span>Vahele jäetud <?= (int)($_GET['skipped']??0) ?></span><span>Vigaseid <?= (int)($_GET['invalid']??0) ?></span></div><?php endif;?>
  <?php if(isset($_GET['cleaned_imported'])):?><div class="email-flash">Puhastusfail imporditud: <?= (int)($_GET['accepted']??0) ?> sobivat kontakti, <?= (int)($_GET['rejected']??0) ?> välistatud, <?= (int)($_GET['clean_added']??0) ?> uut kontakti.</div><?php endif;?>
  <?php if(isset($_GET['segment_saved'])):?><div class="email-flash">Segment loodud.</div><?php endif;?>

  <?php $contactTab=$contactTab??'all'; $segmentId=$segmentId??0; ?>
  <div class="contact-clean-shell">
    <aside class="contact-side">
      <div class="contact-side-title">Listid</div>
      <a class="contact-nav-link <?= $contactTab==='all'?'active':'' ?>" href="index.php?view=email-contacts&amp;contact_tab=all"><span>Kõik kontaktid</span><small><?= $subscriberCount+$unsubscriberCount ?></small></a>
      <a class="contact-nav-link <?= $contactTab==='cleaned'?'active':'' ?>" href="index.php?view=email-contacts&amp;contact_tab=cleaned"><span>Puhastatud kontaktid</span><small><?= (int)$pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE cleaned_at IS NOT NULL')->fetchColumn() ?></small></a>
      <div class="contact-segment-list">
        <div class="contact-side-title">Segmendid</div>
        <?php if(!$emailSegments):?><div class="muted" style="padding:7px 8px;font-size:13px">Segmente pole veel.</div><?php endif;?>
        <?php foreach($emailSegments as $seg):?><a class="contact-nav-link <?= $contactTab==='segment' && $segmentId===(int)$seg['id']?'active':'' ?>" href="index.php?view=email-contacts&amp;contact_tab=segment&amp;segment_id=<?= (int)$seg['id'] ?>"><span><?= h($seg['name']) ?></span><small><?= (int)$seg['member_count'] ?></small></a><?php endforeach;?>
      </div>
    </aside>

    <div class="contact-main">
      <div class="contact-list-head">
        <div>
          <h3><?= $contactTab==='cleaned'?'Puhastatud kontaktid':($contactTab==='segment'&&$activeEmailSegment?h($activeEmailSegment['name']):'Kõik kontaktid') ?></h3>
          <div class="contact-list-meta">
            <?= count($emailContacts) ?> kontakti
            <?php if($contactTab==='cleaned'):?> · Viimati puhastatud: <strong><?= $cleanedLastAt?h(date('d.m.Y H:i',strtotime((string)$cleanedLastAt))):'—' ?></strong><?php endif;?>
            <?php if($contactTab==='segment'&&$activeEmailSegment):?> · Segment<?php endif;?>
          </div>
        </div>
        <form class="contact-search" method="get"><input type="hidden" name="view" value="email-contacts"><input type="hidden" name="contact_tab" value="<?= h($contactTab) ?>"><?php if($segmentId):?><input type="hidden" name="segment_id" value="<?= (int)$segmentId ?>"><?php endif;?><input name="q" value="<?= h((string)($_GET['q']??'')) ?>" placeholder="Otsi e-posti..."><button class="email-btn secondary" type="submit">Otsi</button></form>
      </div>

      <?php if($contactTab==='cleaned'):?>
      <div class="clean-upload-card">
        <strong>Impordi NeverBounce / ZeroBounce puhastusfail</strong>
        <p class="email-help">Laadi teenusest eksporditud CSV või XLSX. Vaikimisi lisatakse „Puhastatud kontaktid” listi ainult <em>valid / deliverable</em> aadressid.</p>
        <form method="post" enctype="multipart/form-data" class="contact-bulkbar" style="margin:0"><input type="hidden" name="import_cleaned_contacts" value="1"><select name="verification_source"><option value="neverbounce">NeverBounce</option><option value="zerobounce">ZeroBounce</option><option value="other">Muu teenus</option></select><input type="file" name="cleaned_file" accept=".csv,.xlsx" required><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="include_catchall" value="1"> Kaasa catch-all</label><button class="email-btn green" type="submit">Impordi puhastatud</button></form>
      </div>
      <?php endif;?>

      <form method="post" id="contactBulkForm">
        <div class="contact-bulkbar">
          <label style="display:flex;align-items:center;gap:7px"><input class="contact-select-all" type="checkbox" id="contactSelectAll"> Vali kõik nähtavad</label>
          <?php if($contactTab!=='segment'):?>
            <select name="segment_id"><option value="">Lisa valitud segmenti...</option><?php foreach($emailSegments as $seg):?><option value="<?= (int)$seg['id'] ?>"><?= h($seg['name']) ?></option><?php endforeach;?></select>
            <button class="email-btn secondary" type="submit" name="add_contacts_to_segment" value="1">Lisa segmenti</button>
            <input name="segment_name" placeholder="Uue segmendi nimi"><input type="hidden" name="segment_scope" value="<?= $contactTab==='cleaned'?'cleaned':'all' ?>"><button class="email-btn green" type="submit" name="create_email_segment" value="1">+ Loo segment valitutest</button>
          <?php else:?><input type="hidden" name="segment_id" value="<?= (int)$segmentId ?>"><button class="email-btn danger" type="submit" name="remove_contacts_from_segment" value="1">Eemalda valitud segmendist</button><?php endif;?>
        </div>

        <div class="contact-sheet">
          <?php if(!$emailContacts):?><div class="contact-empty">Selles listis ei ole kontakte.</div><?php endif;?>
          <?php foreach($emailContacts as $contact): $sent=(int)($contact['sent_count']??0)>0; ?>
          <label class="contact-sheet-row <?= $sent?'sent':'' ?>">
            <input class="contact-row-check" type="checkbox" name="contact_ids[]" value="<?= (int)$contact['id'] ?>">
            <span class="contact-sheet-email"><?= h($contact['email']) ?></span>
            <span class="contact-row-info">
              <?php if($contact['status']==='unsubscribed'):?><span class="contact-pill unsub">Loobunud</span><?php endif;?>
              <?php if(!empty($contact['cleaned_at'])):?><span class="contact-pill clean" title="<?= h(($contact['verification_source']??'').' · '.($contact['verification_status']??'')) ?>">Puhastatud</span><?php endif;?>
              <?php if($sent):?><span class="contact-pill sent" title="<?= h($contact['last_campaign']??'') ?>">Saadetud <?= (int)$contact['sent_count'] ?>×<?= !empty($contact['last_sent_at'])?' · '.h(date('d.m.Y',strtotime((string)$contact['last_sent_at']))):'' ?></span><?php else:?><span class="muted" style="font-size:11px">Pole saadetud</span><?php endif;?>
            </span>
          </label>
          <?php endforeach;?>
        </div>
      </form>

      <?php if($contactTab==='segment'&&$activeEmailSegment):?><form method="post" onsubmit="return confirm('Kustutan selle segmendi? Kontaktid ise jäävad alles.')" style="margin-top:12px"><input type="hidden" name="delete_email_segment" value="1"><input type="hidden" name="segment_id" value="<?= (int)$segmentId ?>"><button class="email-btn danger" type="submit">Kustuta segment</button></form><?php endif;?>

      <details class="contact-import-details" id="contactImport" <?= isset($_GET['import_error'])?'open':'' ?>><summary>+ Kontaktide import / copy + paste / CSV / XLSX</summary><div>
        <div class="contact-import-grid">
          <form method="post" class="contact-import-box"><input type="hidden" name="import_email_contacts" value="1"><input type="hidden" name="import_mode" value="paste"><h3>Copy + paste</h3><p>Kleebi üks email rea kohta või tabel otse Google Sheetsist / Excelist.</p><textarea name="contact_paste" placeholder="email@example.com&#10;teine@example.com"></textarea><label class="contact-import-check"><input type="checkbox" name="reactivate_unsubscribed" value="1"> <span>Aktiveeri loobunud kontaktid uuesti ainult uue nõusoleku korral.</span></label><div class="email-actions"><button class="email-btn green" type="submit">Impordi</button></div></form>
          <form method="post" enctype="multipart/form-data" class="contact-import-box"><input type="hidden" name="import_email_contacts" value="1"><input type="hidden" name="import_mode" value="file"><h3>CSV / XLSX</h3><p>Toetatud on .csv ja .xlsx. Email võib olla ainus veerg.</p><label class="contact-import-drop"><strong>Vali fail</strong><input type="file" name="contact_file" accept=".csv,.xlsx" required></label><label class="contact-import-check"><input type="checkbox" name="reactivate_unsubscribed" value="1"> <span>Aktiveeri loobunud kontaktid uuesti.</span></label><div class="email-actions"><button class="email-btn green" type="submit">Laadi ja impordi</button></div></form>
        </div>
      </div></details>
    </div>
  </div>
</section>
<script>
(function(){const all=document.getElementById('contactSelectAll');const form=document.getElementById('contactBulkForm');if(!all||!form)return;const checks=()=>Array.from(form.querySelectorAll('.contact-row-check'));all.addEventListener('change',()=>checks().forEach(c=>c.checked=all.checked));checks().forEach(c=>c.addEventListener('change',()=>{const list=checks();all.checked=list.length>0&&list.every(x=>x.checked);all.indeterminate=list.some(x=>x.checked)&&!all.checked;}));})();
</script>

<?php elseif ($view === 'email-templates'): ?>
<section class="email-wrap">
 <div class="email-head"><div><h2>Emaili mallid</h2><p>Salvesta korduvkasutatavad kampaania kujundused.</p></div></div>
 <?php if(isset($_GET['saved'])):?><div class="email-flash">Mall salvestatud.</div><?php endif;?>
 <div class="email-template-card"><form method="post"><input type="hidden" name="save_email_template" value="1"><div class="email-form-grid"><div class="email-field"><label>Uue malli nimi</label><input name="name" required></div><div class="email-field"><label>Vaikimisi teema</label><input name="subject"></div><div class="email-field full"><label>HTML sisu</label><textarea name="html_body" style="min-height:180px" placeholder="<div>...</div>"></textarea></div></div><div class="email-actions"><button class="email-btn green">+ Lisa mall</button></div></form></div>
 <?php foreach($emailTemplates as $t):?><div class="email-template-card"><form method="post"><input type="hidden" name="save_email_template" value="1"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>"><div class="email-form-grid"><div class="email-field"><label>Nimi</label><input name="name" value="<?= h($t['name']) ?>"></div><div class="email-field"><label>Teema</label><input name="subject" value="<?= h($t['subject']??'') ?>"></div><div class="email-field full"><label>HTML</label><textarea name="html_body" style="min-height:160px"><?= h($t['html_body']??'') ?></textarea></div></div><div class="email-actions"><button class="email-btn green">Salvesta</button><a class="email-btn secondary" href="index.php?view=email-campaign&amp;template_id=<?= (int)$t['id'] ?>">Kasuta kampaanias</a></div></form><form method="post" onsubmit="return confirm('Kustutan malli?')" style="margin-top:8px"><input type="hidden" name="delete_email_template" value="1"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>"><button class="email-btn danger">Kustuta</button></form></div><?php endforeach;?>
</section>

<?php elseif ($view === 'email-settings'): ?>
<section class="email-wrap">
 <div class="email-head"><div><h2>Emaili seaded / SMTP</h2><p>Seadista teenus, mille kaudu kampaaniad päriselt välja saadetakse.</p></div></div>
 <?php if(isset($_GET['saved'])):?><div class="email-flash">Seaded salvestatud.</div><?php endif;?>
 <div class="email-setting-note"><strong>Deliverability:</strong> soovitatav on kasutada Brevo, Amazon SES, Mailgun või muud SMTP teenust ning seadistada domeenile SPF, DKIM ja DMARC. Ära kasuta ostetud kontaktinimekirju.</div>
 <form method="post"><input type="hidden" name="save_email_settings" value="1"><div class="email-form-grid">
  <div class="email-field"><label>Transport</label><select name="transport"><option value="smtp" <?= ($emailSettings['transport']??'smtp')==='smtp'?'selected':'' ?>>SMTP</option><option value="mail" <?= ($emailSettings['transport']??'')==='mail'?'selected':'' ?>>PHP mail()</option></select></div>
  <div class="email-field"><label>SMTP host</label><input name="smtp_host" value="<?= h($emailSettings['smtp_host']??'') ?>" placeholder="smtp-relay.brevo.com"></div>
  <div class="email-field"><label>SMTP port</label><input type="number" name="smtp_port" value="<?= (int)($emailSettings['smtp_port']??587) ?>"></div>
  <div class="email-field"><label>Krüpteering</label><select name="smtp_encryption"><option value="tls" <?= ($emailSettings['smtp_encryption']??'tls')==='tls'?'selected':'' ?>>STARTTLS</option><option value="ssl" <?= ($emailSettings['smtp_encryption']??'')==='ssl'?'selected':'' ?>>SSL/TLS</option><option value="none" <?= ($emailSettings['smtp_encryption']??'')==='none'?'selected':'' ?>>Puudub</option></select></div>
  <div class="email-field"><label>SMTP kasutajanimi</label><input name="smtp_username" value="<?= h($emailSettings['smtp_username']??'') ?>"></div>
  <div class="email-field"><label>SMTP parool / API SMTP key</label><input type="password" name="smtp_password" value="" placeholder="Jäta tühjaks, et vana parool säiliks"></div>
  <div class="email-field"><label>Saatja nimi</label><input name="sender_name" value="<?= h($emailSettings['sender_name']??'') ?>"></div>
  <div class="email-field"><label>Saatja e-post</label><input type="email" name="sender_email" value="<?= h($emailSettings['sender_email']??'') ?>"></div>
  <div class="email-field"><label>Reply-to</label><input type="email" name="reply_to" value="<?= h($emailSettings['reply_to']??'') ?>"></div>
  <div class="email-field"><label>Partii suurus</label><input type="number" min="1" max="200" name="batch_size" value="<?= (int)($emailSettings['batch_size']??25) ?>"></div>
  <div class="email-field full"><label><input type="checkbox" name="tracking_enabled" value="1" <?= !empty($emailSettings['tracking_enabled'])?'checked':'' ?>> Ava- ja klikijälgimine</label></div>
 </div><div class="email-actions"><button class="email-btn green">Salvesta seaded</button></div></form>
 <hr style="border:0;border-top:1px solid #e5e7eb;margin:22px 0"><h3>Automaatne saatmine (cron)</h3><p class="email-help">Sea cPanel Cron Jobs'is URL-i kutsumine näiteks iga 5 minuti järel. Worker saadab iga käivitusega ühe partii. Ära jaga seda URL-i avalikult.</p><div class="email-code"><?= h(email_base_url().'?email_public=cron&token='.(string)($emailSettings['cron_token']??'')) ?></div>
</section>

<?php elseif ($view === 'subscribers'): ?>
<div class="subscriber-tools"><form method="post"><input type="hidden" name="bulk_add_subscribers" value="1"><strong>Lisa kontaktid korraga</strong><p class="muted">Kleebi e-posti aadressid komade, semikoolonite või reavahetustega. Olemasolev unsubscribe kontakt aktiveeritakse uuesti ainult siis, kui sul on selleks inimese nõusolek.</p><textarea name="emails" placeholder="nimi@example.com&#10;teine@example.com"></textarea><div class="email-actions"><button class="email-btn green" type="submit">Lisa subscriberid</button></div></form><?php if(isset($_GET['imported'])):?><div class="email-flash" style="margin-top:10px;margin-bottom:0">Töödeldud <?= (int)$_GET['imported'] ?> aadressi.</div><?php endif;?></div>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Email</th>
      <th>Staatus</th>
      <th>Liitus</th>
      <th>Allikas</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$subscribers): ?>
    <tr><td colspan="5" class="muted">Subscribereid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($subscribers as $row): ?>
    <tr>
      <td data-label="ID"><?= (int) $row['id'] ?></td>
      <td data-label="Email"><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td data-label="Staatus"><?= h($row['status']) ?></td>
      <td data-label="Liitus"><?= h($row['subscribed_at'] ?? $row['created_at'] ?? '') ?></td>
      <td data-label="Allikas"><?= h($row['source'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Email</th>
      <th>Staatus</th>
      <th>Liitus</th>
      <th>Loobus</th>
      <th>Allikas</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$unsubscribers): ?>
    <tr><td colspan="6" class="muted">Unsubscribereid ei ole veel.</td></tr>
  <?php endif; ?>
  <?php foreach ($unsubscribers as $row): ?>
    <tr>
      <td data-label="ID"><?= (int) $row['id'] ?></td>
      <td data-label="Email"><a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a></td>
      <td data-label="Staatus"><?= h($row['status']) ?></td>
      <td data-label="Liitus"><?= h($row['subscribed_at'] ?? '') ?></td>
      <td data-label="Loobus"><?= h($row['unsubscribed_at'] ?? $row['created_at'] ?? '') ?></td>
      <td data-label="Allikas"><?= h($row['source'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</main>
<div class="admin-lightbox" id="adminLightbox" aria-hidden="true">
  <div class="lightbox-panel" role="dialog" aria-modal="true" aria-label="Pildi eelvaade">
    <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Sulge">×</button>
    <button type="button" class="lightbox-prev" id="lightboxPrev" aria-label="Eelmine pilt">‹</button>
    <img src="" alt="" class="lightbox-image" id="lightboxImage">
    <button type="button" class="lightbox-next" id="lightboxNext" aria-label="Järgmine pilt">›</button>
    <div class="lightbox-caption" id="lightboxCaption"></div>
  </div>
</div>
<script>
(function () {
  const lightbox = document.getElementById("adminLightbox");
  const image = document.getElementById("lightboxImage");
  const caption = document.getElementById("lightboxCaption");
  const closeButton = document.getElementById("lightboxClose");
  const prevButton = document.getElementById("lightboxPrev");
  const nextButton = document.getElementById("lightboxNext");
  let gallery = [];
  let currentIndex = 0;

  function renderImage() {
    const item = gallery[currentIndex];
    if (!item) {
      return;
    }

    image.src = item.href;
    image.alt = item.label;
    caption.textContent = item.label + " (" + (currentIndex + 1) + "/" + gallery.length + ")";
    prevButton.disabled = currentIndex === 0;
    nextButton.disabled = currentIndex === gallery.length - 1;
  }

  function openLightbox(link) {
    const requestId = link.dataset.requestId;
    const links = Array.from(document.querySelectorAll('.lightbox-item[data-request-id="' + requestId + '"]'));
    gallery = links.map((item) => ({
      href: item.getAttribute("href"),
      label: item.dataset.label || item.textContent.trim() || "Pilt"
    }));
    currentIndex = links.indexOf(link);
    lightbox.classList.add("open");
    lightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    renderImage();
    closeButton.focus();
  }

  function closeLightbox() {
    lightbox.classList.remove("open");
    lightbox.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    image.src = "";
    gallery = [];
    currentIndex = 0;
  }

  function showPrevious() {
    if (currentIndex > 0) {
      currentIndex -= 1;
      renderImage();
    }
  }

  function showNext() {
    if (currentIndex < gallery.length - 1) {
      currentIndex += 1;
      renderImage();
    }
  }

  document.querySelectorAll(".lightbox-item").forEach((link) => {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      openLightbox(link);
    });
  });

  closeButton.addEventListener("click", closeLightbox);
  prevButton.addEventListener("click", showPrevious);
  nextButton.addEventListener("click", showNext);
  lightbox.addEventListener("click", function (event) {
    if (event.target === lightbox) {
      closeLightbox();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (!lightbox.classList.contains("open")) {
      return;
    }

    if (event.key === "Escape") {
      closeLightbox();
    } else if (event.key === "ArrowLeft") {
      showPrevious();
    } else if (event.key === "ArrowRight") {
      showNext();
    }
  });
})();
</script>
<?php if ($view === 'statistics' && $analyticsError === '' && !empty($chartRows)): ?>
<script>
(function () {
  const canvas = document.getElementById("analyticsTrend");
  if (!canvas) return;

  const rows = <?= json_encode($chartRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const series = [
    {key: "users", color: "#2563eb"},
    {key: "sessions", color: "#10b981"},
    {key: "views", color: "#f59e0b"}
  ];

  function draw() {
    const rect = canvas.getBoundingClientRect();
    const ratio = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = Math.round(rect.width * ratio);
    canvas.height = Math.round(rect.height * ratio);
    const context = canvas.getContext("2d");
    context.setTransform(ratio, 0, 0, ratio, 0, 0);

    const width = rect.width;
    const height = rect.height;
    const padding = {top: 12, right: 16, bottom: 34, left: 46};
    const chartWidth = Math.max(1, width - padding.left - padding.right);
    const chartHeight = Math.max(1, height - padding.top - padding.bottom);
    const maximum = Math.max(1, ...rows.flatMap((row) => series.map((item) => Number(row[item.key]) || 0)));
    const roundedMaximum = Math.max(5, Math.ceil(maximum / 5) * 5);

    context.clearRect(0, 0, width, height);
    context.font = "12px Arial, sans-serif";
    context.textBaseline = "middle";

    for (let step = 0; step <= 4; step += 1) {
      const value = roundedMaximum * (4 - step) / 4;
      const y = padding.top + chartHeight * step / 4;
      context.strokeStyle = "#e5eaf0";
      context.lineWidth = 1;
      context.beginPath();
      context.moveTo(padding.left, y);
      context.lineTo(width - padding.right, y);
      context.stroke();
      context.fillStyle = "#667085";
      context.textAlign = "right";
      context.fillText(String(Math.round(value)), padding.left - 8, y);
    }

    const labelEvery = Math.max(1, Math.ceil(rows.length / Math.max(4, Math.floor(chartWidth / 70))));
    rows.forEach((row, index) => {
      if (index % labelEvery !== 0 && index !== rows.length - 1) return;
      const x = rows.length === 1 ? padding.left + chartWidth / 2 : padding.left + chartWidth * index / (rows.length - 1);
      context.fillStyle = "#667085";
      context.textAlign = "center";
      context.fillText(row.date, x, height - 13);
    });

    series.forEach((item) => {
      context.strokeStyle = item.color;
      context.lineWidth = 2.5;
      context.lineJoin = "round";
      context.lineCap = "round";
      context.beginPath();
      rows.forEach((row, index) => {
        const x = rows.length === 1 ? padding.left + chartWidth / 2 : padding.left + chartWidth * index / (rows.length - 1);
        const y = padding.top + chartHeight - (Number(row[item.key]) || 0) / roundedMaximum * chartHeight;
        if (index === 0) context.moveTo(x, y); else context.lineTo(x, y);
      });
      context.stroke();
    });
  }

  let resizeTimer = null;
  window.addEventListener("resize", function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(draw, 100);
  });
  draw();
})();
</script>
<?php endif; ?>

<?php if ($view === 'calculations'): ?>
<script>
(function () {
  const distance = document.getElementById("fuelDistance");
  const consumption = document.getElementById("fuelConsumption");
  const price = document.getElementById("fuelPrice");
  const amount = document.getElementById("fuelAmount");
  const cost = document.getElementById("fuelCost");
  const reset = document.getElementById("fuelReset");
  const cheapestPriceButton = document.getElementById("useCheapestFuelPrice");

  function numberValue(input) {
    const value = parseFloat(String(input.value).replace(",", "."));
    return Number.isFinite(value) && value >= 0 ? value : 0;
  }

  function formatNumber(value) {
    return value.toLocaleString("et-EE", {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function calculate() {
    const liters = numberValue(distance) * numberValue(consumption) / 100;
    const total = liters * numberValue(price);
    amount.textContent = formatNumber(liters) + " l";
    cost.textContent = formatNumber(total) + " €";
  }

  [distance, consumption, price].forEach((input) => input.addEventListener("input", calculate));
  reset.addEventListener("click", function () {
    distance.value = "";
    consumption.value = "";
    price.value = "";
    calculate();
    distance.focus();
  });

  if (cheapestPriceButton) {
    cheapestPriceButton.addEventListener("click", function () {
      const cheapestPrice = parseFloat(cheapestPriceButton.dataset.price || "");
      if (!Number.isFinite(cheapestPrice)) return;
      price.value = cheapestPrice.toFixed(3);
      calculate();
      price.focus();
    });
  }

  calculate();
})();
</script>
<?php endif; ?>

<?php if ($view === 'objects'): ?>
<script>
(function () {
  const toggle = document.getElementById("objectAddToggle");
  const panel = document.getElementById("objectAddPanel");
  if (!toggle || !panel) return;

  toggle.addEventListener("click", function () {
    const isOpen = panel.classList.toggle("open");
    toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    toggle.textContent = isOpen ? "− Sulge" : "+ Lisa objekt";
    if (isOpen) {
      const firstInput = panel.querySelector("input");
      if (firstInput) firstInput.focus();
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($view === 'workers'): ?>
<script>
(function(){
  const toggle=document.getElementById('workerAddToggle');
  const panel=document.getElementById('workerAddPanel');
  const close=document.getElementById('workerAddClose');
  if(!toggle||!panel)return;
  const setOpen=(open)=>{panel.classList.toggle('open',open);toggle.textContent=open?'− Sulge vorm':'+ Lisa tööline';if(open){const input=panel.querySelector('input[name="name"]');if(input)input.focus();}};
  toggle.addEventListener('click',()=>setOpen(!panel.classList.contains('open')));
  if(close)close.addEventListener('click',()=>setOpen(false));
})();
</script>
<?php endif; ?>

<?php if ($view === 'workdays'): ?>
<script>
(function () {
  const panel = document.getElementById('workdayAddPanel');
  const toggle = document.getElementById('workdayAddToggle');
  const close = document.getElementById('workdayClose');
  const cancel = document.getElementById('workdayCancel');

  function setPanel(open) {
    if (!panel || !toggle) return;
    panel.classList.toggle('open', open);
    toggle.textContent = open ? '− Sulge vorm' : '+ Lisa tööpäev';
    if (open) {
      const first = panel.querySelector('input:not([type="hidden"])');
      if (first) first.focus();
    }
  }
  if (toggle) toggle.addEventListener('click', () => setPanel(!panel.classList.contains('open')));
  if (close) close.addEventListener('click', () => setPanel(false));
  if (cancel) cancel.addEventListener('click', () => setPanel(false));

  function parseTime(value) {
    const parts = String(value || '').split(':');
    if (parts.length < 2) return null;
    const h = Number(parts[0]), m = Number(parts[1]);
    return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
  }

  document.querySelectorAll('.js-workday-form').forEach((form) => {
    const objectSelect = form.querySelector('.js-object-select');
    const objectName = form.querySelector('.js-object-name');
    const objectAddress = form.querySelector('.js-object-address');
    const start = form.querySelector('.js-start-time');
    const end = form.querySelector('.js-end-time');
    const pause = form.querySelector('.js-break-minutes');
    const rate = form.querySelector('.js-hourly-rate');
    const paymentType = form.querySelector('.js-payment-type');
    const pieceQuantity = form.querySelector('.js-piece-quantity');
    const pieceRate = form.querySelector('.js-piece-rate');
    const pieceUnit = form.querySelector('.js-piece-unit');
    const piecePricingMode = form.querySelector('.js-piece-pricing-mode');
    const pieceFixedPrice = form.querySelector('.js-piece-fixed-price');
    const hoursPreview = form.querySelector('.js-hours-preview');
    const paymentPreview = form.querySelector('.js-payment-preview');
    const costPreview = form.querySelector('.js-cost-preview');

    function updateObject() {
      if (!objectSelect || objectSelect.value === '0') return;
      const option = objectSelect.options[objectSelect.selectedIndex];
      if (objectName && option.dataset.name) objectName.value = option.dataset.name;
      if (objectAddress && option.dataset.address) objectAddress.value = option.dataset.address;
    }

    function calculate() {
      const startMin = parseTime(start && start.value);
      let endMin = parseTime(end && end.value);
      const breakMin = Math.max(0, Number(pause && pause.value) || 0);
      const hourlyRate = Math.max(0, Number(String(rate && rate.value || '0').replace(',', '.')) || 0);
      const type = paymentType && paymentType.value === 'piece' ? 'piece' : 'hourly';
      const quantity = Math.max(0, Number(String(pieceQuantity && pieceQuantity.value || '0').replace(',', '.')) || 0);
      const unitRate = Math.max(0, Number(String(pieceRate && pieceRate.value || '0').replace(',', '.')) || 0);
      const pricingMode = piecePricingMode && piecePricingMode.value === 'fixed' ? 'fixed' : 'unit';
      const fixedPrice = Math.max(0, Number(String(pieceFixedPrice && pieceFixedPrice.value || '0').replace(',', '.')) || 0);
      let hours = 0;
      if (startMin !== null && endMin !== null) {
        if (endMin < startMin) endMin += 24 * 60;
        hours = Math.max(0, (endMin - startMin - breakMin) / 60);
      }
      if (hoursPreview) hoursPreview.textContent = hours.toLocaleString('et-EE', {minimumFractionDigits:1, maximumFractionDigits:2}) + ' h';
      const cost = type === 'piece'
        ? (pricingMode === 'fixed' ? fixedPrice : quantity * unitRate)
        : hours * hourlyRate;
      form.querySelectorAll('.js-hourly-fields').forEach((el) => { el.style.display = type === 'hourly' ? '' : 'none'; });
      form.querySelectorAll('.js-piece-fields').forEach((el) => { el.style.display = type === 'piece' ? '' : 'none'; });
      form.querySelectorAll('.js-piece-unit-fields').forEach((el) => { el.style.display = type === 'piece' && pricingMode === 'unit' ? '' : 'none'; });
      form.querySelectorAll('.js-piece-fixed-fields').forEach((el) => { el.style.display = type === 'piece' && pricingMode === 'fixed' ? '' : 'none'; });
      if (paymentPreview) {
        paymentPreview.textContent = type === 'piece'
          ? (pricingMode === 'fixed'
              ? ('komplekthind ' + fixedPrice.toLocaleString('et-EE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €')
              : ((quantity || 0).toLocaleString('et-EE', {maximumFractionDigits:2}) + ' ' + (pieceUnit ? pieceUnit.value : 'm²') + ' × ' + unitRate.toLocaleString('et-EE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €'))
          : 'tunnitöö';
      }
      if (costPreview) costPreview.textContent = cost.toLocaleString('et-EE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
    }

    if (objectSelect) objectSelect.addEventListener('change', updateObject);
    [start, end, pause, rate, paymentType, pieceQuantity, pieceRate, pieceUnit, piecePricingMode, pieceFixedPrice].forEach((el) => { if (el) { el.addEventListener('input', calculate); el.addEventListener('change', calculate); } });
    calculate();
  });

  document.querySelectorAll('.js-workday-quick-confirm').forEach((form) => {
    form.addEventListener('click', (event) => event.stopPropagation());
    form.addEventListener('submit', (event) => event.stopPropagation());
  });
})();
</script>
<?php endif; ?>

<?php if ($view === 'quote'): ?>
<script>
(function(){
  const euro=(n)=>(Number(n)||0).toLocaleString('et-EE',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';
  const num=(v)=>Math.max(0,Number(String(v||'0').replace(',','.'))||0);
  function renumber(table){table.querySelectorAll('tbody tr').forEach((tr,i)=>{const n=tr.querySelector('.js-row-number');if(n)n.textContent=i+1;});}
  function calcTable(table){let total=0;table.querySelectorAll('tbody tr').forEach(tr=>{const q=num(tr.querySelector('.js-qty')?.value),r=num(tr.querySelector('.js-rate')?.value),sum=q*r;total+=sum;const cell=tr.querySelector('.js-line-total');if(cell)cell.textContent=euro(sum);});return total;}
  function calculate(){const wt=document.getElementById('quoteWorkTable'),mt=document.getElementById('quoteMaterialTable');const w=wt?calcTable(wt):0,m=mt?calcTable(mt):0,g=w+m;const map={quoteWorkTotal:w,quoteMaterialTotal:m,quoteGrandTotal:g,quoteBottomTotal:g};Object.entries(map).forEach(([id,val])=>{const el=document.getElementById(id);if(el)el.textContent=euro(val);});}
  function bindTable(table){if(!table)return;table.addEventListener('input',calculate);table.addEventListener('click',e=>{const b=e.target.closest('.quote-remove-row');if(!b)return;b.closest('tr')?.remove();renumber(table);calculate();});}
  const wt=document.getElementById('quoteWorkTable'),mt=document.getElementById('quoteMaterialTable');bindTable(wt);bindTable(mt);
  document.querySelectorAll('.quote-add-row').forEach(btn=>btn.addEventListener('click',()=>{const table=document.getElementById(btn.dataset.target);if(!table)return;const isWork=table.id==='quoteWorkTable';const tr=document.createElement('tr');tr.innerHTML='<td class="sum-cell js-row-number"></td><td><input name="'+(isWork?'work_desc[]':'material_desc[]')+'" value=""></td><td><input class="num js-qty" name="'+(isWork?'work_qty[]':'material_qty[]')+'" value="1"></td><td><input name="'+(isWork?'work_unit[]':'material_unit[]')+'" value="'+(isWork?'m²':'tk')+'"></td><td><input class="num js-rate" name="'+(isWork?'work_rate[]':'material_rate[]')+'" value="0"></td><td class="sum-cell js-line-total">0,00 €</td><td><button class="quote-remove-row" type="button">×</button></td>';table.querySelector('tbody').appendChild(tr);renumber(table);tr.querySelector('input')?.focus();calculate();}));
  document.getElementById('quotePrint')?.addEventListener('click',()=>window.print());
  calculate();
})();
</script>
<?php endif; ?>

<?php if ($view === 'price-list'): ?>
<script>
(function(){
  const b=document.getElementById('priceAddToggle'),p=document.getElementById('priceAddPanel');
  if(b&&p){b.addEventListener('click',()=>{p.classList.toggle('open');b.textContent=p.classList.contains('open')?'− Sulge vorm':'+ Lisa hinnakirja rida';});}

  const input=document.getElementById('priceQuickSearch');
  const results=document.getElementById('priceQuickResults');
  if(!input||!results)return;

  const items=<?= json_encode(array_values(array_map(static function($r){return [
    'id'=>(int)$r['id'],
    'name'=>(string)$r['name'],
    'category'=>(string)$r['category'],
    'unit'=>(string)($r['unit']??''),
    'price'=>(float)$r['price_from'],
    'material'=>$r['material_price_from']!==null?(float)$r['material_price_from']:null,
    'description'=>(string)($r['description']??''),
    'type'=>(string)($r['item_type']??'service')
  ];}, $priceListItems??[])), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  const normalize=(v)=>String(v||'').toLocaleLowerCase('et-EE').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').trim();
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const money=(v)=>new Intl.NumberFormat('et-EE',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(v)||0)+' €';

  function levenshtein(a,b){
    if(a===b)return 0;if(!a.length)return b.length;if(!b.length)return a.length;
    const prev=Array.from({length:b.length+1},(_,i)=>i),cur=new Array(b.length+1);
    for(let i=1;i<=a.length;i++){
      cur[0]=i;
      for(let j=1;j<=b.length;j++)cur[j]=Math.min(cur[j-1]+1,prev[j]+1,prev[j-1]+(a[i-1]===b[j-1]?0:1));
      for(let j=0;j<=b.length;j++)prev[j]=cur[j];
    }
    return prev[b.length];
  }

  function score(item,q){
    const n=normalize(item.name),c=normalize(item.category),d=normalize(item.description), hay=n+' '+c+' '+d;
    if(!q)return -1;
    if(n===q)return 1000;
    if(n.startsWith(q))return 850-q.length/100;
    if(n.includes(q))return 700-q.length/100;
    const words=q.split(' ').filter(Boolean);
    let s=0;
    for(const w of words){
      if(n.split(' ').some(x=>x.startsWith(w)))s+=180;
      else if(hay.includes(w))s+=100;
      else {
        let best=99;
        for(const x of n.split(' ')) if(Math.abs(x.length-w.length)<=3) best=Math.min(best,levenshtein(x,w));
        if(best<=1)s+=70; else if(best===2&&w.length>=5)s+=35; else s-=40;
      }
    }
    return s;
  }

  function render(){
    const q=normalize(input.value);
    if(!q){results.classList.remove('open');results.innerHTML='';input.setAttribute('aria-expanded','false');return;}
    const ranked=items.map(item=>({item,s:score(item,q)})).filter(x=>x.s>0).sort((a,b)=>b.s-a.s||a.item.name.localeCompare(b.item.name,'et')).slice(0,8);
    results.classList.add('open');input.setAttribute('aria-expanded','true');
    if(!ranked.length){results.innerHTML='<div class="price-search-empty">Sobivat hinnakirja rida ei leidnud. Proovi lühemat märksõna.</div>';return;}
    results.innerHTML=ranked.map(({item})=>{
      const unit=item.unit||'tk';
      const material=item.material!==null?'<span>Materjaliga al '+money(item.material)+' / '+esc(unit)+'</span>':'';
      return '<div class="price-search-result" role="option" tabindex="0" data-price-id="'+item.id+'"><div class="price-search-main"><span class="price-search-name">'+esc(item.name)+'</span><span class="price-search-meta"><span>'+esc(item.category)+'</span><span>•</span><span>ühik: '+esc(unit)+'</span></span></div><div class="price-search-price"><strong>al '+money(item.price)+' / '+esc(unit)+'</strong>'+material+'</div></div>';
    }).join('');
  }

  function openItem(id){
    const hidden=document.querySelector('.price-edit-card input[name="price_id"][value="'+CSS.escape(String(id))+'"]');
    if(!hidden)return;
    const details=hidden.closest('.price-edit-card');
    if(details){details.open=true;details.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>details.classList.add('price-search-focus'),100);setTimeout(()=>details.classList.remove('price-search-focus'),1600);}
  }

  input.addEventListener('input',render);
  input.addEventListener('keydown',e=>{if(e.key==='Escape'){input.value='';render();}});
  results.addEventListener('click',e=>{const row=e.target.closest('[data-price-id]');if(row)openItem(row.dataset.priceId);});
  results.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target.matches('[data-price-id]')){e.preventDefault();openItem(e.target.dataset.priceId);}});
  document.addEventListener('click',e=>{if(!e.target.closest('.price-search-box')){results.classList.remove('open');input.setAttribute('aria-expanded','false');}});
})();
</script>
<style>.price-edit-card.price-search-focus{box-shadow:0 0 0 3px rgba(8,116,91,.18);border-color:#08745b}</style>
<?php endif; ?>
</body>
</html>
