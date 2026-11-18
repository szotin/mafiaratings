<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/cards.php';

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
	
	// A column the list can be sorted by: its heading is a link, with an arrow when it is the order.
	private function sort_th($sort, $label, $class)
	{
		$go = ($sort == SORT_BY_RATING) ? 'undefined' : $sort;
		echo '<th class="num ' . $class . '" scope="col"' . ($this->sort == $sort ? ' aria-sort="descending"' : '') . '><a class="th-sort" href="#" onclick="goTo({sort:' . $go . ',page:undefined}); return false;">' . $label . '</a></th>';
	}
	
	protected function show_body()
	{
		global $_page, $_profile, $_lang;
		
		$condition = new SQL(' WHERE u.club_id = ? AND u.games > 0', $this->id);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.rating as rating, u.red_rating, u.black_rating, u.games as games, u.games_won as won, u.flags, c.id, nc.name, c.flags, cu.flags' .
				' FROM users u' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN clubs c ON u.club_id = c.id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN club_regs cu ON cu.club_id = c.id AND cu.user_id = u.id',
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
			
		$club_reg_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
			
		list ($count) = Db::record(get_label('rating'), $count_query);
		
		// The list of the v3 design, after ratings.php: the search and the count in a toolbar, the
		// table in a sheet.
		echo '<div class="toolbar"><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</div><span class="count">' . get_label('[0] players', number_format($count)) . '</span></div>';
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		$this->sort_th(SORT_BY_RATING, get_label('Rating'), '');
		$this->sort_th(SORT_BY_RED_RATING, get_label('Red rating'), 'p4');
		$this->sort_th(SORT_BY_BLACK_RATING, get_label('Black rating'), 'p4');
		echo '<th class="num p4" scope="col">' . get_label('Redness') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Games played') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		echo '<th class="num p2" scope="col">' . get_label('Winning %') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Rating per game') . '</th>';
		echo '</tr></thead><tbody>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $rating, $red_rating, $black_rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags, $club_reg_flags) = $row;

			echo '<tr' . ($id == $this->user_id ? ' class="me"' : '') . '>';
			echo '<td class="rk"><span class="medal' . ($number <= 3 ? ' m' . $number : '') . '">' . sprintf('%02d', $number) . '</span></td>';
			$club_reg_pic->set($id, $name, $club_reg_flags, 'c' . $this->id)->set($id, $name, $flags);
			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar_of($club_reg_pic, $id, $name, '') .
				'<span class="who-txt"><span class="who-name">' . cut_long_name($name, 45) . '</span></span></a></td>';
			echo '<td class="num' . ($this->sort == SORT_BY_RATING ? ' strong' : '') . '">' . format_rating($rating + USER_INITIAL_RATING) . '</td>';
			echo '<td class="num p4' . ($this->sort == SORT_BY_RED_RATING ? ' strong' : ' dim') . '">' . format_rating($red_rating + USER_INITIAL_RATING) . '</td>';
			echo '<td class="num p4' . ($this->sort == SORT_BY_BLACK_RATING ? ' strong' : ' dim') . '">' . format_rating($black_rating + USER_INITIAL_RATING) . '</td>';
			echo '<td class="num p4 dim">' . format_rating($red_rating - $black_rating) . '</td>';
			echo '<td class="num p3">' . $games_played . '</td>';
			echo '<td class="num p3">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td class="num p2">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td class="num p4 dim">' . format_rating($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td class="num p2"></td><td class="num p4"></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Ratings'));

?>