<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(0);
ob_implicit_flush(true);

$count = 0;
while (true) {
    if (connection_aborted()) break;

    echo "data: Ping {$count} at " . date('H:i:s') . "\n\n";
    flush();
    $count++;
    sleep(1);
}
