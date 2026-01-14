<?php
/**
 * Database Connection - PDO wrapper
 */

if (basename($_SERVER['PHP_SELF']) === 'database.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

$_pdo = null;

/**
 * Get PDO database connection (singleton)
 */
function getDBConnection() {
    global $_pdo;

    if ($_pdo !== null) {
        return $_pdo;
    }

    try {
        $_pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );
        return $_pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Execute a prepared query and return all results
 */
function dbQuery($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
        return false;
    }
}

/**
 * Execute a prepared query and return single row
 */
function dbQueryOne($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
        return false;
    }
}

/**
 * Execute an insert/update/delete and return affected rows
 */
function dbExecute($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Execute failed: " . $e->getMessage() . " SQL: " . $sql);
        return false;
    }
}

/**
 * Execute insert and return last insert ID
 */
function dbInsert($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Insert failed: " . $e->getMessage() . " SQL: " . $sql);
        return false;
    }
}

/**
 * Begin transaction
 */
function dbBeginTransaction() {
    $pdo = getDBConnection();
    if ($pdo && !$pdo->inTransaction()) {
        return $pdo->beginTransaction();
    }
    return false;
}

/**
 * Commit transaction
 */
function dbCommit() {
    $pdo = getDBConnection();
    if ($pdo && $pdo->inTransaction()) {
        return $pdo->commit();
    }
    return false;
}

/**
 * Rollback transaction
 */
function dbRollback() {
    $pdo = getDBConnection();
    if ($pdo && $pdo->inTransaction()) {
        return $pdo->rollBack();
    }
    return false;
}

/**
 * Check if database tables exist
 */
function dbTablesExist() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'trips'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
