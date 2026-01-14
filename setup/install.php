<?php
/**
 * Database Installation Script
 * Run once to set up tables, then DELETE this file.
 */

header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/database.php';

$messages = [];
$success = true;
$confirmInstall = isset($_POST['confirm_install']) && $_POST['confirm_install'] === 'yes';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - Database Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .install-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="d-flex align-items-center py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card install-card">
                    <div class="card-header text-center">
                        <h2><i class="bi bi-database-gear"></i> Database Installation</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!$confirmInstall): ?>
                            <div class="alert alert-info">
                                <h5><i class="bi bi-info-circle"></i> Setup</h5>
                                <p>This will create the following tables (if they don't exist):</p>
                                <ul class="mb-0">
                                    <li><code>trips</code></li>
                                    <li><code>trip_directions</code></li>
                                    <li><code>flight_segments</code></li>
                                    <li><code>login_attempts</code></li>
                                </ul>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="confirm_install" value="yes">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-database-add"></i> Install Database Tables
                                    </button>
                                    <a href="../" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <?php
                            try {
                                $pdo = getDBConnection();
                                if (!$pdo) {
                                    throw new Exception("Failed to connect to database. Check your .env file.");
                                }

                                $schema = file_get_contents(__DIR__ . '/schema.sql');
                                if ($schema === false) {
                                    throw new Exception("Could not read schema.sql");
                                }

                                // Execute each statement separately
                                $statements = array_filter(
                                    array_map('trim', explode(';', $schema)),
                                    function($stmt) { return !empty($stmt) && strpos(trim($stmt), '--') !== 0; }
                                );

                                foreach ($statements as $statement) {
                                    if (empty(trim($statement))) continue;
                                    
                                    try {
                                        $pdo->exec($statement);
                                        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                                            $messages[] = ['type' => 'success', 'text' => "Table ready: {$matches[1]}"];
                                        }
                                    } catch (PDOException $e) {
                                        // Table might already exist, that's okay with IF NOT EXISTS
                                        if (strpos($e->getMessage(), 'already exists') === false) {
                                            throw $e;
                                        }
                                        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                                            $messages[] = ['type' => 'info', 'text' => "Table exists: {$matches[1]}"];
                                        }
                                    }
                                }

                                $messages[] = ['type' => 'success', 'text' => 'Installation complete!'];

                            } catch (Exception $e) {
                                $success = false;
                                $messages[] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
                            }
                            ?>

                            <h5 class="mb-3">Results:</h5>
                            <?php foreach ($messages as $msg): ?>
                                <div class="alert alert-<?php echo $msg['type']; ?> py-2">
                                    <i class="bi bi-<?php echo $msg['type'] === 'danger' ? 'x-circle' : 'check-circle'; ?>"></i>
                                    <?php echo htmlspecialchars($msg['text']); ?>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-warning mt-4">
                                    <h5><i class="bi bi-shield-exclamation"></i> Security</h5>
                                    <p class="mb-0"><strong>Delete this file now!</strong><br>
                                    Remove <code>/setup/install.php</code> from your server.</p>
                                </div>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="../admin/" class="btn btn-primary btn-lg">
                                        <i class="bi bi-gear"></i> Go to Admin Panel
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="install.php" class="btn btn-warning">
                                        <i class="bi bi-arrow-clockwise"></i> Try Again
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
