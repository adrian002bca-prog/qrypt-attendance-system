<?php
$tzSet = @date_default_timezone_set('Asia/Manila');
$activeTz = @date_default_timezone_get();
if ($tzSet === false || $activeTz !== 'Asia/Manila') {
    @date_default_timezone_set('Asia/Manila');
}

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_ATTEMPT_WINDOW_SECONDS = 300;
const LOGIN_LOCKOUT_SECONDS = 600;

$authConfigPath = __DIR__ . '/auth.php';
$authConfigJsonPath = __DIR__ . '/auth.json';
$AUTH_CONFIG = null;

if (!file_exists($authConfigPath) && !file_exists($authConfigJsonPath)) {
    http_response_code(500);
    exit('Authentication configuration is missing.');
}

// Prefer JSON so credential updates are not affected by PHP include caching.
$loadedConfig = null;
if (file_exists($authConfigJsonPath)) {
    $rawJson = @file_get_contents($authConfigJsonPath);
    if (is_string($rawJson) && $rawJson !== '') {
        $decodedJson = json_decode($rawJson, true);
        if (is_array($decodedJson)) {
            $loadedConfig = $decodedJson;
        }
    }
}

if ($loadedConfig === null && file_exists($authConfigPath)) {
    $loadedConfig = require $authConfigPath;
}

if (
    is_array($loadedConfig)
    && isset($loadedConfig['username'], $loadedConfig['password_hash'])
    && is_string($loadedConfig['username'])
    && is_string($loadedConfig['password_hash'])
    && $loadedConfig['username'] !== ''
    && $loadedConfig['password_hash'] !== ''
) {
    $AUTH_CONFIG = $loadedConfig;
    if (!file_exists($authConfigJsonPath)) {
        $jsonSync = json_encode($AUTH_CONFIG, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($jsonSync)) {
            @file_put_contents($authConfigJsonPath, $jsonSync . "\n", LOCK_EX);
        }
    }
} else {
    http_response_code(500);
    exit('Authentication configuration is invalid.');
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_datetime_ph($dateTimeValue) {
    if (!is_string($dateTimeValue) || trim($dateTimeValue) === '') {
        return '';
    }

    try {
        $dt = new DateTime($dateTimeValue, new DateTimeZone('Asia/Manila'));
        return $dt->format('Y-m-d h:i:s A');
    } catch (Exception $e) {
        return (string) $dateTimeValue;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_or_reject() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (is_string($sessionToken) && $sessionToken !== '') {
        if (!is_string($csrfToken) || !hash_equals($sessionToken, $csrfToken)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}

function app_base_path() {
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($projectRoot && $docRoot && strpos($projectRoot, $docRoot) === 0) {
        $relative = trim(str_replace('\\', '/', substr($projectRoot, strlen($docRoot))), '/');
        $basePath = $relative === '' ? '' : '/' . $relative;
        return $basePath;
    }

    $basePath = '/qr-code-attendance-system';
    return $basePath;
}

function redirect_to($relativePath) {
    $base = app_base_path();
    $target = ($base === '' ? '' : $base) . '/' . ltrim($relativePath, '/');
    header('Location: ' . $target);
    exit();
}

function auth_username() {
    global $AUTH_CONFIG;
    return $AUTH_CONFIG['username'];
}

function auth_password_hash() {
    global $AUTH_CONFIG;
    return $AUTH_CONFIG['password_hash'];
}

function save_auth_config($newUsername, $newHash) {
    global $AUTH_CONFIG;

    if (!is_string($newUsername) || trim($newUsername) === '') {
        return false;
    }

    if (!is_string($newHash) || $newHash === '') {
        return false;
    }

    $authConfigPath = __DIR__ . '/auth.php';
    $authConfigJsonPath = __DIR__ . '/auth.json';
    $normalizedUsername = trim($newUsername);
    $newConfig = [
        'username' => $normalizedUsername,
        'password_hash' => $newHash,
    ];

    $content = "<?php\nreturn [\n"
        . "    'username' => " . var_export($newConfig['username'], true) . ",\n"
        . "    'password_hash' => " . var_export($newConfig['password_hash'], true) . ",\n"
        . "];\n";

    $jsonContent = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $jsonWritten = false;
    if (is_string($jsonContent)) {
        $jsonWritten = file_put_contents($authConfigJsonPath, $jsonContent . "\n", LOCK_EX) !== false;
    }
    $phpWritten = file_put_contents($authConfigPath, $content, LOCK_EX) !== false;

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($authConfigJsonPath, true);
        @opcache_invalidate($authConfigPath, true);
    }

    if (!$jsonWritten && !$phpWritten) {
        return false;
    }

    $AUTH_CONFIG = $newConfig;
    return true;
}

function save_auth_password_hash($newHash) {
    return save_auth_config(auth_username(), $newHash);
}

function save_auth_username($newUsername) {
    return save_auth_config($newUsername, auth_password_hash());
}

function is_logged_in() {
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function enable_kiosk_mode() {
    $_SESSION['kiosk_mode'] = true;
}

function is_kiosk_mode() {
    return !empty($_SESSION['kiosk_mode']);
}

function require_kiosk_or_login() {
    if (!is_logged_in() && !is_kiosk_mode()) {
        redirect_to('login.php');
    }
}

function login_user($username) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    csrf_token();
}

function logout_user() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function client_ip_address() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return is_string($ip) ? $ip : 'unknown';
}

function login_attempts_path() {
    return __DIR__ . '/login_attempts.json';
}

function load_login_attempts() {
    $path = login_attempts_path();
    if (!file_exists($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function save_login_attempts($attempts) {
    if (!is_array($attempts)) {
        return false;
    }

    $encoded = json_encode($attempts);
    if (!is_string($encoded)) {
        return false;
    }

    $written = @file_put_contents(login_attempts_path(), $encoded);
    return true;
}

function login_rate_limit_key($username) {
    $normalizedUsername = strtolower(trim((string) $username));
    return client_ip_address() . '|' . $normalizedUsername;
}

function prune_login_attempts($attempts, $now) {
    if (!is_array($attempts)) {
        return [];
    }

    $maxAge = LOGIN_LOCKOUT_SECONDS + LOGIN_ATTEMPT_WINDOW_SECONDS;
    foreach ($attempts as $key => $entry) {
        if (!is_array($entry)) {
            unset($attempts[$key]);
            continue;
        }
        $last = isset($entry['last_attempt']) ? (int) $entry['last_attempt'] : 0;
        if ($last <= 0 || ($now - $last) > $maxAge) {
            unset($attempts[$key]);
        }
    }

    return $attempts;
}

function login_rate_limit_status($username) {
    $now = time();
    $attempts = prune_login_attempts(load_login_attempts(), $now);
    $key = login_rate_limit_key($username);
    $entry = $attempts[$key] ?? null;

    if (!is_array($entry)) {
        $saved = save_login_attempts($attempts);
        if (!$saved) {
            return ['allowed' => false, 'retry_after' => 60, 'storage_ok' => false];
        }
        return ['allowed' => true, 'retry_after' => 0, 'storage_ok' => true];
    }

    $count = isset($entry['count']) ? (int) $entry['count'] : 0;
    $first = isset($entry['first_attempt']) ? (int) $entry['first_attempt'] : 0;
    $last = isset($entry['last_attempt']) ? (int) $entry['last_attempt'] : 0;
    $lockedUntil = isset($entry['locked_until']) ? (int) $entry['locked_until'] : 0;

    if ($lockedUntil > $now) {
        return ['allowed' => false, 'retry_after' => $lockedUntil - $now];
    }

    if ($first > 0 && ($now - $first) > LOGIN_ATTEMPT_WINDOW_SECONDS) {
        unset($attempts[$key]);
        $saved = save_login_attempts($attempts);
        if (!$saved) {
            return ['allowed' => false, 'retry_after' => 60, 'storage_ok' => false];
        }
        return ['allowed' => true, 'retry_after' => 0, 'storage_ok' => true];
    }

    if ($count >= LOGIN_MAX_ATTEMPTS && $last > 0 && ($now - $last) <= LOGIN_ATTEMPT_WINDOW_SECONDS) {
        $retry = LOGIN_LOCKOUT_SECONDS - ($now - $last);
        if ($retry < 1) {
            $retry = 1;
        }
        $attempts[$key]['locked_until'] = $now + $retry;
        $saved = save_login_attempts($attempts);
        if (!$saved) {
            return ['allowed' => false, 'retry_after' => 60, 'storage_ok' => false];
        }
        return ['allowed' => false, 'retry_after' => $retry, 'storage_ok' => true];
    }

    $saved = save_login_attempts($attempts);
    if (!$saved) {
        return ['allowed' => false, 'retry_after' => 60, 'storage_ok' => false];
    }
    return ['allowed' => true, 'retry_after' => 0, 'storage_ok' => true];
}

function record_login_failure($username) {
    $now = time();
    $attempts = prune_login_attempts(load_login_attempts(), $now);
    $key = login_rate_limit_key($username);
    $entry = $attempts[$key] ?? null;

    if (!is_array($entry) || !isset($entry['first_attempt'], $entry['count'])) {
        $attempts[$key] = [
            'first_attempt' => $now,
            'last_attempt' => $now,
            'count' => 1,
            'locked_until' => 0,
        ];
        save_login_attempts($attempts);
        return;
    }

    $first = (int) $entry['first_attempt'];
    if (($now - $first) > LOGIN_ATTEMPT_WINDOW_SECONDS) {
        $entry['first_attempt'] = $now;
        $entry['count'] = 1;
    } else {
        $entry['count'] = ((int) $entry['count']) + 1;
    }

    $entry['last_attempt'] = $now;
    if ((int) $entry['count'] >= LOGIN_MAX_ATTEMPTS) {
        $entry['locked_until'] = $now + LOGIN_LOCKOUT_SECONDS;
    }

    $attempts[$key] = $entry;
    save_login_attempts($attempts);
}

function clear_login_failures($username) {
    $attempts = load_login_attempts();
    $key = login_rate_limit_key($username);
    if (isset($attempts[$key])) {
        unset($attempts[$key]);
        save_login_attempts($attempts);
    }
}
?>
