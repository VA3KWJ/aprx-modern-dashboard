<?php

function getStationMeta(array $config): array {
	$stationData   = parseAprxConfig($config['aprx_config_path']);
	$aprxver       = getAprxVersion();
	$uptime        = getUptime();
	$role          = getRole($stationData);
	$serverLat     = $stationData['myloc_lat'] ?? $config['latitude'];
	$serverLon     = $stationData['myloc_lon'] ?? $config['longitude'];
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

function getAprxServiceStatus(): string {
	$output = shell_exec('systemctl is-active aprx.service 2>/dev/null');
	return trim($output); // Will return: 'active', 'inactive', 'failed', etc.
}
function getCleanLines($filepath) {
	if (!file_exists($filepath)) return [];
	return array_values(array_filter(array_map(function($line) {
		$line = trim($line);
		return ($line === '' || str_starts_with($line, '#')) ? null : $line;
	}, file($filepath))));
}

function aprsCoordToDecimal($coord, $isLat = true) {
	preg_match('/(\d+)(\d\d\.\d+)([NSEW])/', $coord, $m);
	if (!$m) return null;

	$deg  = intval($isLat ? substr($m[1], 0, 2) : substr($m[1], 0, 3));
	$min  = floatval($m[2]);
	$hem  = $m[3];

	$decimal = $deg + ($min / 60);
	if (in_array($hem, ['S', 'W'])) $decimal *= -1;

	return $decimal;
}


function loadConfig() {
    $configFile = __DIR__ . '/config.php';
    return file_exists($configFile) ? include $configFile : [];
}

function getAprxVersion() {
    $output = shell_exec("aprx --v 2>&1 | grep version: | cut -d ':' -f 2");
    return $output ? trim($output) : 'Unknown';
}

function parseAprxConfig($configPath) {
	$data = [
		'callsign' => null,
		'interfaces' => [],
		'digipeat' => false,
		'igate' => false,
		'myloc_lat' => null,
		'myloc_lon' => null
	];

	$lines = getCleanLines($configPath);

	foreach ($lines as $line) {
		if (preg_match('/^mycall\s+(\S+)/i', $line, $m)) {
			$data['callsign'] = strtoupper($m[1]);
		} elseif (preg_match('/^interface\s+(\S+)/i', $line, $m)) {
			$data['interfaces'][] = $m[1];
		} elseif (preg_match('/myloc\s+lat\s+([0-9\.]+[NS])\s+lon\s+([0-9\.]+[EW])/i', $line, $m)) {
			$data['myloc_lat'] = aprsCoordToDecimal($m[1], true);
			$data['myloc_lon'] = aprsCoordToDecimal($m[2], false);
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
			$key = md5(strtoupper($m[3]) . trim($m[4]));
			$digipeatedKeys[$key] = true;
		}
	}

	foreach (array_reverse($lines) as $line) {
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+([RT])\s+([A-Z0-9\-]+)>/', $line, $m)) {
			$timestamp = strtotime($m[1]);
			$src       = strtoupper($m[2]);
			$dir       = $m[3];
			$fromCall  = strtoupper($m[4]);

			if ($minutes !== null && ($now - $timestamp) > ($minutes * 60)) continue;
			if ($dir !== 'R') continue;

			$type = ($src === 'APRSIS') ? 'APRS-IS' : 'RF';
			if ($sourceFilter && $type !== $sourceFilter) continue;

			$distance = null;
			if (preg_match('/([!:@])(?:\d{6}[hz])?([0-9]{4,5}\.\d{2}[NS])[^0-9A-Z]?([0-9]{5}\.\d{2}[EW])/', $line, $coord)) {
				$latStr = $coord[2];
				$lonStr = $coord[3];
				$latitude  = aprsCoordToDecimal($latStr, true);
				$longitude = aprsCoordToDecimal($lonStr, false);

				if ($latitude !== null && $longitude !== null && $serverLat !== null && $serverLon !== null) {
					$distance = calculateDistance($serverLat, $serverLon, $latitude, $longitude);
				}
			}

			$message = extractAprsMessage($line);
			$calls[] = [
				'callsign' => $fromCall,
				'source'   => $src,
				'time'     => date('Y-m-d H:i:s', $timestamp),
				'type'     => $type,
				'distance' => $distance,
				'message'  => $message,
			];
		}
	}

	uasort($calls, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
	return $calls;
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

function getOperatorNotice(string $path = 'operator_notice.txt'): ?string {
	if (file_exists($path)) {
		$content = trim(file_get_contents($path));
		return $content !== '' ? $content : null;
	}
	return null;
}
