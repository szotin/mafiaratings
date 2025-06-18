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
	dialog_title(get_label('Find address on google maps'));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('address')));
	}
	$address_id = $_REQUEST['id'];
	
	list ($name, $address, $map_url, $flags, $city, $country, $club_id) = 
		Db::record(
			get_label('address'), 
			'SELECT a.name, a.address, a.map_url, a.flags, ni.name, no.name, a.club_id FROM addresses a' .
				' JOIN cities i ON i.id = a.city_id' .
				' JOIN countries o ON o.id = i.country_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
				' WHERE a.id = ?',
			$address_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);

	echo '<p><input type="checkbox" id="form-picture"> ' . get_label('change address picture to the map image.') . '</p>';
?>	
	<script>
	function commit(onSuccess)
	{
		var params = 
		{
			op: "google_map"
			, address_id: <?php echo $address_id; ?>
			, picture: $('#form-picture').attr('checked') ? 1 : 0
		};
		json.post("api/ops/address.php", params, onSuccess);
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
