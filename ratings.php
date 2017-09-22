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
	private $user_id;
	private $role;

	protected function prepare()
	{
		global $_page, $_profile;
		
		parent::prepare();
		
		$this->role = POINTS_ALL;
		if (isset($_REQUEST['role']))
		{
			$this->role = $_REQUEST['role'];
		}
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
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
				$pos_query = new DbQuery('SELECT id, name, rating, games, games_won FROM users WHERE id = ?', $this->user_id);
				if ($row = $pos_query->next())
				{
					list ($uid, $uname, $urating, $ugames, $uwon) = $row;
					if ($ugames > 0)
					{
						$pos_query = new DbQuery('SELECT count(*) FROM users u', $condition);
						$pos_query->add(' AND (u.rating > ? OR (u.rating = ? AND (u.games_won > ? OR (u.games_won = ? AND (u.games > ? OR (u.games = ? AND u.id < ?))))))', $urating, $urating, $uwon, $uwon, $ugames, $ugames, $uid);
						list($user_pos) = $pos_query->next();
						$_page = floor($user_pos / PAGE_SIZE);
					}
					else
					{
						$this->errorMessage(get_label('User [0] played no games.', $uname));
						$_page = 0;
					}
				}
				else
				{
					$this->errorMessage(get_label('Player not found.'));
					$_page = 0;
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
				$pos_query = new DbQuery('SELECT u.id, u.name, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won FROM players p JOIN users u ON p.user_id = u.id', $condition);
				$pos_query->add(' AND u.id = ? GROUP BY u.id', $this->user_id);
				if ($row = $pos_query->next())
				{
					list ($uid, $uname, $urating, $ugames, $uwon) = $row;
					if ($ugames > 0)
					{
						$pos_query = new DbQuery('SELECT count(*) FROM (SELECT u.id FROM players p JOIN users u ON p.user_id = u.id ', $condition);
						$pos_query->add(' GROUP BY u.id HAVING (' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) > ? OR (' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) = ? AND (SUM(p.won) > ? OR (SUM(p.won) = ? AND (count(p.game_id) > ? OR (count(p.game_id) = ? AND u.id < ?))))))) as upper', $urating, $urating, $uwon, $uwon, $ugames, $ugames, $uid);
						list($user_pos) = $pos_query->next();
						$_page = floor($user_pos / PAGE_SIZE);
					}
					else
					{
						$this->errorMessage(get_label('User [0] played no games.', $uname));
						$_page = 0;
					}
				}
				else
				{
					$this->errorMessage(get_label('Player not found.'));
					$_page = 0;
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
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
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
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a></td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($club_id, $club_flags, ICONS_DIR, 40, 40, 'title="' . $club_name . '"');
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
		global $_lang_code;

		echo '<table class="transp" width="100%">';
		echo '<select id="role" onChange = "filter()">';
		show_option(0, $this->role, get_label('All roles'));
		show_option(1, $this->role, get_label('Red players'));
		show_option(2, $this->role, get_label('Dark players'));
		show_option(3, $this->role, get_label('Civilians'));
		show_option(4, $this->role, get_label('Sheriffs'));
		show_option(5, $this->role, get_label('Mafiosy'));
		show_option(6, $this->role, get_label('Dons'));
		echo '</select>';
		echo '</td></tr></table>';
	}
	
	protected function show_search_fields()
	{
		echo get_label('Find') . ': ';
		show_user_input('page', '', 'mr.gotoFind');
	}
	
	protected function get_filter_js()
	{
		$result = '+ "&role=" + $("#role option:selected").val()';
		if ($this->user_id > 0)
		{
			$result .= ' + "&page=-' . $this->user_id . '"';
		}
		return $result;
	}
}

$page = new Page();
$page->set_ccc(CCCS_ALL);
$page->run(get_label('Ratings'), PERM_ALL);

?>