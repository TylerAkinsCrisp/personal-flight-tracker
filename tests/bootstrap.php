<?php

$_SERVER['PHP_SELF'] = $_SERVER['PHP_SELF'] ?? 'phpunit';

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/config/security.php';
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/includes/functions.php';
require_once $projectRoot . '/tests/Integration/DatabaseTestCase.php';

function testDbEnabled() {
    return getenv('TEST_DB_ENABLED') === '1';
}

function mapTestDbEnvToRuntime() {
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
}

