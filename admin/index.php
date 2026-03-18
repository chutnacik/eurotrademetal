<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$error = '';
$configError = '';
$submissions = [];
$filteredSubmissions = [];
$filterSearch = trim((string) ($_GET['q'] ?? ''));
$filterLang = strtolower(trim((string) ($_GET['lang'] ?? '')));
$filterSubject = trim((string) ($_GET['subject'] ?? ''));
$availableSubjects = [];
$availableLanguages = [];

try {
    $config = admin_load_config();
} catch (Throwable $exception) {
    $configError = $exception->getMessage();
    $config = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($configError !== '') {
        $error = $configError;
    } elseif (!admin_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Platnost formulara vyprsala. Skuste to znovu.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Zadajte prihlasovacie meno aj heslo.';
        } elseif ($config !== null && hash_equals($config['username'], $username) && password_verify($password, $config['password_hash'])) {
            admin_login($username);
            admin_redirect('/admin/index.php');
        } else {
            $error = 'Nespravne prihlasovacie udaje.';
        }
    }
}

if (admin_is_authenticated()) {
    admin_require_auth();

    try {
        $submissions = read_contact_submissions();
    } catch (Throwable $exception) {
        $submissions = [];
        $configError = $exception->getMessage();
    }

    foreach ($submissions as $submission) {
        $subjectLabel = trim((string) ($submission['subject_label'] ?? ''));
        $language = strtolower(trim((string) ($submission['lang'] ?? '')));

        if ($subjectLabel !== '') {
            $availableSubjects[$subjectLabel] = $subjectLabel;
        }

        if ($language !== '') {
            $availableLanguages[$language] = strtoupper($language);
        }
    }

    ksort($availableSubjects);
    ksort($availableLanguages);

    $filteredSubmissions = array_values(array_filter($submissions, static function (array $submission) use ($filterSearch, $filterLang, $filterSubject): bool {
        $language = strtolower((string) ($submission['lang'] ?? ''));
        $subjectLabel = (string) ($submission['subject_label'] ?? '');

        if ($filterLang !== '' && $language !== $filterLang) {
            return false;
        }

        if ($filterSubject !== '' && $subjectLabel !== $filterSubject) {
            return false;
        }

        if ($filterSearch === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', [
            (string) ($submission['name'] ?? ''),
            (string) ($submission['email'] ?? ''),
            (string) ($submission['phone'] ?? ''),
            (string) ($submission['message'] ?? ''),
            (string) ($submission['subject_label'] ?? ''),
            (string) ($submission['id'] ?? ''),
        ]));

        return mb_stripos($haystack, mb_strtolower($filterSearch)) !== false;
    }));
}

function admin_message_preview(string $message, int $limit = 120): string
{
    $message = trim((string) (preg_replace('/\s+/', ' ', $message) ?? ''));

    if ($message === '') {
        return 'Bez textu spravy.';
    }

    if (mb_strlen($message) <= $limit) {
        return $message;
    }

    return rtrim(mb_substr($message, 0, $limit - 1)) . '...';
}

function admin_initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return '?';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $first = mb_substr((string) ($parts[0] ?? ''), 0, 1);
    $second = mb_substr((string) ($parts[1] ?? ''), 0, 1);
    $initials = mb_strtoupper($first . $second);

    return $initials !== '' ? $initials : mb_strtoupper(mb_substr($name, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | EUROTRADEMETAL</title>
    <style>
        :root {
            color-scheme: light;
            --background: #fafafa;
            --surface: #ffffff;
            --surface-muted: #fcfcfc;
            --surface-soft: #f6f6f7;
            --border: #e5e7eb;
            --border-strong: #d4d4d8;
            --text: #09090b;
            --text-muted: #71717a;
            --text-soft: #a1a1aa;
            --accent: #18181b;
            --accent-soft: #f4f4f5;
            --ring: rgba(24, 24, 27, 0.1);
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;
            --warn-bg: #fffbeb;
            --warn-border: #fde68a;
            --warn-text: #92400e;
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.04), 0 10px 24px rgba(0, 0, 0, 0.04);
            --radius: 18px;
            --radius-sm: 12px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: var(--background);
            color: var(--text);
            font-family: Manrope, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button, input, select {
            font: inherit;
        }

        .page {
            min-height: 100vh;
        }

        .shell {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .login-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 16px;
        }

        .login-card {
            width: min(420px, 100%);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 28px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface-soft);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--text-muted);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
        }

        .title {
            margin: 16px 0 0;
            font-size: 30px;
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-weight: 800;
        }

        .subtitle {
            margin: 10px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.65;
            font-weight: 600;
        }

        .stack {
            display: grid;
            gap: 16px;
        }

        .form {
            margin-top: 24px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--text-muted);
        }

        .input,
        .select {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .input:focus,
        .select:focus {
            border-color: var(--border-strong);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .button {
            height: 44px;
            border: 0;
            border-radius: 12px;
            padding: 0 16px;
            background: var(--accent);
            color: white;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0;
            cursor: pointer;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .button:hover {
            opacity: 0.94;
        }

        .button.secondary {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .alert {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid;
            font-size: 14px;
            line-height: 1.5;
            font-weight: 700;
        }

        .alert.error {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }

        .alert.warn {
            background: var(--warn-bg);
            border-color: var(--warn-border);
            color: var(--warn-text);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(250, 250, 250, 0.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 72px;
        }

        .topbar-title {
            margin: 0;
            font-size: 24px;
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-weight: 800;
        }

        .topbar-meta {
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-chip {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: var(--surface);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .main {
            padding: 28px 0 48px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .filters {
            padding: 18px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) 180px 240px auto;
            gap: 12px;
            align-items: end;
        }

        .filters-actions {
            display: flex;
            gap: 10px;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .metric {
            padding: 14px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .metric-label {
            font-size: 12px;
            color: var(--text-soft);
            font-weight: 700;
        }

        .metric-value {
            margin-top: 4px;
            font-size: 22px;
            letter-spacing: -0.03em;
            font-weight: 800;
        }

        .metric-note {
            margin-top: 2px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .list-header {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) 180px 90px 130px 24px;
            gap: 16px;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
            background: var(--surface-muted);
        }

        .list {
            overflow: hidden;
        }

        details.row {
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        details.row:last-child {
            border-bottom: 0;
        }

        summary.row-summary {
            list-style: none;
            cursor: pointer;
            padding: 16px 18px;
        }

        summary.row-summary::-webkit-details-marker {
            display: none;
        }

        .row-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) 180px 90px 130px 24px;
            gap: 16px;
            align-items: center;
        }

        .person {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: var(--accent-soft);
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            flex: 0 0 auto;
        }

        .person-body {
            min-width: 0;
        }

        .person-name {
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .person-sub {
            margin-top: 2px;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-soft);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .muted {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .chevron {
            color: var(--text-soft);
            transition: transform 0.18s ease;
        }

        details[open] .chevron {
            transform: rotate(180deg);
        }

        .detail {
            padding: 0 18px 18px;
        }

        .detail-inner {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 14px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface-muted);
            padding: 14px;
        }

        .card-title {
            margin: 0 0 12px;
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
        }

        .kv {
            display: grid;
            gap: 12px;
        }

        .kv-item dt {
            margin: 0 0 4px;
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
        }

        .kv-item dd {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
            color: var(--text);
            word-break: break-word;
        }

        .message {
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.75;
            font-weight: 600;
            color: var(--text);
        }

        .empty {
            padding: 48px 20px;
            text-align: center;
        }

        .empty-title {
            margin: 0;
            font-size: 24px;
            letter-spacing: -0.03em;
            font-weight: 800;
        }

        .empty-text {
            max-width: 560px;
            margin: 10px auto 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
            font-weight: 600;
        }

        @media (max-width: 920px) {
            .metrics {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filters-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .list-header {
                display: none;
            }

            .row-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .detail-inner {
                grid-template-columns: 1fr;
            }

            .topbar-inner {
                flex-direction: column;
                align-items: stretch;
                padding: 14px 0;
            }

            .topbar-actions {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<?php if (!admin_is_authenticated()): ?>
    <main class="login-shell">
        <section class="login-card">
            <div class="eyebrow">
                <span class="dot"></span>
                Interný prístup
            </div>
            <h1 class="title">Prihlásenie</h1>
            <p class="subtitle">
                Prehľad správ z kontaktného formulára v jednoduchom internom rozhraní.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?= h($error) ?></div>
            <?php elseif ($configError !== ''): ?>
                <div class="alert warn"><?= h($configError) ?></div>
            <?php endif; ?>

            <form method="post" class="form stack">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= h(admin_csrf_token()) ?>">

                <label class="field">
                    <span class="label">Login</span>
                    <input class="input" type="text" name="username" autocomplete="username">
                </label>

                <label class="field">
                    <span class="label">Heslo</span>
                    <input class="input" type="password" name="password" autocomplete="current-password">
                </label>

                <button class="button" type="submit">Prihlásiť sa</button>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="page">
        <header class="topbar">
            <div class="shell topbar-inner">
                <div>
                    <h1 class="topbar-title">Správy z formulára</h1>
                    <div class="topbar-meta">Prehľad doručených správ s rýchlym filtrovaním a detailom na jeden klik.</div>
                </div>
                <div class="topbar-actions">
                    <div class="user-chip"><?= h((string) ($_SESSION['admin_username'] ?? 'admin')) ?></div>
                    <form action="/admin/logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(admin_csrf_token()) ?>">
                        <button class="button secondary" type="submit">Odhlásiť sa</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="main">
            <div class="shell">
                <?php if ($configError !== ''): ?>
                    <div class="alert error" style="margin-bottom: 14px;"><?= h($configError) ?></div>
                <?php endif; ?>

                <section class="metrics">
                    <article class="metric">
                        <div class="metric-label">Záznamy</div>
                        <div class="metric-value"><?= count($filteredSubmissions) ?></div>
                        <div class="metric-note">z celkového počtu <?= count($submissions) ?></div>
                    </article>
                    <article class="metric">
                        <div class="metric-label">Jazyky</div>
                        <div class="metric-value"><?= count($availableLanguages) ?></div>
                        <div class="metric-note">dostupné filtre podľa jazyka</div>
                    </article>
                    <article class="metric">
                        <div class="metric-label">Predmety</div>
                        <div class="metric-value"><?= count($availableSubjects) ?></div>
                        <div class="metric-note">typy správ v prehľade</div>
                    </article>
                </section>

                <section class="panel filters" style="margin-bottom: 14px;">
                    <form method="get" class="filters-grid">
                        <label class="field">
                            <span class="label">Hľadať</span>
                            <input class="input" type="search" name="q" value="<?= h($filterSearch) ?>" placeholder="meno, e-mail, telefón, správa, ID">
                        </label>

                        <label class="field">
                            <span class="label">Jazyk</span>
                            <select class="select" name="lang">
                                <option value="">Všetky</option>
                                <?php foreach ($availableLanguages as $value => $label): ?>
                                    <option value="<?= h($value) ?>" <?= $filterLang === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Predmet</span>
                            <select class="select" name="subject">
                                <option value="">Všetky</option>
                                <?php foreach ($availableSubjects as $subjectLabel): ?>
                                    <option value="<?= h($subjectLabel) ?>" <?= $filterSubject === $subjectLabel ? 'selected' : '' ?>><?= h($subjectLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="filters-actions">
                            <button class="button" type="submit">Filtrovať</button>
                            <a class="button secondary" href="/admin/index.php" style="display:grid;place-items:center;">Reset</a>
                        </div>
                    </form>
                </section>

                <section class="panel list">
                    <?php if ($filteredSubmissions === []): ?>
                        <div class="empty">
                            <h2 class="empty-title">Žiadne záznamy</h2>
                            <p class="empty-text">
                                Pre aktuálny filter sa nič nenašlo. Skús upraviť hľadanie alebo počkaj na prvú odoslanú správu.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="list-header">
                            <div>Kontakt</div>
                            <div>Predmet</div>
                            <div>Jazyk</div>
                            <div>Dátum</div>
                            <div></div>
                        </div>

                        <?php foreach ($filteredSubmissions as $submission): ?>
                            <?php
                                $submissionName = (string) ($submission['name'] ?? 'Bez mena');
                                $submissionEmail = (string) ($submission['email'] ?? '');
                                $submissionPhone = (string) ($submission['phone'] ?? '');
                                $submissionSubject = (string) ($submission['subject_label'] ?? 'Vseobecny dopyt');
                                $submissionLang = strtoupper((string) ($submission['lang'] ?? 'sk'));
                                $submissionDate = admin_format_datetime((string) ($submission['submitted_at'] ?? ''));
                                $submissionPreview = admin_message_preview((string) ($submission['message'] ?? ''));
                            ?>
                            <details class="row">
                                <summary class="row-summary">
                                    <div class="row-grid">
                                        <div class="person">
                                            <div class="avatar"><?= h(admin_initials($submissionName)) ?></div>
                                            <div class="person-body">
                                                <div class="person-name"><?= h($submissionName) ?></div>
                                                <div class="person-sub"><?= h($submissionEmail !== '' ? $submissionEmail : $submissionPhone) ?></div>
                                                <div class="person-sub"><?= h($submissionPreview) ?></div>
                                            </div>
                                        </div>
                                        <div><span class="badge"><?= h($submissionSubject) ?></span></div>
                                        <div><span class="badge"><?= h($submissionLang) ?></span></div>
                                        <div class="muted"><?= h($submissionDate) ?></div>
                                        <div class="chevron">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                                        </div>
                                    </div>
                                </summary>

                                <div class="detail">
                                    <div class="detail-inner">
                                        <section class="card">
                                            <h3 class="card-title">Kontaktné údaje</h3>
                                            <dl class="kv">
                                                <div class="kv-item">
                                                    <dt>Telefón</dt>
                                                    <dd><?= h((string) ($submission['phone'] ?? '')) ?></dd>
                                                </div>
                                                <div class="kv-item">
                                                    <dt>E-mail</dt>
                                                    <dd><?= h((string) ($submission['email'] ?? '')) ?></dd>
                                                </div>
                                                <div class="kv-item">
                                                    <dt>ID záznamu</dt>
                                                    <dd><?= h((string) ($submission['id'] ?? '')) ?></dd>
                                                </div>
                                                <div class="kv-item">
                                                    <dt>IP adresa</dt>
                                                    <dd><?= h((string) ($submission['ip'] ?? 'nezname')) ?></dd>
                                                </div>
                                                <div class="kv-item">
                                                    <dt>User agent</dt>
                                                    <dd><?= h((string) ($submission['user_agent'] ?? '')) ?></dd>
                                                </div>
                                            </dl>
                                        </section>

                                        <section class="card">
                                            <h3 class="card-title">Správa</h3>
                                            <div class="message"><?= h((string) ($submission['message'] ?? '')) ?></div>
                                        </section>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
