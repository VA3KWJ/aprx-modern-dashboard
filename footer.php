<footer class="footer">
	<div class="footer-info-row">
		<span><strong>Operator:</strong>
			<a href="https://www.qrz.com/db/<?php echo htmlspecialchars($config['callsign']); ?>" target="_blank" title="OP on QRZ">
				<?php echo htmlspecialchars($config['callsign']); ?>
			</a>
		</span>
		<span><strong>Version:</strong> <?php echo htmlspecialchars($aprxver); ?></span>
		<span><strong>Location:</strong> <?php echo htmlspecialchars($locationLabel); ?></span>
		<span><strong>Role:</strong> <?php echo htmlspecialchars($role); ?></span>
		<span><strong>Uptime:</strong> <?php echo htmlspecialchars($uptime); ?></span>
		<span>
			<strong>Status:</strong>
			<span style="color:<?php echo $aprxStatus === 'active' ? 'limegreen' : 'tomato'; ?>">
				<?php echo htmlspecialchars(ucfirst($aprxStatus)); ?>
			</span>
		</span>
	</div>
	<div class="footer-brand">
		<a href="https://github.com/VA3KWJ/aprx-modern-dashboard" target="_blank">APRX Monitor</a>
		<span>&copy;</span>
		<a href="https://va3kwj.ca" target="_blank">VA3KWJ 2025</a>
	</div>
</footer>
<script>
document.querySelectorAll('.localtime').forEach(el => {
	const utc = el.dataset.utc;
	if (!utc) return;
	const d = new Date(utc + ' UTC');
	el.textContent = d.toLocaleString(undefined, {
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
		hour12: false
	});
});
</script>
