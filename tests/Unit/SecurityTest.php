<?php

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {
    protected function tearDown(): void {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testValidateTrackingUrlAllowsFlightAwareDomain(): void {
        $result = validateTrackingUrl('https://www.flightaware.com/live/flight/AAL4046');

        $this->assertTrue($result['valid']);
        $this->assertSame('https://www.flightaware.com/live/flight/AAL4046', $result['url']);
    }

    public function testValidateTrackingUrlRejectsUnlistedDomain(): void {
        $result = validateTrackingUrl('https://example.com/track/123');

        $this->assertFalse($result['valid']);
        $this->assertSame('URL domain not in whitelist', $result['error']);
    }

    public function testGetClientIpUsesRemoteAddressWhenForwardedHeaderMissing(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        $this->assertSame('203.0.113.10', getClientIp());
    }

    public function testGetClientIpUsesFirstForwardedAddress(): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20, 198.51.100.21';

        $this->assertSame('198.51.100.20', getClientIp());
    }

    public function testGetClientIpFallsBackForInvalidInput(): void {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';

        $this->assertSame('0.0.0.0', getClientIp());
    }

    public function testCsrfTokenLifecycle(): void {
        $_SESSION['csrf_token'] = 'known-token';

        $this->assertTrue(validateCsrfToken('known-token'));
        $this->assertFalse(validateCsrfToken('invalid-token'));
    }
}

