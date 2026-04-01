<?php

define('HOURS_IN_LOG_FILE', 2);

function log_to_file($message)
{
	$logs_dir = __DIR__ . '/../logs';
	if (!is_dir($logs_dir))
	{
		mkdir($logs_dir, 0755, true);
	}

	date_default_timezone_set('America/Vancouver');
	$now = time();
	$hour_block = (int)(date('G', $now) / HOURS_IN_LOG_FILE) * HOURS_IN_LOG_FILE;
	$filename = date('Y', $now) . '_' . date('m', $now) . '_' . date('d', $now) . '_' . sprintf('%02d', $hour_block) . '.log';
	$filepath = $logs_dir . '/' . $filename;

	$line = '[' . date('Y-m-d H:i:s', $now) . '] ' . $message . PHP_EOL;
	file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX);
}

?>
