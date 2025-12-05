<?php

require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';
require_once 'include/scoring.php';
require_once 'include/datetime.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('FLAG_FILTER_RATING', 0x0001);
define('FLAG_FILTER_NO_RATING', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NO_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends SeriesPageBase
{
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_page, $_profile, $_lang;
		
		parent::prepare();
		
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->user_id = 0;
		$this->user_name = '';
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery(
				'SELECT nu.name, u.club_id, u.city_id, c.country_id'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities c ON c.id = u.city_id'.
				' WHERE u.id = ?', $this->user_id);
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
		global $_page, $_profile, $_lang;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('referees')));
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('rating games'), get_label('canceled games')), $this->filter);
		echo '</td><td align="right"><img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, '', get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		$subseries_csv = get_subseries_csv($this->id);
		$condition = new SQL(' WHERE st.series_id IN ('.$subseries_csv.')');
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND u.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		if ($this->filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($this->filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery(
				'SELECT count(g.id)'.
				' FROM users u'.
				' JOIN games g ON g.moderator_id = u.id'.
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id',
				$condition);
			$pos_query->add(' AND u.id = ?', $this->user_id);
			if ($row = $pos_query->next())
			{
				list ($u_games) = $row;
				if ($u_games > 0)
				{
					$pos_query = new DbQuery(
						'SELECT count(*)'.
						' FROM (SELECT u.id FROM users u'.
						' JOIN games g ON g.moderator_id = u.id'.
						' JOIN series_tournaments st ON st.tournament_id = g.tournament_id',
						$condition);
					$pos_query->add(' GROUP BY u.id HAVING count(g.id) > ? OR (count(g.id) = ? AND u.id < ?)) as prev', $u_games, $u_games, $this->user_id);
					list($user_pos) = $pos_query->next();
					$_page = floor($user_pos / PAGE_SIZE);
				}
				else
				{
					$this->errorMessage(get_label('[0] refereed no games.', $this->user_name));
				}
			}
			else
			{
				$this->errorMessage(get_label('[0] refereed no games.', $this->user_name));
			}
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g JOIN users u ON u.id = g.moderator_id JOIN series_tournaments st ON st.tournament_id = g.tournament_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$moders = array();
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, COUNT(DISTINCT g.tournament_id), SUM(IF(g.result = ' . GAME_RESULT_TOWN . ', 1, 0)) AS civ, SUM(IF(g.result = ' . GAME_RESULT_MAFIA . ', 1, 0)) AS maf, SUM(IF(g.result = ' . GAME_RESULT_TIE . ', 1, 0)) AS tie, c.id, c.name, c.flags' . 
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id'.
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id',
				$condition);
 		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC, u.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			$moder = new stdClass();
			list($moder->id, $moder->name, $moder->flags, $moder->tournaments, $moder->red_wins, $moder->black_wins, $moder->ties, $moder->club_id, $moder->club_name, $moder->club_flags) = $row;
			$moders[] = $moder;
		}
		
		$query = new DbQuery(
			'SELECT u.id, SUM(p.extra_points), SUM(p.warns),'.
			' SUM(IF((p.flags & ' . SCORING_FLAG_WORST_MOVE . ') = 0, 0, 1)),'.
			' SUM(IF((p.flags & ' . (SCORING_FLAG_WARNINGS_4 | SCORING_FLAG_KICK_OUT | SCORING_FLAG_SURRENDERED | SCORING_FLAG_TEAM_KICK_OUT) . ') = 0, 0, 1)),' . 
			' SUM(IF((p.flags & ' . SCORING_FLAG_TEAM_KICK_OUT . ') = 0, 0, 1))' . 
				' FROM users u' .
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id'.
				' JOIN players p ON p.game_id = g.id',
				$condition);
 		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC, u.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$i = 0;
		while ($row = $query->next())
		{
			$moder = $moders[$i++];
			list($id, $moder->bonus, $moder->warnings, $moder->worst_moves, $moder->kick_offs, $moder->team_kick_offs) = $row;
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="20">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Tournaments refereed').'</td>';
		echo '<td width="60" align="center">'.get_label('Games refereed').'</td>';
		echo '<td width="60" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Mafia wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Ties').'</td>';
		echo '<td width="80" align="center">'.get_label('Warnings').'</td>';
		echo '<td width="80" align="center">'.get_label('Mod kills').'</td>';
		echo '<td width="80" align="center">'.get_label('Mod team kills').'</td>';
		echo '<td width="80" align="center">'.get_label('Removed auto-bonus').'</td>';
		echo '<td width="80" align="center">'.get_label('Bonus points').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		foreach ($moders as $moder)
		{
			++$number;

            if ($moder->id == $this->user_id)
            {
                echo '<tr class="dark">';
            }
            else
            {
                echo '<tr>';
            }
			echo '<td align="center" width="40" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$this->user_pic->set($moder->id, $moder->name, $moder->flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '<td><a href="user_games.php?id=' . $moder->id . '&moder=1&bck=1">' . $moder->name . '</a></td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($moder->club_id, $moder->club_name, $moder->club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			
			$games = $moder->red_wins + $moder->black_wins + $moder->ties;
			
			echo '<td align="center">' . $moder->tournaments . '</td>';
			echo '<td align="center" class="dark">' . $games . '</td>';
			if ($moder->red_wins > 0)
			{
				echo '<td align="center">' . $moder->red_wins . '<br><i>' . number_format(($moder->red_wins*100.0)/$games, 1) . '%</i></td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($moder->black_wins > 0)
			{
				echo '<td align="center">' . $moder->black_wins . '<br><i>' . number_format(($moder->black_wins*100.0)/$games, 1) . '%</i></td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($moder->ties > 0)
			{
				echo '<td align="center">' . $moder->ties . '<br><i>' . number_format(($moder->ties*100.0)/$games, 1) . '%</i></td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($moder->warnings > 0)
			{
				echo '<td align="center">' . $moder->warnings . '<br><i>' . number_format($moder->warnings/$games, 2) . ' ' . get_label('per game') . '</i></td>';
			}
			else
			{
				echo '<td align="center"></td>';
			}
			if ($moder->kick_offs > 0)
			{
				echo '<td align="center">' . $moder->kick_offs . '<br><i>' . number_format($moder->kick_offs/$games, 2) . ' ' . get_label('per game') . '</i></td>';
			}
			else
			{
				echo '<td align="center"></td>';
			}
			if ($moder->team_kick_offs > 0)
			{
				echo '<td align="center">' . $moder->team_kick_offs . '<br><i>' . number_format($moder->team_kick_offs/$games, 2) . ' ' . get_label('per game') . '</i></td>';
			}
			else
			{
				echo '<td align="center"></td>';
			}
			if ($moder->worst_moves > 0)
			{
				echo '<td align="center">' . $moder->worst_moves . '<br><i>' . number_format($moder->worst_moves/$games, 2) . ' ' . get_label('per game') . '</i></td>';
			}
			else
			{
				echo '<td align="center"></td>';
			}
			if ($moder->bonus > 0)
			{
				echo '<td align="center">' . number_format($moder->bonus, 1) . '<br><i>' . number_format($moder->bonus/$games, 2) . ' ' . get_label('per game') . '</i></td>';
			}
			else
			{
				echo '<td align="center"></td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	private function no_user_error()
	{
		global $_profile;
		
		$member = true;
		$ccc_value = $this->ccc_filter->get_value();
		if ($ccc_value != NULL)
		{
			$id = $this->ccc_filter->get_id();
			switch ($this->ccc_filter->get_type())
			{
			case CCCF_CLUB:
				if ($id == 0)
				{
					$member = ($_profile != NULL && isset($_profile->clubs[$this->user_club_id]));
				}
				else
				{
					$member = ($id == $this->user_club_id);
				}
				break;
			case CCCF_CITY:
				$member = ($id == $this->user_city_id);
				break;
			case CCCF_COUNTRY:
				$member = ($id == $this->user_country_id);
				break;
			}
		}
		
		if (!$member)
		{
			$message = get_label('[0] is not from [1].', $this->user_name, $ccc_value);
		}
		else
		{
			$message = get_label('[0] refereed no games.', $this->user_name);
		}
		$this->errorMessage($message);
	}
}

$page = new Page();
$page->run(get_label('Referees'));

?>