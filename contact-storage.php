<?php

declare(strict_types=1);

function contact_submissions_file_path(): string
{
    return __DIR__ . '/data/contact-submissions.json';
}

function decode_contact_submissions(string $json): array
{
    if ($json === '') {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}

function read_contact_submissions(): array
{
    $path = contact_submissions_file_path();

    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException('Unable to read contact submissions storage.');
    }

    return decode_contact_submissions($json);
}

function append_contact_submission(array $submission): array
{
    $path = contact_submissions_file_path();
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create submissions storage directory.');
    }

    $record = [
        'id' => bin2hex(random_bytes(8)),
        'submitted_at' => date(DATE_ATOM),
        'name' => (string) ($submission['name'] ?? ''),
        'phone' => (string) ($submission['phone'] ?? ''),
        'email' => (string) ($submission['email'] ?? ''),
        'subject_key' => (string) ($submission['subject_key'] ?? ''),
        'subject_label' => (string) ($submission['subject_label'] ?? ''),
        'message' => (string) ($submission['message'] ?? ''),
        'lang' => (string) ($submission['lang'] ?? 'sk'),
        'ip' => (string) ($submission['ip'] ?? ''),
        'user_agent' => (string) ($submission['user_agent'] ?? ''),
    ];

    $handle = fopen($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open contact submissions storage.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock contact submissions storage.');
        }

        $existingJson = stream_get_contents($handle);
        $records = decode_contact_submissions($existingJson === false ? '' : $existingJson);
        array_unshift($records, $record);

        $encoded = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode contact submissions.');
        }

        rewind($handle);

        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Unable to truncate contact submissions storage.');
        }

        if (fwrite($handle, $encoded) === false) {
            throw new RuntimeException('Unable to write contact submissions storage.');
        }

        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    return $record;
}
