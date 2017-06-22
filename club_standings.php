<?php

require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/image.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);

class Page extends ClubPageBase
{
	private $my_id;
	private $view_id;
	private $type_id;
	
	protected function prepare()
	{
		global $_profile;
	
		parent::prepare();
		$this->my_id = -1;
		if ($_profile != NULL)
		{
			$this->my_id = $_profile->user_id;
		}

		$this->view_id = POINTS_ALL;
		if (isset($_REQUEST['view']))
		{
			$this->view_id = $_REQUEST['view'];
		}
		
		if (isset($_REQUEST['type']))
		{
			$this->type_id = $_REQUEST['type'];
		}
		else
		{
			list($this->type_id) = Db::record(get_label('points'), 'SELECT id FROM rating_types ORDER BY def DESC, id LIMIT 1');
		}
		
		$this->_title = get_label('[0] standings', $this->name);
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
		
		echo '<select name="type" onChange="document.viewForm.submit()">';
		$query = new DbQuery('SELECT id, name_' . $_lang_code . ' FROM rating_types ORDER BY id');
		while ($row = $query->next())
		{
			list ($tid, $tname) = $row;
			show_option($tid, $this->type_id, $tname);
		}
		echo '</select> ';
		
		echo '<select name="view" onChange="document.viewForm.submit()">';
		for ($i = 0; $i < ROLES_COUNT; ++$i)
		{
			show_option($i, $this->view_id, $role_names[$i]);
		}
		echo '</select>';
		
		echo '</td></tr></table></form>';
		
		list ($count) = Db::record(get_label('points'), 'SELECT count(*) FROM club_ratings WHERE type_id = ? AND club_id = ? AND role = ?', $this->type_id, $this->id, $this->view_id);
		$query = new DbQuery(
			'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags ' . 
				'FROM users u, club_ratings r WHERE u.id = r.user_id AND r.club_id = ? AND r.role = ? AND type_id = ? ' .
				'ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id, $this->view_id, $this->type_id);
		
		show_pages_navigation(PAGE_SIZE, $count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Points').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Points per game').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $points, $games_played, $games_won, $flags) = $row;

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
			echo '<td align="center" class="dark">' . $points . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($points/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td align="center">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Club standings'), PERM_ALL);

?>