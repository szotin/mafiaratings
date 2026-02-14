<?php

require_once '../include/page_base.php';
require_once '../include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = $_REQUEST['league_id'];

	list($name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
	$is_manager = is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id);
	$can_add = false;
	
	dialog_title(get_label('Add club'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('Club') . ':</td><td><select id="form-club">';
	$query = new DbQuery('SELECT c.id, c.name FROM clubs c WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0 AND c.id NOT IN (SELECT club_id FROM league_clubs WHERE league_id = ?) ORDER BY name', $league_id);
	while ($row = $query->next())
	{
		list($club_id, $club_name) = $row;
		if ($is_manager || is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			show_option((int)$club_id, 0, $club_name);
			$can_add = true;
		}
	}
	echo '</select></td></tr>';
	echo '</table>';
	
	if (!$can_add)
	{
		throw new FatalExc(get_label('There is no clubs you can add to [0]', $name));
	}

?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/league.php",
		{
			op: "add_club"
			, league_id: <?php echo $league_id; ?>
			, club_id: $("#form-club").val()
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