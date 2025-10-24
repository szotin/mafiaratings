<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);
define('SORT_BY_RATING', 0);
define('SORT_BY_RED_RATING', 1);
define('SORT_BY_BLACK_RATING', 2);
define('SORT_BY_REDNESS', 3);
define('SORT_BY_BLACKNESS', 4);
define('SORT_BY_NEUTRAL', 5);
	
class Page extends GeneralPageBase
{
	private $role;
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	private $ccc_value;
	
	protected function prepare()
	{
		global $_page, $_profile, $_lang;
		
		parent::prepare();
		
		$this->sort = SORT_BY_RATING;
		if (isset($_REQUEST['sort']))
		{
			$this->sort = (int)$_REQUEST['sort'];
			if ($this->sort < 0 || $this->sort > SORT_BY_NEUTRAL)
			{
				$this->sort = SORT_BY_RATING;
			}
		}
		
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$this->ccc_value = $this->ccc_filter->get_value();
		if ($this->ccc_value != NULL)
		{
			$this->_title = $this->ccc_value . '.';
		}
		else
		{
			$this->_title = get_label('Ratings.');
		}
		
		$this->user_id = 0;
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
	
	private function common_condition()
	{
		global $_profile;
		
		$condition = new SQL(' WHERE u.games > 0');
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND u.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		return $condition;
	}
	
	private function no_user_error()
	{
		global $_profile;
		
		$member = true;
		if ($this->ccc_value != NULL)
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
			$message = get_label('[0] is not from [1].', $this->user_name, $this->ccc_value);
		}
		else
		{
			$message = get_label('[0] played no games.', $this->user_name);
		}
		$this->errorMessage($message);
	}
	
	protected function show_body()
	{
		global $_page, $_profile, $_lang;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('players')));
		echo '</td><td align="right"><img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, '', get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		$condition = $this->common_condition();
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.rating as rating, u.red_rating, u.black_rating, u.games as games, u.games_won as won, u.flags, c.id, c.name, c.flags, ni.name FROM users u' . 
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id', $condition);
		$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery('SELECT u.id, u.rating, u.red_rating, u.black_rating, u.games, u.games_won FROM users u WHERE u.id = ?', $this->user_id);
			if ($row = $pos_query->next())
			{
				list ($u_id, $u_rating, $u_red_rating, $u_black_rating, $u_games, $u_won) = $row;
				if ($u_games > 0)
				{
					$pos_query = new DbQuery('SELECT count(*) FROM users u', $condition);
					switch ($this->sort)
					{
					case SORT_BY_RED_RATING:
						$pos_query->add(' AND u.id <> ? AND (u.red_rating > ? OR (u.red_rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_red_rating, $u_red_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
						break;
					case SORT_BY_BLACK_RATING:
						$pos_query->add(' AND u.id <> ? AND (u.black_rating > ? OR (u.black_rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_black_rating, $u_black_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
						break;
					case SORT_BY_REDNESS:
						$pos_query->add(' AND u.id <> ? AND ((u.red_rating - u.black_rating) > ? OR ((u.red_rating - u.black_rating) = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))', $u_id, $u_red_rating - $u_black_rating, $u_red_rating - $u_black_rating, $u_games, $u_games, $u_id);
						break;
					case SORT_BY_BLACKNESS:
						$pos_query->add(' AND u.id <> ? AND ((u.black_rating - u.red_rating) > ? OR ((u.black_rating - u.red_rating) = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))', $u_id, $u_black_rating - $u_red_rating, $u_black_rating - $u_red_rating, $u_games, $u_games, $u_id);
						break;
					case SORT_BY_NEUTRAL:
						$pos_query->add(' AND u.id <> ? AND (ABS(red_rating - black_rating) < ? OR (ABS(red_rating - black_rating) = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))', $u_id, abs($u_red_rating - $u_black_rating), abs($u_red_rating - $u_black_rating), $u_games, $u_games, $u_id);
						break;
					case SORT_BY_RATING:
					default:
						$pos_query->add(' AND u.id <> ? AND (u.rating > ? OR (u.rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_rating, $u_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
						break;
					}
					list($user_pos) = $pos_query->next();
					$_page = floor($user_pos / PAGE_SIZE);
				}
				else
				{
					$this->no_user_error();
				}
			}
		}
		switch ($this->sort)
		{
		case SORT_BY_RED_RATING:
			$query->add(' ORDER BY red_rating DESC, won DESC, games DESC, u.id');
			break;
		case SORT_BY_BLACK_RATING:
			$query->add(' ORDER BY black_rating DESC, won DESC, games DESC, u.id');
			break;
		case SORT_BY_REDNESS:
			$query->add(' ORDER BY black_rating - red_rating, games DESC, u.id');
			break;
		case SORT_BY_BLACKNESS:
			$query->add(' ORDER BY red_rating - black_rating, games DESC, u.id');
			break;
		case SORT_BY_NEUTRAL:
			$query->add(' ORDER BY ABS(red_rating - black_rating), games DESC, u.id');
			break;
		case SORT_BY_RATING:
		default:
			$query->add(' ORDER BY rating DESC, won DESC, games DESC, u.id');
			break;
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		list ($count) = Db::record(get_label('rating'), $count_query);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('Player').'</td>';
		if ($this->sort == SORT_BY_RATING)
		{
			echo '<td width="60" align="center">'.get_label('Rating').'</td>';
		}
		else
		{
			echo '<td width="60" align="center"><a href="#" onclick="goTo({sort:undefined,page:undefined})">'.get_label('Rating').'</a></td>';
		}
		if ($this->sort == SORT_BY_RED_RATING)
		{
			echo '<td width="60" align="center">'.get_label('Red rating').'</td>';
		}
		else
		{
			echo '<td width="60" align="center"><a href="#" onclick="goTo({sort:' . SORT_BY_RED_RATING . ',page:undefined})">'.get_label('Red rating').'</a></td>';
		}
		if ($this->sort == SORT_BY_BLACK_RATING)
		{
			echo '<td width="60" align="center">'.get_label('Black rating').'</td>';
		}
		else
		{
			echo '<td width="60" align="center"><a href="#" onclick="goTo({sort:' . SORT_BY_BLACK_RATING . ',page:undefined})">'.get_label('Black rating').'</a></td>';
		}
		echo '<td width="60" align="center">'.get_label('Redness').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $rating, $red_rating, $black_rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags, $city_name) = $row;

			if ($id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}

			echo '<td align="center" class="' . $highlight . '">' . $number . '</td>';
			echo '<td width="60" align="center">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1"><b>' . $name . '</b><br>' . $city_name . '</a></td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			if ($this->sort == SORT_BY_RATING)
			{
				echo '<td align="center" class="' . $highlight . '"><b>' . format_rating($rating + USER_INITIAL_RATING) . '</b></td>';
			}
			else
			{
				echo '<td align="center">' . format_rating($rating + USER_INITIAL_RATING) . '</td>';
			}
			if ($this->sort == SORT_BY_RED_RATING)
			{
				echo '<td align="center" class="' . $highlight . '"><b>' . format_rating($red_rating + USER_INITIAL_RATING) . '</b></td>';
			}
			else
			{
				echo '<td align="center">' . format_rating($red_rating + USER_INITIAL_RATING) . '</td>';
			}
			if ($this->sort == SORT_BY_BLACK_RATING)
			{
				echo '<td align="center" class="' . $highlight . '"><b>' . format_rating($black_rating + USER_INITIAL_RATING) . '</b></td>';
			}
			else
			{
				echo '<td align="center">' . format_rating($black_rating + USER_INITIAL_RATING) . '</td>';
			}
			echo '<td align="center">' . format_rating($red_rating - $black_rating) . '</td>';
			
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . format_rating($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterRoles()
		{
			goTo({role: $("#roles option:selected").val(), page: undefined});
		}
<?php
	}
}

$page = new Page();
$page->run();

?>