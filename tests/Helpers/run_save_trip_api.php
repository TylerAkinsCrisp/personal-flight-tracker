<?php

$projectRoot = dirname(__DIR__, 2);

$keys = ['HOST', 'NAME', 'USER', 'PASS'];
foreach ($keys as $key) {
    $testKey = 'TEST_DB_' . $key;
    $dbKey = 'DB_' . $key;
    $value = getenv($testKey);
    if ($value !== false && $value !== '') {
        putenv($dbKey . '=' . $value);
        $_ENV[$dbKey] = $value;
    }
}

$input = stream_get_contents(STDIN);
$payload = json_decode($input, true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid JSON input\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
$_SERVER['PHP_SELF'] = '/admin/api/save-trip.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_login_time'] = time();
$_SESSION['admin_ip'] = '127.0.0.1';
$_SESSION['csrf_token'] = 'test-csrf-token';

$_POST = $payload;
$_POST['csrf_token'] = 'test-csrf-token';

require $projectRoot . '/admin/api/save-trip.php';

