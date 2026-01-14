<?php
/**
 * Track Tyler - Admin Logout
 */

require_once __DIR__ . '/../config/database.php';

// Initialize and destroy session
initSecureSession();

logSecurityEvent('ADMIN_LOGOUT', 'Admin logged out');

destroySession();

// Redirect to login
header('Location: login.php');
exit;
