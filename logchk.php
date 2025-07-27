<?php
$config = require 'config.php';
$logFile = $config['aprx_log_path'];
echo "Testing: $logFile\n";
echo is_readable($logFile) ? "✔ Readable\n" : "✖ Not readable\n";
?>
