<?php

define('LANG_NO', 0);
define('LANG_ENGLISH', 1);
define('LANG_RUSSIAN', 2);
define('LANG_UKRAINIAN', 4);
define('LANG_ALL', 7);
define('LANG_ALL_VISUAL', 3);
define('LANG_DEFAULT', 1); // English

function get_lang($langs, $default_lang = -1)
{
	global $_REQUEST;
	
	if (isset($_REQUEST['lang']))
	{
		$lang = $_REQUEST['lang'] & $langs;
	}
	else if ($default_lang >= 0)
	{
		return $default_lang & $langs;
	}
	else
	{
		$lang = $langs;
	}
	
	if (($lang & LANG_ENGLISH) != 0)
	{
		return LANG_ENGLISH;
	}
	if (($lang & LANG_RUSSIAN) != 0)
	{
		return LANG_RUSSIAN;
	}
	if (($lang & LANG_UKRAINIAN) != 0)
	{
		return LANG_UKRAINIAN;
	}
	return 0;
}

define('LOWERCASE', 0);
define('UPPERCASE', 1);
define('CAPITAL_LETTER', 2);
function get_lang_str($lang, $case = CAPITAL_LETTER, $browser_lang = LANG_NO)
{
	switch ($lang)
	{
		case LANG_ENGLISH:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'English';
						case UPPERCASE:
							return 'ENGLISH';
					}
					return 'English';
				case LANG_RUSSIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'английский';
						case UPPERCASE:
							return 'АНГЛИЙСКИЙ';
					}
					return 'Английский';
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'англійська';
						case UPPERCASE:
							return 'АНГЛІЙСЬКА';
					}
					return 'Англійська';
			}
			switch ($case)
			{
				case LOWERCASE:
					return get_label('english');
				case UPPERCASE:
					return get_label('ENGLISH');
			}
			return get_label('English');
			
		case LANG_RUSSIAN:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'Russian';
						case UPPERCASE:
							return 'RUSSIAN';
					}
					return 'Russian';
				case LANG_RUSSIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'русский';
						case UPPERCASE:
							return 'РУССКИЙ';
					}
					return 'Русский';
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'російська';
						case UPPERCASE:
							return 'РОСІЙСЬКА';
					}
					return 'Російська';
			}
			switch ($case)
			{
				case LOWERCASE:
					return get_label('russian');
				case UPPERCASE:
					return get_label('RUSSIAN');
			}
			return get_label('Russian');
			
		case LANG_UKRAINIAN:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'Ukrainian';
						case UPPERCASE:
							return 'UKRAINIAN';
					}
					return 'Ukrainian';
				case LANG_RUSSIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'украинский';
						case UPPERCASE:
							return 'УКРАИНСКИЙ';
					}
					return 'Украинский';
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'українська';
						case UPPERCASE:
							return 'УКРАЇНСЬКВА';
					}
					return 'Українська';
			}
			switch ($case)
			{
				case LOWERCASE:
					return get_label('Ukrainian');
				case UPPERCASE:
					return get_label('UKRAINIAN');
			}
			return get_label('Ukrainian');
	}
	
	switch ($browser_lang)
	{
		case LANG_ENGLISH:
			switch ($case)
			{
				case LOWERCASE:
					return 'Unknown';
				case UPPERCASE:
					return 'UNKNOWN';
			}
			return 'Unknown';
		case LANG_RUSSIAN:
			switch ($case)
			{
				case LOWERCASE:
					return 'неизвестный';
				case UPPERCASE:
					return 'НЕИЗВЕСТНЫЙ';
			}
			return 'Неизвестный';
		case LANG_UKRAINIAN:
			switch ($case)
			{
				case LOWERCASE:
					return 'невідомий';
				case UPPERCASE:
					return 'НЕВІДОМИЙ';
			}
			return 'Невідомий';
	}
	switch ($case)
	{
		case LOWERCASE:
			return get_label('unknown');
		case UPPERCASE:
			return get_label('UNKNOWN');
	}
	return get_label('Unknown');
}

function get_short_lang_str($lang, $case = UPPERCASE, $browser_lang = LANG_NO)
{
	if ($browser_lang == LANG_NO)
	{
		$browser_lang = $lang;
	}
	
	switch ($lang)
	{
		case LANG_ENGLISH:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'Eng';
						case UPPERCASE:
							return 'ENG';
					}
					return 'Eng';
				case LANG_RUSSIAN:
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'анг';
						case UPPERCASE:
							return 'АНГ';
					}
					return 'Анг';
			}
			break;
			
		case LANG_RUSSIAN:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'Rus';
						case UPPERCASE:
							return 'RUS';
					}
					return 'Rus';
				case LANG_RUSSIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'рус';
						case UPPERCASE:
							return 'РУС';
					}
					return 'Рус';
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'рос';
						case UPPERCASE:
							return 'РОС';
					}
					return 'Рос';
			}
			break;
			
		case LANG_UKRAINIAN:
			switch ($browser_lang)
			{
				case LANG_ENGLISH:
					switch ($case)
					{
						case LOWERCASE:
							return 'Ukr';
						case UPPERCASE:
							return 'UKR';
					}
					return 'Ukr';
				case LANG_RUSSIAN:
				case LANG_UKRAINIAN:
					switch ($case)
					{
						case LOWERCASE:
							return 'укр';
						case UPPERCASE:
							return 'УКР';
					}
					return 'Укр';
			}
			break;
	}
	return '';
}

function get_lang_code($lang)
{
	switch ($lang)
	{
		case LANG_RUSSIAN:
			return 'ru';
		case LANG_UKRAINIAN:
			return 'ua';
	}
	return 'en';
}

function get_lang_by_code($code)
{
	switch ($code)
	{
		case 'ru':
			return LANG_RUSSIAN;
		case 'ua':
			return LANG_UKRAINIAN;
	}
	return LANG_DEFAULT;
}

function get_langs($def)
{
	global $_profile;
	
	if (isset($_REQUEST['langs']))
	{
		return (int)$_REQUEST['langs'];
	}
	
	if (isset($_REQUEST['langs_set']))
	{
		$langs = 0;
		if (isset($_REQUEST[get_lang_code(LANG_ENGLISH)]))
		{
			$langs |= LANG_ENGLISH;
		}
		if (isset($_REQUEST[get_lang_code(LANG_RUSSIAN)]))
		{
			$langs |= LANG_RUSSIAN;
		}
		if (isset($_REQUEST[get_lang_code(LANG_UKRAINIAN)]))
		{
			$langs |= LANG_UKRAINIAN;
		}
		return $langs;
	}
/*	else if ($_profile != NULL)
	{
		return $_profile->user_langs;
	}*/
	return $def;
}

function get_next_lang($lang, $langs = LANG_ALL)
{
	if ($lang != LANG_NO)
	{
		$langs &= (~($lang - 1)) << 1;
	}
	$langs -= (($langs - 1) & $langs);
	return $langs;
}

function get_langs_str($langs, $separator, $case = LOWERCASE, $browser_lang = LANG_NO)
{
	$str = '';
	$sep = '';
	$lang = LANG_NO;
	while (($lang = get_next_lang($lang, $langs)) != LANG_NO)
	{
		$str .= $sep . get_lang_str($lang, $case, $browser_lang);
		$sep = $separator;
	}
	return $str;
}

function get_langs_codes($langs, $separator)
{
	$str = '';
	$sep = '';
	$lang = LANG_NO;
	while (($lang = get_next_lang($lang, $langs)) != LANG_NO)
	{
		$str .= $sep . get_lang_code($lang);
		$sep = $separator;
	}
	return $str;
}

function get_langs_count($langs)
{
	$count = 0;
	while ($langs != 0)
	{
		++$count;
		$langs &= ($langs - 1);
	}
	return $count;
}

function get_browser_lang()
{
	if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
	{
		return LANG_DEFAULT;
	}

	$browserlangs = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
	
	$row = LANG_DEFAULT;
	$lang = LANG_NO;
	while (($lang = get_next_lang($lang)) != LANG_NO)
	{
		if (strpos($browserlangs, get_lang_code($lang)) !== false)
		{
			$row = $lang;
		}
	}
	return $row;
}

function correct_lang($lang_code)
{
	$lang_code = strtolower($lang_code);
	if ($lang_code == 'en' || $lang_code == 'ru')
	{
		return $lang_code;
	}
	return get_lang_code(get_browser_lang());
}

function langs_checkboxes($langs, $filter = LANG_ALL, $form_name = NULL, $separator = '<br>', $prefix='', $on_click = NULL)
{
	if (is_valid_lang($filter))
	{
		echo '<input type="hidden" name="' . $prefix . 'langs" id="' . $prefix . 'langs" value="' . $filter . '">' . get_lang_str($filter);
		return;
	}
	
	if ($on_click == NULL && $form_name != NULL)
	{
		$on_click = 'document.' . $form_name . '.submit()';
	}
	
	echo '<input type="hidden" name="langs_set" value="">';
	
	if ($on_click != NULL)
	{
		$input_beg = '<input type="checkbox" onClick="' . $on_click . '" ';
	}
	else
	{
		$input_beg = '<input type="checkbox" ';
	}
	
	$lang = LANG_NO;
	$sep = '';
	while (($lang = get_next_lang($lang, $filter)) != LANG_NO)
	{
		echo $sep . $input_beg;
		if (($langs & $lang) != 0)
		{
			echo 'checked ';
		}
		echo 'name="' . $prefix . get_lang_code($lang) . '" id="' . $prefix . get_lang_code($lang) . '"> ' . get_lang_str($lang);
		$sep = $separator;
	}
}

function is_valid_lang($lang, $langs = LANG_ALL)
{
	if ((($lang - 1) & $lang) != 0)
	{
		return false;
	}
	return (($lang & $langs) != 0);
}

function is_valid_lang_code($lang_code)
{
	switch ($lang_code)
	{
	case 'en';
	case 'ru';
	case 'ua';
		return true;
	}
	return false;
}

function detect_lang($str)
{
	$lat_count = 0;
	$rus_count = 0;
	
	$length = strlen($str);
	$language = LANG_NO;
	$skip_counter = 0;
	for ($i = 0; $i < $length; ++$i)
	{
		$char = substr($str, $i, 1);
		if ($skip_counter > 0)
		{
			if ($char == ']')
			{
				--$skip_counter;
			}
		}
		else if ($char == '[')
		{
			++$skip_counter;
		}
		else
		{
			$code = ord($char);
			if (($code >= 65 && $code <= 90) || ($code >= 97 && $code <= 122))
			{
				++$lat_count;
			}
			else if ($code == 208)
			{
				if (++$i < $length)
				{
					$char = substr($str, $i, 1);
					$code = ord($char);
					if ($code >= 144 && $code <= 191)
					{
						++$rus_count;
					}
				}
			}
			else if ($code == 209)
			{
				if (++$i < $length)
				{
					$char = substr($str, $i, 1);
					$code = ord($char);
					if ($code >= 128 && $code <= 143)
					{
						++$rus_count;
					}
				}
			}
		}
	}
	
	if ($rus_count > 0)
	{
		if ($rus_count * 2 > $lat_count)
		{
			return LANG_RUSSIAN;
		}
		return LANG_DEFAULT;
	}
	else if ($lat_count > 0)
	{
		return LANG_ENGLISH;
	}
	return LANG_NO;
}

// Not used because flags have too much political context now. Currently we are using text only.
function show_language_picture($lang, $dir, $width = 0, $height = 0)
{
	if ($width <= 0 && $height <= 0)
	{
		if ($dir == ICONS_DIR)
		{
			$width = ICON_WIDTH;
			$height = ICON_HEIGHT;
		}
		else if ($dir == TNAILS_DIR)
		{
			$width = TNAIL_WIDTH;
			$height = TNAIL_HEIGHT;
		}
	}

	echo '<img src="images/' . $dir . 'lang' . $lang . '.png" title="' . get_lang_str($lang) . '"';
	if ($width > 0)
	{
		echo ' width="' . $width . '"';
	}
	if ($height > 0)
	{
		echo ' height="' . $height . '"';
	}
	echo '>';
}

function show_lang_select($name, $lang, $lang_mask = LANG_ALL, $on_change = NULL)
{
	if ($lang_mask & ($lang_mask - 1))
	{
		echo '<select name="' . $name . '" id="' . $name . '"';
		if ($on_change != NULL)
		{
			echo ' onChange="' . $on_change . '"';
		}
		
		echo '>';
			
		$l = LANG_NO;
		while (($l = get_next_lang($l)) != LANG_NO)
		{
			
			show_option($l, $lang, get_lang_str($l));
		}
		echo '</select>';
	}
	else
	{		
		if ($lang_mask != 0)
		{
			$lang = $lang_mask;
		}
		else if ($lang == 0 || ($lang & ($lang - 1)) != 0)
		{
			$lang = get_browser_lang();
		}
		echo '<input type="hidden" name="' . $name . '" id="' . $name . '" value="' .  $lang. '">';
	}
}

function valid_langs_help()
{
	$result = '<p>Currently supported language ids are:<ol>';
	$lang = LANG_NO;
	while (($lang = get_next_lang($lang)) != LANG_NO)
	{
		$result .= '<li value="' . $lang . '">' . get_lang_str($lang) . '</li>';
	}
	$result .= '</ol></p>';
	return $result;
}

function valid_lang_codes_help()
{
	$result = '<p>Currently supported language codes are:<ul>';
	$lang = LANG_NO;
	while (($lang = get_next_lang($lang)) != LANG_NO)
	{
		$result .= '<li>' . get_lang_code($lang) . ' - ' . get_lang_str($lang, LOWERCASE) . '</li>';
	}
	$result .= '</ul>';
	return $result;
}

?>