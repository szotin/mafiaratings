<?php

function is_assoc_array($array) 
{
	foreach (array_keys($array) as $k => $v)
	{
		if ($k !== $v)
		{
			return true;
		}
	}
	return false;
}

function object_json($object, $newLine, $exclude_object)
{
	if ($object === $exclude_object)
	{
		return '{..}';
	}
	
	$nl = $newLine . '   ';
	
	switch (count((array)$object))
	{
		case 0:
			$result = '{}';
			break;
		case 1:
			foreach ($object as $key => $value)
			{
				$result = '{ "' . $key . '": ' . formatted_json($value, $nl, $exclude_object) . ' }';
			}
			break;
		default:
			if ($newLine != "\n")
			{
				$result = $newLine . '{';
			}
			else
			{
				$result = '{';
			}
			$first = true;
			foreach ($object as $key => $value)
			{
				if ($first)
				{
					$first = false;
				}
				else
				{
					$result .= ',';
				}
				$result .= $nl . '"' . $key . '": ' . formatted_json($value, $nl, $exclude_object);
			}
			$result .= $newLine . '}';
			break;
	}
	return $result;
}

function escape_json_string($str)
{
	static $f = array("\b", "\f", "\n", "\r", "\t", "\"", "\\");
	static $r = array('\b', '\f', '\n', '\r', '\t', '\"', '\\');
	return str_replace($f, $r, $str);
}

function formatted_json($object, $newLine = "\n", $exclude_object = NULL)
{
	if (is_null($object))
	{
		$result = 'null';
	}
	else if ($object === $exclude_object)
	{
		$result = '{..}';
	}
	else if (is_array($object))
	{
		if (is_assoc_array($object))
		{
			$result = object_json($object, $newLine, $exclude_object);
		}
		else switch (count($object))
		{
			case 0:
				$result = '[]';
				break;
			case 1:
				$result = '[ ' . formatted_json($object[0], $newLine . '   ', $exclude_object) . ' ]';
				break;
			default:
				$nl = $newLine . '   ';
				$result = '[';
				for ($i = 0; $i < count($object); ++$i)
				{
					if ($i > 0)
					{
						$result .= ', ';
					}
					$result .= formatted_json($object[$i], $nl, $exclude_object);
				}
				$result .= ']';
				break;
		}
	}
	else if (is_object($object))
	{
		$result = object_json($object, $newLine, $exclude_object);
	}
	else if (is_string($object))
	{
		$result = '"' . escape_json_string($object) . '"';
	}
	else if (is_bool($object))
	{
		$result = $object ? 'true' : 'false';
	}
	else
	{
		$result = $object;
	}
	return $result;
}

function check_json($string)
{
	$obj = json_decode($string);
	$error_code = json_last_error();
	if ($obj == null && $error_code != JSON_ERROR_NONE)
	{
		if (function_exists('json_last_error_msg'))
		{
			$msg = json_last_error_msg();
		}
		else switch ($error_code)
		{
			case JSON_ERROR_NONE: 
				$msg = 'No error';
				break;
			case JSON_ERROR_DEPTH: 
				$msg = 'Maximum stack depth exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH: 
				$msg = 'State mismatch (invalid or malformed JSON)';
				break;
			case JSON_ERROR_CTRL_CHAR: 
				$msg = 'Control character error, possibly incorrectly encoded';
				break;
			case JSON_ERROR_SYNTAX: 
				$msg = 'Syntax error';
				break;
			case JSON_ERROR_UTF8: 
				$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
			default:
				$msg = 'Unknown error';
				break;
		}
		throw new Exc($msg);
	}
	return json_encode($obj);
}

function print_json($object, $exclude_object = NULL)
{
	echo '<pre>';
	echo formatted_json($object, "\n", $exclude_object);
    //echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	echo '</pre>';
}

?>