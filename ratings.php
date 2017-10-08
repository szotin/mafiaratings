<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);
	
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
		global $_page, $_profile;
		
		parent::prepare();
		
		$this->ccc_title = get_label('Filter players by club, city, or country.');
		
		$this->role = POINTS_ALL;
		if (isset($_REQUEST['role']))
		{
			$this->role = $_REQUEST['role'];
		}
		
		$this->ccc_value = $this->ccc_filter->get_value();
		if ($this->role != POINTS_ALL)
		{
			if ($this->ccc_value != NULL)
			{
				$this->_title = $this->ccc_value . '. ' . get_role_name($this->role) . '.';
			}
			else
			{
				$this->_title = get_role_name($this->role) . '.';
			}
		}
		else if ($this->ccc_value != NULL)
		{
			$this->_title = $this->ccc_value . '.';
		}
		else
		{
			$this->_title = get_label('All ratings.');
		}
		
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
	
	private function common_condition()
	{
		global $_profile;
		
		$condition = new SQL(' WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 AND u.games > 0');
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
/*			if ($ccc_id > 0)
			{
				$condition->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND club_id IN (SELECT club_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND user_id = ?))', $_profile->user_id);
			}*/
			if ($ccc_id > 0)
			{
				$condition->add(' AND u.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.club_id IN (SELECT club_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND user_id = ?)', $_profile->user_id);
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
		else if ($this->role == POINTS_ALL)
		{
			$message = get_label('[0] played no games.', $this->user_name);
		}
		else
		{
			$message = get_label('[0] played no games as [1].', $this->user_name, get_role_name($this->role, ROLE_NAME_FLAG_SINGLE | ROLE_NAME_FLAG_LOWERCASE));
		}
		$this->errorMessage($message);
	}
	
	protected function show_body()
	{
		global $_page, $_profile;
		
		$condition = $this->common_condition();
		if ($this->role == POINTS_ALL)
		{
			$query = new DbQuery(
				'SELECT u.id, u.name, u.rating as rating, u.games as games, u.games_won as won, u.flags, c.id, c.name, c.flags FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id', $condition);
			$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
			if ($this->user_id > 0)
			{
				$pos_query = new DbQuery('SELECT u.id, u.name, u.rating, u.games, u.games_won FROM users u WHERE u.id = ?', $this->user_id);
				if ($row = $pos_query->next())
				{
					list ($uid, $uname, $urating, $ugames, $uwon) = $row;
					if ($ugames > 0)
					{
						$pos_query = new DbQuery('SELECT count(*) FROM users u', $condition);
						$pos_query->add(' AND u.id <> ? AND (u.rating > ? OR (u.rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $uid, $urating, $urating, $uwon, $uwon, $ugames, $ugames, $uid);
						list($user_pos) = $pos_query->next();
						$_page = floor($user_pos / PAGE_SIZE);
					}
					else
					{
						$this->no_user_error();
					}
				}
			}
		}
		else
		{
			$condition->add(get_roles_condition($this->role));
			$query = new DbQuery(
				'SELECT u.id, u.name, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won, u.flags, c.id, c.name, c.flags FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.id', $condition);
			$query->add(' GROUP BY u.id');
			$count_query = new DbQuery('SELECT count(DISTINCT u.id) FROM users u JOIN players p ON p.user_id = u.id', $condition);
			if ($this->user_id > 0)
			{
				$pos_query = new DbQuery('SELECT u.id, u.name, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won, u.club_id, u.city_id, c.country_id FROM players p JOIN users u ON p.user_id = u.id JOIN cities c ON u.city_id = c.id', $condition);
				$pos_query->add(' AND u.id = ? GROUP BY u.id', $this->user_id);
				
				if ($row = $pos_query->next())
				{
					list ($uid, $uname, $urating, $ugames, $uwon) = $row;
					$pos_query = new DbQuery('SELECT count(*) FROM (SELECT u.id FROM players p JOIN users u ON p.user_id = u.id ', $condition);
					$pos_query->add(' AND u.id <> ? GROUP BY u.id HAVING (' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) > ? OR (' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) = ? AND (SUM(p.won) > ? OR (SUM(p.won) = ? AND (count(p.game_id) > ? OR (count(p.game_id) = ? AND u.id < ?))))))) as upper', $uid, $urating, $urating, $uwon, $uwon, $ugames, $ugames, $uid);
					list($user_pos) = $pos_query->next();
					$_page = floor($user_pos / PAGE_SIZE);
				}
				else
				{
					$this->no_user_error();
				}
			}
		}
		$query->add(' ORDER BY rating DESC, won DESC, games DESC, u.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		list ($count) = Db::record(get_label('rating'), $count_query);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Rating').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Victories').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Rating per game').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags) = $row;

			if ($id == $this->user_id)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}

			echo '<td align="center" class="dark">' . $number . '</td>';
			echo '<td width="60" align="center"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $name, $flags, ICONS_DIR, 50, 50);
			echo '</a></td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 40, 40);
			echo '</td>';
			echo '<td align="center" class="dark">' . format_rating($rating) . '</td>';
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
	}
	
	protected function show_filter_fields()
	{
		show_roles_select($this->role, 'filter()', get_label('Use only the rating earned in a specific role.'));
	}
	
	protected function show_search_fields()
	{
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, get_label('Go to the page where a specific player is located.'));
	}
	
	protected function get_filter_js()
	{
		$result = '+ "&role=" + $("#roles option:selected").val()';
		if ($this->user_id > 0)
		{
			$result .= ' + "&page=-' . $this->user_id . '"';
		}
		return $result;
	}
}

$page = new Page();
$page->set_ccc(CCCS_ALL);
$page->run('', PERM_ALL);

?>