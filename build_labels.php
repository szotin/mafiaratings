<?php

$labels=array();
$comments=array();
$dirs = array('.', 'include', 'api', 'api/ops', 'api/get', 'api/control', 'form');

function write_labels($lang, $backup = false)
{
	global $labels;
	global $comments;

	if ($backup)
	{
		$backupDir = 'include/languages/'.$lang.'/backups';
		if (!file_exists($backupDir))
		{
			mkdir($backupDir, 0777, true);
		}
		
		$backupFileName = $backupDir . '/labels.'.date('Y-m-d-Hi', time()).'.php';

		echo "\r\nMaking backup copy: ". $labelFileName .' --> '. $backupFileName .'... ';

		if (copy($labelFileName, $backupFileName)) echo "SUCCESS\r\n";
		else echo "FAIL!\r\n";
	}
	
	$labelFileName = 'include/languages/'.$lang.'/labels.php';
	echo 'Now writing updated '. $labelFileName .' file... ';
	

	$fh = fopen($labelFileName, 'w');
	fwrite($fh, "<?php\r\n");
	fwrite($fh, '$labelMenu = array ('."\r\n");

	foreach ($labels as $key => $value)
	{
		if (array_key_exists($key, $comments))
		{
			if (($value == '') and ($lang != 'en'))
			{
				fwrite($fh, '\''.$key.'\' => \'[EMPTY]\', // '.$comments[$key]."\r\n");
			}
			else
			{
				fwrite($fh, '\''.$key.'\' => \''. $value .'\', // '.$comments[$key]."\r\n");
			}
		}
	}

	fwrite($fh, ");\r\n");
	fwrite($fh, "\r\n".'return $labelMenu;'."\r\n");
	fwrite($fh, '?>');

	if (fclose($fh)) echo "SUCCESS!\r\n\r\n\r\n";
	else echo "FAIL!\r\n\r\n\r\n";

	return;
}


function insert_label($label, $filename)
{
	global $labels;
	global $comments;
	
	if (strpos($label, '$') === 0)
	{
		return;
	}
	
	$label = rtrim($label, '"\'');
	$label = trim($label, '"\'');
	
	if (strncmp($filename, "./", 2) === 0)
	{
		$filename = substr($filename, 2);
	}
	
	if (array_key_exists($label, $labels))
	{
		$comments[$label] = array_key_exists($label, $comments) ? $comments[$label] . ', ' . $filename : $filename;
	}
	else 
	{
//		echo "\r\n\t[" . $label . ']';
		$labels[$label] = '';
		$comments[$label] = $filename;
	}
	
	echo '.';
		
	return;
}


function process_file_labels($file)
{
	echo 'Processing file: '. $file;
	
	$CurrentFile = $file;
	$fh = fopen($CurrentFile, 'r');
	if ($fh === false)
	{
		echo " FAIL!\r\n";
		return;
	}
	
	$filesize = filesize($CurrentFile);
	if ($filesize == 0)
	{
		echo " DONE!\r\n";
		return;
	}
	
	$theData = fread($fh, $filesize);
	if ($theData == false)
	{
		echo " FAIL!\r\n";
		return;
	}
	fclose($fh);
	
	$tokens = token_get_all($theData);
	
	//var_dump($tokens);
	$signal = 0; // signals: 1 for 'get_label', 2 for '(', 3 for label
	foreach ($tokens as $value) 
	{
		switch ($signal)
		{
			case 0:
				if (is_array($value) && count($value) > 1 && $value[1] == 'get_label')
				{
					$signal = 1;
				}
				break;
			case 1:
				if ($value == '(')
				{
					$signal = 2;
				}
				break;
			case 2:
				if (is_array($value) && count($value) > 1)
				{
					$str = trim($value[1]);
					if ($str != '')
					{
						insert_label($str, $CurrentFile);
						$signal = 0;
					}
				}
				break;
		}
	}
	echo " DONE!\r\n";
	return;
}

function build_labels($lang)
{
	global $labels;
	global $comments;
	global $dirs;

	$labels = include 'include/languages/'.$lang.'/labels.php';

	echo 'Working on labels for the following language: '. strtoupper($lang) ."\r\n\r\n";

//	When reading the array from file, we will lose all escape symbols, so we need to restore them
	$repl_labels = $labels;
	echo 'Restoring escape symbols';
	foreach ($repl_labels as $key => $value)
	{
		if (strchr($key, "'"))
		{
			unset($labels[$key]);
			$newkey = str_replace("'", "\'", $key);
			$newvalue = str_replace("'", "\'", $value);
			$labels[$newkey] = $newvalue;
			echo '.';
		}
		elseif (strchr($value, "'"))
		{
			$labels[$key] = str_replace("'", "\'", $value);
			echo ',';
		
		}
	}
	echo " DONE!\r\n";

	foreach($dirs as $dir)
	{
		$dh = opendir($dir);
		while (false !== ($file = readdir($dh))) 
		{
			$path = $dir . '/' . $file;
			if (!is_dir($path))
			{
				process_file_labels($path);
			}
		}
		closedir($dh);
	}
	
	write_labels($lang);
	return;
}

date_default_timezone_set('America/Vancouver');
build_labels('ru');

?>