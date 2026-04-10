<?php

final class TripsApiContractTest extends DatabaseTestCase {
    public function testTripsApiReturnsTrackingUrlFallbackAndFlags(): void {
        $tripId = $this->seedTripWithSegments();

        $response = $this->runPhpScriptWithJsonInput(
            dirname(__DIR__) . '/Helpers/run_trips_api.php',
            ['get' => ['id' => (string) $tripId]]
        );

        $this->assertTrue($response['success']);
        $trip = $response['trip'];
        $segments = $trip['directions']['departure']['segments'];
        $this->assertCount(2, $segments);

        $withCustomUrl = $segments[0];
        $withFallback = $segments[1];

        $this->assertSame('https://www.flightaware.com/live/flight/AA100', $withCustomUrl['tracking_url']);
        $this->assertTrue($withCustomUrl['has_tracking_link']);
        $this->assertArrayHasKey('tracking_url', $withCustomUrl);
        $this->assertArrayHasKey('has_tracking_link', $withCustomUrl);

        $this->assertSame('https://flightaware.com/live/flight/UA250', $withFallback['tracking_url']);
        $this->assertTrue($withFallback['has_tracking_link']);
    }

    private function seedTripWithSegments(): int {
        $this->pdo->exec("INSERT INTO trips (name, destination_city, destination_country, start_date, end_date, status)
            VALUES ('API Contract Trip', 'Tokyo', 'Japan', '2030-01-01', '2030-01-10', 'active')");
        $tripId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO trip_directions (trip_id, direction, sort_order)
            VALUES ({$tripId}, 'departure', 0)");
        $directionId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO flight_segments
            (direction_id, flight_number, airline_name, departure_airport, arrival_airport,
             scheduled_departure, scheduled_arrival, departure_timezone, arrival_timezone,
             flightaware_url, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $directionId, 'AA 100', 'American Airlines', 'DFW', 'NRT',
            '2030-01-01 10:00:00', '2030-01-02 13:00:00', 'America/Chicago', 'Asia/Tokyo',
            'https://www.flightaware.com/live/flight/AA100', 0
        ]);

        $stmt->execute([
            $directionId, 'UA 250', 'United Airlines', 'ORD', 'LAX',
            '2030-01-01 08:00:00', '2030-01-01 10:30:00', 'America/Chicago', 'America/Los_Angeles',
            null, 1
        ]);

        return $tripId;
    }
}

