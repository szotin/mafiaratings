<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);
define('SORT_BY_RATING', 0);
define('SORT_BY_RED_RATING', 1);
define('SORT_BY_BLACK_RATING', 2);
	
class Page extends ClubPageBase
{
	private $role;
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;

	protected function prepare()
	{
		global $_page, $_profile, $_lang;
		
		parent::prepare();
		
		$this->sort = SORT_BY_RATING;
		if (isset($_REQUEST['sort']))
		{
			$this->sort = (int)$_REQUEST['sort'];
			if ($this->sort < 0 || $this->sort > SORT_BY_BLACK_RATING)
			{
				$this->sort = SORT_BY_RATING;
			}
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
				' WHERE u.id = ? AND u.club_id = ?', 
				$this->user_id, $this->id);
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
	
	private function no_user_error()
	{
		global $_profile;
		$message = get_label('[0] played no games.', $this->user_name);
		$this->errorMessage($message);
	}
	
	protected function show_body()
	{
		global $_page, $_profile, $_lang;
		
		echo '<p><table class="transp" width="100%"><tr><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		$condition = new SQL(' WHERE u.club_id = ? AND u.games > 0', $this->id);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.rating as rating, u.red_rating, u.black_rating, u.games as games, u.games_won as won, u.flags, c.id, c.name, c.flags, cu.flags' .
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN club_users cu ON cu.club_id = c.id AND cu.user_id = u.id',
				$condition);
		$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery(
				'SELECT u.id, nu.name, u.rating, u.games, u.games_won'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE u.id = ? AND u.club_id = ?', 
				$this->user_id, $this->id);
			if ($row = $pos_query->next())
			{
				list ($u_id, $u_name, $u_rating, $u_games, $u_won) = $row;
				if ($u_games > 0)
				{
					$pos_query = new DbQuery('SELECT count(*) FROM users u', $condition);
					$pos_query->add(' AND u.id <> ? AND (u.rating > ? OR (u.rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_rating, $u_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
					switch ($this->sort)
					{
					case SORT_BY_RED_RATING:
						$pos_query->add(' AND u.id <> ? AND (u.red_rating > ? OR (u.red_rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_red_rating, $u_red_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
						break;
					case SORT_BY_BLACK_RATING:
						$pos_query->add(' AND u.id <> ? AND (u.black_rating > ? OR (u.black_rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $u_id, $u_black_rating, $u_black_rating, $u_won, $u_won, $u_games, $u_games, $u_id);
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
		case SORT_BY_RATING:
		default:
			$query->add(' ORDER BY rating DESC, won DESC, games DESC, u.id');
			break;
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		$club_user_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
			
		list ($count) = Db::record(get_label('rating'), $count_query);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
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
			list ($id, $name, $rating, $red_rating, $black_rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags, $club_user_flags) = $row;

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
			$club_user_pic->set($id, $name, $club_user_flags, 'c' . $this->id)->set($id, $name, $flags);
			$club_user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
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
}

$page = new Page();
$page->run(get_label('Ratings'));

?>