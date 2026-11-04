<?php
declare(strict_types=1);

function shop_config(): array
{
    static $config;
    if (isset($config)) return $config;
    $path = __DIR__ . '/config.local.php';
    if (!is_file($path)) throw new RuntimeException('Shop is not configured');
    $config = require $path;
    if (!is_array($config) || empty($config['db_name']) || empty($config['db_user'])) {
        throw new RuntimeException('Invalid shop configuration');
    }
    return $config;
}

function shop_db(): PDO
{
    static $pdo;
    if (isset($pdo)) return $pdo;
    $c = shop_config();
    $dsn = 'mysql:host=' . $c['db_host'] . ';port=' . ($c['db_port'] ?? '3306') .
        ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
    return $pdo = new PDO($dsn, $c['db_user'], $c['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function shop_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name(shop_config()['session_name'] ?? 'myca_shop_admin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Strict'
    ]);
    session_start();
    if (!isset($_SESSION['shop_csrf'])) $_SESSION['shop_csrf'] = bin2hex(random_bytes(32));
}

function shop_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
}

function shop_json(array $data, int $status = 200): never
{
    shop_headers();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function shop_error(string $message, int $status = 400): never
{
    shop_json(['ok' => false, 'error' => $message], $status);
}

function shop_method(string $expected): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $expected) shop_error('متد درخواست مجاز نیست.', 405);
}

function shop_input(): array
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) shop_error('درخواست بیش از حد بزرگ است.', 413);
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data) || array_is_list($data)) shop_error('داده‌های ارسالی معتبر نیست.', 400);
    return $data;
}

function shop_csrf(): void
{
    shop_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals((string)$_SESSION['shop_csrf'], $token)) {
        shop_error('نشست نامعتبر است؛ صفحه را تازه‌سازی کنید.', 419);
    }
}

function shop_require_admin(): array
{
    shop_session();
    if (!isset($_SESSION['shop_admin']['id'])) shop_error('برای ادامه وارد پنل شوید.', 401);
    return $_SESSION['shop_admin'];
}

function shop_text(mixed $value, int $max, string $field, bool $required = false): string
{
    if (!is_string($value) && !is_numeric($value)) shop_error("فیلد {$field} نامعتبر است.");
    $value = trim((string)$value);
    if (($required && $value === '') || strlen($value) > $max) shop_error("فیلد {$field} نامعتبر است.");
    return $value;
}

function shop_slug(mixed $value): string
{
    $slug = shop_text($value, 190, 'نامک', true);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) shop_error('نامک باید انگلیسی و شامل حروف کوچک، اعداد و خط تیره باشد.');
    return $slug;
}

function shop_unsigned(mixed $value, string $field, int $max = 999999999999): int
{
    $string = (string)$value;
    if (!preg_match('/^(0|[1-9][0-9]*)$/D', $string) || strlen($string) > 13 || (float)$string > $max) {
        shop_error("مقدار {$field} معتبر نیست.");
    }
    return (int)$string;
}

function shop_url(mixed $url, string $field = 'آدرس رسانه'): string
{
    $url = shop_text($url ?? '', 2048, $field);
    if ($url === '') return '';
    // Local uploads or HTTPS URLs only: no data:, javascript:, http:, //, or external SVG.
    if (str_starts_with($url, '/uploads/') && preg_match('#^/uploads/[a-f0-9]{32}\.(png|jpg|webp|gif)$#D', $url)) return $url;
    $bits = parse_url($url);
    if ($bits === false || ($bits['scheme'] ?? '') !== 'https' || empty($bits['host']) || isset($bits['user']) || isset($bits['pass'])) {
        shop_error("{$field} باید یک URL امن HTTPS یا تصویر آپلودشده باشد.");
    }
    return $url;
}

function shop_audit(PDO $db, string $actor, string $action, string $kind, string $entityId): void
{
    $q = $db->prepare('INSERT INTO shop_audit_log (actor, action_name, entity_type, entity_id) VALUES (?,?,?,?)');
    $q->execute([$actor, $action, $kind, $entityId]);
}

function shop_exception(Throwable $e): never
{
    error_log('[MYCA SHOP] ' . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        shop_error('اطلاعات تکراری است یا این مورد در بخش دیگری استفاده شده است.', 409);
    }
    shop_error('خطای سرور؛ تنظیمات پایگاه داده یا لاگ PHP را بررسی کنید.', 503);
}
