<?php

require_once '../include/tournament.php';
require_once '../include/club.php';
require_once '../include/pages.php';
require_once '../include/user.php';
require_once '../include/scoring.php';
require_once '../include/picture.php';

define('ROW_COUNT', 10);
define('COULUMN_COUNT', 3);

try
{
	initiate_session();
	check_maintenance();
	
	//$club_pic = new Picture(CLUB_PICTURE);
	$tournament_user_pic =
		new Picture(USER_TOURNAMENT_PICTURE,
		new Picture(USER_CLUB_PICTURE,
		new Picture(USER_PICTURE)));
	
	list ($tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $scoring, $normalizer, $scoring_options) = 
		Db::record(get_label('tournament'), 
			'SELECT t.id, t.name, t.flags, c.id, c.name, c.flags, s.scoring, n.normalizer, t.scoring_options'.
			' FROM tournaments t'.
			' JOIN clubs c ON c.id = t.club_id'.
			' JOIN scoring_versions s ON s.scoring_id = t.scoring_id AND s.version = t.scoring_version'.
			' LEFT OUTER JOIN normalizer_versions n ON n.normalizer_id = t.normalizer_id AND n.version = t.normalizer_version'.
			' WHERE t.id = ?', $_REQUEST['id']);
	if (is_null($normalizer))
	{
		$normalizer = '{}';
	}
	$scoring = json_decode($scoring);
	$normalizer = json_decode($normalizer);
	$scoring_options = json_decode($scoring_options);
	$players = tournament_scores($tournament_id, $tournament_flags, NULL, SCORING_LOD_PER_GROUP, $scoring, $normalizer, $scoring_options);
	$players_count = count($players);
	if ($players_count == 0)
	{
		$query = new DbQuery('SELECT series_id FROM series_tournaments WHERE tournament_id = ? ORDER BY stars DESC LIMIT 1', $tournament_id);
		if ($row = $query->next())
		{
			list($series_id) = $row;
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, tu.flags, cu.flags, sp.score, sp.place'.
				' FROM tournament_users tu'.
				' JOIN users u ON u.id = tu.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN series_places sp ON sp.user_id = u.id AND series_id = ?'.
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?'.
				' WHERE tu.tournament_id = ? AND (tu.flags & ' . USER_PERM_PLAYER . ') <> 0 ORDER BY sp.score DESC, u.rating DESC, u.id', $series_id, $club_id, $tournament_id);
			while ($row = $query->next())
			{
				$player = new stdClass();
				list($player->id, $player->name, $player->flags, $player->tournament_user_flags, $player->club_user_flags, $player->points, $player->place) = $row;
				$players[] = $player;
				++$players_count;
			}
		}
	}
	else
	{
		$place = 0;
		foreach ($players as $player)
		{
			$player->place = ++$place;
		}
	}
	
	echo '<!DOCTYPE HTML>';
	echo '<html>';
	echo '<head>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	echo '</head><body>';
	
	$number = 0;
	echo '<table border="1"><tr>';
	for ($i = 0; $i < COULUMN_COUNT; ++$i)
	{
		for ($j = 0; $j < ROW_COUNT; ++$j)
		{
			if ($number >= $players_count)
			{
				break;
			}
			$player = $players[$number++];
			
			if ($j == 0)
			{
				echo '<td valign="top" style="padding-left:40px; padding-right:40px;"><table class="bordered light" width="100%">';
			}
			
			echo '<tr>';
			echo '<td align="center" class="dark"><big><big><b>' . $player->place . '</b></big></big></td>';
			echo '<td width="50">';
			$tournament_user_pic->
				set($player->id, $player->name, $player->tournament_user_flags, 't' . $tournament_id)->
				set($player->id, $player->name, $player->club_user_flags, 'c' . $club_id)->
				set($player->id, $player->name, $player->flags);
			echo '<img src="' . '../' . $tournament_user_pic->url(ICONS_DIR) . '" width="60">';
			echo '</td><td width="300"><big><big><b>' . $player->name . '</b></big></big></td>';
			echo '<td width="50" align="center">';
			// if (!is_null($player->club_id) && $player->club_id > 0)
			// {
				// $club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				// echo '<img src="' . '../' . $club_pic->url(ICONS_DIR) . '" width="40">';
			// }
			echo '</td>';
			
			echo '<td align="center" class="dark"><big><big><b>' . format_score($player->points) . '</b></big></big></td>';
			
			echo '</tr>';
		}
		echo '</table></td>';
	}
	echo '</tr></table>';
	echo '</body>';
}
catch (Exception $e)
{
	echo $e->getMessage();
	Exc::log($e);
}
?>

<script>
setTimeout(function() { window.location.replace(document.URL); }, <?php echo 60000; ?>);
</script>
