// /js/live-log.js
document.addEventListener("DOMContentLoaded", () => {
	const logElement = document.getElementById("log");
	const logType = logElement?.dataset.logType || "rf";
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

		allLines.push(...data.lines);
		if (allLines.length > maxLines) {
			allLines = allLines.slice(allLines.length - maxLines);
		}

		renderLog(allLines);
	}

	searchInput.addEventListener("input", () => renderLog(allLines));

	document.getElementById("export-log").addEventListener("click", () => {
		const filter = searchInput.value.toLowerCase();
		const visibleLines = allLines.filter(line => line.toLowerCase().includes(filter));
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

	fetchLog();
	setInterval(fetchLog, 5000);
});
