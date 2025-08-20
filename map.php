<?php
session_start();
$config = include 'config.php';
require_once 'functions.php';

/*	Load meta once (for server coords/callsign and footer reuse) */
$meta = getStationMeta($config);
$stationCallsign = $meta['stationData']['callsign'] ?? ($config['interface'] ?? 'STATION');

/*	Sanitize & validate ?source= (allowed: both|rf|aprsis) */
$selectedSource = $_GET['source'] ?? 'both';
$allowedSources = ['both', 'rf', 'aprsis'];
if (!in_array($selectedSource, $allowedSources, true)) {
	$selectedSource = 'both';
}

/*	Build station list for map (deduped latest per callsign) */
$stations = getHeardStations($config, $selectedSource);
/*	Normalize to a pure numeric array for safe JSON → JS Array */
if (!is_array($stations)) {
	$stations = [];
} else {
	$stations = array_values($stations);
}

/*	Prepare self-station marker from coords (aprx.conf preferred, config.php fallback) */
$selfStation = null;
//	Strong fallback to config.php if aprx.conf coords are missing
$selfLat = $meta['serverLat'] ?? null;
$selfLon = $meta['serverLon'] ?? null;
if ($selfLat === null || $selfLon === null) {
	$selfLat = $config['latitude']  ?? null;
	$selfLon = $config['longitude'] ?? null;
}
if (is_numeric($selfLat) && is_numeric($selfLon)) {
	$selfStation = [
		'callsign'  => $stationCallsign,
		'lat'       => (float)$selfLat,
		'lon'       => (float)$selfLon,
		'source'    => 'self',
		'last_seen' => time(),
	];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>APRX Map</title>
	<link rel="stylesheet" href="assets/css/style.css">
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
</head>
<body>
<div class="log-header">
	<div class="log-header-row">
		<h1 class="log-title">Station Map</h1>
		<a href="index.php" class="submit">← Back to Home</a>
	</div>

	<div class="filter-row">
		<form method="get" style="display:flex; gap:0.5em; align-items:center;">
			<label for="source">Source:</label>
			<select name="source" id="source" onchange="this.form.submit()">
				<option value="both" <?= $selectedSource === 'both' ? 'selected' : '' ?>>RF + APRS-IS</option>
				<option value="rf" <?= $selectedSource === 'rf' ? 'selected' : '' ?>>RF Only</option>
				<option value="aprsis" <?= $selectedSource === 'aprsis' ? 'selected' : '' ?>>APRS-IS Only</option>
			</select>
		</form>
	</div>
</div>

	<div id="map" class="map-container"></div>

	<script>
		/*	Expose station list + self station for Leaflet renderer */
		window.stationData = <?= json_encode($stations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
		window.selfStation = <?= $selfStation ? json_encode($selfStation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'null' ?>;
		console.debug('map.php stationData count:', Array.isArray(window.stationData) ? window.stationData.length : 'not-array');
	</script>
	<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
	<?php $mapJsVer = @filemtime('assets/js/map.js') ?: time(); ?>
	<script src="assets/js/map.js?v=<?= $mapJsVer ?>"></script>
	<?php
		/*	Footer: APRX meta (reuse loaded meta) + common branding */
		extract($meta);					/* Make vars available ($aprxver, $uptime, etc.) */
		include 'footer.php';			/* Output the consistent footer */
	?>
</body>
</html>
