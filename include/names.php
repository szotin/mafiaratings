<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/languages.php';
define('DB_ALL_LANGS', 0xffffff);

function cut_long_name($name, $length)
{
	if (mb_strlen($name, 'UTF-8') > $length)
	{
		return mb_substr($name, 0, $length - 3, 'UTF-8') . '...';
	}
	return $name;
}

function is_valid_name($name)
{
	return preg_match('/^([a-zA-Z0-9,.; _\x80-\xFF-])+$/', $name);
}

function correct_name($name)
{
	return preg_replace("/[^a-zA-Z0-9,.; _\x80-\xFF-]/", '_', $name);
}

function check_name($name, $obj_name)
{
	if (empty($name))
	{
		throw new Exc(get_label('Please enter [0].', $obj_name));
	}
	
	if (!is_valid_name($name))
	{
		throw new Exc(get_label('Invalid characters in [0]. Only alphanumeric, spaces, underscores and dashes are allowed.', $obj_name));
	}
}

function check_password($password, $confirm)
{
//	return preg_match("/^.*(?=.{8,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $password);
	if ($password != $confirm)
	{
		throw new Exc(get_label('Passwords do not match.'));
	}
	
	if (strlen($password) < 4)
	{
		throw new Exc(get_label('Password length must be at least 4 characters.'));
	}
}

function nick_name_chooser($user_id, $user_name, $nickname = NULL)
{
	$nicks = array();
	$query = new DbQuery('SELECT nickname FROM event_regs WHERE user_id = ? GROUP BY nickname ORDER BY COUNT(*) DESC', $user_id);
	$nicks[] = $user_name;
	$nick = $user_name;
	if ($row = $query->next())
	{
		list ($nick) = $row;
		do
		{
			list ($n) = $row;
			if ($n != $user_name)
			{
				$nicks[] = $n;
			}
		
		} while ($row = $query->next());
	}
	$nicks_count = count($nicks);
	
	if ($nickname != NULL)
	{
		$nick = $nickname;
	}
	
	echo '<input name="nick" id="nick" value="' . $nick . '" onkeyup="nick_changed()">&nbsp';
	echo '<select name="nicks" id="nicks" onchange="nicks_changed()">';
	echo '<option value=""></option>';
	for ($i = 0; $i < $nicks_count; ++$i)
	{
		$n = $nicks[$i];
		if ($n == $nick)
		{
			echo '<option value="' . $n . '" selected>' . cut_long_name($n, 50) . '</option>';
		}
		else
		{
			echo '<option value="' . $n . '">' . cut_long_name($n, 50) . '</option>';
		}
	}
	echo '</select>';
?>
	<script language="JavaScript" type="text/javascript">
	<!--
		function nick_changed()
		{
			$('#nicks option[value=]').attr('selected', 'selected');
		}
		
		function nicks_changed()
		{
			$('#nick').val($('#nicks').val());
		}
	//-->
	</script>
<?php
}

function check_nickname($nick, $event_id)
{
	global $_profile;
	
	if ($nick == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('nick-name')));
	}

	check_name($nick, get_label('nick-name'));
	$count = 0;
	if ($event_id > 0)
	{
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_regs WHERE event_id = ? AND nickname = ?', $event_id, $nick);
	}
	
	if ($count > 0)
	{
		throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Nick-name'), $nick));
	}
}

// The addresses of a club are told apart by their names: no other address of the club may have $name in one of the
// languages $langs.
function check_address_name($name, $club_id, $address_id = -1, $langs = DB_ALL_LANGS)
{
	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('address name')));
	}

	$query = new DbQuery(
		'SELECT a.id FROM addresses a JOIN names n ON n.id = a.name_id' .
		' WHERE a.club_id = ? AND n.name = ? AND (n.langs & ?) <> 0 AND a.id <> ?',
		$club_id, $name, $langs, $address_id);
	if ($query->next())
	{
        throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Address name'), $name));
	}
}

// The names of an address from the request - name, and name_xx for the languages that have their own - checked
// with check_address_name(). They are kept html-escaped, as address names always were. An address given no name
// is called by $default, its street address, which the caller has already escaped.
function get_address_names($club_id, $default, $address_id = -1)
{
	$names = new Names(0, get_label('Address name'), NULL, 0, NULL, 'name', false);
	foreach ($names->names as $n)
	{
		$n->name = htmlspecialchars($n->name, ENT_QUOTES);
	}
	if (count($names->names) == 0)
	{
		$names = new Names(-1, get_label('Address name'), NULL, 0, NULL, $default, false);
	}
	else if ($names->names[0]->name == '')
	{
		$names->names[0]->name = $default;
	}

	foreach ($names->names as $n)
	{
		check_address_name($n->name, $club_id, $address_id, $n->langs);
	}
	return $names;
}

class Names
{
	// if $name_id == 0 - name_id does not exist it has to be created - names are taken from $_REQUEST
	// if $name_id > 0 - name_id does exist - names are taken from the database
	// if $name_id < 0 - name_id does not exist it has to be created - $param_name is treated as the only name.
	// $validate is false for the objects whose names may hold any characters - events, where "1/8 final"
	// is a name; check_name() is not called for them.
	function __construct($name_id, $obj_name, $table_name = NULL, $obj_id = 0, $condition = NULL, $param_name = 'name', $validate = true)
	{
		$this->obj_name = $obj_name;
		$this->names = array();
		if ($name_id > 0)
		{
			$this->id = $name_id;
			$query = new DbQuery('SELECT langs, name FROM names WHERE id = ? ORDER BY langs DESC', $name_id);
			while ($row = $query->next())
			{
				$name = new stdClass();
				list($name->langs, $name->name) = $row;
				$name->langs = (int)$name->langs;
				$this->names[] = $name;
			}
			return;
		}
		
		$default_mask = DB_ALL_LANGS;
		if ($name_id == 0)
		{
			$lang = LANG_NO;
			if (isset($_REQUEST[$param_name]))
			{
				$name = new stdClass();
				$name->name = trim($_REQUEST[$param_name]);
				$name->langs = 0;
				$this->names[] = $name;
			}
			while (($lang = get_next_lang($lang)) != LANG_NO)
			{
				$pname = $param_name . '_' . get_lang_code($lang);
				if (isset($_REQUEST[$pname]))
				{
					$name = new stdClass();
					$name->name = trim($_REQUEST[$pname]);
					if (empty($name->name))
					{
						continue;
					}
					if ($validate)
					{
						check_name($name->name, $obj_name);
					}
					$name->langs = $lang;
					$default_mask &= ~$lang;
					$this->names[] = $name;
				}
			}
		}
		else
		{
			$param_name = trim($param_name);
			if ($validate)
			{
				check_name($param_name, $obj_name);
			}
			else if ($param_name == '')
			{
				throw new Exc(get_label('Please enter [0].', $obj_name));
			}
			$name = new stdClass();
			$name->name = $param_name;
			$name->langs = 0;
			$this->names[] = $name;
		}
		
		if (count($this->names) > 0)
		{
			$this->names[0]->langs |= $default_mask; // the first one is the default one
			if ($this->names[0]->langs == 0)
			{
				$this->names = array_slice($this->names, 0, 1);
			}
			
			// check that names are unique between each other
			if (!is_null($table_name))
			{
				if ($condition == NULL)
				{
					$condition = new SQL();
				}
				else if (is_string($condition))
				{
					$condition = new SQL($condition);
				}
				
				if ($obj_id > 0)
				{
					$condition->add(' AND o.id <> ?', $obj_id);
				}
			
				foreach ($this->names as $n)
				{
					list ($count) = Db::record(get_label('name'), 'SELECT count(*) FROM ' . $table_name . ' o JOIN names n ON n.id = o.name_id WHERE n.name = ? AND (n.langs & ?) <> 0', $n->name, $n->langs, $condition);
					if ($count > 0)
					{
						throw new Exc(get_label('[1] is already used as a [0]. Please try another one.', $obj_name, $n->name));
					}
				}
			}
		}
	}
	
	function get_id()
	{
		if (isset($this->id))
		{
			return $this->id;
		}
			
		if (count($this->names) == 0)
		{
			// 0 is for the situation when names are not set 
			return $this->id = 0;
		}
		
		// merge duplicated names - pretty bad algorythm, but it should not be a problem sinse names count is usually very low.
		for ($i = 0; $i < count($this->names); ++$i)
		{
			$n1 = $this->names[$i];
			for ($j = $i + 1; $j < count($this->names); )
			{
				$n2 = $this->names[$j];
				if ($n1->name == $n2->name)
				{
					array_splice($this->names, $j, 1);
					$n1->langs |= $n2->langs;
				}
				else
				{
					++$j;
				}
			}
		}

		// Find if this set of names already exists
		$query = new DbQuery('SELECT n1.id, COUNT(n2.id) as c FROM names n1 JOIN names n2 ON n2.id = n1.id WHERE BINARY n1.name = ? AND n1.langs = ? GROUP BY n1.id HAVING c = ?', $this->names[0]->name, $this->names[0]->langs, count($this->names));
		while ($row = $query->next())
		{
			list($id) = $row;
			if (count($this->names) == 1)
			{
				return $this->id = $id;
			}
			
			$match_count = 0;
			$query1 = new DbQuery('SELECT name, langs FROM names WHERE id = ?', $id);
			while ($row1 = $query1->next())
			{
				list($name, $langs) = $row1;
				foreach ($this->names as $n)
				{
					if ($n->name == $name && $n->langs == $langs)
					{
						++$match_count;
						break;
					}
				}
			}
			if ($match_count == count($this->names))
			{
				return $this->id = $id;
			}
		}
		
		// Create new set of names
		$n = $this->names[0];
		Db::exec(get_label('name'), 'INSERT INTO names (langs, name) VALUES (?, ?)', $n->langs, $n->name);
		list ($this->id) = Db::record(get_label('name'), 'SELECT LAST_INSERT_ID()');
		for ($i = 1; $i < count($this->names); ++$i)
		{
			$n = $this->names[$i];
			Db::exec(get_label('name'), 'INSERT INTO names (id, langs, name) VALUES (?, ?, ?)', $this->id, $n->langs, $n->name);
		}
		return $this->id;
	}
	
	function to_string($langs = LANG_ALL, $delimiter = '; ')
	{
		$str = '';
		$delim = '';
		for ($i = 0; $i < count($this->names); ++$i)
		{
			if ($this->names[$i]->langs & $langs)
			{
				$str .= $delim;
				$str .= $this->names[$i]->name;
				$delim = $delimiter;
			}
		}
		return $str;
	}
	
	function get_name($lang)
	{
		for ($i = 0; $i < count($this->names); ++$i)
		{
			if ($this->names[$i]->langs & $lang)
			{
				return $this->names[$i]->name;
			}
		}
		return '';
	}
	
	// The names as the values of a NameControl in js/src/mr.js: { name: "<for all languages>", ru: "...", ... }.
	function to_js()
	{
		$values = array();
		if (count($this->names) > 0)
		{
			$values['name'] = $this->names[0]->name;
			for ($i = 1; $i < count($this->names); ++$i)
			{
				$n = $this->names[$i];
				$lang = LANG_NO;
				while (($lang = get_next_lang($lang, $n->langs)) != LANG_NO)
				{
					$values[get_lang_code($lang)] = $n->name;
				}
			}
		}
		return json_encode((object)$values, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
	}

	// The names of an object the site names itself in every language: $by_lang is the name by language,
	// array(LANG_ENGLISH => 'final', LANG_RUSSIAN => 'финал', ...). The English one is the name for all
	// languages; a language gets its own only where it differs. Nothing is checked - the names come
	// from the labels.
	static function from_languages($by_lang, $obj_name)
	{
		$names = new Names(-1, $obj_name, NULL, 0, NULL, $by_lang[LANG_ENGLISH], false);
		foreach ($by_lang as $lang => $name)
		{
			if ($lang != LANG_ENGLISH && $name != $by_lang[LANG_ENGLISH])
			{
				$n = new stdClass();
				$n->name = $name;
				$n->langs = $lang;
				$names->names[] = $n;
				$names->names[0]->langs &= ~$lang;
			}
		}
		return $names;
	}

	// The id of the set holding just $name for all languages - the one there is, or a new one. For
	// the code that names an object itself, such as a tournament naming its rounds.
	static function get_single_id($name, $obj_name)
	{
		$names = new Names(-1, $obj_name, NULL, 0, NULL, $name, false);
		return $names->get_id();
	}

	static function show_control($names = NULL, $control_id = 'form-name', $var_name = 'nameControl')
	{
		// Languages are shown by their three letter codes, the way the language menu of the page shows them.
		$lang_names = '';
		$delim = '';
		echo '<ul id="' . $control_id . '-menu" style="display:none;position:absolute;width:42px;text-align:left;z-index:2147483647;">';
		$lang = LANG_NO;
		while (($lang = get_next_lang($lang)) != LANG_NO)
		{
			$lang_code = get_lang_code($lang);
			echo '<li><a href="javascript:' . $var_name . '.addLang(\'' . $lang_code . '\')" title="' . get_lang_str($lang) . '">';
			echo get_short_lang_str($lang);
			echo '</a></li>';
			$lang_names .= $delim . $lang_code . ':' . json_encode(get_short_lang_str($lang), JSON_UNESCAPED_UNICODE);
			$delim = ', ';
		}
		echo '</ul><div id="' . $control_id . '-div"></div><script>';
		if (!is_null($names) && count($names->names) > 0)
		{
			$values_var = $var_name . 'Values';
			echo 'var ' . $values_var . ' = ' . $names->to_js() . '; ';
		}
		else
		{
			$values_var = 'null';
		}
		echo 'var ' . $var_name . ' = new NameControl(' . $values_var . ', "' . $control_id . '", "' . $var_name . '", {' . $lang_names . '});';
		echo '</script>';
	}

	// For a name control of a small dialog: its language menu is shown over the page. Inside the
	// dialog the menu does not fit - it stretches the dialog, which scrolls, and the menu moves away
	// from its button. The menu goes with the dialog when the dialog is closed. Call right after
	// show_control(), from a form shown by dlg.form().
	static function show_menu_over_dialog($control_id = 'form-name')
	{
		echo '<script>';
		echo '(function() { var m = $("#' . $control_id . '-menu").appendTo("body");';
		echo ' $("#dlg" + dlg.curId()).on("dialogclose", function() { m.remove(); }); })();';
		echo '</script>';
	}
	
	static function help($help, $object, $for_update, $param_name = 'name')
	{
		if ($for_update)
		{
			$missing = 'a name with the lowest language id is used (most likely English). When none of '.$param_name.'_xx is set - names remain the same.';
		}
		else
		{
			$missing = 'a name with the lowest language id is used (most likely English). At least one parameter with '.$param_name.'_xx must be set.';
		}
		$help->request_param(
			$param_name, 
			$object . ' name. This name is used in all languages. If a separate name is needed for any other language it can be set as "'.$param_name.'_xx" where xx is a two letter language code. For example: { '.$param_name.': "Jason", '.$param_name.'_ru: "Джейсон" }. "Jason" is shown for all languages except Russian (code "ru"). For Russian "Джейсон" is shown.' . valid_lang_codes_help(), 
			$missing);
	}
}

?>