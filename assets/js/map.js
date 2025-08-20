// =========================================================
// map.js - Leaflet renderer for APRX Station Map
// - Expects window.stationData = [{ callsign, lat, lon, source, last_seen }]
// - Optionally window.selfStation = { callsign, lat, lon, source:'self', last_seen }
// - Colors: RF = blue, APRS-IS = yellow, default/other = green
// - Adds popups, fits bounds, legend control, and basic robustness checks
// =========================================================

(function () {
	"use strict";

	// -----------------------------------------------------
	// Guard: ensure Leaflet and stationData are available
	// -----------------------------------------------------
	if (typeof L === "undefined") {
		console.error("Leaflet not found. Ensure leaflet.js is included before map.js.");
		return;
	}
	// Normalize stationData → Array (handles accidental Object/null)
	if (!Array.isArray(window.stationData)) {
		try {
			if (window.stationData && typeof window.stationData === "object") {
				window.stationData = Object.values(window.stationData);
			}
		} catch (e) {
			/* ignore */
		}
		if (!Array.isArray(window.stationData)) {
			console.warn("stationData missing or not an array; initializing to empty.");
			window.stationData = [];
		}
	}
	console.debug("map.js stationData count:", window.stationData.length);

	// -----------------------------------------------------
	// Map setup
	// -----------------------------------------------------
	var mapEl = document.getElementById("map");
	if (!mapEl) {
		console.error("#map element not found.");
		return;
	}
	// Warn if CSS hasn't given the map a height (common cause of invisible map)
	if (!mapEl.offsetHeight) {
		console.warn("#map has zero height; check that .map-container is defined and CSS loaded.");
	}

	// Default view if we have no markers
	var DEFAULT_VIEW = [20, 0];
	var DEFAULT_ZOOM = 2;

	var map = L.map("map", {
		zoomControl: true,
		// Prefer canvas for many markers; switch to false if you use custom icons
		preferCanvas: true
	});

	// OpenStreetMap tiles (respect usage guidelines)
	var tiles = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution: "&copy; OpenStreetMap contributors",
		maxZoom: 19
	});
	tiles.on("tileerror", function (e) {
		console.warn("Tile load error:", e);
	});
	tiles.addTo(map);

	// -----------------------------------------------------
	// Marker styling
	// -----------------------------------------------------
	function colorForSource(src) {
		var s = (src || "").toLowerCase();
		if (s === "self") return "#ef4444";	// red-500 for our own station
		if (s === "rf") return "#3b82f6";		// blue-500
		if (s === "aprsis") return "#facc15";	// yellow-400
		return "#22c55e";						// green-500 (fallback / both)
	}

	function labelForSource(src) {
		var s = (src || "").toLowerCase();
		if (s === "self") return "This Station";
		if (s === "rf") return "RF";
		if (s === "aprsis") return "APRS-IS";
		return "Both/Unknown";
	}

	//	Strip SSID (e.g., -U, -3). Handle ASCII hyphen and Unicode dash variants.
	function baseCallsign(cs) {
		//	Split on first hyphen-like character: -, -, –, —
		var raw = String(cs || "");
		var parts = raw.split(/[-\u2011\u2013\u2014]/);	// hyphen, non-breaking hyphen, en-dash, em-dash
		return parts[0].trim();
	}

	function formatTs(ts) {
		if (!ts) return "n/a";
		try {
			var d = new Date(Number(ts) * 1000);
			if (isNaN(d.getTime())) return "n/a";
			return d.toLocaleString();
		} catch (e) {
			return "n/a";
		}
	}

	// -----------------------------------------------------
	// Render markers
	// -----------------------------------------------------
	var markers = [];
	var bounds = [];
	var seen = Object.create(null); // de-duplicate by callsign+source

	window.stationData.forEach(function (stn) {
		// Ensure numeric lat/lon and within valid ranges
		var lat = Number(stn.lat);
		var lon = Number(stn.lon);
		if (!isFinite(lat) || !isFinite(lon)) return;
		if (lat < -90 || lat > 90 || lon < -180 || lon > 180) {
			console.warn("Skipping out-of-range coords for", stn.callsign, lat, lon);
			return;
		}
		// De-duplicate by (callsign, source) to avoid stacked markers
		var key = String(stn.callsign || "UNKNOWN").toUpperCase() + "|" + String(stn.source || "both").toLowerCase();
		if (seen[key]) return;
		seen[key] = true;

		var color = colorForSource(stn.source);
		var marker = L.circleMarker([lat, lon], {
			radius: 6,
			weight: 2,
			fillOpacity: 0.7,
			color: color,
			fillColor: color
		});

		var popupHtml = [
			"<strong>" + (stn.callsign || "Unknown") + "</strong>",
			"Source: " + labelForSource(stn.source),
			"Last seen: " + formatTs(stn.last_seen),
			"Lat/Lon: " + lat.toFixed(5) + ", " + lon.toFixed(5),
			'<div style="margin-top:6px; display:flex; gap:6px;">' +
				(stn.callsign
					? '<a href="https://www.qrz.com/db/' + encodeURIComponent(baseCallsign(stn.callsign)) + '" target="_blank" rel="noopener noreferrer">QRZ</a>' +
					  '<a href="https://aprs.fi/?call=' + encodeURIComponent(stn.callsign) + '" target="_blank" rel="noopener noreferrer">APRS.fi</a>'
					: "") +
			"</div>"
		].join("<br>");

		marker.bindPopup(popupHtml);
		marker.addTo(map);

		markers.push(marker);
		bounds.push([lat, lon]);
	});

	// -----------------------------------------------------
	// Add our own station marker (unique color & styling)
	// -----------------------------------------------------
	if (window.selfStation && typeof window.selfStation === "object") {
		var s = window.selfStation;
		var slat = Number(s.lat);
		var slon = Number(s.lon);
		if (isFinite(slat) && isFinite(slon) && slat >= -90 && slat <= 90 && slon >= -180 && slon <= 180) {
			var sc = colorForSource("self");
			var selfMarker = L.circleMarker([slat, slon], {
				radius: 8,
				weight: 3,
				fillOpacity: 0.8,
				color: sc,
				fillColor: sc
			});
			var selfPopup = [
				"<strong>" + (s.callsign || "Station") + " (You)</strong>",
				"Source: " + labelForSource("self"),
				"Lat/Lon: " + slat.toFixed(5) + ", " + slon.toFixed(5),
				'<div style="margin-top:6px; display:flex; gap:6px;">' +
					(s.callsign
						? '<a href="https://www.qrz.com/db/' + encodeURIComponent(baseCallsign(s.callsign)) + '" target="_blank" rel="noopener noreferrer">QRZ</a>' +
						  '<a href="https://aprs.fi/?call=' + encodeURIComponent(s.callsign) + '" target="_blank" rel="noopener noreferrer">APRS.fi</a>'
						: "") +
				"</div>"
			].join("<br>");
			selfMarker.bindPopup(selfPopup);
			selfMarker.addTo(map);
			selfMarker.bringToFront();
			bounds.push([slat, slon]);	// include in fit bounds
		} else {
			console.warn("selfStation has invalid coordinates; skipping marker.", s);
		}
	}

	// -----------------------------------------------------
	// Fit map to markers, or fallback to a sane default
	// -----------------------------------------------------
	if (bounds.length > 1) {
		map.fitBounds(bounds, { padding: [20, 20] });
	} else if (bounds.length === 1) {
		map.setView(bounds[0], 10);
	} else {
		map.setView(DEFAULT_VIEW, DEFAULT_ZOOM);
	}

	// Keep tiles/layout correct on viewport changes
	window.addEventListener("resize", function () {
		map.invalidateSize();
	}, { passive: true });

	// -----------------------------------------------------
	// Legend control
	// -----------------------------------------------------
	var Legend = L.Control.extend({
		options: { position: "bottomright" },
		onAdd: function () {
			var div = L.DomUtil.create("div", "leaflet-control legend-control");
			div.style.background = "rgba(0,0,0,0.4)";
			div.style.color = "#fff";
			div.style.padding = "6px 8px";
			div.style.borderRadius = "6px";
			div.style.font = "12px/1.2 Segoe UI, sans-serif";
			div.style.boxShadow = "0 1px 4px rgba(0,0,0,0.3)";
			div.innerHTML =
				'<div style="font-weight:bold; margin-bottom:4px;">Sources</div>' +
				legendRow("#ef4444", "This Station") +
				legendRow("#3b82f6", "RF") +
				legendRow("#facc15", "APRS-IS") +
				legendRow("#22c55e", "Both/Unknown");
			return div;
		}
	});

	function legendRow(color, label) {
		return (
			'<div style="display:flex; align-items:center; gap:6px; margin:2px 0;">' +
			'<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:' + color + ';"></span>' +
			'<span>' + label + "</span>" +
			"</div>"
		);
	}

	map.addControl(new Legend());
})();
