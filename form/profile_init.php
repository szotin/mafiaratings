<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/user.php';
require_once '../include/image.php';

initiate_session();

try
{
	dialog_title(get_label('Please answer a few questions about yourself'));
	if ($_profile == NULL)
	{
		throw new FatalExc('No permissions');
	}
	
	echo '<table class="bordered" width="100%">';
	
	list ($city_name, $country_name) = Db::record(get_label('city'), 
		'SELECT nct.name, ncr.name FROM cities ct' .
		' JOIN countries cr ON cr.id = ct.country_id' .
		' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
		' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
		' WHERE ct.id = ?', $_profile->city_id);
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country_name, 'form-city', 'updateClub');
	echo '</td>';
	
	echo '<td width="' . ICON_WIDTH . '" align="center" valign="top" rowspan="8">';
	start_upload_logo_button($_profile->user_id);
	echo get_label('Change picture') . '<br>';
	$user_pic = new Picture(USER_PICTURE);
	$user_pic->set($_profile->user_id, $_profile->user_name, $_profile->user_flags);
	$user_pic->show(ICONS_DIR, false);
	end_upload_logo_button(USER_PIC_CODE, $_profile->user_id);
	echo '</td></tr>';

	$club_id = $_profile->user_club_id;
	if ($club_id == NULL)
	{
		$club_id = 0;
	}
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city_name, 'form-country', 'updateClub');
	echo '</td></tr>';
	
	echo '<tr><td width="120" valign="top">' . get_label('Gender') . ':</td><td>';
	echo '<input type="radio" name="form-is_male" id="form-male" value="1"';
	if ($_profile->user_flags & USER_FLAG_MALE)
	{
		echo ' checked';
	}
	echo '/>'.get_label('male').'<br>';
		
	echo '<input type="radio" name="form-is_male" id="form-female" value="0"';
	if (($_profile->user_flags & USER_FLAG_MALE) == 0)
	{
		echo ' checked';
	}
	echo '/>'.get_label('female');
	echo '</td></tr>';
	
	echo '<tr><td valign="top">'.get_label('Languages').':</td><td>';
	langs_checkboxes($_profile->user_langs);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Phone') . ':</td><td>';
	echo '<input id="form-phone" value="' . $_profile->user_phone . '"></td></tr>';
	
	echo '<tr><td valign="top">'.get_label('Main club').':</td><td>';
	echo '<select id="form-club">';
	show_option(0, $club_id, '');
	$query = new DbQuery('SELECT id, name FROM clubs ORDER BY name');
	while ($row = $query->next())
	{
		list ($c_id, $c_name) = $row;
		show_option($c_id, $club_id, $c_name);
	}
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Password').':</td><td><input type="password" id="form-pwd"></td></tr>';
	echo '<tr><td>'.get_label('Confirm password').':</td><td><input type="password" id="form-confirm"></td></tr>';
		
	echo '</table>';
		
?>	
	<script>
	var clubSetManually = <?php echo $club_id > 0 ? 'true' : 'false'; ?>;
	function updateClub()
	{
		if (!clubSetManually)
		{
			var languages = mr.getLangs();
			json.post("api/ops/account.php", 
			{ 
				op: 'suggest_club'
				, langs: languages
				, city: $("#form-city").val()
				, country: $("#form-country").val()
			}, 
			function(obj)
			{
				$("#form-club").val(obj['club_id']);
			});
		}
	}
	
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		var isMale = $("#form-male").attr("checked") ? 1 : 0;
		var clubId = parseInt($("#form-club").val());
		var params =
		{
			op: 'edit'
			, pwd1: $("#form-pwd").val()
			, pwd2: $("#form-confirm").val()
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, phone: $("#form-phone").val()
			, langs: languages
			, male: isMale
			, club_id: clubId
		};
		json.post("api/ops/user.php", params, onSuccess);
	}
	
	$("#form-club").change(function() { clubSetManually = true; });
	$("#form-country").on( "autocompletechange", updateClub );
	$("#form-city").on( "autocompletechange", updateClub );
	$("#ru" ).change(updateClub);
	$("#en" ).change(updateClub);
	updateClub();
	
	function uploadLogo(userId, onSuccess)
	{
		json.upload('api/ops/user.php', 
		{
			op: "edit",
			picture: document.getElementById("upload").files[0]
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