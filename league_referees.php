<?php

require_once 'include/league.php';
require_once 'include/ccc_filter.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';
require_once 'include/scoring.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('FLAG_FILTER_RATING', 0x0001);
define('FLAG_FILTER_NO_RATING', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NO_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends LeaguePageBase
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
		
		
		$condition = new SQL(' WHERE s.league_id = ?', $this->id);
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
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id'.
				' JOIN series s ON s.id = st.series_id',
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
						' JOIN series_tournaments st ON st.tournament_id = g.tournament_id'.
						' JOIN series s ON s.id = st.series_id',
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
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g JOIN users u ON u.id = g.moderator_id JOIN series_tournaments st ON st.tournament_id = g.tournament_id JOIN series s ON s.id = st.series_id', $condition);
		// The filters, the search and the count in a toolbar, as on ratings.php; the three-way filters
		// go on a line of their own under them.
		echo '<div class="toolbar">';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('referees')));
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, '', get_label('Go to the page where a specific player is located.'));
		echo '</div><span class="count">' . get_label('[0] referees', number_format($count)) . '</span>';
		show_checkbox_filter(array(get_label('rating games'), get_label('canceled games')), $this->filter);
		echo '</div>';
		show_pages_navigation(PAGE_SIZE, $count);
		
		$moders = array();
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, COUNT(DISTINCT g.tournament_id), SUM(IF(g.result = ' . GAME_RESULT_TOWN . ', 1, 0)) AS civ, SUM(IF(g.result = ' . GAME_RESULT_MAFIA . ', 1, 0)) AS maf, SUM(IF(g.result = ' . GAME_RESULT_TIE . ', 1, 0)) AS tie, c.id, nc.name, c.flags' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id'.
				' JOIN series s ON s.id = st.series_id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0',
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
				' JOIN series s ON s.id = st.series_id'.
				' JOIN players p ON p.game_id = g.id',
				$condition);
 		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC, u.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$i = 0;
		while ($row = $query->next())
		{
			$moder = $moders[$i++];
			list($id, $moder->bonus, $moder->warnings, $moder->worst_moves, $moder->kick_offs, $moder->team_kick_offs) = $row;
		}
		
		// The table of the v3 design, after ratings.php: the place, the referee with a round picture,
		// the club with its name, the counts - each with the share of the games or the rate per game
		// it comes to under it.
		echo '<div class="sheet"><table class="tbl mid th-wrap">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('User name') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Tournaments refereed') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Games refereed') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Civil wins') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Mafia wins') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Ties') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Warnings') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Mod kills') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Mod team kills') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Removed auto-bonus') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Bonus points') . '</th>';
		echo '</tr></thead><tbody>';
		
		// A count, and under it what it comes to; nothing when there is none.
		$cell = function($value, $shown, $sub, $class)
		{
			if ($value <= 0)
			{
				return '<td class="num ' . $class . '"></td>';
			}
			return '<td class="num ' . $class . '">' . $shown . '<br><small class="dim">' . $sub . '</small></td>';
		};
		
		$club_pic = new Picture(CLUB_PICTURE);
		$number = $_page * PAGE_SIZE;
		foreach ($moders as $moder)
		{
			++$number;
			echo '<tr' . ($moder->id == $this->user_id ? ' class="me"' : '') . '>';
			echo '<td class="rk"><span class="medal' . ($number <= 3 ? ' m' . $number : '') . '">' . sprintf('%02d', $number) . '</span></td>';
			$avatar = card_avatar($this->user_pic, $moder->id, $moder->name, $moder->flags, '');
			echo '<td><a class="who" href="user_games.php?id=' . $moder->id . '&moder=1&bck=1">' . $avatar . '<span class="who-txt"><span class="who-name">' . $moder->name . '</span></span></a></td>';
			echo '<td class="p2">' . card_club($club_pic, $moder->club_id, $moder->club_name, $moder->club_flags) . '</td>';
			
			$games = $moder->red_wins + $moder->black_wins + $moder->ties;
			echo '<td class="num p3">' . $moder->tournaments . '</td>';
			echo '<td class="num strong">' . $games . '</td>';
			echo $cell($moder->red_wins, $moder->red_wins, number_format(($moder->red_wins * 100.0) / $games, 1) . '%', 'p3');
			echo $cell($moder->black_wins, $moder->black_wins, number_format(($moder->black_wins * 100.0) / $games, 1) . '%', 'p3');
			echo $cell($moder->ties, $moder->ties, number_format(($moder->ties * 100.0) / $games, 1) . '%', 'p3');
			echo $cell($moder->warnings, $moder->warnings, number_format($moder->warnings / $games, 2) . ' ' . get_label('per game'), 'p4');
			echo $cell($moder->kick_offs, $moder->kick_offs, number_format($moder->kick_offs / $games, 2) . ' ' . get_label('per game'), 'p4');
			echo $cell($moder->team_kick_offs, $moder->team_kick_offs, number_format($moder->team_kick_offs / $games, 2) . ' ' . get_label('per game'), 'p4');
			echo $cell($moder->worst_moves, $moder->worst_moves, number_format($moder->worst_moves / $games, 2) . ' ' . get_label('per game'), 'p4');
			echo $cell($moder->bonus, number_format($moder->bonus, 1), number_format($moder->bonus / $games, 2) . ' ' . get_label('per game'), 'p4');
			echo '</tr>';
		}
		echo '</tbody></table></div>';
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