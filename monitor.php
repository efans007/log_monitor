<?php

// usage:
//
// create crontab:
// * * * * * flock -nx /tmp/script.logMonitor6.lock -c '/usr/bin/php5 /path/to/monitor.php /var/log/beiquan.log >> logMonitor.log'

require __DIR__ . '/vendor/autoload.php';

if ($argc <= 1) {
    echo "usage: /path/to/php ".__file__." /path/to/log_file\n";
    exit();
}

$logFile = $argv[1];
if (!file_exists($logFile)) {
    echo "log file \"".$logFile."\" not exists.\n";
    exit();
}

$monitor = new LogMonitor($logFile);
$monitor->run();
