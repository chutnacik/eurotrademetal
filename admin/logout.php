<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    admin_logout();
}

admin_redirect('/admin/index.php');
