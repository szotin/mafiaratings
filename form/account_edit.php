<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/image.php';
require_once '../include/languages.php';

initiate_session();

try
{
	dialog_title(get_label('Settings'));
	
	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	$owner_id = $_profile->user_id;
	
	if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
	}
	else
	{
		$user_id = $owner_id;
	}
	
	list($user_club_id, $user_name, $user_flags, $user_city_id, $user_email, $user_langs, $user_phone) = Db::record(get_label('user'), 'SELECT club_id, name, flags, city_id, email, languages, phone FROM users WHERE id = ?', $user_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $user_club_id, $user_id);

	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark">' . get_label('User name') . ':</td><td class="light"><input id="form-name" value="' . $user_name . '"></td>';
	echo '</td><td align="center" valign="top" rowspan="8">';
	start_upload_logo_button();
	echo get_label('Change picture') . '<br>';
	$user_pic = new Picture(USER_PICTURE);
	$user_pic->set($user_id, $user_name, $user_flags);
	$user_pic->show(ICONS_DIR, false);
	end_upload_logo_button(USER_PIC_CODE, $user_id);
	echo '</td></tr>';
	
	$club_id = $user_club_id;
	if ($club_id == NULL)
	{
		$club_id = 0;
	}
	
	list ($city_name, $country_name) = Db::record(get_label('city'), 'SELECT ct.name_' . $_lang_code . ', cr.name_' . $_lang_code . ' FROM cities ct JOIN countries cr ON cr.id = ct.country_id WHERE ct.id = ?', $user_city_id);
	
	echo '<tr><td class="dark">' . get_label('Email') . ':</td><td class="light"><input id="form-email" value="' . $user_email . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country_name, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city_name, 'form-country');
	echo '</td></tr>';
	
	// echo '<tr><td>' . get_label('Main club') . ':</td><td>';
	// show_city_input('form-club', $city_name, 'form-country');
	// echo '</td></tr>';
	echo '<tr><td class="dark" valign="top">'.get_label('Main club').':</td><td class="light">';
	echo '<select id="form-club">';
	show_option(0, $club_id, '');
	$query = new DbQuery('SELECT id, name FROM clubs WHERE (flags & ' . CLUB_FLAG_RETIRED . ') = 0 ORDER BY name');
	while ($row = $query->next())
	{
		list ($c_id, $c_name) = $row;
		show_option($c_id, $club_id, $c_name);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
	if ($user_flags & USER_FLAG_MALE)
	{
		if ($user_flags & USER_ICON_MASK)
		{
			echo '<input type="radio" id="form-male" name="is_male" value="1" checked/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0"/>'.get_label('female');
		}
		else
		{
			echo '<input type="radio" id="form-male" name="is_male" value="1" onClick="document.profileForm.submit()" checked/>'.get_label('male').'<br>';
			echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()"/>'.get_label('female');
		}
	}
	else if ($user_flags & USER_ICON_MASK)
	{
		echo '<input type="radio" id="form-male" name="is_male" value="1"/>'.get_label('male').'<br>';
		echo '<input type="radio" name="is_male" value="0" checked/>'.get_label('female');
	}
	else
	{
		echo '<input type="radio" id="form-male" name="is_male" value="1" onClick="document.profileForm.submit()"/>'.get_label('male').'<br>';
		echo '<input type="radio" name="is_male" value="0" onClick="document.profileForm.submit()" checked/>'.get_label('female');
	}
	echo '</td></tr>';
	
	echo '<tr><td class="dark" valign="top">'.get_label('Languages').':</td><td class="light">';
	langs_checkboxes($user_langs);
	echo '</td></tr>';
	
	echo '<tr><td class="dark">' . get_label('Phone') . ':</td><td class="light"><input id="form-phone" value="' . $user_phone . '"></td></tr>';
	
	echo '</table>';
	
	echo '<p><input type="checkbox" id="form-message_notify"';
	if (($user_flags & USER_FLAG_MESSAGE_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone replies to me or sends me a private message.');
	echo '<br><input type="checkbox" id="form-photo_notify"';
	if (($user_flags & USER_FLAG_PHOTO_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone tags me on a photo.').'</p>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/user.php",
		{
			op: 'edit'
			, user_id: <?php echo $user_id; ?>
			, name: $("#form-name").val()
			, email: $("#form-email").val()
			, club_id: $("#form-club").val()
			, male: ($("#form-male").attr("checked") ? 1 : 0)
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, phone: $("#form-phone").val()
			, langs: languages
			, message_notify: ($("#form-message_notify").attr("checked") ? 1 : 0)
			, photo_notify: ($("#form-photo_notify").attr('checked') ? 1 : 0)
		},
		onSuccess);
	}
	
	function uploadLogo(onSuccess)
	{
		json.upload('api/ops/user.php', 
		{
			op: "edit"
			, user_id: <?php echo $user_id; ?>
			, picture: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
		onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>