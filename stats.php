<?php
require_once 'functions.php';
$config = loadConfig();
$meta = getStationMeta($config);
extract($meta);

$interfaces = getRfInterfaces($config['aprx_config_path']);
$logPath = $config['aprx_log_path'];

// Define time ranges and cutoff timestamps
$ranges = [
    '1h' => '-1 hour',
    '2h' => '-2 hours',
    '6h' => '-6 hours',
    '12h' => '-12 hours',
    '1d' => '-1 day',
    '7d' => '-7 days',
    '14d' => '-14 days',
    '30d' => '-30 days'
];

$selectedRange = $_GET['range'] ?? '7d';
$cutoff = strtotime($ranges[$selectedRange] ?? '-7 days');
$useHourly = in_array($selectedRange, ['1h', '2h', '6h', '12h']);

$lines = file_exists($logPath) ? file($logPath) : [];

$stats = [];
$allBuckets = [];

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

        if (!isset($stats[$iface][$bucket])) $stats[$iface][$bucket] = ['rx' => 0, 'tx' => 0];
        if ($dir === 'R') $stats[$iface][$bucket]['rx']++;
        if ($dir === 'T') $stats[$iface][$bucket]['tx']++;
    }
}

$allBuckets = array_keys($allBuckets);
sort($allBuckets);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APRX Interface Stats</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="log-header">
    <div class="log-header-row">
        <h1 class="log-title">Interface Stats</h1>
        <a href="index.php" class="submit">← Back to Home</a>
    </div>
    <form method="get" style="margin-top: 1rem;">
        <label for="range">Date Range:</label>
        <select name="range" id="range" onchange="this.form.submit()">
            <?php foreach ($ranges as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php if ($selectedRange === $key) echo 'selected'; ?>>
                    <?php echo $key; ?>
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
                text: '<?php echo $iface; ?> RX/TX - <?php echo strtoupper($selectedRange); ?>'
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
