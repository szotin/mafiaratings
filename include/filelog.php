<?php

function log_to_file($message)
{
	$logs_dir = __DIR__ . '/../logs';
	if (!is_dir($logs_dir))
	{
		mkdir($logs_dir, 0755, true);
	}

	date_default_timezone_set('America/Vancouver');
	$now = time();
	$hour_block = (int)(date('G', $now) / 4) * 4;
	$filename = date('Y', $now) . date('m', $now) . date('d', $now) . '_' . sprintf('%02d', $hour_block) . '.log';
	$filepath = $logs_dir . '/' . $filename;

	$line = '[' . date('Y-m-d H:i:s', $now) . '] ' . $message . PHP_EOL;
	file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX);
}

?>
