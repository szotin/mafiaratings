<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/languages.php';
define('DB_ALL_LANGS', 0xffffff);

function cut_long_name($name, $length)
{
	if (mb_strlen($name ?? '', 'UTF-8') > $length)
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
	$query = new DbQuery('SELECT nickname FROM event_users WHERE user_id = ? GROUP BY nickname ORDER BY COUNT(*) DESC', $user_id);
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
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE event_id = ? AND nickname = ?', $event_id, $nick);
	}
	
	if ($count > 0)
	{
		throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Nick-name'), $nick));
	}
}

function check_address_name($name, $club_id, $address_id = -1)
{
	global $_profile;

	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('address name')));
	}

	if ($address_id > 0)
	{
		$query = new DbQuery('SELECT name FROM addresses WHERE name = ? AND club_id = ? AND id <> ?', $name, $club_id, $address_id);
	}
	else
	{
		$query = new DbQuery('SELECT name FROM addresses WHERE name = ? AND club_id = ?', $name, $club_id);
	}
	if ($query->next())
	{
        throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Address name'), $name));
	}
}

class Names
{
	// if $name_id == 0 - name_id does not exist it has to be created - names are taken from $_REQUEST
	// if $name_id > 0 - name_id does exist - names are taken from the database
	// if $name_id < 0 - name_id does not exist it has to be created - $param_name is treated as the only name.
	function __construct($name_id, $obj_name, $table_name = NULL, $obj_id = 0, $condition = NULL, $param_name = 'name')
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
				$name->name = $_REQUEST[$param_name];
				$name->langs = 0;
				$this->names[] = $name;
			}
			while (($lang = get_next_lang($lang)) != LANG_NO)
			{
				$pname = $param_name . '_' . get_lang_code($lang);
				if (isset($_REQUEST[$pname]))
				{
					$name = new stdClass();
					$name->name = $_REQUEST[$pname];
					if (empty($name->name))
					{
						continue;
					}
					check_name($name->name, $obj_name);
					$name->langs = $lang;
					$default_mask &= ~$lang;
					$this->names[] = $name;
				}
			}
		}
		else
		{
			check_name($param_name, $obj_name);
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
					$this->names = array_slice($this->names, $j, 1);
					$n1->langs |= $n2->langs;
				}
				else
				{
					++$j;
				}
			}
		}

		// Find if this set of names already exists
		$query = new DbQuery('SELECT n1.id, COUNT(n2.id) as c FROM names n1 JOIN names n2 ON n2.id = n1.id WHERE n1.name = ? AND n1.langs = ? GROUP BY n1.id HAVING c = ?', $this->names[0]->name, $this->names[0]->langs, count($this->names));
		while ($row = $query->next())
		{
			list($id) = $row;
			if (count($this->names) == 1)
			{
				return $this->id = $id;
			}
			
			$match_count = 0;
			$query1 = new DbQuery('SELECT name, langs FROM names WHERE id = ?', $id);
			while ($row1 = $query->next())
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
	
	static function show_control($names = NULL, $control_id = 'form-name', $var_name = 'nameControl')
	{
		echo '<ul id="' . $control_id . '-menu" style="display:none;position:absolute;text-align:left;z-index:2147483647;">';
		$lang = LANG_NO;
		while (($lang = get_next_lang($lang)) != LANG_NO)
		{
			$lang_code = get_lang_code($lang);
			echo '<li><a href="javascript:' . $var_name . '.addLang(\'' . $lang_code . '\')">';
			echo '<img src="images/' . $lang_code . '.png" width="20">';
			echo '</a></li>';
		}
		echo '</ul><div id="' . $control_id . '-div"></div><script>';
		if (!is_null($names) && count($names->names) > 0)
		{
			$values_var = $var_name . 'Values';
			echo 'var ' . $values_var . ' = {name:"' . $names->names[0]->name . '"';
			for ($i = 1; $i < count($names->names); ++$i)
			{
				$n = $names->names[$i];
				$lang = LANG_NO;
				while (($lang = get_next_lang($lang, $n->langs)) != LANG_NO)
				{
					echo ', ' . get_lang_code($lang) . ':"' . $n->name . '"';
				}
			}
			echo '}; ';
		}
		else
		{
			$values_var = 'null';
		}
		echo 'var ' . $var_name . ' = new NameControl(' . $values_var . ', "' . $control_id . '", "' . $var_name . '");';
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