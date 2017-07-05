<?php

require_once 'include/user.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 20);

class Page extends UserPageBase
{
	private $my_id;

	protected function prepare()
	{
		global $_profile;
	
		parent::prepare();
		$this->_title = get_label('[0] compare', $this->title);
		
		$this->my_id = -1;
		if ($_profile != NULL)
		{
			$this->my_id = $_profile->user_id;
		}
	}
	
	protected function show_body()
	{
		global $_page;
	
		$filter = '';
		if (isset($_REQUEST['filter']))
		{
			$filter = $_REQUEST['filter'];
		}
	
		$condition = new SQL(' FROM users u WHERE u.id <> ? AND u.games > 0', $this->id);
		if ($filter != '')
		{
			$condition->add(' AND u.name LIKE ?', $filter . '%');
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*)', $condition);
		
		echo '<form method="get" name="form">';
		echo '<table class="transp" width="100%"><tr>';
		echo '<td valign="center"><input type="hidden" name="id" value="' . $this->id . '">';
		echo get_label('Filter') . ':&nbsp;<input name="filter" value="' . $filter . '" onChange="onChange="document.form.submit()"></td>';
		
		echo '<td align="right">';
		echo '<a href="player_compare.php?id1=' . $this->id . '&id2=0">'.get_label('Compare with the average player').'</a>';
		echo '</td></tr></table></form>';
		
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td>'.get_label('Player').'</td><td width="100">Rating</td><td width="100">'.get_label('Games played').'</td></tr>';
		
		$query = new DbQuery('SELECT u.id, u.name, u.rating, u.games', $condition);
		$query->add(' ORDER BY u.rating DESC, u.games LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($uid, $uname, $urating, $ugames) = $row;
			if ($uid == $this->my_id)
			{
				echo '<tr class="light">';
			}
			else
			{
				echo '<tr>';
			}
			echo '<td><a href="player_compare.php?id1=' . $this->id . '&id2=' . $uid . '">' . cut_long_name($uname, 80) . '</a></td>';
			echo '<td>' . $urating . '</td>';
			echo '<td>' . $ugames . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('[0] compare', get_label('User')), PERM_ALL);

?>