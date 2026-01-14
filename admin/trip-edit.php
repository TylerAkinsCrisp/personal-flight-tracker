<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

initSecureSession();
requireAdmin();

// Get trip ID from query string (null for new trip)
$tripId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$trip = null;
$isEdit = false;

// Load existing trip data
if ($tripId) {
    $trip = getTripWithSegments($tripId);
    if (!$trip) {
        header('Location: index.php');
        exit;
    }
    $isEdit = true;
}

// Default values for new trip
$tripData = $trip ?? [
    'name' => '',
    'destination_city' => '',
    'destination_country' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+7 days')),
    'status' => 'planned',
    'directions' => [
        'departure' => ['segments' => []],
        'return' => ['segments' => []]
    ]
];

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Flight Tracker';
$pageTitle = ($isEdit ? 'Edit' : 'New') . " Trip - $siteName";
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

        .page-header {
            background: var(--tt-gradient);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .section-card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .section-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px 12px 0 0;
        }

        .direction-departure .section-header { border-left: 4px solid var(--tt-primary); }
        .direction-return .section-header { border-left: 4px solid var(--tt-secondary); }

        .segment-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .segment-card .remove-segment {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .segment-number {
            background: var(--tt-gradient);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }

        .fa-url-input {
            background: rgba(102, 126, 234, 0.1);
            border: 1px dashed rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--tt-primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-parse {
            background: var(--tt-gradient);
            border: none;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: #0d1117;
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 100;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="index.php" class="text-white text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Trips
                    </a>
                    <h1 class="h4 mt-2 mb-0">
                        <?php echo $isEdit ? 'Edit Trip' : 'New Trip'; ?>
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <form id="tripForm" method="POST" action="api/save-trip.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId ?? ''; ?>">

            <!-- Trip Details -->
            <div class="section-card">
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-airplane"></i> Trip Details</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Trip Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required
                                   value="<?php echo e($tripData['name']); ?>"
                                   placeholder="e.g., Vietnam Adventure 2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="planned" <?php echo $tripData['status'] === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                <option value="active" <?php echo $tripData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $tripData['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="destination_city" required
                                   value="<?php echo e($tripData['destination_city']); ?>"
                                   placeholder="e.g., Ho Chi Minh City">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination Country <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="destination_country" required
                                   value="<?php echo e($tripData['destination_country']); ?>"
                                   placeholder="e.g., Vietnam">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" required
                                   value="<?php echo e($tripData['start_date']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" required
                                   value="<?php echo e($tripData['end_date']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Departure Flights -->
            <div class="section-card direction-departure">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-right"></i> Departure Flights</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSegment('departure')">
                        <i class="bi bi-plus-circle"></i> Add Segment
                    </button>
                </div>
                <div class="card-body p-3">
                    <div id="departure-segments">
                        <?php
                        $depSegments = $tripData['directions']['departure']['segments'] ?? [];
                        if (empty($depSegments)):
                        ?>
                            <p class="text-muted text-center py-3 mb-0" id="departure-empty">
                                No departure segments. Click "Add Segment" to add flights.
                            </p>
                        <?php else: ?>
                            <?php foreach ($depSegments as $index => $segment): ?>
                                <?php echo renderSegmentForm('departure', $index, $segment); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Return Flights -->
            <div class="section-card direction-return">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-box-arrow-in-left"></i> Return Flights</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSegment('return')">
                        <i class="bi bi-plus-circle"></i> Add Segment
                    </button>
                </div>
                <div class="card-body p-3">
                    <div id="return-segments">
                        <?php
                        $retSegments = $tripData['directions']['return']['segments'] ?? [];
                        if (empty($retSegments)):
                        ?>
                            <p class="text-muted text-center py-3 mb-0" id="return-empty">
                                No return segments. Click "Add Segment" to add flights.
                            </p>
                        <?php else: ?>
                            <?php foreach ($retSegments as $index => $segment): ?>
                                <?php echo renderSegmentForm('return', $index, $segment); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sticky Actions -->
            <div class="sticky-actions">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg"></i> <?php echo $isEdit ? 'Update Trip' : 'Create Trip'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Segment Template (hidden) -->
    <template id="segment-template">
        <div class="segment-card" data-segment-index="{{INDEX}}">
            <button type="button" class="btn btn-sm btn-outline-danger remove-segment" onclick="removeSegment(this)">
                <i class="bi bi-x-lg"></i>
            </button>

            <h6 class="mb-3">
                <span class="segment-number">{{NUMBER}}</span>
                Segment {{NUMBER}}
            </h6>

            <!-- FlightAware URL Parser -->
            <div class="fa-url-input">
                <label class="form-label small">
                    <i class="bi bi-link-45deg"></i> Paste FlightAware URL to auto-fill
                </label>
                <div class="input-group">
                    <input type="text" class="form-control form-control-sm fa-url"
                           placeholder="https://www.flightaware.com/live/flight/AAL4046/history/..."
                           data-direction="{{DIRECTION}}" data-index="{{INDEX}}">
                    <button type="button" class="btn btn-sm btn-parse" onclick="parseFlightAwareUrl(this)">
                        <i class="bi bi-magic"></i> Parse
                    </button>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Flight Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][flight_number]" required
                           placeholder="AA 4046">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Airline Name</label>
                    <input type="text" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][airline_name]"
                           placeholder="American Airlines">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">RadarBox ID</label>
                    <input type="text" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][radarbox_id]"
                           placeholder="e.g., 2738100616">
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label small">From Airport <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][departure_airport]" required
                           placeholder="XNA" maxlength="10">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Departure Timezone</label>
                    <select class="form-select form-select-sm" name="segments[{{DIRECTION}}][{{INDEX}}][departure_timezone]">
                        <?php echo getTimezoneOptionsHtml(); ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">To Airport <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][arrival_airport]" required
                           placeholder="DFW" maxlength="10">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Arrival Timezone</label>
                    <select class="form-select form-select-sm" name="segments[{{DIRECTION}}][{{INDEX}}][arrival_timezone]">
                        <?php echo getTimezoneOptionsHtml(); ?>
                    </select>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-md-6">
                    <label class="form-label small">Scheduled Departure <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][scheduled_departure]" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Scheduled Arrival <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][scheduled_arrival]" required>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-12">
                    <label class="form-label small">FlightAware URL (for Track Live button)</label>
                    <input type="url" class="form-control form-control-sm"
                           name="segments[{{DIRECTION}}][{{INDEX}}][flightaware_url]"
                           placeholder="https://www.flightaware.com/live/flight/...">
                </div>
            </div>

            <input type="hidden" name="segments[{{DIRECTION}}][{{INDEX}}][sort_order]" value="{{INDEX}}">
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/admin.js?v=2"></script>
    <script>
        // Initialize segment counters from existing data
        window.segmentCounters = {
            departure: <?php echo count($depSegments); ?>,
            return: <?php echo count($retSegments); ?>
        };
    </script>
</body>
</html>

<?php
/**
 * Render a segment form with data
 */
function renderSegmentForm($direction, $index, $segment = []) {
    $defaults = [
        'flight_number' => '',
        'airline_name' => '',
        'departure_airport' => '',
        'arrival_airport' => '',
        'scheduled_departure' => '',
        'scheduled_arrival' => '',
        'departure_timezone' => 'America/Chicago',
        'arrival_timezone' => 'America/Chicago',
        'radarbox_id' => '',
        'flightaware_url' => '',
        'sort_order' => $index
    ];

    $segment = array_merge($defaults, $segment);
    $number = $index + 1;

    // Format datetime for input
    $depDt = $segment['scheduled_departure'] ? date('Y-m-d\TH:i', strtotime($segment['scheduled_departure'])) : '';
    $arrDt = $segment['scheduled_arrival'] ? date('Y-m-d\TH:i', strtotime($segment['scheduled_arrival'])) : '';

    ob_start();
    ?>
    <div class="segment-card" data-segment-index="<?php echo $index; ?>">
        <button type="button" class="btn btn-sm btn-outline-danger remove-segment" onclick="removeSegment(this)">
            <i class="bi bi-x-lg"></i>
        </button>

        <h6 class="mb-3">
            <span class="segment-number"><?php echo $number; ?></span>
            Segment <?php echo $number; ?>
        </h6>

        <!-- FlightAware URL Parser -->
        <div class="fa-url-input">
            <label class="form-label small">
                <i class="bi bi-link-45deg"></i> Paste FlightAware URL to auto-fill
            </label>
            <div class="input-group">
                <input type="text" class="form-control form-control-sm fa-url"
                       placeholder="https://www.flightaware.com/live/flight/AAL4046/history/..."
                       data-direction="<?php echo $direction; ?>" data-index="<?php echo $index; ?>">
                <button type="button" class="btn btn-sm btn-parse" onclick="parseFlightAwareUrl(this)">
                    <i class="bi bi-magic"></i> Parse
                </button>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Flight Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][flight_number]" required
                       value="<?php echo e($segment['flight_number']); ?>"
                       placeholder="AA 4046">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Airline Name</label>
                <input type="text" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][airline_name]"
                       value="<?php echo e($segment['airline_name']); ?>"
                       placeholder="American Airlines">
            </div>
            <div class="col-md-4">
                <label class="form-label small">RadarBox ID</label>
                <input type="text" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][radarbox_id]"
                       value="<?php echo e($segment['radarbox_id']); ?>"
                       placeholder="e.g., 2738100616">
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label small">From Airport <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][departure_airport]" required
                       value="<?php echo e($segment['departure_airport']); ?>"
                       placeholder="XNA" maxlength="10">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Departure Timezone</label>
                <select class="form-select form-select-sm" name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][departure_timezone]">
                    <?php echo getTimezoneOptionsHtml($segment['departure_timezone']); ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">To Airport <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][arrival_airport]" required
                       value="<?php echo e($segment['arrival_airport']); ?>"
                       placeholder="DFW" maxlength="10">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Arrival Timezone</label>
                <select class="form-select form-select-sm" name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][arrival_timezone]">
                    <?php echo getTimezoneOptionsHtml($segment['arrival_timezone']); ?>
                </select>
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <label class="form-label small">Scheduled Departure <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][scheduled_departure]" required
                       value="<?php echo $depDt; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Scheduled Arrival <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][scheduled_arrival]" required
                       value="<?php echo $arrDt; ?>">
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-12">
                <label class="form-label small">FlightAware URL (for Track Live button)</label>
                <input type="url" class="form-control form-control-sm"
                       name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][flightaware_url]"
                       value="<?php echo e($segment['flightaware_url']); ?>"
                       placeholder="https://www.flightaware.com/live/flight/...">
            </div>
        </div>

        <input type="hidden" name="segments[<?php echo $direction; ?>][<?php echo $index; ?>][sort_order]" value="<?php echo $index; ?>">
    </div>
    <?php
    return ob_get_clean();
}
?>
