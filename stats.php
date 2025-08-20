<?php
require_once 'functions.php';

$config = loadConfig();

$selectedRange = filter_input(INPUT_GET, 'range', FILTER_SANITIZE_STRING) ?? '7d';
$allowedRanges = ['1h', '2h', '6h', '12h', '1d', '7d', '14d', '30d'];
$selectedRange = in_array($selectedRange, $allowedRanges) ? $selectedRange : '7d';

$statData = generateStats($config, $selectedRange);
extract($statData); // includes $stats, $buckets, $ranges, etc.
$stats = $statData['stats'];
$allBuckets = $statData['buckets'];
$useHourly = $statData['useHourly'];
$interfaces = $statData['interfaces'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APRX Interface Stats</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="log-header">
    <div class="log-header-row">
        <h1 class="log-title">Interface Stats (<?php echo strtoupper($selectedRange); ?>)</h1>
        <a href="index.php" class="submit">← Back to Home</a>
    </div>
    <form method="get" style="margin-top: 1rem;">
        <label for="range">Date Range:</label>
        <select name="range" id="range" onchange="this.form.submit()">
	<?php foreach ($ranges as $key => $range): ?>
		<option value="<?php echo $key; ?>" <?php if ($selectedRange === $key) echo 'selected'; ?>>
			<?php echo $range['label']; ?>
		</option>
	<?php endforeach; ?>
        </select>
    </form>
</div>

<div class="chart-container">
<?php foreach ($stats as $iface => $data): ?>
    <div class="chart-box">
        <canvas id="chart_<?php echo $iface; ?>"></canvas>
    </div>
<?php endforeach; ?>
</div>

<script>
<?php foreach ($stats as $iface => $data): ?>
new Chart(document.getElementById('chart_<?php echo $iface; ?>'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($allBuckets); ?>,
        datasets: [
            {
                label: '<?php echo $iface; ?> RX',
                data: <?php echo json_encode(array_map(fn($d) => $data[$d]['rx'] ?? 0, $allBuckets)); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                tension: 0.3
            },
            {
                label: '<?php echo $iface; ?> TX',
                data: <?php echo json_encode(array_map(fn($d) => $data[$d]['tx'] ?? 0, $allBuckets)); ?>,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.2)',
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: '<?php echo $iface; ?> RX/TX - <?php echo $ranges[$selectedRange]['label']; ?>'
            }
         },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
<?php endforeach; ?>
</script>

<?php
	$meta = getStationMeta($config);
	extract($meta);
	include 'footer.php';
?>
</body>
</html>
