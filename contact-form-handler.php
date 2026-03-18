<?php

declare(strict_types=1);

require_once __DIR__ . '/contact-storage.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond(int $statusCode, bool $success, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_text(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r", "\n"], ' ', $value);

    return preg_replace('/\s+/', ' ', $value) ?? '';
}

function clean_multiline(string $value): string
{
    $value = trim($value);
    $value = str_replace("\r\n", "\n", $value);
    $value = str_replace("\r", "\n", $value);

    return preg_replace("/\n{3,}/", "\n\n", $value) ?? '';
}

function load_smtp_config(): array
{
    $configPath = __DIR__ . '/smtp-config.php';

    if (!is_file($configPath)) {
        throw new RuntimeException('SMTP config file is missing.');
    }

    $config = require $configPath;

    if (!is_array($config)) {
        throw new RuntimeException('SMTP config is invalid.');
    }

    $requiredKeys = ['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name', 'to_email'];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config) || $config[$key] === '') {
            throw new RuntimeException('Missing SMTP config value: ' . $key);
        }
    }

    $config['timeout'] = isset($config['timeout']) ? (int) $config['timeout'] : 15;

    return $config;
}

function smtp_read($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3} /', $line) === 1) {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server did not respond.');
    }

    return $response;
}

function smtp_expect($socket, array $expectedCodes): string
{
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");

    return smtp_expect($socket, $expectedCodes);
}

function send_via_smtp(array $config, string $replyToEmail, string $replyToName, string $subject, string $body): void
{
    $transport = $config['encryption'] === 'ssl' ? 'ssl://' : '';
    $host = $transport . $config['host'];
    $socket = @stream_socket_client(
        $host . ':' . (int) $config['port'],
        $errorNumber,
        $errorMessage,
        (float) $config['timeout']
    );

    if ($socket === false) {
        throw new RuntimeException('SMTP connection failed: ' . $errorMessage . ' (' . $errorNumber . ')');
    }

    stream_set_timeout($socket, (int) $config['timeout']);

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO eurotrade.sk', [250]);

        if ($config['encryption'] === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }

            smtp_command($socket, 'EHLO eurotrade.sk', [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode((string) $config['username']), [334]);
        smtp_command($socket, base64_encode((string) $config['password']), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $config['to_email'] . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
            'Reply-To: ' . $replyToName . ' <' . $replyToEmail . '>',
            'To: ' . $config['to_email'],
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $safeBody = preg_replace('/^\./m', '..', $body) ?? $body;
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $safeBody) . "\r\n.";

        fwrite($socket, $payload . "\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

$name = clean_text((string) ($_POST['name'] ?? ''));
$phone = clean_text((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = clean_text((string) ($_POST['subject'] ?? ''));
$message = clean_multiline((string) ($_POST['message'] ?? ''));
$terms = (string) ($_POST['terms'] ?? '');
$website = trim((string) ($_POST['website'] ?? ''));
$lang = strtolower(clean_text((string) ($_POST['lang'] ?? 'sk')));

if ($website !== '') {
    respond(200, true, 'OK');
}

if ($name === '' || mb_strlen($name) < 2) {
    respond(422, false, $lang === 'en' ? 'Please enter your full name.' : 'Prosím, zadajte vaše meno a priezvisko.');
}

if ($phone === '' || mb_strlen($phone) < 7) {
    respond(422, false, $lang === 'en' ? 'Please enter your phone number.' : 'Prosím, zadajte vaše telefónne číslo.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, $lang === 'en' ? 'Please enter a valid email address.' : 'Prosím, zadajte platnú e-mailovú adresu.');
}

if ($message === '' || mb_strlen($message) < 10) {
    respond(422, false, $lang === 'en' ? 'Please write a longer message.' : 'Prosím, napíšte podrobnejšiu správu.');
}

if (!in_array($terms, ['on', '1', 'true'], true)) {
    respond(422, false, $lang === 'en' ? 'You must agree to the processing of personal data.' : 'Musíte súhlasiť so spracovaním osobných údajov.');
}

$subjectMapSk = [
    'dopyt' => 'Obchodný dopyt',
    'spolupraca' => 'Spolupráca',
    'produkty' => 'Otázka k produktom',
    'faktury' => 'Faktúry a ekonomické oddelenie',
    'ine' => 'Iné',
];

$subjectMapEn = [
    'dopyt' => 'Business enquiry',
    'spolupraca' => 'Partnership',
    'produkty' => 'Product question',
    'faktury' => 'Invoices and finance',
    'ine' => 'Other',
];

$subjectLabel = $lang === 'en'
    ? ($subjectMapEn[$subject] ?? 'General enquiry')
    : ($subjectMapSk[$subject] ?? 'Všeobecný dopyt');

$mailSubject = ($lang === 'en' ? 'Contact form: ' : 'Kontaktný formulár: ') . $subjectLabel;

$bodyLines = [
    'EUROTRADEMETAL contact form submission',
    '',
    'Name: ' . $name,
    'Phone: ' . $phone,
    'Email: ' . $email,
    'Subject: ' . $subjectLabel,
    'Language: ' . strtoupper($lang),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    '',
    'Message:',
    $message,
];

$body = implode("\n", $bodyLines);

try {
    append_contact_submission([
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'subject_key' => $subject,
        'subject_label' => $subjectLabel,
        'message' => $message,
        'lang' => $lang,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);
} catch (Throwable $exception) {
    error_log('Contact form storage error: ' . $exception->getMessage());
    respond(500, false, $lang === 'en' ? 'The message could not be saved right now. Please try again later.' : 'Správu sa teraz nepodarilo uložiť. Skúste to prosím znova neskôr.');
}

try {
    $smtpConfig = load_smtp_config();
    send_via_smtp($smtpConfig, $email, $name, $mailSubject, $body);
} catch (Throwable $exception) {
    error_log('Contact form SMTP error: ' . $exception->getMessage());
    respond(200, true, $lang === 'en' ? 'Your message has been saved, but the email notification could not be sent right now.' : 'Správa bola uložená, ale e-mailové upozornenie sa teraz nepodarilo odoslať.');
}

respond(200, true, $lang === 'en' ? 'Thank you, your message has been sent.' : 'Ďakujeme, vaša správa bola odoslaná.');
