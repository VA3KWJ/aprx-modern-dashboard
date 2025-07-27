<?php
// config.php
return [
	'aprx_config_path' => '/etc/aprx.conf',
	'aprx_log_path'    => '/var/log/aprx/aprx-rf.log',	// rf packets
	'aprx_daemon_log_path'  => '/var/log/aprx/aprx.log',	// daemon status
	'interface'        => 'VA3KWJ-10',	// the interface APRX talks to. We're not using this yet
	'callsign'         => 'VA3KWJ',
	'transmitter'      => 'VA3KWJ-10',
	'latitude'         => 43.70011,
	'longitude'        => -79.4163,
];
