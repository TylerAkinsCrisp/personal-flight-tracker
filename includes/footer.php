<?php
/**
 * Track Tyler - Common Footer
 * Include Bootstrap JS and common scripts
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'footer.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}
?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (isset($additionalScripts)): ?>
    <?php echo $additionalScripts; ?>
    <?php endif; ?>
</body>
</html>
