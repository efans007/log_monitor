# log_monitor
A simple log monitor

Usage:
add crontab
```shell
* * * * * flock -nx /tmp/script.logMonitor6.lock -c '/usr/bin/php5 /path/to/monitor.php /var/log/beiquan.log >> logMonitor.log'
```
