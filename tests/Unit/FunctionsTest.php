<?php

use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase {
    public function testParseFlightAwareUrlWithHistoryPath(): void {
        $result = parseFlightAwareUrl('https://www.flightaware.com/live/flight/AAL4046/history/20260116/1220Z/KXNA/KDFW');

        $this->assertTrue($result['success']);
        $this->assertSame('AA 4046', $result['flight_number']);
        $this->assertSame('AAL', $result['airline_code']);
        $this->assertSame('2026-01-16', $result['date']);
        $this->assertSame('12:20', $result['time_utc']);
        $this->assertSame('XNA', $result['departure_airport']);
        $this->assertSame('DFW', $result['arrival_airport']);
    }

    public function testParseFlightAwareUrlSimplePath(): void {
        $result = parseFlightAwareUrl('https://www.flightaware.com/live/flight/UAL1234');

        $this->assertTrue($result['success']);
        $this->assertSame('UA 1234', $result['flight_number']);
        $this->assertSame('UAL', $result['airline_code']);
    }

    public function testParseFlightAwareUrlRejectsWrongDomain(): void {
        $result = parseFlightAwareUrl('https://example.com/live/flight/AAL4046');

        $this->assertFalse($result['success']);
        $this->assertSame('URL must be from flightaware.com', $result['error']);
    }

    public function testIcaoToIataConvertsUsAndMappedInternationalCodes(): void {
        $this->assertSame('DFW', icaoToIata('KDFW'));
        $this->assertSame('NRT', icaoToIata('RJAA'));
    }

    public function testFormatFlightNumberNormalizesIcaoAirlineCode(): void {
        $this->assertSame('AA 4046', formatFlightNumber('AAL4046'));
        $this->assertSame('BA 12', formatFlightNumber('BA12'));
    }

    public function testCalculateDurationHandlesPositiveAndNegativeRanges(): void {
        $this->assertSame(90, calculateDuration('2026-01-01 10:00:00', '2026-01-01 11:30:00'));
        $this->assertSame(0, calculateDuration('2026-01-01 11:30:00', '2026-01-01 10:00:00'));
    }

    public function testCalculateFlightDurationHandlesTimezoneDifference(): void {
        $minutes = calculateFlightDuration(
            '2026-01-16 10:00:00',
            'America/Chicago',
            '2026-01-16 14:00:00',
            'America/New_York'
        );

        $this->assertSame(180, $minutes);
    }

    public function testFormatDurationCoversEdgeCases(): void {
        $this->assertSame('—', formatDuration(0));
        $this->assertSame('45m', formatDuration(45));
        $this->assertSame('2h 5m', formatDuration(125));
    }
}

