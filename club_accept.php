<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/club.php';

initiate_session();

try
{
	dialog_title(get_label('Accept club request'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$id = $_REQUEST['id'];
	
	list($name, $city_id, $city, $country, $url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone) = Db::record(
		get_label('club'),
		'SELECT c.name, c.city_id, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone FROM club_requests c' .
			' JOIN users u ON c.user_id = u.id' .
			' JOIN cities i ON c.city_id = i.id' .
			' JOIN countries o ON i.country_id = o.id' .
			' WHERE c.id = ?',
		$id);
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('User') . ':</td><td><a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td></tr>';
	echo '<tr><td>' . get_label('Club name') . ':</td><td><input class="longest" id="form-name" value="' . $name . '"></td></tr>';
	echo '<tr><td>' . get_label('Web site') . ':</td><td><a href="' . $url . '" target="_blank">' . $url . '</a></td></tr>';
	echo '<tr><td>'.get_label('Languages').':</td><td>' . get_langs_str($langs, ', ') . '</td><tr>';
	echo '<tr><td>'.get_label('Contact email').':</td><td><a href="mailto:' . $email . '">' . $email . '</a></td><tr>';
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>' . $phone . '</td><tr>';
	echo '<tr><td>'.get_label('City').':</td><td>' . $city . ', ' . $country . '</td><tr>';
	
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("club_ops.php",
			{
				id: <?php echo $id; ?>,
				name: $("#form-name").val(),
				accept: ""
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
	echo $e->getMessage();
}

?>