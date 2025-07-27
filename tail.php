<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(0);
ob_implicit_flush(true);

$config = require 'config.php';
$type = $_GET['log'] ?? 'rf';
$logFile = $type === 'daemon'
    ? ($config['aprx_daemon_log_path'] ?? '/var/log/aprx/aprx.log')
    : ($config['aprx_log_path'] ?? '/var/log/aprx/aprx-rf.log');

if (!is_readable($logFile)) {
    echo "event: error\ndata: Cannot read log file: $logFile\n\n";
    flush();
    exit;
}

// Print last N lines
function tail_last_lines($file, $lines = 25) {
    $fp = fopen($file, "r");
    if (!$fp) return [];

    $pos = -1;
    $lineCount = 0;
    $buffer = '';
    $result = [];

    fseek($fp, 0, SEEK_END);
    $length = ftell($fp);

    while ($lineCount < $lines && $length + $pos > 0) {
        fseek($fp, $pos, SEEK_END);
        $char = fgetc($fp);
        if ($char === "\n") {
            $lineCount++;
            if ($buffer) {
                array_unshift($result, strrev($buffer));
                $buffer = '';
            }
        } else {
            $buffer .= $char;
        }
        $pos--;
    }

    if ($buffer) array_unshift($result, strrev($buffer));
    fclose($fp);
    return $result;
}

foreach (tail_last_lines($logFile) as $line) {
    echo "data: " . trim($line) . "\n\n";
}
flush();

// Watch for new lines
$lastSize = filesize($logFile);
$start = time();
$maxRuntime = 60;

while (true) {
    if (connection_aborted() || (time() - $start) > $maxRuntime) break;

    clearstatcache();
    $currentSize = filesize($logFile);

    // If file rotated (smaller than before), reset
    if ($currentSize < $lastSize) {
        $lastSize = 0;
    }

    if ($currentSize > $lastSize) {
        $fp = fopen($logFile, 'r');
        fseek($fp, $lastSize);
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                echo "data: " . trim($line) . "\n\n";
                flush();
            }
        }
        fclose($fp);
        $lastSize = $currentSize;
    }

    usleep(250000); // 0.25s
}
