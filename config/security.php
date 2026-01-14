<?php
/**
 * Security Helpers - CSRF, sessions, rate limiting, escaping
 */

if (basename($_SERVER['PHP_SELF']) === 'security.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

if (!defined('SESSION_TIMEOUT')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Initialize secure session with hardened settings
 */
function initSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Set secure session parameters before starting
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Set secure flag if HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        destroySession();
        return;
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Regenerate session ID (call on login)
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Destroy session completely
 */
function destroySession() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        initSecureSession();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF hidden input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Validate CSRF token from POST request
 */
function validateCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or exit with error
 */
function requireCsrfToken() {
    if (!validateCsrfToken()) {
        http_response_code(403);
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token']);
        } else {
            echo 'Security validation failed. Please try again.';
        }
        exit;
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 */
function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Check for forwarded IP (behind proxy)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
    }

    // Validate IP format
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }

    return $ip;
}

/**
 * Check login rate limit using database
 */
function checkLoginRateLimit($pdo) {
    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = ?
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND success = 0
        ");
        $stmt->execute([$ip, LOGIN_LOCKOUT_MINUTES]);
        $failedAttempts = $stmt->fetchColumn();

        return $failedAttempts < LOGIN_MAX_ATTEMPTS;
    } catch (PDOException $e) {
        // If table doesn't exist yet, allow login
        return true;
    }
}

/**
 * Record login attempt
 */
function recordLoginAttempt($pdo, $success) {
    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, success) VALUES (?, ?)
        ");
        $stmt->execute([$ip, $success ? 1 : 0]);
    } catch (PDOException $e) {
        // Silently fail if table doesn't exist
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Clean old login attempts (call periodically)
 */
function cleanOldLoginAttempts($pdo) {
    try {
        $pdo->exec("
            DELETE FROM login_attempts
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    } catch (PDOException $e) {
        // Silently fail
    }
}

/**
 * Check API rate limit (session-based)
 */
function checkApiRateLimit() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        initSecureSession();
    }

    $currentTime = time();

    if (!isset($_SESSION['api_requests'])) {
        $_SESSION['api_requests'] = [];
    }

    // Clean old requests (older than 1 hour)
    $_SESSION['api_requests'] = array_filter(
        $_SESSION['api_requests'],
        function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < 3600;
        }
    );

    if (count($_SESSION['api_requests']) >= API_RATE_LIMIT) {
        return false;
    }

    $_SESSION['api_requests'][] = $currentTime;
    return true;
}

/**
 * Escape output for HTML context
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for JavaScript context
 */
function ejs($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

/**
 * Validate and sanitize URL for tracking providers
 */
function validateTrackingUrl($url) {
    if (empty($url)) {
        return ['valid' => true, 'url' => ''];
    }

    // Parse URL
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return ['valid' => false, 'error' => 'Invalid URL format'];
    }

    // Check against whitelist
    $host = strtolower($parsed['host']);
    $host = preg_replace('/^www\./', '', $host);

    $allowed = false;
    foreach (ALLOWED_TRACKING_PROVIDERS as $provider => $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        return ['valid' => false, 'error' => 'URL domain not in whitelist'];
    }

    return ['valid' => true, 'url' => $url];
}

/**
 * Check if admin is authenticated
 */
function isAdminAuthenticated() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        initSecureSession();
    }

    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > SESSION_TIMEOUT) {
        destroySession();
        return false;
    }

    // Validate IP hasn't changed (optional strict mode)
    if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== getClientIp()) {
        destroySession();
        return false;
    }

    return true;
}

/**
 * Require admin authentication or redirect
 */
function requireAdmin($redirectUrl = 'login.php') {
    if (!isAdminAuthenticated()) {
        if (isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Refresh session timeout
    $_SESSION['admin_login_time'] = time();
}

/**
 * Log security event
 */
function logSecurityEvent($event, $details = '') {
    $logDir = BASE_PATH . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIp();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = "[$timestamp] [$ip] $event - $details | UA: $userAgent\n";

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * Send success JSON response
 */
function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}
