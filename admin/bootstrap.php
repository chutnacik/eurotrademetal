<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/contact-storage.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => $isHttps,
]);

session_start();

function admin_config_path(): string
{
    return dirname(__DIR__) . '/admin-config.php';
}

function admin_load_config(): array
{
    $path = admin_config_path();

    if (!is_file($path)) {
        throw new RuntimeException('Admin config file is missing. Create admin-config.php from admin-config.example.php.');
    }

    $config = require $path;

    if (!is_array($config)) {
        throw new RuntimeException('Admin config is invalid.');
    }

    foreach (['username', 'password_hash'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
            throw new RuntimeException('Missing admin config value: ' . $key);
        }
    }

    return $config;
}

function admin_is_authenticated(): bool
{
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function admin_login(string $username): void
{
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $username;
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function admin_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function admin_require_auth(): void
{
    if (!admin_is_authenticated()) {
        admin_redirect('/admin/index.php');
    }
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['admin_csrf'];
}

function admin_verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['admin_csrf'])
        && is_string($_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], $token);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_format_datetime(string $value): string
{
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        return $value;
    }

    return $date->format('d.m.Y H:i');
}
