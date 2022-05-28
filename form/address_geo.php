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
			'SELECT a.name, a.address, a.map_url, a.flags, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', a.club_id FROM addresses a' .
				' JOIN cities i ON i.id = a.city_id' .
				' JOIN countries o ON o.id = i.country_id' .
				' WHERE a.id = ?',
			$address_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);

	echo '<p><input type="checkbox" id="form-picture" checked> ' . get_label('change address picture to the map image.') . '</p>';
	echo '<p><input type="checkbox" id="form-url" checked> ' . get_label('add google maps link to the address.') . '</p>';
?>	
	<script>
	function commit(onSuccess)
	{
		var params = 
		{
			op: "google_map"
			, address_id: <?php echo $address_id; ?>
			, picture: $('#form-picture').attr('checked') ? 1 : 0
			, url: $('#form-url').attr('checked') ? 1 : 0
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
