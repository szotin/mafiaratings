<?php

require_once 'include/session.php';
require_once 'include/user.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';
require_once 'include/image.php';
require_once 'include/languages.php';

initiate_session();

try
{
	dialog_title(get_label('Settings'));
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark">' . get_label('User name') . ':</td><td class="light"><input id="form-name" value="' . $_profile->user_name . '"></td>';
	echo '</td><td align="center" valign="top" rowspan=7>';
	show_user_pic($_profile->user_id, $_profile->user_name, $_profile->user_flags, ICONS_DIR);
	echo '<p>';
	show_upload_button();
	echo '</p></td></tr>';
	
	$club_id = $_profile->user_club_id;
	if ($club_id == NULL)
	{
		$club_id = 0;
	}
	
	echo '<tr><td class="dark">' . get_label('Email') . ':</td><td class="light"><input id="form-email" value="' . $_profile->user_email . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $_profile->country, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $_profile->city, 'form-country');
	echo '</td></tr>';
	
	// echo '<tr><td>' . get_label('Main club') . ':</td><td>';
	// show_city_input('form-club', $_profile->city, 'form-country');
	// echo '</td></tr>';
	echo '<tr><td class="dark" valign="top">'.get_label('Main club').':</td><td class="light">';
	echo '<select id="form-club">';
	show_option(0, $club_id, '');
	$query = new DbQuery('SELECT id, name FROM clubs WHERE (flags & ' . CLUB_FLAG_RETIRED . ') = 0 ORDER BY name');
	while ($row = $query->next())
	{
		list ($cid, $cname) = $row;
		show_option($cid, $club_id, $cname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
	if ($_profile->user_flags & U_FLAG_MALE)
	{
		if ($_profile->user_flags & U_ICON_MASK)
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
	else if ($_profile->user_flags & U_ICON_MASK)
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
	langs_checkboxes($_profile->user_langs);
	echo '</td></tr>';
	
	echo '<tr><td class="dark">' . get_label('Phone') . ':</td><td class="light"><input id="form-phone" value="' . $_profile->user_phone . '"></td></tr>';
	
	echo '</table>';
	
	echo '<p><input type="checkbox" id="form-message_notify"';
	if (($_profile->user_flags & U_FLAG_MESSAGE_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone replies to me or sends me a private message.');
	echo '<br><input type="checkbox" id="form-private_message_notify"';
	if (($_profile->user_flags & U_FLAG_PHOTO_NOTIFY) != 0)
	{
		echo ' checked';
	}
	echo '>'.get_label('I would like to receive emails when someone tags me on a photo.').'</p>';
	echo '</table>';
	
	show_upload_script(USER_PIC_CODE, $_profile->user_id);
?>
	<script>
	function doCommit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("profile_ops.php",
		{
			name: $("#form-name").val(),
			email: $("#form-email").val(),
			club: $("#form-club").val(),
			male: ($("#form-male").attr("checked") ? 1 : 0),
			country: $("#form-country").val(),
			city: $("#form-city").val(),
			phone: $("#form-phone").val(),
			langs: languages,
			message_notify: ($("#form-message_notify").attr("checked") ? 1 : 0),
			private_message_notify: ($("#form-private_message_notify").attr('checked') ? 1 : 0),
			edit_account: ""
		},
		onSuccess);
	}
	
	function commit(onSuccess)
	{
		if ($("#form-email").val() != "<?php echo $_profile->user_email; ?>")
		{
			dlg.yesNo(
				"<?php echo get_label('<p>Changing your email address deactivates your account. You will need to activate it back using your new email address.</p><p>Do you want to change it?</p>'); ?>", null, null,
				function()
				{
					doCommit(onSuccess);
				});
		}
		else
		{
			doCommit(onSuccess);
		}
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