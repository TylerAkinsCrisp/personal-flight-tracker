<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

initSecureSession();
requireAdmin();

// Get all trips with segments
$trips = getAllTripsWithSegments();

// Calculate stats
$totalTrips = count($trips);
$totalSegments = 0;
$activeTrips = 0;
$completedTrips = 0;

foreach ($trips as $trip) {
    $totalSegments += countTripSegments($trip);
    if ($trip['status'] === 'active') $activeTrips++;
    if ($trip['status'] === 'completed') $completedTrips++;
}

$timeLeft = SESSION_TIMEOUT - (time() - ($_SESSION['admin_login_time'] ?? time()));
$minutesLeft = floor($timeLeft / 60);

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
$pageTitle = "Trip Admin - $siteName";
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
        :root {
            --tt-primary: #667eea;
            --tt-secondary: #764ba2;
            --tt-gradient: linear-gradient(135deg, var(--tt-primary) 0%, var(--tt-secondary) 100%);
        }

        body { background: #0d1117; }

        .hero-section {
            background: var(--tt-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .trip-card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: transform 0.2s ease;
        }

        .trip-card:hover {
            transform: translateY(-2px);
        }

        .segment-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
        }

        .direction-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .direction-departure { background: rgba(102, 126, 234, 0.3); }
        .direction-return { background: rgba(118, 75, 162, 0.3); }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .status-planned { background: rgba(108, 117, 125, 0.3); }
        .status-active { background: rgba(25, 135, 84, 0.3); color: #75b798; }
        .status-completed { background: rgba(13, 110, 253, 0.3); color: #6ea8fe; }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-gear"></i> Trip Management
                    </h1>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="me-3 small">
                        <i class="bi bi-clock"></i> Session: <?php echo $minutesLeft; ?>m
                    </span>
                    <a href="../" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-eye"></i> View Site
                    </a>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Stats -->
        <div class="row mb-4 g-3">
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalTrips; ?></div>
                    <small class="text-muted">Total Trips</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalSegments; ?></div>
                    <small class="text-muted">Flight Segments</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $activeTrips; ?></div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $completedTrips; ?></div>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>

        <!-- Add Trip Button -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <a href="trip-edit.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle"></i> Add New Trip
                </a>
            </div>
        </div>

        <!-- Trips List -->
        <?php if (empty($trips)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No trips found. Create your first trip!
            </div>
        <?php else: ?>
            <?php foreach ($trips as $trip): ?>
                <div class="card trip-card mb-4">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1"><?php echo e($trip['name']); ?></h5>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt"></i> <?php echo e($trip['destination_city']); ?>, <?php echo e($trip['destination_country']); ?>
                                &nbsp;|&nbsp;
                                <i class="bi bi-calendar"></i> <?php echo e($trip['start_date']); ?> - <?php echo e($trip['end_date']); ?>
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-badge status-<?php echo e($trip['status']); ?>">
                                <?php echo ucfirst(e($trip['status'])); ?>
                            </span>
                            <a href="trip-edit.php?id=<?php echo $trip['id']; ?>" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    onclick="confirmDelete(<?php echo $trip['id']; ?>, '<?php echo e(addslashes($trip['name'])); ?>')">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $segmentCount = countTripSegments($trip);
                        if ($segmentCount === 0):
                        ?>
                            <p class="text-muted mb-0">
                                <i class="bi bi-info-circle"></i> No flight segments added yet.
                                <a href="trip-edit.php?id=<?php echo $trip['id']; ?>">Add segments</a>
                            </p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach (['departure', 'return'] as $dirType): ?>
                                    <?php if (isset($trip['directions'][$dirType]) && !empty($trip['directions'][$dirType]['segments'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <h6 class="mb-2">
                                                <span class="direction-badge direction-<?php echo $dirType; ?>">
                                                    <?php echo ucfirst($dirType); ?>
                                                </span>
                                                <small class="text-muted ms-2">
                                                    (<?php echo count($trip['directions'][$dirType]['segments']); ?> segment<?php echo count($trip['directions'][$dirType]['segments']) > 1 ? 's' : ''; ?>)
                                                </small>
                                            </h6>
                                            <?php foreach ($trip['directions'][$dirType]['segments'] as $segment): ?>
                                                <div class="segment-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo e($segment['flight_number']); ?></strong>
                                                            <span class="text-muted mx-2">
                                                                <?php echo e($segment['departure_airport']); ?> → <?php echo e($segment['arrival_airport']); ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!empty($segment['radarbox_id'])): ?>
                                                            <span class="badge bg-success" title="RadarBox embed available">
                                                                <i class="bi bi-broadcast"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo formatDateTime($segment['scheduled_departure'], $segment['departure_timezone'], 'M j, g:i A'); ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="deleteTripName"></span>"?</p>
                    <p class="text-danger"><strong>This will also delete all flight segments and cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" action="api/delete-trip.php" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="trip_id" id="deleteTripId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Trip
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(tripId, tripName) {
            document.getElementById('deleteTripId').value = tripId;
            document.getElementById('deleteTripName').textContent = tripName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Handle delete form submission
        document.getElementById('deleteForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete trip'));
                }
            } catch (error) {
                alert('Error deleting trip');
            }
        });
    </script>
</body>
</html>
