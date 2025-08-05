<?php
    session_start();
    $config = include 'config.php';
    require_once 'functions.php';
    $selectedLog = $_GET['log'] ?? 'rf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live APRX Log</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="log-header">
	<div class="log-header-row">
		<h1 class="log-title">Live APRX Log Stream</h1>
		<a href="index.php" class="submit">← Back to Home</a>
	</div>
	<div class="log-header-row">
		<form method="get" style="display: flex; align-items: center; gap: 0.75em;">
			<label for="logselect">Log:</label>
			<select name="log" id="logselect" onchange="this.form.submit()">
				<option value="rf" <?= $selectedLog === 'rf' ? 'selected' : '' ?>>RF (aprx-rf.log)</option>
				<option value="daemon" <?= $selectedLog === 'daemon' ? 'selected' : '' ?>>Daemon (aprx.log)</option>
			</select>
		</form>
		<div class="log-search-row">
			<label for="log-search">Search:</label>
			<input type="text" id="log-search" placeholder="Enter filter text..." style="width: 200px; padding: 6px;">
			<button id="export-log" class="submit" style="height: 36px;">Export</button>
		</div>
	</div>
</div>
    <div class="log-container" id="log"></div>
<script>
	// Pass log type to JS via data attribute
	document.addEventListener("DOMContentLoaded", () => {
		const logContainer = document.getElementById("log");
		logContainer.dataset.logType = "<?= htmlspecialchars($selectedLog) ?>";
	});
</script>
<script src="/assets/js/live-log.js"></script>
<?php
	$meta = getStationMeta($config);  // Load shared APRX/Station data
	extract($meta);                   // Make vars available ($aprxver, $uptime, etc.)
	include 'footer.php';             // Output the consistent footer
?>
</body>
</html>
