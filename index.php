<?php
    session_start();
    $config = include 'config.php';
    require_once 'functions.php';

    // Load APRX config
    $stationData = parseAprxConfig($config['aprx_config_path']);
    $aprxver = getAprxVersion();
    $uptime = getUptime();
    $role = getRole($stationData);

	// Load Location
//	$lat = $config['lat'];
//	$lon = $config['lon'];
	$serverLat = $config['latitude'];
	$serverLon = $config['longitude'];
	$locationLabel = reverseGeocode($config['latitude'], $config['longitude']);
	//die("Location: $locationLabel");


    // Handle time filter
    $filter = $_GET['filter'] ?? '1h';
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
      $config['latitude'],
      $config['longitude']
    );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APRX Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header-container">
	<div style="display: flex; align-items: center; gap: 1em;">
		<img src="aprslogo.png" class="logo" alt="APRS Logo">
		<h1 class="dashboard-title"><?php echo htmlspecialchars($config['callsign']); ?> - APRX Dashboard</h1>
	</div>
	<div class="header-nav">
		<a href="/live.php">Live Stats</a>
	</div>
</header>
<section class="form">
    <form method="get" action="">
        <label for="filter">Show calls heard:</label>
        <select name="filter" id="filter" onchange="this.form.submit()">
            <option value="1h"  <?php if ($filter == '1h') echo 'selected'; ?>>Last 1 hour</option>
	    <option value="2h"  <?php if ($filter == '2h') echo 'selected'; ?>>Last 2 hour</option>
	    <option value="4h"  <?php if ($filter == '4h') echo 'selected'; ?>>Last 4 hour</option>
	    <option value="6h"  <?php if ($filter == '6h') echo 'selected'; ?>>Last 6 hour</option>
	    <option value="12h"  <?php if ($filter == '12h') echo 'selected'; ?>>Last 12 hour</option>
            <option value="24h" <?php if ($filter == '24h') echo 'selected'; ?>>Last 24 hours</option>
            <option value="7d"  <?php if ($filter == '7d') echo 'selected'; ?>>Last 7 days</option>
            <option value="all" <?php if ($filter == 'all') echo 'selected'; ?>>All time</option>
        </select>
    </form>
</section>

<div class="table-center">
    <table>
        <caption class="table-caption">Stations Heard</caption>
        <thead>
            <tr>
                <th>Callsign</th>
                <th>Source</th>
                <th>Last Heard (UTC)</th>
		<th>Distance (KM)</th>
		<th>Message</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($recentCalls)): ?>
		<?php foreach ($recentCalls as $info): ?>
		    <?php $call = $info['callsign']; ?>
		    <?php $baseCall = explode('-', $call)[0]; ?>
		    <tr>
		        <td class="callsign">
		            <a href="https://www.qrz.com/db/<?php echo urlencode($baseCall); ?>" target="_blank" title="View on QRZ">📖</a>&nbsp;
		            <a href="https://aprs.fi/<?php echo urlencode($call); ?>" target="_blank"><?php echo htmlspecialchars($call); ?></a>
		        </td>
		        <td><?php echo htmlspecialchars($info['type']); ?></td>
		        <td><?php echo htmlspecialchars($info['time']); ?></td>
		        <td><?php echo isset($info['distance']) ? round($info['distance'], 1) . ' km' : '–'; ?></td>
		        <td><?php echo isset($info['message']) ? htmlspecialchars($info['message']) : '–'; ?></td>
		    </tr>
		<?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">No stations heard during selected time range.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<footer class="footer">
	<div class="footer-info-row">
		<span><strong>Interface:</strong> <?php echo htmlspecialchars($config['interface']); ?></span>
		<span><strong>Version:</strong> <?php echo htmlspecialchars($aprxver); ?></span>
		<span><strong>Location:</strong> <?php echo htmlspecialchars($locationLabel); ?></span>
		<span><strong>Role:</strong> <?php echo $role; ?></span>
		<span><strong>Uptime:</strong> <?php echo htmlspecialchars($uptime); ?></span>
	</div>
	<div class="footer-brand">
		<a href="https://github.com/VA3KWJ/aprx-modern-dashboard" target="_blank">APRX Monitor</a>
		<span>&copy;</span>
		<a href="https://va3kwj.ca" target="_blank">VA3KWJ 2025</a>
	</div>
</footer>
</body>
</html>
