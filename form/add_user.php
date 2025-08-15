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
	}
	else if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
	}
	
	// Note that PERMISSION_OWNER is allowed for tournaments only
	// If we need to implement is for events and clubs in the future - we need to implement manager acceptance, as it is done for the tournaments. 
	$club_id = 0;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list($club_id, $tour_id, $name) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, name FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		list($club_id, $name, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, name, flags FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
	}
	else if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	}
	
	if (isset($_profile->clubs[$club_id]))
	{
		$club_name = $_profile->clubs[$club_id]->name;
	}
	else
	{
		list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
	}

	if (isset($event_id) || isset($tournament_id))
	{
		dialog_title(get_label('Add new participant to [0]', $name));
	}
	else 
	{
		dialog_title(get_label('Add new member to [0]', $club_name));
	}
	
	echo '<table class="dialog_form" width="100%">';
	if ($user_id <= 0)
	{
		echo '<tr><td width="120">' . get_label('Player') . ':</td><td>';
		show_user_input('form-user', '', '', get_label('Select player.'), 'onSelect');
		echo '</td></tr>';
	}
	if (isset($tournament_id))
	{
		$city_name = '';
		if ($user_id > 0)
		{
			list ($city_name) = Db::record(get_label('user'), 
				'SELECT nc.name FROM users u'.
				' JOIN cities c ON c.id = u.city_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE u.id = ?', $user_id);
		}
		
		echo '<tr><td>' . get_label('City') . ':</td><td>';
		show_city_input('form-city', $city_name, -1, 'onCitySelect');
		echo '</tr>';
		
		if ($flags & TOURNAMENT_FLAG_TEAM)
		{
			echo '<tr><td>' . get_label('Team') . ':</td><td>';
			
			echo '<input type="text" id="form-team" placeholder="' . get_label('Select team') . '" title="Select player\'s team in the tournament."/>';
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
	}
	echo '</table>';

?>
	<script>
	var userId = <?php echo $user_id; ?>;
	var cityId = 0;
	function onSelect(_user)
	{
		userId = _user.id;
		$("#form-city").val('');
		if (userId > 0)
		{
			json.post("api/get/players.php", { user_id: userId }, 
				function (data)
				{
					if (isArray(data.players) && data.players.length > 0 && isSet(data.players[0].city_id))
					{
						$("#form-city").val(data.players[0].city);
					}
					else
					{
						$("#form-city").val('');
					}
					onCitySelect({id:0})
				});				
		}
	}
	
	function onCitySelect(_city)
	{
		console.log(_city);
		cityId = _city.id;
	}
	
	function commit(onSuccess)
	{
		if (userId <= 0)
		{
			dlg.error("<?php echo get_label('Unknown [0]', get_label('player')); ?>");
			return;
		}
<?php
		
	if (isset($event_id))
	{
?>
		json.post("api/ops/event.php",
		{
			op: "add_user"
			, user_id: userId
			, event_id: <?php echo $event_id; ?>
		}, onSuccess);
<?php
	}
	else if (isset($tournament_id))
	{
		if ($flags & TOURNAMENT_FLAG_TEAM)
		{
?>
			json.post("api/ops/tournament.php",
			{
				op: "add_user"
				, user_id: userId
				, city_id: cityId
				, tournament_id: <?php echo $tournament_id; ?>
				, team: $('#form-team').val()
			}, onSuccess);
<?php
		}
		else
		{
?>
			json.post("api/ops/tournament.php",
			{
				op: "add_user"
				, user_id: userId
				, city_id: cityId
				, tournament_id: <?php echo $tournament_id; ?>
			}, onSuccess);
<?php
		}
	}
	else
	{
?>
		json.post("api/ops/club.php",
		{
			op: "add_user"
			, user_id: userId
			, club_id: <?php echo $club_id; ?>
		}, onSuccess);
<?php
	}
?>
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