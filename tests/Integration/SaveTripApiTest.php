<?php

final class SaveTripApiTest extends DatabaseTestCase {
    public function testSaveTripApiCreatesTripAndSegmentWithoutRadarboxDependency(): void {
        $payload = [
            'name' => 'Integration Test Trip',
            'destination_city' => 'Osaka',
            'destination_country' => 'Japan',
            'start_date' => '2030-03-01',
            'end_date' => '2030-03-07',
            'status' => 'planned',
            'segments' => [
                'departure' => [
                    [
                        'flight_number' => 'AA 4046',
                        'airline_name' => 'American Airlines',
                        'departure_airport' => 'XNA',
                        'arrival_airport' => 'DFW',
                        'scheduled_departure' => '2030-03-01 09:00:00',
                        'scheduled_arrival' => '2030-03-01 10:30:00',
                        'departure_timezone' => 'America/Chicago',
                        'arrival_timezone' => 'America/Chicago',
                        'flightaware_url' => 'https://www.flightaware.com/live/flight/AAL4046',
                        'sort_order' => 0,
                    ],
                ],
            ],
        ];

        $response = $this->runPhpScriptWithJsonInput(
            dirname(__DIR__) . '/Helpers/run_save_trip_api.php',
            $payload
        );

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('trip_id', $response);
        $tripId = (int) $response['trip_id'];
        $this->assertGreaterThan(0, $tripId);

        $segment = $this->pdo->query(
            "SELECT flight_number, flightaware_url, sort_order FROM flight_segments LIMIT 1"
        )->fetch();

        $this->assertNotFalse($segment);
        $this->assertSame('AA 4046', $segment['flight_number']);
        $this->assertSame('https://www.flightaware.com/live/flight/AAL4046', $segment['flightaware_url']);
        $this->assertSame(0, (int) $segment['sort_order']);
    }
}

