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

function object_json($object, $newLine)
{
	$nl = $newLine . '   ';
	
	switch (count((array)$object))
	{
		case 0:
			$result = '{}';
			break;
		case 1:
			foreach ($object as $key => $value)
			{
				$result = '{ "' . $key . '": ' . formatted_json($value, $nl) . ' }';
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
				$result .= $nl . '"' . $key . '": ' . formatted_json($value, $nl);
			}
			$result .= $newLine . '}';
			break;
	}
	return $result;
}

function formatted_json($object, $newLine = "\n")
{
	if (is_null($object))
	{
		$result = 'null';
	}
	else if (is_array($object))
	{
		if (is_assoc_array($object))
		{
			$result = object_json($object, $newLine);
		}
		else switch (count($object))
		{
			case 0:
				$result = '[]';
				break;
			case 1:
				$result = '[ ' . formatted_json($object[0], $newLine . '   ') . ' ]';
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
					$result .= formatted_json($object[$i], $nl);
				}
				$result .= ']';
				break;
		}
	}
	else if (is_object($object))
	{
		$result = object_json($object, $newLine);
	}
	else if (is_string($object))
	{
		$result = '"' . addslashes($object) . '"';
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
	if ($obj == null && json_last_error() != JSON_ERROR_NONE)
	{
		throw new Exc(json_last_error_msg());
	}
	return json_encode($obj);
}

function print_json($object)
{
	echo '<pre>';
	echo formatted_json($object);
    //echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	echo '</pre>';
}

?>