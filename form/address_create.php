<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/club.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('address')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	
	check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_CLUB_MANAGER, $club_id);
	list($city_name, $country_name) = Db::record(get_label('club'),
		'SELECT ni.name, no.name FROM clubs c' .
			' JOIN cities i ON c.city_id = i.id' .
			' JOIN countries o ON i.country_id = o.id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE c.id = ?', 
		$club_id);

	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="120" valign="top">'.get_label('Address name').':</td><td>';
	Names::show_control(new Names(0, get_label('Address name'), NULL, 0, NULL, 'name', false));
	echo '</td></tr>';

	// The logo, as in form/tournament_create.php: there is no address to give it to yet, so it is only
	// chosen here - and shown - and is uploaded by commit() once the address is made. Without one the
	// address shows the piece of the map the site makes for it. The picture is the button that chooses it.
	echo '<tr><td>' . get_label('Logo') . ':</td><td>';
	echo '<input type="file" id="form-logo" accept="image/*" style="display:none" onchange="logoChosen()">';
	echo '<button type="button" class="upload" onclick="$(\'#form-logo\').trigger(\'click\');">';
	echo '<img id="form-logo-img" src="images/' . ICONS_DIR . 'address.png" width="56" height="56"></button>';
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country_name, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city_name, 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Address').':</td><td><input id="form-address"></td></tr>';
	echo '</table>';
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, address: $("#form-address").val()
			, city: $("#form-city").val()
			, country: $("#form-country").val()
		};
		nameControl.fillRequest(params);
		json.post("api/ops/address.php", params, function(obj)
		{
			// The address is made whatever happens to its logo: a logo that fails to upload is said,
			// and can be changed on the page the address is managed on.
			var logo = document.getElementById('form-logo').files[0];
			if (!logo)
			{
				onSuccess(obj);
				return;
			}
			json.upload('api/ops/address.php',
			{
				op: "change"
				, address_id: obj.address_id
				, logo: logo
			},
			<?php echo UPLOAD_LOGO_MAX_SIZE; ?>,
			function() { onSuccess(obj); },
			function(message) { dlg.error(message, null, null, function() { onSuccess(obj); }); return true; });
		});
	}

	function logoChosen()
	{
		var file = document.getElementById('form-logo').files[0];
		if (file && window.URL)
		{
			var url = URL.createObjectURL(file);
			$('#form-logo-img').attr('src', url);
			// The summary beside the form shows it too, where there is one - see create.php.
			$('#wz-sum-logo-img').attr('src', url);
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