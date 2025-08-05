<?php
	$config = include 'config.php';
	require_once 'functions.php';

	$data = loadDashboardData($config, $_GET);

	$meta = $data['meta'];
	$operatorNotice = $data['operatorNotice'];
	$recentCalls = $data['recentCalls'];
	$totalCount = $data['totalCount'];
	$rfCount = $data['rfCount'];
	$aprsisCount = $data['aprsisCount'];
	$selectedInterface = $data['selectedInterface'];
	$filter = $data['filter'];
	$source = $data['source'];
	$rfInterfaces = $data['rfInterfaces'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APRX Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header-container">
	<div style="display: flex; align-items: center; gap: 1em;">
		<img src="/assets/img/aprslogo.png" class="logo" alt="APRS Logo">
		<h1 class="dashboard-title"><?php echo htmlspecialchars($config['callsign']); ?> - APRX Dashboard</h1>
	</div>
	<?php if (!empty($operatorNotice)): ?>
	<div class="notice-box">
		<?php echo nl2br(htmlspecialchars($operatorNotice)); ?>
	</div>
	<?php endif; ?>
	<div class="header-nav">
		<a href="/live.php">Live Log</a>
		<a href="/stats.php">Interface Stats</a>
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
	<label for="source">Source:</label>
	<select name="source" id="source" onchange="this.form.submit()">
		<option value="">All</option>
		<option value="RF" <?php if ($source === 'RF') echo 'selected'; ?>>RF</option>
		<option value="APRS-IS" <?php if ($source === 'APRS-IS') echo 'selected'; ?>>APRS-IS</option>
	</select>
	<label for="interface">Interface:</label>
	<select name="interface" id="interface" onchange="this.form.submit()">
		<option value="">All</option>
		<?php foreach ($rfInterfaces as $iface): ?>
			<option value="<?php echo htmlspecialchars($iface); ?>" <?php if (($selectedInterface ?? '') === $iface) echo 'selected'; ?>>
				<?php echo htmlspecialchars($iface); ?>
			</option>
		<?php endforeach; ?>
	</select>
    </form>
</section>

<div class="table-center">
    <table>
        <caption class="table-caption">Stations Heard: (<?php echo $rfCount; ?> RF, <?php echo $aprsisCount; ?> APRS-IS)</caption>
        <thead>
            <tr>
                <th>Callsign</th>
                <th>Source</th>
                <th>Last Heard</th>
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
			<td data-utc="<?php echo htmlspecialchars($info['time']); ?>" class="localtime">
				<?php echo htmlspecialchars($info['time']); ?>
			</td>
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
<?php
	/* $meta = getStationMeta($config);  // Load shared APRX/Station data */
	extract($meta);                   // Make vars available ($aprxver, $uptime, etc.)
	include 'footer.php';             // Output the consistent footer
?>
</body>
</html>
