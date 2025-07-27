<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

set_time_limit(0);
ob_implicit_flush(true);

echo "data: Connected to tail.php\n\n";
flush();

for ($i = 0; $i < 10; $i++) {
    if (connection_aborted()) break;
    echo "data: Test line $i at " . date('H:i:s') . "\n\n";
    flush();
    sleep(1);
}

