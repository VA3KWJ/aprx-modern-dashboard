<?php

/**
 * Build station meta derived from aprx.conf, system status, and fallbacks.
 * Prefers coordinates from aprx.conf (myloc) and falls back to config.php.
 * @param array $config
 * @return array{
 *     aprxver:string,uptime:string,role:string,stationData:array,
 *     serverLat:float|null,serverLon:float|null,locationLabel:string,aprxStatus:string
 * }
 */
function getStationMeta(array $config): array {
	$stationData   = parseAprxConfig($config['aprx_config_path']);
	$aprxver       = getAprxVersion();
	$uptime        = getUptime();
	$role          = getRole($stationData);
	// Prefer aprx.conf myloc; fall back to config.php, otherwise null (avoid Null Island)
	$serverLat = $stationData['myloc_lat'] ?? ($config['latitude'] ?? null);
	$serverLon = $stationData['myloc_lon'] ?? ($config['longitude'] ?? null);

	// Reverse geocode only when we have valid coordinates
	$locationLabel = ($serverLat !== null && $serverLon !== null)
	? reverseGeocode($serverLat, $serverLon)
	: 'Unknown Location';

	// Note: the next line overrides the guarded result above. If intentional, keep;
	// otherwise remove this second call to preserve the null-safe fallback.
	//$locationLabel = reverseGeocode($serverLat, $serverLon);

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

/**
 * Return current aprx.service state from systemd.
 * @return string e.g., 'active', 'inactive', 'failed'
 */
function getAprxServiceStatus(): string {
	$output = shell_exec('systemctl is-active aprx.service 2>/dev/null');
	return trim($output); // Will return: 'active', 'inactive', 'failed', etc.
}

/**
 * Read a config file and return non-empty, non-comment lines (comments start with '#').
 * @param string $filepath
 * @return array<int,string>
 */
function getCleanLines($filepath) {
	if (!file_exists($filepath)) return [];
	return array_values(array_filter(array_map(function($line) {
		$line = trim($line);
		return ($line === '' || str_starts_with($line, '#')) ? null : $line;
	}, file($filepath))));
}

/**
 * Convert APRS DDMM.mmH / DDDMM.mmH formatted coordinate to decimal degrees.
 * @param string $coord Coordinate string, e.g., "4351.23N" or "07932.47W"
 * @param bool $isLat True for latitude parsing (2-degree digits), false for longitude (3-degree digits)
 * @return float|null Decimal degrees or null if not matched
 */
function aprsCoordToDecimal($coord, $isLat = true) {
	preg_match('/(\d+)(\d\d\.\d+)([NSEW])/', $coord, $m);
	if (!$m) return null;

	// Extract degrees, minutes, and hemisphere
	$deg  = intval($isLat ? substr($m[1], 0, 2) : substr($m[1], 0, 3));
	$min  = floatval($m[2]);
	$hem  = $m[3];

	$decimal = $deg + ($min / 60);
	if (in_array($hem, ['S', 'W'])) $decimal *= -1;

	return $decimal;
}

/**
 * Load local config.php if present; return empty array otherwise.
 * @return array
 */
function loadConfig() {
	$configFile = __DIR__ . '/config.php';
	// Load config if present, otherwise return empty array
	return file_exists($configFile) ? include $configFile : [];
}


/**
 * Get APRX version string via cli; returns 'Unknown' if unavailable.
 * @return string
 */
function getAprxVersion() {
	$output = shell_exec("aprx --v 2>&1 | grep version: | cut -d ':' -f 2");
	return $output ? trim($output) : 'Unknown';
}

/**
 * Parse /etc/aprx.conf (or provided path) extracting callsign, roles, interfaces, and myloc.
 * Comments are ignored. Role detection prefers an enabled <digipeater> block.
 * @param string $configPath
 * @return array{
 *   callsign:?string,interfaces:array<int,string>,digipeat:bool,igate:bool,
 *   myloc_lat:?float,myloc_lon:?float
 * }
 */
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

	// Detect digipeater role strictly by presence of an enabled <digipeater> block.
	// If absent, default role to iGate.
	$data['digipeat'] = hasEnabledBlock($configPath, 'digipeater');
	$data['igate']    = !$data['digipeat'];

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
		/**
		 * Parse station location:
		 *   myloc lat 4351.23N lon 07932.47W
		 *   myloc 4351.23N 07932.47W
		 *   myloc 43.85383 -79.54117
		 */
		} elseif (preg_match('/^myloc\s+lat\s+([0-9]{4,5}\.\d{2}[NS])\s+lon\s+([0-9]{5}\.\d{2}[EW])/i', $line, $m)) {
			$latDec = aprsCoordToDecimal($m[1], true);
			$lonDec = aprsCoordToDecimal($m[2], false);
			if ($latDec !== null && $lonDec !== null) {
				$data['myloc_lat'] = $latDec;
				$data['myloc_lon'] = $lonDec;
			}
		} elseif (preg_match('/^myloc\s+([0-9]{4,5}\.\d{2}[NS])\s+([0-9]{5}\.\d{2}[EW])/i', $line, $m)) {
			$latDec = aprsCoordToDecimal($m[1], true);
			$lonDec = aprsCoordToDecimal($m[2], false);
			if ($latDec !== null && $lonDec !== null) {
				$data['myloc_lat'] = $latDec;
				$data['myloc_lon'] = $lonDec;
			}
		} elseif (preg_match('/^myloc\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/i', $line, $m)) {
			$data['myloc_lat'] = (float)$m[1];
			$data['myloc_lon'] = (float)$m[2];
		}
	}
	return $data;
}

/**
 * Detects whether an uncommented <tagName>...</tagName> block exists.
 * Comments are already stripped by getCleanLines(), so we only need to
 * look for exact block delimiters in the cleaned lines.
 */
function hasEnabledBlock(string $configPath, string $tagName): bool {
	$lines = getCleanLines($configPath);
	$open  = sprintf('<%s>', strtolower($tagName));
	$close = sprintf('</%s>', strtolower($tagName));

	$inBlock = false;
	foreach ($lines as $line) {
		$l = strtolower(trim($line));
		if ($l === $open) {
			$inBlock = true;
			continue;
		}
		if ($inBlock && $l === $close) {
			return true; // found a complete, enabled block
		}
	}
	return false;
}

/**
 * Haversine distance in kilometers between two lat/lon points, rounded to 0.1 km.
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @return float
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
	 $earthRadius = 6371; // kilometers
	// COnvert degrees to radians
	$lat1 = deg2rad($lat1);
	$lon1 = deg2rad($lon1);
	$lat2 = deg2rad($lat2);
	$lon2 = deg2rad($lon2);

	// Haversine formula
	$deltaLat = $lat2 - $lat1;
	$deltaLon = $lon2 - $lon1;

	$a = sin($deltaLat/2)**2 + cos($lat1) * cos($lat2) * sin($deltaLon/2)**2;
	$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

	return round($earthRadius * $c, 1);
}

/**
 * Parse aprx-rf.log style lines for recent received (R) calls.
 * Optionally limit by minutes, compute distance if server coords provided,
 * and filter by source type ('RF' or 'APRS-IS').
 * Now also returns lat/lon (when present) and a numeric timestamp for reuse (e.g., maps).
 *
 * @param string $logPath Path to RF log
 * @param int|null $minutes Time window in minutes or null for all
 * @param float|null $serverLat Station latitude
 * @param float|null $serverLon Station longitude
 * @param string $sourceFilter '' | 'RF' | 'APRS-IS'
 * @param string|null $selectedInterface (kept for call-compat; not used here)
 * @return array<int,array{
 *   callsign:string,source:string,time:string,type:string,
 *   distance:?float,message:?string,lat:?float,lon:?float,timestamp:int
 * }>
 */
function getRecentCalls($logPath, $minutes = null, $serverLat = null, $serverLon = null, $sourceFilter = '', $selectedInterface = null) {
	if (!file_exists($logPath)) return [];

	$lines = file($logPath);
	$now = time();
	$calls = [];
	$digipeatedKeys = [];

	/* Pre-pass: mark digipeated payloads (T) so we can de-dup if needed later */
	foreach ($lines as $line) {
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+T\s+([A-Z0-9\-]+)>.*?:([^\r\n]+)/', $line, $m)) {
			$key = md5(strtoupper($m[3]) . trim($m[4]));
			$digipeatedKeys[$key] = true;
		}
	}

	/* Walk lines newest-first */
	foreach (array_reverse($lines) as $line) {
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\.\d+\s+(\S+)\s+([RT])\s+([A-Z0-9\-]+)>/', $line, $m)) {
			$timestamp = strtotime($m[1]);
			$src		= strtoupper($m[2]);
			$dir		= $m[3];
			$fromCall	= strtoupper($m[4]);

			/* Apply optional time window and direction filter */
			if ($minutes !== null && ($now - $timestamp) > ($minutes * 60)) continue;
			if ($dir !== 'R') continue;

			$type = ($src === 'APRSIS') ? 'APRS-IS' : 'RF';
			if ($sourceFilter && $type !== $sourceFilter) continue;

			/* -------------------------------------------------------------
			   Position extraction & distance
			   ------------------------------------------------------------- */
			$distance	= null;
			$latitude	= null;
			$longitude	= null;

			/* Payload (text after the last ':') */
			$payload = null;
			if (preg_match('/:([^\r\n]+)$/', $line, $pm)) {
                $payload = trim($pm[1]);
			}

			/* (1) Uncompressed coords anywhere in payload.
			     Allow ANY single separator after N/S (handles odd overlays like 'D'). */
			if ($payload && preg_match('/([0-9]{4,5}\.[0-9]{2}[NS]).?([0-9]{5}\.[0-9]{2}[EW])/', $payload, $uc)) {
				$latitude  = aprsCoordToDecimal($uc[1], true);
				$longitude = aprsCoordToDecimal($uc[2], false);
			}

			/* (2) Compressed Base-91 (strict start-of-payload positions only).
			     Avoid false hits like '/A=' altitude by honoring APRS framing:
			     - '!' or '='  → symbol-table at index 1
			     - '@' or '/'  → hhmmss{z|/|h|H} timestamp, symbol-table at index 8
			     First 4 chars after table are LAT (y), next 4 are LON (x). */
			if (($latitude === null || $longitude === null) && $payload) {
				$pos = -1;
				$len = strlen($payload);
				if ($len >= 9) {
					$lead = $payload[0];
					if ($lead === '!' || $lead === '=') {
						$pos = 1;
					} elseif (($lead === '@' || $lead === '/') && $len >= 9) {
						/* After 6-digit time + 1 time-qualifier → table at index 7 or 8.
						   Using 8 catches the common hhmmss{z|/|h|H} forms. */
						$pos = 8;
					}
				}
				if ($pos >= 0 && $pos + 9 <= $len) {
					$tbl = $payload[$pos];
					if ($tbl === '/' || $tbl === '\\') {
						$blkLat = substr($payload, $pos + 1, 4); /* LAT (y) */
						$blkLon = substr($payload, $pos + 5, 4); /* LON (x) */
						$valid = true;
						for ($i = 0; $i < 4; $i++) {
							$o1 = ord($blkLat[$i]);
							$o2 = ord($blkLon[$i]);
							if ($o1 < 33 || $o1 > 123 || $o2 < 33 || $o2 > 123) { $valid = false; break; }
						}
						if ($valid) {
							$y = 0; $x = 0;
							for ($i = 0; $i < 4; $i++) { $y = ($y * 91) + (ord($blkLat[$i]) - 33); }
							for ($i = 0; $i < 4; $i++) { $x = ($x * 91) + (ord($blkLon[$i]) - 33); }
							$lat = 90.0  - ($y / 380926.0);
							$lon = -180.0 + ($x / 190463.0);
							if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
								$latitude  = $lat;
								$longitude = $lon;
							}
						}
					}
				}
			}

			/* Distance if we have both ends */
			if ($latitude !== null && $longitude !== null && $serverLat !== null && $serverLon !== null) {
				$distance = calculateDistance($serverLat, $serverLon, $latitude, $longitude);
			}

			$message = extractAprsMessage($line);
			$calls[] = [
				'callsign' => $fromCall,
				'source'   => $src,
				'time'     => date('Y-m-d H:i:s', $timestamp),
				'type'     => $type,
				'distance' => $distance,
				'lat'      => $latitude,
				'lon'      => $longitude,
				'timestamp'=> $timestamp,
				'message'  => $message,
			];
		}
	}

	/* Sort newest-first (simple comparator) */
	uasort($calls, function ($a, $b) {
		$tb = isset($b['time']) ? strtotime($b['time']) : 0;
		$ta = isset($a['time']) ? strtotime($a['time']) : 0;
		if ($tb === $ta) return 0;
		return ($tb < $ta) ? -1 : 1;
	});

	return $calls;
}

// =========================================================
// Build station dataset for map.php by reusing getRecentCalls()
// - Deduplicates to most recent packet per callsign
// - Filters by source when requested ('rf' | 'aprsis' | 'both')
// - Exposes: callsign, lat, lon, source ('rf'|'aprsis'), last_seen (unix)
// =========================================================
function getHeardStations(array $config, string $source = 'both', int $minutes = null): array {
	$logPath = $config['aprx_log_path'] ?? '/var/log/aprx/aprx-rf.log';
	$filter = match (strtolower($source)) {
		'rf' => 'RF',
		'aprsis' => 'APRS-IS',
		default => ''
	};

	// Reuse canonical parser; no extra log pass.
	$calls = getRecentCalls($logPath, $minutes, null, null, $filter);
	if (empty($calls)) return [];

	$latestByCall = [];
	foreach ($calls as $row) {
		// Require coordinates
		if (!isset($row['lat'], $row['lon'])) continue;
		if ($row['lat'] === null || $row['lon'] === null) continue;

		$cs = strtoupper($row['callsign'] ?? 'UNKNOWN');
		if (isset($latestByCall[$cs])) continue; // list is newest-first

		$latestByCall[$cs] = [
			'callsign'  => $cs,
			'lat'       => (float)$row['lat'],
			'lon'       => (float)$row['lon'],
			'source'    => (strtolower($row['type']) === 'aprs-is') ? 'aprsis' : 'rf',
			'last_seen' => (int)($row['timestamp'] ?? 0)
		];
	}

	return array_values($latestByCall);
}

/**
 * Determine node role from parsed config array.
 * Returns 'Digipeater', 'iGate', or 'Unknown'.
 * @param array $configData
 * @return string
 */
function getRole($configData) {
	// Role is determined by an enabled <digipeater> block.
	// If not found, default to iGate as per requirements.
	if (!empty($configData['digipeat'])) {
		return 'Digipeater';
	}
	if (!empty($configData['igate'])) {
		return 'iGate';
	}
	return 'Unknown';
 }

/**
 * Simple lat/lon label; used when reverse geocoding is unavailable.
 * @param float $lat
 * @param float $lon
 * @return string
 */
function getLocationLabel($lat, $lon) {
	return sprintf("%.5f, %.5f", $lat, $lon);
}

/**
 * Get pretty system uptime ("up 3 hours, 2 minutes") or 'Unknown' if not available.
 * @return string
 */
function getUptime() {
	$output = shell_exec("uptime -p 2>/dev/null");
	return $output ? trim($output) : 'Unknown';
}

/**
 * Extract human-readable APRS message/comment from an AX.25 payload line.
 * Filters out position/telemetry/object frames and returns trailing comment when present.
 * @param string $line
 * @return string|null
 */
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

/**
 * Reverse-geocode coordinates to a "City, State/Province" label via Nominatim.
 * Returns "Unknown Location" when unresolved or on error.
 * NOTE: Callers should avoid invoking this with null lat/lon.
 * @param float|null $lat
 * @param float|null $lon
 * @return string
 */
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

/**
 * Parse aprx.conf for <interface> blocks and collect their callsigns.
 * Comments are ignored; only enabled interface blocks are considered.
 * @param string $configPath
 * @return array<int,string>
 */
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


/**
 * Load optional operator notice from a text file.
 * Returns sanitized HTML with minimal Markdown support or null when absent/empty.
 * @param string $path
 * @return string|null
 */
function getOperatorNotice(string $path = 'operator_notice.txt'): ?string {
	if (!file_exists($path)) {
		return null;
	}

	$content = trim(file_get_contents($path));
	if ($content === '') {
		return null;
	}

	// Escape HTML
	$content = htmlspecialchars($content);

	// Markdown: [text](url)
	$content = preg_replace_callback(
		'/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/',
		function ($matches) {
			$text = $matches[1];
			$url = $matches[2];
			return '<a href="' . $url . '" target="_blank" rel="noopener">' . $text . '</a>';
		},
		$content
	);

	// Auto-link bare URLs
	$content = preg_replace(
		'#(?<!["\'=])\b(https?://[^\s<]+)#i',
		'<a href="$1" target="_blank" rel="noopener">$1</a>',
		$content
	);

	// Basic markdown formatting
	$content = preg_replace('/^# (.+)$/m', '<h3>$1</h3>', $content);
	$content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
	$content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);

	// Newlines to <br>
	$content = preg_replace('/\r\n|\r|\n/', '<br>', $content);

	return $content;
}

/**
 * Aggregate dashboard data (meta, notice, recent calls, counts, filters).
 * Applies time/source/interface filters and annotates recent calls as RF:<iface> or APRS-IS.
 *
 * @param array $config
 * @param array $params Expected: interface, source, filter
 * @return array{
 *   meta:array,operatorNotice:?string,recentCalls:array,totalCount:int,
 *   rfCount:int,aprsisCount:int,selectedInterface:string,filter:string,
 *   source:string,rfInterfaces:array
 * }
 */
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

	// Tag each row as "RF: <iface>" or "APRS-IS" for display clarity
	foreach ($recentCalls as &$info) {
		$iface = strtoupper($info['source'] ?? '');
		$info['type'] = in_array($iface, $rfInterfaces) ? "RF: $iface" : "APRS-IS";
	}
	unset($info);

	// Unique callsign counts overall and split by RF/APRS-IS
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

/**
 * Build RX/TX statistics per RF interface across time buckets.
 * Supports hourly buckets for short ranges, daily for longer ranges.
 *
 * @param array $config Requires 'aprx_config_path' and 'aprx_log_path'
 * @param string $selectedRange One of: 1h,2h,6h,12h,1d,7d,14d,30d
 * @return array{
 *   stats:array,buckets:array,useHourly:bool,selectedRange:string,
 *   interfaces:array,ranges:array
 * }
 */
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
	// Hourly granularity for short windows; daily for longer
	$useHourly = in_array($selectedRange, ['1h', '2h', '6h', '12h', '1d']);

	$lines = file_exists($logPath) ? file($logPath) : [];
	$stats = [];
	$allBuckets = [];

	$now = time();
	$step = $useHourly ? 3600 : 86400;

	// Pre-populate all bucket keys so charts render gaps as zero
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

			// Only count enabled RF interfaces and records within window
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

	// Return the data structure used by stats.php charts
	return [
		'stats' => $stats,
		'buckets' => $bucketList,
		'useHourly' => $useHourly,
		'selectedRange' => $selectedRange,
		'interfaces' => $interfaces,
		'ranges' => $ranges
	];
}
