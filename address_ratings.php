<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);

class Page extends AddressPageBase
{
	private $my_id;
	private $view_id;
	
	protected function prepare()
	{
		global $_profile;
	
		parent::prepare();
		$this->my_id = -1;
		if ($_profile != NULL)
		{
			$this->my_id = $_profile->user_id;
		}

		$this->view_id = RATING_ALL;
		if (isset($_REQUEST['view']))
		{
			$this->view_id = $_REQUEST['view'];
		}
		
		$this->_title = get_label('[0] ratings', $this->name);
	}
	
	protected function show_body()
	{
		global $_page, $_lang_code;
		
		$role_names = array(
			get_label('All roles'),
			get_label('Red players'),
			get_label('Dark players'),
			get_label('Civilians'),
			get_label('Sheriffs'),
			get_label('Mafiosy'),
			get_label('Dons'));
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		
		echo '<select name="view" onChange="document.viewForm.submit()">';
		for ($i = 0; $i < ROLES_COUNT; ++$i)
		{
			show_option($i, $this->view_id, $role_names[$i]);
		}
		echo '</select>';
		
		echo '</td></tr></table></form>';
		
		$role_condition = new SQL();
		switch ($this->view_id)
		{
		case RATING_RED:
			$role_condition->add(' AND p.role < 2');
			break;
		case RATING_DARK:
			$role_condition->add(' AND p.role > 1');
			break;
		case RATING_CIVIL:
			$role_condition->add(' AND p.role = 0');
			break;
		case RATING_SHERIFF:
			$role_condition->add(' AND p.role = 1');
			break;
		case RATING_MAFIA:
			$role_condition->add(' AND p.role = 2');
			break;
		case RATING_DON:
			$role_condition->add(' AND p.role = 3');
			break;
		}
		
		list ($count) = Db::record(get_label('rating'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON p.game_id = g.id JOIN events e ON g.event_id = e.id WHERE e.address_id = ?', $this->id, $role_condition);
		$query = new DbQuery(
			'SELECT u.id, u.name, SUM(p.rating) as rating, count(*) as games, SUM(p.won) as won, u.flags FROM players p' .
				' JOIN users u ON p.user_id = u.id' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN events e ON g.event_id = e.id' .
				' WHERE e.address_id = ?',
			$this->id, $role_condition);
		$query->add(' GROUP BY u.id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		show_pages_navigation(PAGE_SIZE, $count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
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
			list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

			if ($id == $this->my_id)
			{
				echo '<tr class="lighter"><td align="center">';
			}
			else
			{
				echo '<tr class="light"><td align="center" class="dark">';
			}

			echo $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td class="dark" align="center">' . $rating . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td>&nbsp;</td><td>&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Address'), PERM_ALL);

?>