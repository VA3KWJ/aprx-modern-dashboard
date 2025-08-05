<?php

function getStationMeta(array $config): array {
	$stationData   = parseAprxConfig($config['aprx_config_path']);
	$aprxver       = getAprxVersion();
	$uptime        = getUptime();
	$role          = getRole($stationData);
	$serverLat = $stationData['myloc_lat'] ?? ($config['latitude'] ?? 0);
	$serverLon = $stationData['myloc_lon'] ?? ($config['longitude'] ?? 0);
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
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#')) continue; // Ignore blank and comment lines

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
            "header" => "User-Agent: APRX-Dashboard/1.0 (https://github.com/VA3KWJ/aprx-modern-dashboard)\r\n"
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

	$lines = file($configPath);
	$inInterfaceBlock = false;

	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#')) continue;

		if (preg_match('/^<interface>/i', $line)) {
			$inInterfaceBlock = true;
			continue;
		}

		if ($inInterfaceBlock && preg_match('/^<\/interface>/i', $line)) {
			$inInterfaceBlock = false;
			continue;
		}

		if ($inInterfaceBlock && preg_match('/^callsign\s+(\S+)/i', $line, $m)) {
			$interfaces[] = strtoupper(trim($m[1]));
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
function loadDashboardData(array $config, array $params): array {
	session_start();

	$meta = getStationMeta($config);
	$operatorNotice = getOperatorNotice();

	$selectedInterface = $params['interface'] ?? '';
	$source = $params['source'] ?? '';
	$filter = $params['filter'] ?? '1h';

	$minutes = match($filter) {
		'1h' => 60,
		'2h' => 120,
		'4h' => 240,
		'6h' => 360,
		'24h' => 1440,
		'7d' => 10080,
		'all' => null,
		default => 60,
	};

	$recentCalls = getRecentCalls(
		$config['aprx_log_path'],
		$minutes,
		$meta['serverLat'],
		$meta['serverLon'],
		$source,
		$selectedInterface
	);

	if (!empty($selectedInterface)) {
		$recentCalls = array_filter($recentCalls, function ($info) use ($selectedInterface) {
			return strtoupper($info['source'] ?? '') === strtoupper($selectedInterface);
		});
	}

	$rfInterfaces = getRfInterfaces($config['aprx_config_path']);

	foreach ($recentCalls as &$info) {
		$iface = strtoupper($info['source'] ?? '');
		$info['type'] = in_array($iface, $rfInterfaces) ? "RF: $iface" : "APRS-IS";
	}
	unset($info);

	$callsigns = array_column($recentCalls, 'callsign');
	$unique = array_unique($callsigns);
	$totalCount = count($unique);

	$rfCount = count(array_unique(array_column(
		array_filter($recentCalls, fn($e) => str_starts_with($e['type'], 'RF:')),
		'callsign'
	)));

	$aprsisCount = count(array_unique(array_column(
		array_filter($recentCalls, fn($e) => $e['type'] === 'APRS-IS'),
		'callsign'
	)));

	return [
		'meta' => $meta,
		'operatorNotice' => $operatorNotice,
		'recentCalls' => $recentCalls,
		'totalCount' => $totalCount,
		'rfCount' => $rfCount,
		'aprsisCount' => $aprsisCount,
		'selectedInterface' => $selectedInterface,
		'filter' => $filter,
		'source' => $source,
		'rfInterfaces' => $rfInterfaces
	];
}
function generateStats(array $config, string $selectedRange = '7d'): array {
	$interfaces = getRfInterfaces($config['aprx_config_path']);
	$logPath = $config['aprx_log_path'];

	$ranges = [
		'1h' => ['label' => '1 Hour', 'cut' => '-1 hour'],
		'2h' => ['label' => '2 Hours', 'cut' => '-2 hours'],
		'6h' => ['label' => '6 Hours', 'cut' => '-6 hours'],
		'12h' => ['label' => '12 Hours', 'cut' => '-12 hours'],
		'1d' => ['label' => '1 Day', 'cut' => '-1 day'],
		'7d' => ['label' => '7 Days', 'cut' => '-7 days'],
		'14d' => ['label' => '14 Days', 'cut' => '-14 days'],
		'30d' => ['label' => '30 Days', 'cut' => '-30 days']
	];


	$selectedRange = $_GET['range'] ?? '7d';
	$cutoff = strtotime($ranges[$selectedRange]['cut'] ?? '-7 days');
	$useHourly = in_array($selectedRange, ['1h', '2h', '6h', '12h', '1d']);

	$lines = file_exists($logPath) ? file($logPath) : [];
	$stats = [];
	$allBuckets = [];

	$now = time();
	$step = $useHourly ? 3600 : 86400;

	for ($t = $cutoff; $t <= $now; $t += $step) {
		$bucket = $useHourly ? date('Y-m-d H:00', $t) : date('Y-m-d', $t);
		$allBuckets[$bucket] = true;
	}

	foreach ($lines as $line) {
		if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+([RT])\s+/', $line, $m)) {
			$date = $m[1];
			$time = $m[2];
			$iface = $m[3];
			$dir = $m[4];
			$ts = strtotime("$date $time");

			if (!in_array($iface, $interfaces)) continue;
			if ($ts < $cutoff) continue;

			$bucket = $useHourly ? date('Y-m-d H:00', $ts) : $date;
			$allBuckets[$bucket] = true;

			if (!isset($stats[$iface][$bucket])) {
				$stats[$iface][$bucket] = ['rx' => 0, 'tx' => 0];
			}
			if ($dir === 'R') $stats[$iface][$bucket]['rx']++;
			if ($dir === 'T') $stats[$iface][$bucket]['tx']++;
		}
	}

	$bucketList = array_keys($allBuckets);
	sort($bucketList);

	return [
		'stats' => $stats,
		'buckets' => $bucketList,
		'useHourly' => $useHourly,
		'selectedRange' => $selectedRange,
		'interfaces' => $interfaces,
		'ranges' => $ranges
	];
}
