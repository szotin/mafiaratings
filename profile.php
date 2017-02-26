<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/city.php';
require_once 'include/country.php';

class Page extends OptionsPageBase
{
	private $message;
	private $city_id;
	private $country_id;
	private $club_id;
	private $phone;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();

		$this->club_id = $_profile->user_club_id;
		if (isset($_REQUEST['club']))
		{
			$this->club_id = $_REQUEST['club'];
			if ($this->club_id <= 0)
			{
				$this->club_id = NULL;
			}
		}
		
		$this->phone = $_profile->user_phone;
		if (isset($_REQUEST['phone']))
		{
			$this->phone = $_REQUEST['phone'];
		}
		
		$this->city_id = -1;
		if (isset($_REQUEST['city']))
		{
			$this->city_id = $_REQUEST['city'];
		}
	
		$this->country_id = -1;
		if (isset($_REQUEST['country']))
		{
			$this->country_id = $_REQUEST['country'];
		}
	
		$this->message = NULL;
		if ($this->country_id <= 0)
		{
			$this->country_id = $_profile->country_id;
		}
	
		$langs = get_langs($_profile->user_langs);
		
		$this->user_flags = $_profile->user_flags;
		if (isset($_POST['mnot']))
		{
			$this->user_flags |= U_FLAG_MESSAGE_NOTIFY;
		}
		else
		{
			$this->user_flags &= ~U_FLAG_MESSAGE_NOTIFY;
		}
		if (isset($_POST['pnot']))
		{
			$this->user_flags |= U_FLAG_PHOTO_NOTIFY;
		}
		else
		{
			$this->user_flags &= ~U_FLAG_PHOTO_NOTIFY;
		}
		
		if (isset($_POST['is_male']))
		{
			if ($_POST['is_male'])
			{
				$this->user_flags |= U_FLAG_MALE;
			}
			else
			{
				$this->user_flags &= ~U_FLAG_MALE;
			}
		}
			
		if (isset($_POST['save']))
		{
			if ($this->city_id <= 0)
			{
				throw new Exc(get_label('Unknown [0]', get_label('city')));
			}
			
			$update_clubs = false;
			Db::begin();
			Db::exec(
				get_label('user'), 
				'UPDATE users SET flags = ?, city_id = ?, languages = ?, phone = ?, club_id = ? WHERE id = ?',
				$this->user_flags, $this->city_id, $langs, $this->phone, $this->club_id, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				list($city_name) = Db::record(get_label('user'), 'SELECT name_en FROM cities WHERE id = ?', $this->city_id);
				$log_details = 
					'flags=' . $this->user_flags .
					"<br>city=" . $city_name . ' (' . $this->city_id . ')' .
					"<br>langs=" . $langs;
				db_log('user', 'Changed', $log_details, $_profile->user_id);
				
				if ($this->club_id != NULL && !isset($_profile->clubs[$this->club_id]))
				{
					Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $this->club_id);
					db_log('user', 'Joined the club', NULL, $_profile->user_id, $this->club_id);
					$update_clubs = true;
				}
			}
			Db::commit();
				
			$this->message = get_label('Profile is saved.');
			$_profile->user_flags = $this->user_flags;
			$_profile->user_langs = $langs;
			$_profile->user_phone = $this->phone;
			$_profile->user_club_id = $this->club_id;
			if ($_profile->city_id != $this->city_id)
			{
				$_profile->city_id = $this->city_id;
				list ($_profile->country_id, $_profile->timezone) =
					Db::record(get_label('city'), 'SELECT country_id, timezone FROM cities WHERE id = ?', $this->city_id);
			}
			if ($update_clubs)
			{
				$_profile->update_clubs();
			}
		}
		
		if ($this->city_id <= 0)
		{
			$this->city_id = $_profile->city_id;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		echo '<table class="transp" width="100%"><tr><td align="right" valign="center">' . get_label('Change photo') . ':</td><td height="52" width="60">';
		show_upload_button();
		echo '</td></tr></table>';
		
		echo '<form method="post" name="profileForm" action="profile.php">';
		echo '<table class="bordered" width="100%">';
		if ($this->message != NULL)
		{
			echo '<tr><td colspan="2" class="light">' . $this->message . '</td></tr>';
		}
		
		echo '<tr><td class="dark" width="80">'.get_label('Login name').':</td><td class="light">' . cut_long_name($_profile->user_name, 80) . '</td></tr>';
		
		$club_id = $this->club_id;
		if ($club_id == NULL)
		{
			$club_id = 0;
		}
		echo '<tr><td class="dark" valign="top">'.get_label('Club').':</td><td class="light">' . get_label('Please enter your favourite club. The club you want to represent on championships.') . '<br>';
		echo '<select name="club">';
		show_option(0, $club_id, '');
		$query = new DbQuery('SELECT id, name FROM clubs ORDER BY name');
		while ($row = $query->next())
		{
			list ($cid, $cname) = $row;
			show_option($cid, $club_id, $cname);
		}
		echo '</td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
		if ($this->user_flags & U_FLAG_MALE)
		{
			if ($this->user_flags & U_ICON_MASK)
			{
				echo '<input type="radio" name="is_male" value="1" checked/>'.get_label('male').'<br>';
				echo '<input type="radio" name="is_male" value="0"/>'.get_label('female');
			}
			else
			{
				echo '<input type="radio" name="is_male" value="1" onClick="document.profileForm.submit()" checked/>'.get_label('male').'<br>';
				echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()"/>'.get_label('female');
			}
		}
		else if ($this->user_flags & U_ICON_MASK)
		{
			echo '<input type="radio" name="is_male" value="1"/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0" checked/>'.get_label('female');
		}
		else
		{
			echo '<input type="radio" name="is_male" value="1" onClick="document.profileForm.submit()"/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()" checked/>'.get_label('female');
		}
		echo '</td></tr>';
		
		echo '<tr><td class="dark" valign="top">'.get_label('Languages').':</td><td class="light">';
		langs_checkboxes($_profile->user_langs);
		echo '</td></tr>';
		
		echo '<tr><td class="dark">'.get_label('Region').':</td><td class="light">';
		
		$query = new DbQuery('SELECT id, name_' . $_lang_code . ' FROM countries ORDER BY name_' . $_lang_code);
		echo '<select name="country" onChange="document.profileForm.submit()">';
		while ($row = $query->next())
		{
			show_option($row[0], $this->country_id, $row[1]);
		}
		echo '</select> ';
		
		$query = new DbQuery('SELECT id, name_' . $_lang_code . ' FROM cities WHERE country_id = ? ORDER BY name_' . $_lang_code, $this->country_id);
		echo '<select name="city">';
		while ($row = $query->next())
		{
			show_option($row[0], $this->city_id, $row[1]);
		}
		echo '</select>';
		
		echo '<tr><td class="dark">' . get_label('Phone') . ':</td><td class="light">';
		echo get_label('Phone is optional. You can give us your phone if you do not mind us calling you.') . '<br><input name="phone" value="' . $_profile->user_phone . '"></td></tr>';
		
		echo '</table>';
		
		echo '<p><input type="checkbox" name="mnot"';
		if (($_profile->user_flags & U_FLAG_MESSAGE_NOTIFY) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('I would like to receive emails when someone replies to me or sends me a private message.');
		echo '<br><input type="checkbox" name="pnot"';
		if (($_profile->user_flags & U_FLAG_PHOTO_NOTIFY) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('I would like to receive emails when someone tags me on a photo.').'</p>';
		
		echo '<input type="submit" value="'.get_label('Save').'" name="save" class="btn norm">';
		echo '</form>';
		
		show_upload_script(USER_PIC_CODE, $_profile->user_id);
	}
}

$page = new Page();
$page->run(get_label('My profile'), PERM_USER);

?>