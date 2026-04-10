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
$payload = json_decode($input, true) ?: [];

$_GET = $payload['get'] ?? [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/api/trips.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require $projectRoot . '/api/trips.php';

