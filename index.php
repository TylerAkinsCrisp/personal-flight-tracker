<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$currentTrip = getCurrentTrip();
if ($currentTrip) {
    $currentTrip = getTripWithSegments($currentTrip['id']);
}

$pastTrips = getPastTrips(5);
foreach ($pastTrips as &$trip) {
    $trip = getTripWithSegments($trip['id']);
}

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
$pageTitle = $siteName;
if ($currentTrip) {
    $pageTitle .= ' - ' . $currentTrip['name'];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Track flights and adventures in real-time">
    <title><?php echo e($pageTitle); ?></title>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✈️</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --tt-primary: #667eea;
            --tt-secondary: #764ba2;
            --tt-gradient: linear-gradient(135deg, var(--tt-primary) 0%, var(--tt-secondary) 100%);
        }

        body {
            background: #0d1117;
            min-height: 100vh;
        }

        .hero-section {
            background: var(--tt-gradient);
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .countdown-display {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .trip-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .trip-stat {
            text-align: center;
        }

        .trip-stat-value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .trip-stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .direction-section {
            margin-bottom: 2rem;
        }

        .direction-header {
            background: rgba(102, 126, 234, 0.15);
            border-left: 4px solid var(--tt-primary);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0 8px 8px 0;
        }

        .direction-return .direction-header {
            border-left-color: var(--tt-secondary);
            background: rgba(118, 75, 162, 0.15);
        }

        .flight-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .flight-card-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .flight-card-body {
            padding: 1.25rem;
        }

        .airport-code {
            font-size: 2rem;
            font-weight: 700;
            color: var(--tt-primary);
        }

        .airport-name {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .flight-time {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .flight-date {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .flight-number {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .flight-duration {
            color: rgba(255, 255, 255, 0.6);
        }

        .countdown {
            font-family: 'Courier New', monospace;
            color: var(--tt-primary);
            font-size: 0.9rem;
        }

        .track-live-btn {
            background: var(--tt-gradient);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .track-live-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .segment-connector {
            width: 2px;
            height: 30px;
            background: linear-gradient(to bottom, var(--tt-primary), transparent);
            margin: 0 auto;
        }

        .no-trip-message {
            text-align: center;
            padding: 4rem 2rem;
        }

        .past-trips-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .past-trip-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 1.75rem; }
            .airport-code { font-size: 1.5rem; }
            .trip-stats { gap: 1rem; }
        }
    </style>
</head>
<body>
    <?php if ($currentTrip): ?>
        <!-- Hero Section -->
        <section class="hero-section text-white">
            <div class="container">
                <h1 class="hero-title">
                    <?php echo getCountryFlag($currentTrip['destination_country']); ?> <?php echo e($currentTrip['name']); ?>
                </h1>

                <div class="trip-stats">
                    <div class="trip-stat">
                        <div class="trip-stat-value"><?php echo e($currentTrip['destination_city']); ?></div>
                        <div class="trip-stat-label"><?php echo e($currentTrip['destination_country']); ?></div>
                    </div>
                    <div class="trip-stat">
                        <div class="trip-stat-value"><?php echo date('M j', strtotime($currentTrip['start_date'])); ?></div>
                        <div class="trip-stat-label">Departure</div>
                    </div>
                    <div class="trip-stat">
                        <div class="trip-stat-value"><?php echo date('M j', strtotime($currentTrip['end_date'])); ?></div>
                        <div class="trip-stat-label">Return</div>
                    </div>
                    <div class="trip-stat">
                        <div class="trip-stat-value" id="main-countdown">—</div>
                        <div class="trip-stat-label">Until Adventure</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container pb-5">
            <?php foreach (['departure' => 'Departure Flights', 'return' => 'Return Flights'] as $dirType => $dirLabel): ?>
                <?php if (isset($currentTrip['directions'][$dirType]) && !empty($currentTrip['directions'][$dirType]['segments'])): ?>
                    <section class="direction-section direction-<?php echo $dirType; ?>">
                        <div class="direction-header">
                            <h2 class="h5 mb-0">
                                <i class="bi bi-<?php echo $dirType === 'departure' ? 'box-arrow-right' : 'box-arrow-in-left'; ?>"></i>
                                <?php echo $dirLabel; ?>
                            </h2>
                        </div>

                        <?php foreach ($currentTrip['directions'][$dirType]['segments'] as $index => $segment): ?>
                            <div class="flight-card">
                                <div class="flight-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="flight-number"><?php echo e($segment['flight_number']); ?></span>
                                        <?php if ($segment['airline_name']): ?>
                                            <span class="text-muted ms-2"><?php echo e($segment['airline_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $duration = calculateFlightDuration(
                                        $segment['scheduled_departure'],
                                        $segment['departure_timezone'],
                                        $segment['scheduled_arrival'],
                                        $segment['arrival_timezone']
                                    );
                                    if ($duration > 0):
                                    ?>
                                    <div class="flight-duration">
                                        <i class="bi bi-clock"></i>
                                        <?php echo formatDuration($duration); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flight-card-body">
                                    <div class="row align-items-center">
                                        <div class="col-5 col-md-4 text-center">
                                            <div class="airport-code"><?php echo e($segment['departure_airport']); ?></div>
                                            <div class="airport-name"><?php echo e(getAirportName($segment['departure_airport'])); ?></div>
                                            <div class="flight-time mt-2">
                                                <?php echo formatTime($segment['scheduled_departure'], $segment['departure_timezone']); ?>
                                                <small class="text-muted"><?php echo getTimezoneAbbr($segment['departure_timezone']); ?></small>
                                            </div>
                                            <div class="flight-date">
                                                <?php echo formatDate($segment['scheduled_departure'], $segment['departure_timezone']); ?>
                                            </div>
                                            <div class="countdown mt-1" data-countdown="<?php echo e($segment['scheduled_departure']); ?>"></div>
                                        </div>

                                        <div class="col-2 col-md-4 text-center">
                                            <i class="bi bi-airplane fs-2 text-primary"></i>
                                        </div>

                                        <div class="col-5 col-md-4 text-center">
                                            <div class="airport-code"><?php echo e($segment['arrival_airport']); ?></div>
                                            <div class="airport-name"><?php echo e(getAirportName($segment['arrival_airport'])); ?></div>
                                            <div class="flight-time mt-2">
                                                <?php echo formatTime($segment['scheduled_arrival'], $segment['arrival_timezone']); ?>
                                                <small class="text-muted"><?php echo getTimezoneAbbr($segment['arrival_timezone']); ?></small>
                                            </div>
                                            <div class="flight-date">
                                                <?php echo formatDate($segment['scheduled_arrival'], $segment['arrival_timezone']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    // Only show tracking on the day of the flight or later
                                    $flightDate = date('Y-m-d', strtotime($segment['scheduled_departure']));
                                    $today = date('Y-m-d');
                                    $showTracking = ($today >= $flightDate);
                                    ?>
                                    <?php if ($showTracking): ?>
                                        <div class="text-center mt-3">
                                            <?php
                                            $trackUrl = $segment['flightaware_url'];
                                            if (empty($trackUrl)) {
                                                $cleanNumber = str_replace(' ', '', $segment['flight_number']);
                                                $trackUrl = 'https://flightaware.com/live/flight/' . $cleanNumber;
                                            }
                                            ?>
                                            <a href="<?php echo e($trackUrl); ?>" target="_blank" rel="noopener noreferrer" class="track-live-btn">
                                                <i class="bi bi-broadcast"></i> Track Live on FlightAware
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($index < count($currentTrip['directions'][$dirType]['segments']) - 1): ?>
                                <div class="segment-connector"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- No Current Trip -->
        <section class="hero-section text-white">
            <div class="container">
                <h1 class="hero-title">
                    <i class="bi bi-airplane"></i> Track Tyler
                </h1>
                <p class="lead mb-0">Follow Tyler's adventures around the world</p>
            </div>
        </section>

        <div class="container">
            <div class="no-trip-message">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <h2 class="h4 mt-3">No Upcoming Trips</h2>
                <p class="text-muted">Check back soon for the next adventure!</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Past Trips Section -->
    <?php if (!empty($pastTrips)): ?>
        <div class="container pb-5">
            <section class="past-trips-section">
                <div class="accordion" id="pastTripsAccordion">
                    <div class="accordion-item bg-transparent border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-white" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#pastTripsContent">
                                <i class="bi bi-clock-history me-2"></i> Past Trips (<?php echo count($pastTrips); ?>)
                            </button>
                        </h2>
                        <div id="pastTripsContent" class="accordion-collapse collapse" data-bs-parent="#pastTripsAccordion">
                            <div class="accordion-body">
                                <?php foreach ($pastTrips as $trip): ?>
                                    <div class="past-trip-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo e($trip['name']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt"></i> <?php echo e($trip['destination_city']); ?>, <?php echo e($trip['destination_country']); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M Y', strtotime($trip['start_date'])); ?>
                                            </small>
                                        </div>
                                        <?php
                                        $segmentCount = countTripSegments($trip);
                                        if ($segmentCount > 0):
                                        ?>
                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-airplane"></i> <?php echo $segmentCount; ?> flight<?php echo $segmentCount > 1 ? 's' : ''; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="text-center py-4 text-muted">
        <small>&copy; <?php echo date('Y'); ?> Track Tyler</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown functionality
        function updateCountdowns() {
            const countdowns = document.querySelectorAll('[data-countdown]');
            const now = new Date();

            countdowns.forEach(el => {
                const target = new Date(el.dataset.countdown);
                const diff = target - now;

                if (diff <= 0) {
                    el.textContent = 'Departed';
                    el.classList.add('text-success');
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

                let text = '';
                if (days > 0) text += days + 'd ';
                if (hours > 0 || days > 0) text += hours + 'h ';
                text += minutes + 'm';

                el.textContent = text;
            });

            // Update main countdown
            const mainCountdown = document.getElementById('main-countdown');
            if (mainCountdown) {
                const firstCountdown = document.querySelector('[data-countdown]');
                if (firstCountdown) {
                    const target = new Date(firstCountdown.dataset.countdown);
                    const diff = target - now;

                    if (diff <= 0) {
                        mainCountdown.textContent = 'In Progress!';
                    } else {
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        mainCountdown.textContent = days + 'd ' + hours + 'h';
                    }
                }
            }
        }

        // Initial update and interval
        updateCountdowns();
        setInterval(updateCountdowns, 60000); // Update every minute
    </script>
</body>
</html>
