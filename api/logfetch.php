<?php
header('Content-Type: application/json');

$config = require '../config.php';

$type = filter_input(INPUT_GET, 'log', FILTER_SANITIZE_STRING) ?? 'rf';
$type = in_array($type, ['rf', 'daemon']) ? $type : 'rf';
$logFile = $type === 'daemon'
	? $config['aprx_daemon_log_path']
	: $config['aprx_log_path'];

$lastSize = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$currentSize = is_readable($logFile) ? filesize($logFile) : 0;

if (!$logFile || !is_readable($logFile)) {
    http_response_code(404);
    echo json_encode(["error" => "Cannot read log file."]);
    exit;
}

$response = [
    "offset" => $currentSize,
    "lines" => []
];

// File rotated or too small, fallback to last 50 lines
if ($lastSize === 0 || $currentSize < $lastSize) {
    $lines = [];
    $fp = fopen($logFile, 'r');
    if ($fp) {
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $buffer = '';
        while ($pos > 0 && count($lines) < 50) {
            $pos--;
            fseek($fp, $pos);
            $char = fgetc($fp);
            if ($char === "\n" && $buffer) {
                array_unshift($lines, strrev($buffer));
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if ($buffer) array_unshift($lines, strrev($buffer));
        fclose($fp);
    }
    $response['lines'] = $lines;
} else {
    $fp = fopen($logFile, 'r');
    if ($fp) {
        fseek($fp, $lastSize);
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                $response['lines'][] = rtrim($line);
            }
        }
        fclose($fp);
    }
}

echo json_encode($response);
