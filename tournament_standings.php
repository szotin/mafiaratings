<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

function score_title($points, $raw_points, $normalization)
{
	if ($normalization != 1 && $points != 0)
	{
		return ' title="' . format_score($raw_points) . ' * ' . format_coeff($normalization) . ' = ' . format_score($points) . '"';
	}
	return '';
}

class Page extends TournamentPageBase
{
	private $scoring;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		
		$this->tournament_player_params = '&id=' . $this->id;
		if (isset($_REQUEST['sid']))
		{
			$this->scoring_id = (int)$_REQUEST['sid'];
			$this->tournament_player_params .= '&sid=' . $this->scoring_id;
			if (isset($_REQUEST['sver']))
			{
				$this->scoring_version = (int)$_REQUEST['sver'];
				$this->tournament_player_params .= '&sver=' . $this->scoring_version;
				list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
			}
			else
			{
				list($this->scoring, $this->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->scoring_id);
			}
		}
		else
		{
			list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		}
		$this->scoring = json_decode($this->scoring);
		
		$this->normalizer = '{}';
		if (isset($_REQUEST['nid']))
		{
			$this->normalizer_id = (int)$_REQUEST['nid'];
			$this->tournament_player_params .= '&nid=' . $this->normalizer_id;
			if ($this->normalizer_id > 0)
			{
				if (isset($_REQUEST['nver']))
				{
					$this->normalizer_version = (int)$_REQUEST['nver'];
					$this->tournament_player_params .= '&nver=' . $this->normalizer_version;
					list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
				}
				else
				{
					list($this->normalizer, $this->normalizer_version) = Db::record(get_label('normalizer'), 'SELECT normalizer, version FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $this->normalizer_id);
				}
			}
		}
		else if (!is_null($this->normalizer_id) && $this->normalizer_id > 0)
		{
			list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
		}
		$this->normalizer = json_decode($this->normalizer);
		$this->has_normalizer = isset($this->normalizer->policies) && count($this->normalizer->policies) > 0;
		
		$this->scoring_options = json_decode($this->scoring_options);
		if (isset($_REQUEST['sops']))
		{
			$this->tournament_player_params .= '&sops=' . rawurlencode($_REQUEST['sops']);
			$ops = json_decode($_REQUEST['sops']);
			foreach($ops as $key => $value) 
			{
				$this->scoring_options->$key = $value;
			}
		}
		$this->tournament_player_params .= '&bck=1';
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery('SELECT u.name, u.club_id, u.city_id, c.country_id FROM users u JOIN cities c ON c.id = u.city_id WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
				$this->_title .= ' ' . get_label('Following [0].', $this->user_name);
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		if ($this->flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_OPTIONS;
		}
		else
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION;
		}
		
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'submitScoring', $scoring_select_flags);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'tournament=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table>';
		
		$condition = new SQL(' AND g.tournament_id = ?', $this->id);
		
		$players = tournament_scores($this->id, $this->flags, null, SCORING_LOD_PER_GROUP, $this->scoring, $this->normalizer, $this->scoring_options);
		$players_count = count($players);
		if ($this->user_id > 0)
		{
			$_page = get_user_page($players, $this->user_id, PAGE_SIZE);
			if ($_page < 0)
			{
				$_page = 0;
				$this->no_user_error();
			}
		}
		
		show_pages_navigation(PAGE_SIZE, $players_count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="3" rowspan="2">'.get_label('Player').'</td>';
		echo '<td width="36" align="center" colspan="6">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Winning %').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Points per game').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Rounds played').'</td>';
		if ($this->has_normalizer)
		{
			echo '<td width="36" align="center" rowspan="2">'.get_label('Normalization rate').'</td>';
		}
		echo '</tr>';
		echo '<tr class="th darker" align="center"><td width="36">' . get_label('Sum') . '</td><td width="36">' . get_label('Main') . '</td><td width="36">' . get_label('Guess') . '</td><td width="36">' . get_label('Extra') . '</td><td width="36">' . get_label('Penlt') . '</td><td width="36">' . get_label('FK') . '</td></tr>';
		
		$page_start = $_page * PAGE_SIZE;
		if ($players_count > $page_start + PAGE_SIZE)
		{
			$players_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $players_count; ++$number)
		{
			$player = $players[$number];
			if ($player->id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darkest';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}
			echo '<td align="center" class="' . $highlight . '">' . ($number + 1) . '</td>';
			echo '<td width="50"><a href="tournament_player_games.php?user_id=' . $player->id . $this->tournament_player_params . '">';
			$this->user_pic->set($player->id, $player->name, $player->flags);
			$this->user_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="tournament_player_games.php?user_id=' . $player->id . $this->tournament_player_params . '">' . $player->name . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($player->club_id) && $player->club_id > 0)
			{
				$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '"' . score_title($player->points, $player->raw_points, $player->normalization) . '>' . format_score($player->points) . '</td>';
			echo '<td align="center"' . score_title($player->main_points, $player->raw_main_points, $player->normalization) . '>' . format_score($player->main_points) . '</td>';
			echo '<td align="center"' . score_title($player->legacy_points, $player->raw_legacy_points, $player->normalization) . '>' . format_score($player->legacy_points) . '</td>';
			echo '<td align="center"' . score_title($player->extra_points, $player->raw_extra_points, $player->normalization) . '>' . format_score($player->extra_points) . '</td>';
			echo '<td align="center"' . score_title($player->penalty_points, $player->raw_penalty_points, $player->normalization) . '>' . format_score($player->penalty_points) . '</td>';
			echo '<td align="center"' . score_title($player->night1_points, $player->raw_night1_points, $player->normalization) . '>' . format_score($player->night1_points) . '</td>';
			echo '<td align="center">' . $player->games_count . '</td>';
			echo '<td align="center">' . $player->wins . '</td>';
			if ($player->games_count != 0)
			{
				echo '<td align="center">' . number_format(($player->wins * 100.0) / $player->games_count, 1) . '%</td>';
				echo '<td align="center">';
				echo format_score($player->raw_points / $player->games_count);
				echo '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			echo '<td align="center">' . $player->events_count . '</td>';
			if ($this->has_normalizer)
			{
				echo '<td align="center">' . format_coeff($player->normalization) . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("tournament", <?php echo $this->id; ?>, 5);
			
			function submitScoring(s)
			{
				goTo({ sid: s.sId, sver: s.sVer, nid: s.nId, nver: s.nVer, sops: s.ops, page: 0 });
			}
		</script>
<?php
	}
	
	
	private function no_user_error()
	{
		$this->errorMessage(get_label('[0] did not play in [1].', $this->user_name, $this->name));
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
