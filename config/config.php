<?php
/**
 * Central Configuration - Loads from .env file
 */

if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^(["\']).*\1$/', $value)) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    return true;
}

$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// Site Configuration
define('SITE_NAME', env('SITE_NAME', 'Flight Tracker'));

// Database
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'flight_tracker'));
define('DB_USER', env('DB_USER', 'db_user'));
define('DB_PASS', env('DB_PASS', ''));

// Authentication
define('ADMIN_SECRET_CODE', env('ADMIN_SECRET_CODE', ''));

// Session
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 3600));

// Rate Limiting
define('API_RATE_LIMIT', (int) env('API_RATE_LIMIT', 100));
define('LOGIN_MAX_ATTEMPTS', (int) env('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int) env('LOGIN_LOCKOUT_MINUTES', 15));

// Paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');

// Tracking provider whitelist
define('ALLOWED_TRACKING_PROVIDERS', [
    'flightaware' => 'flightaware.com',
    'radarbox' => 'airnavradar.com',
    'flightradar24' => 'flightradar24.com',
    'adsbexchange' => 'globe.adsbexchange.com'
]);

// Timezone options for forms
define('TIMEZONE_OPTIONS', [
    'North America' => [
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'America/Anchorage' => 'Alaska Time (AKT)',
        'Pacific/Honolulu' => 'Hawaii Time (HST)',
    ],
    'Asia - East' => [
        'Asia/Tokyo' => 'Japan Standard Time (JST)',
        'Asia/Seoul' => 'Korea Standard Time (KST)',
        'Asia/Shanghai' => 'China Standard Time (CST)',
        'Asia/Hong_Kong' => 'Hong Kong Time (HKT)',
        'Asia/Taipei' => 'Taiwan Standard Time (TST)',
    ],
    'Asia - Southeast' => [
        'Asia/Ho_Chi_Minh' => 'Vietnam Time (ICT)',
        'Asia/Bangkok' => 'Thailand Time (ICT)',
        'Asia/Manila' => 'Philippines Time (PHT)',
        'Asia/Jakarta' => 'Indonesia Western Time (WIB)',
        'Asia/Singapore' => 'Singapore Time (SGT)',
        'Asia/Kuala_Lumpur' => 'Malaysia Time (MYT)',
    ],
    'Europe' => [
        'Europe/London' => 'Greenwich Mean Time (GMT)',
        'Europe/Paris' => 'Central European Time (CET)',
        'Europe/Berlin' => 'Central European Time (CET)',
    ],
    'Oceania' => [
        'Australia/Sydney' => 'Australian Eastern Time (AET)',
        'Australia/Perth' => 'Australian Western Time (AWT)',
        'Pacific/Auckland' => 'New Zealand Standard Time (NZST)',
    ],
]);
