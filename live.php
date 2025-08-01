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
    <link rel="stylesheet" href="style.css">
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
    const logElement = document.getElementById("log");
    const logType = "<?= htmlspecialchars($selectedLog) ?>";
    const searchInput = document.getElementById("log-search");
    let allLines = [];
    let lastOffset = 0;
    const maxLines = 100;

    function classifyLine(line) {
        if (line.includes(" T ")) return "log-tx";
        if (line.includes(" R ")) return "log-rx";
        return "log-sys";
    }

    function renderLog(lines) {
        const filter = searchInput.value.toLowerCase();
        logElement.innerHTML = "";
        lines.forEach(line => {
            if (line.toLowerCase().includes(filter)) {
                const div = document.createElement("div");
                div.className = "log-line " + classifyLine(line);
                div.textContent = line;
                logElement.appendChild(div);
            }
        });
        logElement.scrollTop = logElement.scrollHeight;
    }

    async function fetchLog() {
        const res = await fetch(`/api/logfetch.php?log=${logType}&offset=${lastOffset}`);
        if (!res.ok) {
            logElement.innerHTML = "<div class='log-line log-sys'>Error loading log.</div>";
            return;
        }

        const data = await res.json();
        lastOffset = data.offset || 0;

        // Update line buffer
        allLines.push(...data.lines);
        if (allLines.length > maxLines) {
            allLines = allLines.slice(allLines.length - maxLines); // truncate
        }

        renderLog(allLines);
    }

    searchInput.addEventListener("input", () => renderLog(allLines));

    fetchLog();
    setInterval(fetchLog, 5000);
document.getElementById("export-log").addEventListener("click", () => {
    const filter = searchInput.value.toLowerCase();
    const visibleLines = allLines.filter(line =>
        line.toLowerCase().includes(filter)
    );

    const blob = new Blob([visibleLines.join("\n")], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    const ts = new Date().toISOString().replace(/[:.]/g, "-");
    const fileName = `aprx-log-${logType}-${ts}.txt`;

    link.href = url;
    link.download = fileName;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();

    URL.revokeObjectURL(url);
    document.body.removeChild(link);
});
</script>
<?php
	$meta = getStationMeta($config);  // Load shared APRX/Station data
	extract($meta);                   // Make vars available ($aprxver, $uptime, etc.)
	include 'footer.php';             // Output the consistent footer
?>
</body>
</html>
