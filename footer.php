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
	</div>
	<div class="footer-brand">
		<a href="https://github.com/VA3KWJ/aprx-modern-dashboard" target="_blank">APRX Monitor</a>
		<span>&copy;</span>
		<a href="https://va3kwj.ca" target="_blank">VA3KWJ 2025</a>
	</div>
</footer>
