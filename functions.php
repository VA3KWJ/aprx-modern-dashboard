<?php

function getAprxServiceStatus(): string {
	$output = shell_exec('systemctl is-active aprx.service 2>/dev/null');
	return trim($output); // Will return: 'active', 'inactive', 'failed', etc.
}

function getStationMeta(array $config): array {
	$stationData   = parseAprxConfig($config['aprx_config_path']);
	$aprxver       = getAprxVersion();
	$uptime        = getUptime();
	$role          = getRole($stationData);
	$serverLat     = $config['latitude'];
	$serverLon     = $config['longitude'];
	$locationLabel = reverseGeocode($serverLat, $serverLon);
	$aprxStatus    = getAprxServiceStatus();

	return [
		'aprxver'       => $aprxver,
		'uptime'        => $uptime,
		'role'          => $role,
		'stationData'   => $stationData,
		'serverLat'     => $serverLat,
		'serverLon'     => $serverLon,
		'locationLabel' => $locationLabel,
		'aprxStatus'    => $aprxStatus,
	];
}

function loadConfig() {
    $configFile = __DIR__ . '/config.php';
    return file_exists($configFile) ? include $configFile : [];
}

function getAprxVersion() {
    $output = shell_exec("aprx --v 2>&1 | grep version: | cut -d ':' -f 2");
    return $output ? trim($output) : 'Unknown';
}

function parseAprxConfig($path) {
    $data = [
        'callsign'   => null,
        'interfaces' => [],
        'igate'      => false,
        'digipeat'   => false,
    ];

    if (!file_exists($path)) return $data;

    $lines = file($path);
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^mycall\s+(\S+)/i', $line, $m)) {
            $data['callsign'] = strtoupper($m[1]);
        } elseif (preg_match('/^interface\s+(\S+)/i', $line, $m)) {
            $data['interfaces'][] = $m[1];
        } elseif (stripos($line, 'igate') !== false) {
            $data['igate'] = true;
        } elseif (stripos($line, 'digipeater') !== false) {
            $data['digipeat'] = true;
        }
    }

    return $data;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // kilometers
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $deltaLat = $lat2 - $lat1;
    $deltaLon = $lon2 - $lon1;

    $a = sin($deltaLat/2)**2 + cos($lat1) * cos($lat2) * sin($deltaLon/2)**2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadius * $c, 1);
}

function getRecentCalls($logPath, $minutes = null, $serverLat = null, $serverLon = null, $sourceFilter = '') {
    if (!file_exists($logPath)) return [];

    $lines = file($logPath);
    $now = time();
    $calls = [];

$digipeatedKeys = [];

foreach ($lines as $line) {
	if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+T\s+([A-Z0-9\-]+)>.*?:([^\r\n]+)/', $line, $m)) {
		$fromCall = strtoupper($m[3]);
		$payload  = trim($m[4]);
		$key = md5($fromCall . $payload);
		$digipeatedKeys[$key] = true;
		$timestamp = strtotime($m[1]);
		$src       = strtoupper($m[2]);
		$dir       = $m[3];
		$fromCall  = strtoupper($m[4]);
	}
}


    foreach (array_reverse($lines) as $line) {
        // Match timestamp, source (e.g. VA3KWJ-10), direction (R or T), and callsign
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+([RT])\s+([A-Z0-9\-]+)>/', $line, $m)) {
            $timestamp = strtotime($m[1]);
            $src       = strtoupper($m[2]);
            $dir       = $m[3];
            $fromCall  = strtoupper($m[4]);

            if ($minutes !== null && ($now - $timestamp) > ($minutes * 60)) continue;
            if ($dir !== 'R') continue; // Skip digipeated (T) packets

            $type = ($src === 'APRSIS') ? 'APRS-IS' : 'RF';

            if ($sourceFilter && $type !== $sourceFilter) continue;

            $distance = null;

            // Match position packets in !, @, or : format with optional timestamp
            if (preg_match('/([!:@])(?:\d{6}[hz])?(\d{2})(\d{2}\.\d{2})([NS])(.)(\d{3})(\d{2}\.\d{2})([EW])/', $line, $coord)) {
                // Latitude
                $latDeg = (int) $coord[2];
                $latMin = (float) $coord[3];
                $latHem = $coord[4];
                $latitude = $latDeg + ($latMin / 60);
                if ($latHem === 'S') $latitude *= -1;

                // Longitude
                $lonDeg = (int) $coord[6];
                $lonMin = (float) $coord[7];
                $lonHem = $coord[8];
                $longitude = $lonDeg + ($lonMin / 60);
                if ($lonHem === 'W') $longitude *= -1;

                // Compute distance
                if ($serverLat !== null && $serverLon !== null) {
                    $distance = calculateDistance($serverLat, $serverLon, $latitude, $longitude);
                }
            }

		$message = extractAprsMessage($line);
		$calls[] = [
		    'callsign' => $fromCall,
		    'source'   => $src,                  // your interface, e.g., VA3KWJ-10
		    'time'     => date('Y-m-d H:i:s', $timestamp),
		    //'type'     => $type,
		    'type'     => 'RF',
		    'distance' => $distance,
		    'message'  => $message,
		    //'message'  => trim($line),
		];

        }
    }

    uasort($calls, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
    return $calls;
}

function getRfInterfaces($configPath) {
	$interfaces = [];

	if (!file_exists($configPath)) return $interfaces;

	$data = file_get_contents($configPath);
	if (preg_match_all('/<interface>.*?callsign\s+(\S+)/is', $data, $matches)) {
		foreach ($matches[1] as $match) {
			$interfaces[] = strtoupper(trim($match));
		}
	}
	return $interfaces;
}

function getRole($configData) {
    if ($configData['igate'] && $configData['digipeat']) {
        return 'iGate & Digipeater';
    } elseif ($configData['igate']) {
        return 'iGate';
    } elseif ($configData['digipeat']) {
        return 'Digipeater';
    }
    return 'Unknown';
}

function getLocationLabel($lat, $lon) {
    return sprintf("%.5f, %.5f", $lat, $lon);
}
function getUptime() {
    $output = shell_exec("uptime -p 2>/dev/null");
    return $output ? trim($output) : 'Unknown';
}
function extractAprsMessage($line) {
    // Get AX.25 payload
    if (!preg_match('/:([^:]+)$/', $line, $m)) return null;
    $payload = trim($m[1]);

    // Remove known structured position/weather/telemetry prefixes
    // ! or = or @ = position
    // T# = telemetry
    // ; = object
    // ) = IRLP/WX
    if (preg_match('/^([!@=;T#])/', $payload)) {
        return null;
    }

    // Try extracting comment from:
    // >comment, =comment, }comment, etc.
    if (preg_match('/[>=]}([^\x00-\x1F\x7F]{6,})$/', $payload, $cm)) {
        return trim($cm[1]);
    }

    // Final fallback: try to find readable comment at the end
    if (preg_match('/([a-zA-Z0-9].{5,})$/', $payload, $cm)) {
        return trim($cm[1]);
    }

    return null;
}
function reverseGeocode($lat, $lon) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lon}&zoom=16&addressdetails=1";

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: APRX-Dashboard/1.0 (https://va3kwj.ca)\r\n"
        ]
    ];

    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) return "Unknown Location";

    $data = json_decode($json, true);
    if (!isset($data['address'])) return "Unknown Location";

    $addr = $data['address'];
    $city =
        $addr['neighbourhood'] ??
        $addr['suburb'] ??
        $addr['town'] ??
        $addr['city_district'] ??
        $addr['city'] ??
        $addr['village'] ??
        $addr['hamlet'] ??
        null;

    $state = $addr['state'] ?? $addr['province'] ?? null;

    return ($city && $state) ? "$city, $state" : "Unknown Location";
}
