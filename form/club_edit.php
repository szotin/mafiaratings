<?php

require_once '../include/session.php';
require_once '../include/club.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/image.php';
require_once '../include/url.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('club')));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$id = $_REQUEST['id'];
	
	if ($_profile == NULL || !$_profile->is_club_manager($id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$club = $_profile->clubs[$id];

	list ($url, $email, $phone, $price, $flags) =
		Db::record(get_label('club'), 'SELECT web_site, email, phone, price, flags FROM clubs WHERE id = ?', $id);
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Club name') . ':</td><td><input class="longest" id="form-club_name" value="' . htmlspecialchars($club->name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="9">';
	show_club_pic($id, $club->name, $flags, ICONS_DIR);
	echo '<p>';
	show_upload_button();
	echo '</p></td>';
	
	echo '</tr>';
	
	echo '<tr><td>' . get_label('Club system') . ':</td><td>';
	echo '<select id="form-parent">';
	show_option(0, $club->parent_id, '');
	$query = new DbQuery('SELECT id, name FROM clubs WHERE parent_id IS NULL AND (flags & ' . CLUB_FLAG_RETIRED . ') = 0 AND id <> ? ORDER BY name', $club->id);
	while ($row = $query->next())
	{
		list($c_id, $c_name) = $row;
		show_option($c_id, $club->parent_id, $c_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Web site').':</td><td><input id="form-url" class="longest" value="' . htmlspecialchars($url, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td valign="top">'.get_label('Languages').':</td><td>';
	langs_checkboxes($club->langs);
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Contact email').':</td><td><input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '"></td></tr>';
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td><input class="longest" id="form-phone" value="' . htmlspecialchars($phone, ENT_QUOTES) . '"></td></tr>';
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input class="longest" id="form-price" value="' . htmlspecialchars($club->price, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $club->country, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</td></tr>';
	
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $id);
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td><select id="form-scoring">';
	while ($row = $query->next())
	{
		list ($scoring_id, $scoring_name) = $row;
		echo '<option value="' . $scoring_id . '"';
		if ($scoring_id == $club->scoring_id)
		{
			echo ' selected';
		}
		echo '>' . $scoring_name . '</option>';
	} 
	echo '</select></td></tr>';
	
	echo '</table>';
	
	show_upload_script(CLUB_PIC_CODE, $id);
?>	
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/club.php",
		{
			op: "change"
			, club_id: <?php echo $id; ?>
			, parent_id: $("#form-parent").val()
			, name: $("#form-club_name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, price: $("#form-price").val()
			, city: $("#form-city").val()
			, country: $("#form-country").val()
			, scoring_id: $("#form-scoring").val()
			, langs: languages
		},
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
