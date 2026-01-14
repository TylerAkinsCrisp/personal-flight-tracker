<?php
/**
 * Track Tyler - Delete Trip API
 * Deletes a trip and all associated directions/segments
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

// Get trip ID
$tripId = !empty($_POST['trip_id']) ? (int) $_POST['trip_id'] : null;

if (!$tripId) {
    jsonError('Trip ID is required');
}

// Verify trip exists
$trip = dbQueryOne("SELECT id, name FROM trips WHERE id = ?", [$tripId]);

if (!$trip) {
    jsonError('Trip not found', 404);
}

try {
    // Delete trip (cascade will handle directions and segments)
    $result = dbExecute("DELETE FROM trips WHERE id = ?", [$tripId]);

    if ($result === false) {
        throw new Exception('Failed to delete trip');
    }

    logSecurityEvent('TRIP_DELETED', "Trip ID: $tripId, Name: {$trip['name']}");

    jsonSuccess([], 'Trip deleted successfully');

} catch (Exception $e) {
    error_log("Delete trip error: " . $e->getMessage());
    jsonError('Failed to delete trip', 500);
}
