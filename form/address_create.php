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
	
	check_permissions(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, $club_id);
	list($city_name, $country_name) = Db::record(get_label('club'),
		'SELECT i.name_' . $_lang_code . ', o.name_' . $_lang_code . ' FROM clubs c' .
			' JOIN cities i ON c.city_id = i.id' .
			' JOIN countries o ON i.country_id = o.id' .
			' WHERE c.id = ?', 
		$club_id);

	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="120">'.get_label('Address name').':</td><td><input id="form-name"></td></tr>';
	
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
		json.post("api/ops/address.php",
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, name: $("#form-name").val()
			, address: $("#form-address").val()
			, city: $("#form-city").val()
			, country: $("#form-country").val()
		},
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