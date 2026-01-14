<?php
require_once __DIR__ . '/../config/database.php';

initSecureSession();

// Check if already authenticated
if (isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$rateLimited = false;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();

    // Check rate limiting
    if ($pdo && !checkLoginRateLimit($pdo)) {
        $rateLimited = true;
        $error = 'Too many login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
        logSecurityEvent('LOGIN_RATE_LIMITED', 'IP blocked due to too many failed attempts');
    } else {
        $secretCode = trim($_POST['secret_code'] ?? '');

        if (empty($secretCode)) {
            $error = 'Please enter the secret code.';
        } elseif (empty(ADMIN_SECRET_CODE)) {
            $error = 'Admin authentication not configured. Check .env file.';
        } elseif (hash_equals(ADMIN_SECRET_CODE, $secretCode)) {
            // Success
            regenerateSession();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_ip'] = getClientIp();
            $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // Record successful login
            if ($pdo) {
                recordLoginAttempt($pdo, true);
                cleanOldLoginAttempts($pdo);
            }

            logSecurityEvent('ADMIN_LOGIN_SUCCESS', 'Admin logged in successfully');

            header('Location: index.php');
            exit;
        } else {
            // Failed
            if ($pdo) {
                recordLoginAttempt($pdo, false);
            }

            logSecurityEvent('ADMIN_LOGIN_FAILED', 'Invalid secret code attempt');
            $error = 'Invalid secret code. Access denied.';
        }
    }
}

logSecurityEvent('ADMIN_LOGIN_PAGE', 'Login page accessed');

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
$pageTitle = "Admin Login - $siteName";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
        }
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .btn-primary:disabled {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .security-info {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <h1 class="text-white">
                        <i class="bi bi-shield-lock"></i> Admin Access
                    </h1>
                    <p class="text-white-50">Enter secret code to continue</p>
                </div>

                <div class="login-card">
                    <form method="POST" action="" autocomplete="off">
                        <?php echo csrfField(); ?>

                        <div class="mb-3">
                            <label for="secret_code" class="form-label text-white">
                                <i class="bi bi-key"></i> Secret Code
                            </label>
                            <input
                                type="password"
                                class="form-control form-control-lg"
                                id="secret_code"
                                name="secret_code"
                                placeholder="Enter your secret code"
                                required
                                autocomplete="off"
                                <?php echo $rateLimited ? 'disabled' : ''; ?>
                            >
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary btn-lg w-100" <?php echo $rateLimited ? 'disabled' : ''; ?>>
                            <i class="bi bi-unlock"></i> Access Admin
                        </button>
                    </form>

                    <div class="security-info">
                        <h6 class="text-warning">
                            <i class="bi bi-shield-exclamation"></i> Security Notice
                        </h6>
                        <small class="text-white-50">
                            All access attempts are logged with IP address and timestamp.
                            After <?php echo LOGIN_MAX_ATTEMPTS; ?> failed attempts, access is blocked for <?php echo LOGIN_LOCKOUT_MINUTES; ?> minutes.
                        </small>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="../" class="text-white-50 text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Site
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('secret_code').focus();
        window.addEventListener('load', function() {
            document.querySelector('form').reset();
        });
    </script>
</body>
</html>
