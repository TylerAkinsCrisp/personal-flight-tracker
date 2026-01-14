<?php
/**
 * Track Tyler - Parse FlightAware URL API
 * Extracts flight details from a FlightAware URL
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Initialize session and require admin
initSecureSession();
requireAdmin('../login.php');

// Accept both GET and POST
$url = $_POST['url'] ?? $_GET['url'] ?? '';

if (empty($url)) {
    jsonError('URL is required');
}

// Parse the FlightAware URL
$result = parseFlightAwareUrl($url);

if (!$result['success']) {
    jsonError($result['error'] ?? 'Failed to parse URL');
}

// Try to get airline name from code
if (!empty($result['airline_code'])) {
    $result['airline_name'] = getAirlineName($result['airline_code']);
}

// Get airport names and timezones
if (!empty($result['departure_airport'])) {
    $result['departure_airport_name'] = getAirportName($result['departure_airport']);
    $result['departure_timezone'] = getAirportTimezone($result['departure_airport']);
}
if (!empty($result['arrival_airport'])) {
    $result['arrival_airport_name'] = getAirportName($result['arrival_airport']);
    $result['arrival_timezone'] = getAirportTimezone($result['arrival_airport']);
}

// If we have date and time_utc, convert to local departure time
if (!empty($result['date']) && !empty($result['time_utc'])) {
    $depTz = $result['departure_timezone'] ?? 'America/Chicago';
    try {
        // Parse UTC datetime
        $utcDatetime = $result['date'] . ' ' . $result['time_utc'] . ':00';
        $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));

        // Convert to departure airport's local time
        $dt->setTimezone(new DateTimeZone($depTz));

        // Format for datetime-local input (YYYY-MM-DDTHH:MM)
        $result['scheduled_departure_local'] = $dt->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        // If conversion fails, just use the UTC time
        $result['scheduled_departure_local'] = $result['date'] . 'T' . $result['time_utc'];
    }
}

jsonSuccess($result, 'URL parsed successfully');
