<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/security.php';
require_once '../include/city.php';

initiate_session();

try
{
	$user_id = 0;
	if (!is_null($_profile) && isset($_REQUEST['self']) && $_REQUEST['self'])
	{
		$user_id = $_profile->user_id;
		$user_name = $_profile->user_name;
	}
	else if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
		list ($user_name) = Db::record(get_label('user'), 'SELECT n.name FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
	}
	
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list($club_id, $name, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, name, flags FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
	
	
	list ($city_id, $city_name, $team_id, $team_name) = Db::record(get_label('registration'), 
		'SELECT r.city_id, n.name, r.team_id, t.name'.
		' FROM tournament_users r'.
		' JOIN cities c ON c.id = r.city_id' .
		' JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0'.
		' LEFT OUTER JOIN tournament_teams t ON t.id = r.team_id'.
		' WHERE r.user_id = ? AND r.tournament_id = ?', $user_id, $tournament_id);

	dialog_title(get_label('Edit [0] registration for [1]', $name, $user_name));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $city_name, -1, 'onCitySelect');
	echo '</tr>';
	
	if ($flags & TOURNAMENT_FLAG_TEAM)
	{
		echo '<tr><td>' . get_label('Team') . ':</td><td>';
		
		echo '<input type="text" id="form-team" value="' . $team_name . '" placeholder="' . get_label('Select team') . '" title="Select player\'s team in the tournament."/>';
		$url = 'api/control/team.php?tournament_id=' . $tournament_id . '&term=';
?>
		<script>
		$("#form-team").autocomplete(
		{ 
			source: function(request, response)
			{
				$.getJSON("<?php echo $url; ?>" + $("#form-team").val(), null, response);
			},
			minLength: 0
		})
		.on("focus", function () { $(this).autocomplete("search", ''); });
		</script>
<?php
		
		echo '</td></tr>';
	}
	else
	{
		echo '<input id="form-team" type="hidden" value="">';
	}
	echo '</table>';

?>
	<script>
	var cityId = <?php echo $city_id; ?>;
	function onCitySelect(_city)
	{
		cityId = _city.id;
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/tournament.php",
		{
			op: "edit_user"
			, user_id: <?php echo $user_id; ?>
			, tournament_id: <?php echo $tournament_id; ?>
			, city_id: cityId
			, team: $('#form-team').val()
		}, onSuccess);
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