<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!file_exists(APP_ROOT . '/config.php')) {
    http_response_code(500);
    exit('Missing config.php. Copy config.sample.php to config.php and run install/install.php.');
}

$GLOBALS['config'] = require APP_ROOT . '/config.php';

date_default_timezone_set(config('app.timezone', 'Africa/Nairobi'));

if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
}

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/citizen.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('hsa_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

/** Read a config value with dot notation, e.g. config('db.host'). */
function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
