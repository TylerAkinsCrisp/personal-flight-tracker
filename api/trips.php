<?php
/**
 * Track Tyler - Public Trips API
 * Returns current and past trips with segments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');

// Check rate limiting
if (!checkApiRateLimit()) {
    jsonError('Rate limit exceeded. Please try again later.', 429);
}

// Get query parameters
$includesPast = isset($_GET['include_past']) && $_GET['include_past'] === 'true';
$tripId = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // If specific trip requested
    if ($tripId) {
        $trip = getTripWithSegments($tripId);
        if (!$trip) {
            jsonError('Trip not found', 404);
        }

        // Format trip for API response
        $response = formatTripForApi($trip);
        jsonSuccess(['trip' => $response]);
    }

    // Get current/upcoming trip
    $currentTrip = dbQueryOne("
        SELECT * FROM trips
        WHERE end_date >= CURDATE()
        ORDER BY start_date ASC
        LIMIT 1
    ");

    $response = [
        'current_trip' => null,
        'past_trips' => []
    ];

    if ($currentTrip) {
        $currentTrip = getTripWithSegments($currentTrip['id']);
        $response['current_trip'] = formatTripForApi($currentTrip);
    }

    // Get past trips if requested
    if ($includesPast) {
        $pastTrips = dbQuery("
            SELECT * FROM trips
            WHERE end_date < CURDATE()
            ORDER BY end_date DESC
            LIMIT 10
        ");

        foreach ($pastTrips as $trip) {
            $tripWithSegments = getTripWithSegments($trip['id']);
            $response['past_trips'][] = formatTripForApi($tripWithSegments);
        }
    }

    jsonSuccess($response);

} catch (Exception $e) {
    error_log("Trips API error: " . $e->getMessage());
    jsonError('Failed to fetch trips', 500);
}

/**
 * Format trip data for API response
 */
function formatTripForApi($trip) {
    if (!$trip) {
        return null;
    }

    $formatted = [
        'id' => (int) $trip['id'],
        'name' => $trip['name'],
        'destination_city' => $trip['destination_city'],
        'destination_country' => $trip['destination_country'],
        'start_date' => $trip['start_date'],
        'end_date' => $trip['end_date'],
        'status' => $trip['status'],
        'directions' => []
    ];

    // Format directions and segments
    foreach (['departure', 'return'] as $dirType) {
        if (isset($trip['directions'][$dirType])) {
            $direction = $trip['directions'][$dirType];
            $formattedDir = [
                'direction' => $dirType,
                'segments' => []
            ];

            if (!empty($direction['segments'])) {
                foreach ($direction['segments'] as $segment) {
                    $trackingUrl = $segment['flightaware_url'];
                    if (empty($trackingUrl) && !empty($segment['flight_number'])) {
                        $trackingUrl = 'https://flightaware.com/live/flight/' . str_replace(' ', '', $segment['flight_number']);
                    }

                    $formattedDir['segments'][] = [
                        'id' => (int) $segment['id'],
                        'flight_number' => $segment['flight_number'],
                        'airline_name' => $segment['airline_name'],
                        'departure_airport' => $segment['departure_airport'],
                        'departure_airport_name' => getAirportName($segment['departure_airport']),
                        'arrival_airport' => $segment['arrival_airport'],
                        'arrival_airport_name' => getAirportName($segment['arrival_airport']),
                        'scheduled_departure' => $segment['scheduled_departure'],
                        'scheduled_arrival' => $segment['scheduled_arrival'],
                        'departure_timezone' => $segment['departure_timezone'],
                        'arrival_timezone' => $segment['arrival_timezone'],
                        'duration_minutes' => calculateDuration($segment['scheduled_departure'], $segment['scheduled_arrival']),
                        'flightaware_url' => $segment['flightaware_url'],
                        'tracking_url' => $trackingUrl,
                        'has_tracking_link' => !empty($trackingUrl)
                    ];
                }
            }

            $formatted['directions'][$dirType] = $formattedDir;
        }
    }

    // Calculate total segments
    $formatted['total_segments'] = 0;
    foreach ($formatted['directions'] as $dir) {
        $formatted['total_segments'] += count($dir['segments']);
    }

    return $formatted;
}
