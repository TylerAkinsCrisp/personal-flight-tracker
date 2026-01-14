<?php
/**
 * Utility Functions - FlightAware parser, data helpers
 */

if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Parse FlightAware URL to extract flight details
 * Example: https://www.flightaware.com/live/flight/AAL4046/history/20260116/1220Z/KXNA/KDFW
 * Example: https://www.flightaware.com/live/flight/AAL61/history/20260116/1655Z/KDFW/RJAA
 *
 * @param string $url FlightAware URL
 * @return array Parsed flight data or error
 */
function parseFlightAwareUrl($url) {
    $result = [
        'success' => false,
        'flight_number' => '',
        'airline_code' => '',
        'date' => '',
        'time_utc' => '',
        'departure_airport' => '',
        'arrival_airport' => '',
        'flightaware_url' => $url
    ];

    // Validate URL domain
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        $result['error'] = 'Invalid URL format';
        return $result;
    }

    $host = strtolower($parsed['host']);
    if (strpos($host, 'flightaware.com') === false) {
        $result['error'] = 'URL must be from flightaware.com';
        return $result;
    }

    // Extract path components
    $path = $parsed['path'] ?? '';

    // Pattern: /live/flight/AAL4046/history/20260116/1220Z/KXNA/KDFW
    // Airports can be 3-4 letter ICAO codes (KDFW, RJAA, etc.)
    $pattern = '#/live/flight/([A-Z]{2,3}\d+)(?:/history/(\d{8})/(\d{4})Z?/([A-Z]{3,4})/([A-Z]{3,4}))?#i';

    if (!preg_match($pattern, $path, $matches)) {
        // Try simpler pattern
        $simplePattern = '#/live/flight/([A-Z]{2,3}\d+)#i';
        if (preg_match($simplePattern, $path, $matches)) {
            $result['success'] = true;
            $result['flight_number'] = formatFlightNumber($matches[1]);
            $result['airline_code'] = extractAirlineCode($matches[1]);
            return $result;
        }

        $result['error'] = 'Could not parse flight information from URL';
        return $result;
    }

    $result['success'] = true;
    $result['flight_number'] = formatFlightNumber($matches[1]);
    $result['airline_code'] = extractAirlineCode($matches[1]);

    if (!empty($matches[2])) {
        // Parse date: 20260116 -> 2026-01-16
        $result['date'] = substr($matches[2], 0, 4) . '-' .
                          substr($matches[2], 4, 2) . '-' .
                          substr($matches[2], 6, 2);
    }

    if (!empty($matches[3])) {
        // Parse time: 1655 -> 16:55
        $result['time_utc'] = substr($matches[3], 0, 2) . ':' . substr($matches[3], 2, 2);
    }

    if (!empty($matches[4])) {
        // Convert ICAO to IATA code
        $result['departure_airport'] = icaoToIata(strtoupper($matches[4]));
    }

    if (!empty($matches[5])) {
        // Convert ICAO to IATA code
        $result['arrival_airport'] = icaoToIata(strtoupper($matches[5]));
    }

    return $result;
}

/**
 * Convert ICAO airport code to IATA code
 * US airports: KDFW -> DFW (strip K prefix)
 * International: RJAA -> NRT (lookup table)
 */
function icaoToIata($icao) {
    $icao = strtoupper($icao);

    // US airports start with K - just strip the prefix
    if (strlen($icao) === 4 && $icao[0] === 'K') {
        return substr($icao, 1);
    }

    // International ICAO to IATA mapping
    $icaoToIataMap = [
        // Japan
        'RJAA' => 'NRT',  // Tokyo Narita
        'RJTT' => 'HND',  // Tokyo Haneda
        'RJBB' => 'KIX',  // Osaka Kansai
        'RJOO' => 'ITM',  // Osaka Itami
        'RJGG' => 'NGO',  // Nagoya Chubu
        'RJFF' => 'FUK',  // Fukuoka
        'RJCC' => 'CTS',  // Sapporo New Chitose
        'ROAH' => 'OKA',  // Okinawa Naha
        // South Korea
        'RKSI' => 'ICN',  // Seoul Incheon
        'RKSS' => 'GMP',  // Seoul Gimpo
        'RKPK' => 'PUS',  // Busan
        // China
        'ZBAA' => 'PEK',  // Beijing Capital
        'ZBAD' => 'PKX',  // Beijing Daxing
        'ZSPD' => 'PVG',  // Shanghai Pudong
        'ZSSS' => 'SHA',  // Shanghai Hongqiao
        'ZGGG' => 'CAN',  // Guangzhou
        'VHHH' => 'HKG',  // Hong Kong
        'VMMC' => 'MFM',  // Macau
        // Taiwan
        'RCTP' => 'TPE',  // Taipei Taoyuan
        'RCSS' => 'TSA',  // Taipei Songshan
        // Southeast Asia
        'WSSS' => 'SIN',  // Singapore Changi
        'VTBS' => 'BKK',  // Bangkok Suvarnabhumi
        'VTBD' => 'DMK',  // Bangkok Don Mueang
        'VVTS' => 'SGN',  // Ho Chi Minh City
        'VVNB' => 'HAN',  // Hanoi
        'VVDN' => 'DAD',  // Da Nang
        'RPLL' => 'MNL',  // Manila
        'WMKK' => 'KUL',  // Kuala Lumpur
        'WIII' => 'CGK',  // Jakarta
        'WADD' => 'DPS',  // Bali Denpasar
        // Australia/Oceania
        'YSSY' => 'SYD',  // Sydney
        'YMML' => 'MEL',  // Melbourne
        'YBBN' => 'BNE',  // Brisbane
        'YPPH' => 'PER',  // Perth
        'NZAA' => 'AKL',  // Auckland
        // Europe
        'EGLL' => 'LHR',  // London Heathrow
        'EGKK' => 'LGW',  // London Gatwick
        'EGLC' => 'LCY',  // London City
        'LFPG' => 'CDG',  // Paris Charles de Gaulle
        'LFPO' => 'ORY',  // Paris Orly
        'EDDF' => 'FRA',  // Frankfurt
        'EDDM' => 'MUC',  // Munich
        'EHAM' => 'AMS',  // Amsterdam
        'LEMD' => 'MAD',  // Madrid
        'LEBL' => 'BCN',  // Barcelona
        'LIRF' => 'FCO',  // Rome Fiumicino
        // Middle East
        'OMDB' => 'DXB',  // Dubai
        'OMAA' => 'AUH',  // Abu Dhabi
        'OTHH' => 'DOH',  // Doha
        // Canada
        'CYYZ' => 'YYZ',  // Toronto Pearson
        'CYVR' => 'YVR',  // Vancouver
        'CYUL' => 'YUL',  // Montreal
        // Mexico
        'MMMX' => 'MEX',  // Mexico City
        'MMUN' => 'CUN',  // Cancun
    ];

    return $icaoToIataMap[$icao] ?? $icao;
}

/**
 * Format flight number (AAL4046 -> AA 4046)
 */
function formatFlightNumber($code) {
    // Extract letters and numbers
    if (preg_match('/^([A-Z]{2,3})(\d+)$/i', $code, $matches)) {
        $airline = strtoupper($matches[1]);
        $number = $matches[2];

        // Convert 3-letter ICAO to 2-letter IATA where known
        $icaoToIata = [
            'AAL' => 'AA',  // American Airlines
            'UAL' => 'UA',  // United Airlines
            'DAL' => 'DL',  // Delta Air Lines
            'SWA' => 'WN',  // Southwest Airlines
            'JBU' => 'B6',  // JetBlue
            'ASA' => 'AS',  // Alaska Airlines
            'NKS' => 'NK',  // Spirit Airlines
            'FFT' => 'F9',  // Frontier Airlines
            'SKW' => 'OO',  // SkyWest Airlines
            'ENY' => 'MQ',  // Envoy Air (American Eagle)
            'RPA' => 'YX',  // Republic Airways
            'ANA' => 'NH',  // All Nippon Airways
            'JAL' => 'JL',  // Japan Airlines
            'KAL' => 'KE',  // Korean Air
            'CPA' => 'CX',  // Cathay Pacific
            'SIA' => 'SQ',  // Singapore Airlines
            'VNA' => 'VN',  // Vietnam Airlines
            'EVA' => 'BR',  // EVA Air
            'CAL' => 'CI',  // China Airlines
        ];

        if (isset($icaoToIata[$airline])) {
            $airline = $icaoToIata[$airline];
        }

        return $airline . ' ' . $number;
    }

    return strtoupper($code);
}

/**
 * Extract airline code from flight code
 */
function extractAirlineCode($code) {
    if (preg_match('/^([A-Z]{2,3})/i', $code, $matches)) {
        return strtoupper($matches[1]);
    }
    return '';
}

/**
 * Get airline name from code
 */
function getAirlineName($code) {
    $airlines = [
        'AA' => 'American Airlines',
        'AAL' => 'American Airlines',
        'UA' => 'United Airlines',
        'UAL' => 'United Airlines',
        'DL' => 'Delta Air Lines',
        'DAL' => 'Delta Air Lines',
        'WN' => 'Southwest Airlines',
        'SWA' => 'Southwest Airlines',
        'B6' => 'JetBlue Airways',
        'JBU' => 'JetBlue Airways',
        'AS' => 'Alaska Airlines',
        'ASA' => 'Alaska Airlines',
        'NK' => 'Spirit Airlines',
        'NKS' => 'Spirit Airlines',
        'F9' => 'Frontier Airlines',
        'FFT' => 'Frontier Airlines',
        'NH' => 'All Nippon Airways',
        'ANA' => 'All Nippon Airways',
        'JL' => 'Japan Airlines',
        'JAL' => 'Japan Airlines',
        'KE' => 'Korean Air',
        'KAL' => 'Korean Air',
        'CX' => 'Cathay Pacific',
        'CPA' => 'Cathay Pacific',
        'SQ' => 'Singapore Airlines',
        'SIA' => 'Singapore Airlines',
        'VN' => 'Vietnam Airlines',
        'VNA' => 'Vietnam Airlines',
        'BR' => 'EVA Air',
        'EVA' => 'EVA Air',
        'CI' => 'China Airlines',
        'CAL' => 'China Airlines',
        'OZ' => 'Asiana Airlines',
        'TG' => 'Thai Airways',
        'MH' => 'Malaysia Airlines',
        'GA' => 'Garuda Indonesia',
        'PR' => 'Philippine Airlines',
    ];

    $code = strtoupper($code);
    return $airlines[$code] ?? '';
}

/**
 * Get airport name from code
 */
function getAirportName($code) {
    $airports = [
        'XNA' => 'Northwest Arkansas Regional',
        'DFW' => 'Dallas/Fort Worth International',
        'ORD' => 'Chicago O\'Hare International',
        'LAX' => 'Los Angeles International',
        'JFK' => 'John F. Kennedy International',
        'SFO' => 'San Francisco International',
        'SEA' => 'Seattle-Tacoma International',
        'MIA' => 'Miami International',
        'ATL' => 'Hartsfield-Jackson Atlanta',
        'DEN' => 'Denver International',
        'LAS' => 'Harry Reid International',
        'PHX' => 'Phoenix Sky Harbor',
        'IAH' => 'George Bush Intercontinental',
        'NRT' => 'Tokyo Narita International',
        'HND' => 'Tokyo Haneda',
        'ICN' => 'Incheon International',
        'HKG' => 'Hong Kong International',
        'SIN' => 'Singapore Changi',
        'BKK' => 'Suvarnabhumi',
        'SGN' => 'Tan Son Nhat International',
        'HAN' => 'Noi Bai International',
        'DAD' => 'Da Nang International',
        'TPE' => 'Taiwan Taoyuan International',
        'KIX' => 'Kansai International',
        'PVG' => 'Shanghai Pudong International',
        'PEK' => 'Beijing Capital International',
        'MNL' => 'Ninoy Aquino International',
        'KUL' => 'Kuala Lumpur International',
        'CGK' => 'Soekarno-Hatta International',
    ];

    $code = strtoupper($code);
    return $airports[$code] ?? $code;
}

/**
 * Get timezone for airport code
 */
function getAirportTimezone($code) {
    $timezones = [
        // US Airports
        'XNA' => 'America/Chicago',
        'DFW' => 'America/Chicago',
        'ORD' => 'America/Chicago',
        'IAH' => 'America/Chicago',
        'MSP' => 'America/Chicago',
        'STL' => 'America/Chicago',
        'MCI' => 'America/Chicago',
        'LAX' => 'America/Los_Angeles',
        'SFO' => 'America/Los_Angeles',
        'SEA' => 'America/Los_Angeles',
        'PDX' => 'America/Los_Angeles',
        'SAN' => 'America/Los_Angeles',
        'LAS' => 'America/Los_Angeles',
        'JFK' => 'America/New_York',
        'EWR' => 'America/New_York',
        'LGA' => 'America/New_York',
        'BOS' => 'America/New_York',
        'PHL' => 'America/New_York',
        'DCA' => 'America/New_York',
        'IAD' => 'America/New_York',
        'MIA' => 'America/New_York',
        'FLL' => 'America/New_York',
        'ATL' => 'America/New_York',
        'CLT' => 'America/New_York',
        'DTW' => 'America/New_York',
        'DEN' => 'America/Denver',
        'PHX' => 'America/Phoenix',
        'ANC' => 'America/Anchorage',
        'HNL' => 'Pacific/Honolulu',

        // Japan
        'NRT' => 'Asia/Tokyo',
        'HND' => 'Asia/Tokyo',
        'KIX' => 'Asia/Tokyo',
        'ITM' => 'Asia/Tokyo',
        'NGO' => 'Asia/Tokyo',
        'FUK' => 'Asia/Tokyo',
        'CTS' => 'Asia/Tokyo',
        'OKA' => 'Asia/Tokyo',

        // South Korea
        'ICN' => 'Asia/Seoul',
        'GMP' => 'Asia/Seoul',
        'PUS' => 'Asia/Seoul',

        // China
        'PEK' => 'Asia/Shanghai',
        'PKX' => 'Asia/Shanghai',
        'PVG' => 'Asia/Shanghai',
        'SHA' => 'Asia/Shanghai',
        'CAN' => 'Asia/Shanghai',
        'HKG' => 'Asia/Hong_Kong',
        'MFM' => 'Asia/Macau',

        // Taiwan
        'TPE' => 'Asia/Taipei',
        'TSA' => 'Asia/Taipei',

        // Southeast Asia
        'SIN' => 'Asia/Singapore',
        'BKK' => 'Asia/Bangkok',
        'DMK' => 'Asia/Bangkok',
        'SGN' => 'Asia/Ho_Chi_Minh',
        'HAN' => 'Asia/Ho_Chi_Minh',
        'DAD' => 'Asia/Ho_Chi_Minh',
        'MNL' => 'Asia/Manila',
        'KUL' => 'Asia/Kuala_Lumpur',
        'CGK' => 'Asia/Jakarta',
        'DPS' => 'Asia/Makassar',

        // Australia/Oceania
        'SYD' => 'Australia/Sydney',
        'MEL' => 'Australia/Melbourne',
        'BNE' => 'Australia/Brisbane',
        'PER' => 'Australia/Perth',
        'AKL' => 'Pacific/Auckland',

        // Europe
        'LHR' => 'Europe/London',
        'LGW' => 'Europe/London',
        'LCY' => 'Europe/London',
        'CDG' => 'Europe/Paris',
        'ORY' => 'Europe/Paris',
        'FRA' => 'Europe/Berlin',
        'MUC' => 'Europe/Berlin',
        'AMS' => 'Europe/Amsterdam',
        'MAD' => 'Europe/Madrid',
        'BCN' => 'Europe/Madrid',
        'FCO' => 'Europe/Rome',

        // Middle East
        'DXB' => 'Asia/Dubai',
        'AUH' => 'Asia/Dubai',
        'DOH' => 'Asia/Qatar',

        // Canada
        'YYZ' => 'America/Toronto',
        'YVR' => 'America/Vancouver',
        'YUL' => 'America/Montreal',

        // Mexico
        'MEX' => 'America/Mexico_City',
        'CUN' => 'America/Cancun',
    ];

    $code = strtoupper($code);
    return $timezones[$code] ?? 'America/Chicago'; // Default to Central Time
}

/**
 * Format datetime for display
 * Note: Times are stored in local timezone, not UTC. The timezone param is for reference only.
 */
function formatDateTime($datetime, $timezone = 'America/Chicago', $format = 'M j, Y g:i A') {
    try {
        // Times are stored as local time in the specified timezone
        // No conversion needed - just format the datetime as-is
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Get timezone abbreviation for display
 */
function getTimezoneAbbr($timezone) {
    try {
        $dt = new DateTime('now', new DateTimeZone($timezone));
        return $dt->format('T'); // Returns abbreviation like CST, JST, etc.
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Format time only for display
 */
function formatTime($datetime, $timezone = 'America/Chicago') {
    return formatDateTime($datetime, $timezone, 'g:i A');
}

/**
 * Format date only for display
 */
function formatDate($datetime, $timezone = 'America/Chicago') {
    return formatDateTime($datetime, $timezone, 'M j, Y');
}

/**
 * Calculate flight duration in minutes (simple, no timezone conversion)
 */
function calculateDuration($departure, $arrival) {
    try {
        $dep = new DateTime($departure);
        $arr = new DateTime($arrival);
        $diff = $arr->getTimestamp() - $dep->getTimestamp();
        return max(0, (int) ($diff / 60));
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Calculate flight duration in minutes with proper timezone handling
 * Converts both times to UTC before calculating difference
 */
function calculateFlightDuration($departure, $depTimezone, $arrival, $arrTimezone) {
    try {
        // Create DateTime objects in their respective local timezones
        $depTz = new DateTimeZone($depTimezone);
        $arrTz = new DateTimeZone($arrTimezone);

        $dep = new DateTime($departure, $depTz);
        $arr = new DateTime($arrival, $arrTz);

        // Calculate difference in seconds
        $diff = $arr->getTimestamp() - $dep->getTimestamp();

        // Return minutes, ensuring non-negative
        return max(0, (int) ($diff / 60));
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get country flag emoji from country name
 */
function getCountryFlag($country) {
    $flags = [
        'Vietnam' => '🇻🇳',
        'Japan' => '🇯🇵',
        'South Korea' => '🇰🇷',
        'Korea' => '🇰🇷',
        'China' => '🇨🇳',
        'Taiwan' => '🇹🇼',
        'Hong Kong' => '🇭🇰',
        'Thailand' => '🇹🇭',
        'Singapore' => '🇸🇬',
        'Malaysia' => '🇲🇾',
        'Indonesia' => '🇮🇩',
        'Philippines' => '🇵🇭',
        'Australia' => '🇦🇺',
        'New Zealand' => '🇳🇿',
        'United Kingdom' => '🇬🇧',
        'UK' => '🇬🇧',
        'England' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
        'France' => '🇫🇷',
        'Germany' => '🇩🇪',
        'Italy' => '🇮🇹',
        'Spain' => '🇪🇸',
        'Netherlands' => '🇳🇱',
        'Portugal' => '🇵🇹',
        'Greece' => '🇬🇷',
        'Switzerland' => '🇨🇭',
        'Austria' => '🇦🇹',
        'Ireland' => '🇮🇪',
        'Scotland' => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
        'Canada' => '🇨🇦',
        'Mexico' => '🇲🇽',
        'Brazil' => '🇧🇷',
        'Argentina' => '🇦🇷',
        'Chile' => '🇨🇱',
        'Colombia' => '🇨🇴',
        'Peru' => '🇵🇪',
        'Costa Rica' => '🇨🇷',
        'United Arab Emirates' => '🇦🇪',
        'UAE' => '🇦🇪',
        'Dubai' => '🇦🇪',
        'Qatar' => '🇶🇦',
        'Saudi Arabia' => '🇸🇦',
        'Israel' => '🇮🇱',
        'Egypt' => '🇪🇬',
        'Morocco' => '🇲🇦',
        'South Africa' => '🇿🇦',
        'India' => '🇮🇳',
        'Cambodia' => '🇰🇭',
        'Laos' => '🇱🇦',
        'Myanmar' => '🇲🇲',
        'Nepal' => '🇳🇵',
        'Sri Lanka' => '🇱🇰',
        'Maldives' => '🇲🇻',
        'Iceland' => '🇮🇸',
        'Norway' => '🇳🇴',
        'Sweden' => '🇸🇪',
        'Finland' => '🇫🇮',
        'Denmark' => '🇩🇰',
        'Belgium' => '🇧🇪',
        'Czech Republic' => '🇨🇿',
        'Czechia' => '🇨🇿',
        'Poland' => '🇵🇱',
        'Hungary' => '🇭🇺',
        'Croatia' => '🇭🇷',
        'Turkey' => '🇹🇷',
        'Russia' => '🇷🇺',
        'USA' => '🇺🇸',
        'United States' => '🇺🇸',
    ];

    return $flags[$country] ?? '✈️';
}

/**
 * Format duration as human readable
 */
function formatDuration($minutes) {
    if ($minutes <= 0) {
        return '—';
    }

    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    if ($hours > 0) {
        return $hours . 'h ' . $mins . 'm';
    }
    return $mins . 'm';
}

/**
 * Generate RadarBox iframe HTML
 */
function renderRadarBoxEmbed($radarboxId, $width = '100%', $height = 400) {
    if (empty($radarboxId)) {
        return '';
    }

    $radarboxId = preg_replace('/[^0-9]/', '', $radarboxId);

    return sprintf(
        '<div class="radarbox-embed mb-3">
            <iframe src="https://www.airnavradar.com/?widget=1&z=7&fid=%s"
                    width="%s" height="%d"
                    frameborder="0" scrolling="no"
                    marginheight="0" marginwidth="0"
                    loading="lazy"
                    title="Live Flight Tracking"></iframe>
        </div>',
        e($radarboxId),
        $width,
        $height
    );
}

/**
 * Generate FlightAware tracking button
 */
function renderTrackLiveButton($flightawareUrl, $flightNumber = '') {
    if (empty($flightawareUrl) && empty($flightNumber)) {
        return '';
    }

    $url = $flightawareUrl;
    if (empty($url) && !empty($flightNumber)) {
        $cleanNumber = str_replace(' ', '', $flightNumber);
        $url = 'https://flightaware.com/live/flight/' . $cleanNumber;
    }

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="btn track-live-btn text-white">
            <i class="bi bi-broadcast"></i> Track Live on FlightAware
        </a>',
        e($url)
    );
}

/**
 * Get current/upcoming trip
 */
function getCurrentTrip() {
    return dbQueryOne("
        SELECT * FROM trips
        WHERE end_date >= CURDATE()
        ORDER BY start_date ASC
        LIMIT 1
    ");
}

/**
 * Get past trips
 */
function getPastTrips($limit = 10) {
    return dbQuery("
        SELECT * FROM trips
        WHERE end_date < CURDATE()
        ORDER BY end_date DESC
        LIMIT ?
    ", [$limit]);
}

/**
 * Get trip with all directions and segments
 */
function getTripWithSegments($tripId) {
    $trip = dbQueryOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
    if (!$trip) {
        return null;
    }

    $trip['directions'] = [];

    $directions = dbQuery("
        SELECT * FROM trip_directions
        WHERE trip_id = ?
        ORDER BY sort_order, direction
    ", [$tripId]);

    foreach ($directions as $direction) {
        $direction['segments'] = dbQuery("
            SELECT * FROM flight_segments
            WHERE direction_id = ?
            ORDER BY sort_order, scheduled_departure
        ", [$direction['id']]);

        $trip['directions'][$direction['direction']] = $direction;
    }

    return $trip;
}

/**
 * Get all trips with directions and segments
 */
function getAllTripsWithSegments() {
    $trips = dbQuery("SELECT * FROM trips ORDER BY start_date DESC");

    foreach ($trips as &$trip) {
        $trip['directions'] = [];

        $directions = dbQuery("
            SELECT * FROM trip_directions
            WHERE trip_id = ?
            ORDER BY sort_order, direction
        ", [$trip['id']]);

        foreach ($directions as $direction) {
            $direction['segments'] = dbQuery("
                SELECT * FROM flight_segments
                WHERE direction_id = ?
                ORDER BY sort_order, scheduled_departure
            ", [$direction['id']]);

            $trip['directions'][$direction['direction']] = $direction;
        }
    }

    return $trips;
}

/**
 * Count total segments in a trip
 */
function countTripSegments($trip) {
    $count = 0;
    if (isset($trip['directions'])) {
        foreach ($trip['directions'] as $direction) {
            if (isset($direction['segments'])) {
                $count += count($direction['segments']);
            }
        }
    }
    return $count;
}

/**
 * Get timezone select options HTML
 */
function getTimezoneOptionsHtml($selected = 'America/Chicago') {
    $html = '<option value="">Select timezone...</option>';

    foreach (TIMEZONE_OPTIONS as $group => $timezones) {
        $html .= '<optgroup label="' . e($group) . '">';
        foreach ($timezones as $tz => $label) {
            $isSelected = ($tz === $selected) ? ' selected' : '';
            $html .= '<option value="' . e($tz) . '"' . $isSelected . '>' . e($label) . '</option>';
        }
        $html .= '</optgroup>';
    }

    return $html;
}
