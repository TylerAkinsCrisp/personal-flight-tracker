<?php
/**
 * Track Tyler - Save Trip API
 * Creates or updates a trip with directions and segments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Initialize session and require admin
initSecureSession();
requireAdmin('../login.php');

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Validate CSRF token
requireCsrfToken();

// Validate required fields
$requiredFields = ['name', 'destination_city', 'destination_country', 'start_date', 'end_date'];
foreach ($requiredFields as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        jsonError("Missing required field: $field");
    }
}

// Sanitize trip data
$tripId = !empty($_POST['trip_id']) ? (int) $_POST['trip_id'] : null;
$name = trim($_POST['name']);
$destinationCity = trim($_POST['destination_city']);
$destinationCountry = trim($_POST['destination_country']);
$startDate = trim($_POST['start_date']);
$endDate = trim($_POST['end_date']);
$status = in_array($_POST['status'] ?? '', ['planned', 'active', 'completed']) ? $_POST['status'] : 'planned';

// Validate dates
if (!strtotime($startDate) || !strtotime($endDate)) {
    jsonError('Invalid date format');
}

if (strtotime($startDate) > strtotime($endDate)) {
    jsonError('Start date must be before or equal to end date');
}

// Get segments data
$segments = $_POST['segments'] ?? [];

// Start transaction
$pdo = getDBConnection();
if (!$pdo) {
    jsonError('Database connection failed', 500);
}

try {
    dbBeginTransaction();

    if ($tripId) {
        // Update existing trip
        $result = dbExecute(
            "UPDATE trips SET name = ?, destination_city = ?, destination_country = ?,
             start_date = ?, end_date = ?, status = ? WHERE id = ?",
            [$name, $destinationCity, $destinationCountry, $startDate, $endDate, $status, $tripId]
        );

        if ($result === false) {
            throw new Exception('Failed to update trip');
        }

        // Delete existing directions and segments (will cascade)
        dbExecute("DELETE FROM trip_directions WHERE trip_id = ?", [$tripId]);

    } else {
        // Create new trip
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO trips (name, destination_city, destination_country, start_date, end_date, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $destinationCity, $destinationCountry, $startDate, $endDate, $status]);
            $tripId = $pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to create trip: ' . $e->getMessage());
        }

        if (!$tripId) {
            throw new Exception('Failed to create trip: No ID returned');
        }
    }

    // Create directions and segments
    foreach (['departure', 'return'] as $sortOrder => $direction) {
        // Only create direction if there are segments
        if (empty($segments[$direction])) {
            continue;
        }

        // Create direction
        $directionId = dbInsert(
            "INSERT INTO trip_directions (trip_id, direction, sort_order) VALUES (?, ?, ?)",
            [$tripId, $direction, $sortOrder]
        );

        if (!$directionId) {
            throw new Exception("Failed to create $direction direction");
        }

        // Create segments
        foreach ($segments[$direction] as $index => $segmentData) {
            // Validate required segment fields
            if (empty(trim($segmentData['flight_number'] ?? ''))) {
                continue; // Skip empty segments
            }

            $flightNumber = strtoupper(trim($segmentData['flight_number']));
            $airlineName = trim($segmentData['airline_name'] ?? '');
            $departureAirport = strtoupper(trim($segmentData['departure_airport'] ?? ''));
            $arrivalAirport = strtoupper(trim($segmentData['arrival_airport'] ?? ''));
            $scheduledDeparture = trim($segmentData['scheduled_departure'] ?? '');
            $scheduledArrival = trim($segmentData['scheduled_arrival'] ?? '');
            $departureTimezone = trim($segmentData['departure_timezone'] ?? 'America/Chicago');
            $arrivalTimezone = trim($segmentData['arrival_timezone'] ?? 'America/Chicago');
            $radarboxId = trim($segmentData['radarbox_id'] ?? '');
            $flightawareUrl = trim($segmentData['flightaware_url'] ?? '');
            $sortOrder = (int) ($segmentData['sort_order'] ?? $index);

            // Validate required segment fields
            if (empty($departureAirport) || empty($arrivalAirport) ||
                empty($scheduledDeparture) || empty($scheduledArrival)) {
                continue; // Skip incomplete segments
            }

            // Validate FlightAware URL if provided
            if (!empty($flightawareUrl)) {
                $urlValidation = validateTrackingUrl($flightawareUrl);
                if (!$urlValidation['valid']) {
                    $flightawareUrl = ''; // Clear invalid URL
                }
            }

            // Sanitize RadarBox ID (numbers only)
            $radarboxId = preg_replace('/[^0-9]/', '', $radarboxId);

            // Insert segment
            $segmentId = dbInsert(
                "INSERT INTO flight_segments
                 (direction_id, flight_number, airline_name, departure_airport, arrival_airport,
                  scheduled_departure, scheduled_arrival, departure_timezone, arrival_timezone,
                  radarbox_id, flightaware_url, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $directionId, $flightNumber, $airlineName, $departureAirport, $arrivalAirport,
                    $scheduledDeparture, $scheduledArrival, $departureTimezone, $arrivalTimezone,
                    $radarboxId ?: null, $flightawareUrl ?: null, $sortOrder
                ]
            );

            if (!$segmentId) {
                throw new Exception("Failed to create segment: $flightNumber");
            }
        }
    }

    dbCommit();

    logSecurityEvent('TRIP_SAVED', "Trip ID: $tripId, Name: $name");

    jsonSuccess(['trip_id' => $tripId], 'Trip saved successfully');

} catch (Exception $e) {
    dbRollback();
    error_log("Save trip error: " . $e->getMessage());
    jsonError('Failed to save trip: ' . $e->getMessage(), 500);
}
