<?php
if (basename($_SERVER['PHP_SELF']) === 'header.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
$pageTitle = $pageTitle ?? $siteName;
$pageDescription = $pageDescription ?? 'Track flights and adventures';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <title><?php echo e($pageTitle); ?></title>

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✈️</text></svg>">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --tt-primary: #667eea;
            --tt-secondary: #764ba2;
            --tt-gradient: linear-gradient(135deg, var(--tt-primary) 0%, var(--tt-secondary) 100%);
        }

        body {
            min-height: 100vh;
            background: #0d1117;
        }

        .hero-gradient {
            background: var(--tt-gradient);
        }

        .card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
        }

        .flight-card {
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .flight-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .airport-code {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--tt-primary);
        }

        .flight-number {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .countdown {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--tt-primary);
        }

        .radarbox-embed {
            border-radius: 8px;
            overflow: hidden;
            background: #1a1a2e;
        }

        .radarbox-embed iframe {
            display: block;
        }

        .track-live-btn {
            background: var(--tt-gradient);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .track-live-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .direction-header {
            background: rgba(102, 126, 234, 0.1);
            border-left: 4px solid var(--tt-primary);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0 8px 8px 0;
        }

        .segment-connector {
            width: 2px;
            height: 30px;
            background: linear-gradient(to bottom, var(--tt-primary), transparent);
            margin: 0 auto;
        }

        <?php if (isset($additionalStyles)): ?>
        <?php echo $additionalStyles; ?>
        <?php endif; ?>
    </style>

    <?php if (isset($additionalHead)): ?>
    <?php echo $additionalHead; ?>
    <?php endif; ?>
</head>
<body>
