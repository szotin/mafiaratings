<?php

require_once '../include/session.php';
require_once '../include/club.php';
require_once '../include/address.php';
require_once '../include/country.php';
require_once '../include/city.php';
require_once '../include/image.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('address')));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('address')));
	}
	$id = $_REQUEST['id'];
	
	list ($name, $address, $map_url, $flags, $city, $country, $club_id) = 
		Db::record(
			get_label('address'), 
			'SELECT a.name, a.address, a.map_url, a.flags, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', a.club_id FROM addresses a' .
				' JOIN cities i ON i.id = a.city_id' .
				' JOIN countries o ON o.id = i.country_id' .
				' WHERE a.id = ?',
			$id);
			
	if ($_profile == NULL || !$_profile->is_club_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('Address name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="5">';
	start_upload_logo_button();
	echo get_label('Change logo') . '<br>';
	$address_pic = new Picture(ADDRESS_PICTURE);
	$address_pic->set($id, $name, $flags);
	$address_pic->show(ICONS_DIR, false);
	end_upload_logo_button(ADDRESS_PIC_CODE, $id);
	echo '</td>';
	
	echo '</tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $country, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city, 'form-country');
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Address').':</td><td><input class="longest" id="form-address" value="' . htmlspecialchars($address, ENT_QUOTES) . '"></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params = 
		{
			op: "change"
			, address_id: <?php echo $id; ?>
			, name: $("#form-name").val()
			, address: $("#form-address").val()
			, city: $("#form-city").val()
			, country: $("#form-country").val()
		};
		json.post("api/ops/address.php", params, onSuccess);
	}
	
	function uploadLogo(onSuccess)
	{
		json.upload('api/ops/address.php', 
		{
			op: "change",
			address_id: <?php echo $id; ?>,
			logo: document.getElementById("upload").files[0]
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
	Exc::log($e, true);
	echo '<error=' . $e->getMessage() . '>';
}

?>
