<?php
function env_or_default($key, $defaultValue) {
    $value = getenv($key);
    if (!is_string($value)) {
        return $defaultValue;
    }

    $value = trim($value);
    return $value === '' ? $defaultValue : $value;
}

function file_db_config() {
    $configPath = __DIR__ . '/db.config.php';
    if (!is_file($configPath)) {
        return null;
    }

    $config = require $configPath;
    if (!is_array($config)) {
        return null;
    }

    $required = ['host', 'user', 'pass', 'name'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $config) || !is_string($config[$key]) || trim($config[$key]) === '') {
            return null;
        }
    }

    $placeholders = [
        'host' => 'CHANGE_ME_DB_HOST',
        'user' => 'CHANGE_ME_DB_USER',
        'pass' => 'CHANGE_ME_DB_PASSWORD',
        'name' => 'CHANGE_ME_DB_NAME',
    ];
    foreach ($placeholders as $key => $placeholder) {
        if (isset($config[$key]) && trim((string) $config[$key]) === $placeholder) {
            return null;
        }
    }

    if (stripos((string) $config['host'], 'CHANGE_ME_') === 0 || stripos((string) $config['user'], 'CHANGE_ME_') === 0 || stripos((string) $config['name'], 'CHANGE_ME_') === 0) {
        return null;
    }

    return $config;
}

$fileConfig = file_db_config();
$requestHost = '';
if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) {
    $requestHost = strtolower(trim((string) $_SERVER['HTTP_HOST']));
}
if ($requestHost !== '' && strpos($requestHost, ':') !== false) {
    $requestHost = explode(':', $requestHost, 2)[0];
}

function is_private_ipv4_host($host) {
    if (!is_string($host) || $host === '') {
        return false;
    }

    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $long = ip2long($host);
    if ($long === false) {
        return false;
    }

    // 10.0.0.0/8
    if (($long & ip2long('255.0.0.0')) === ip2long('10.0.0.0')) {
        return true;
    }
    // 172.16.0.0/12
    if (($long & ip2long('255.240.0.0')) === ip2long('172.16.0.0')) {
        return true;
    }
    // 192.168.0.0/16
    if (($long & ip2long('255.255.0.0')) === ip2long('192.168.0.0')) {
        return true;
    }

    return false;
}

$isLocalServer = $requestHost === 'localhost'
    || $requestHost === '127.0.0.1'
    || $requestHost === '::1'
    || $requestHost === '[::1]'
    || is_private_ipv4_host($requestHost)
    || ($requestHost !== '' && substr($requestHost, -6) === '.local');

// Default behavior:
// - localhost requests use local XAMPP DB
// - non-localhost requests use hosting config (env vars or db.config.php)
// Set QRYPT_FORCE_HOSTING_DB=1 to force hosting DB even on localhost.
$forceHostingDb = env_or_default('QRYPT_FORCE_HOSTING_DB', '0') === '1';

if ($isLocalServer && !$forceHostingDb) {
    $servername = env_or_default('QRYPT_DB_HOST', 'localhost');
    $username = env_or_default('QRYPT_DB_USER', 'root');
    $password = env_or_default('QRYPT_DB_PASS', '');
    $dbname = env_or_default('QRYPT_DB_NAME', 'qr_attendance_db');
} else {
    $servername = env_or_default('QRYPT_DB_HOST', $fileConfig['host'] ?? '');
    $username = env_or_default('QRYPT_DB_USER', $fileConfig['user'] ?? '');
    $password = env_or_default('QRYPT_DB_PASS', $fileConfig['pass'] ?? '');
    $dbname = env_or_default('QRYPT_DB_NAME', $fileConfig['name'] ?? '');
}

if ($servername === '' || $username === '' || $dbname === '') {
    http_response_code(500);
    exit('Database configuration is missing or incomplete. Set QRYPT_DB_* env vars or complete conn/db.config.php.');
}

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}
?>
