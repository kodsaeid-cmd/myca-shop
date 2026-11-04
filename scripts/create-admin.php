<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php name@example.com\n"); exit(1);
}
fwrite(STDOUT, "New password (at least 12 characters): ");
$tty = function_exists('posix_isatty') && posix_isatty(STDIN);
if ($tty) @system('stty -echo');
try {
    $password = rtrim((string)fgets(STDIN), "\r\n");
} finally {
    if ($tty) @system('stty echo');
    fwrite(STDOUT, "\n");
}
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "Password length must be between 12 and 200 characters.\n"); exit(1);
}
try {
    $db = shop_db();
    $q = $db->prepare('INSERT INTO shop_admins(email,password_hash,role,active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), active=1');
    $q->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    fwrite(STDOUT, "Admin account is ready. Visit /admin/ over HTTPS.\n");
} catch (Throwable $e) {
    error_log('[MYCA SHOP CLI] '.$e->getMessage());
    fwrite(STDERR, "Failed. Check config.local.php and imported MySQL schema.\n"); exit(1);
}
