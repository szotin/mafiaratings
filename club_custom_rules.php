<?php

require_once 'include/club.php';
require_once 'include/league.php';

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		list ($count) = Db::record(get_label('rules'), 'SELECT count(*) FROM club_rules r WHERE r.club_id = ?', $this->id);
		++$count;

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52"><a href ="javascript:mr.createRules(' . $this->id . ')" title="'.get_label('New rules').'">';
		echo '<img src="images/create.png" border="0"></a></td>';
		
		echo '<td>'.get_label('Rules name').'</td></tr>';
		
		echo '<tr><td><a href="#" onclick="mr.editRules(' . $this->id . ')" title="' . get_label('Edit [0] in [1]', get_label('[default]'), $this->name) . '"><img src="images/edit.png" border="0"></a>';
		echo '</td><td>' . get_label('[default]') . '</td></tr>';
		
		$query = new DbQuery('SELECT l.id, l.name, l.flags FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER BY l.name', $this->id);
		while ($row = $query->next())
		{
			list ($league_id, $league_name, $league_flags) = $row;
			echo '<tr><td class="dark"><a href="#" onclick="mr.editRules(' . $this->id . ', ' . $league_id . ')" title="' . get_label('Edit [0] in [1]', $league_name, $this->name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td><table class="transp" width="100%"><tr><td width="30">';
			$this->league_pic->set($league_id, $league_name, $league_flags);
			$this->league_pic->show(ICONS_DIR, false, 24);
			echo '</td><td>' . $league_name . '</td></tr></table></td></tr>';
		}

		$query = new DbQuery('SELECT id, name FROM club_rules WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list ($rules_id, $rules_name) = $row;
			echo '<tr><td class="dark"><a href="#" onclick="mr.editRules(' . $this->id . ', undefined, ' . $rules_id . ')" title="' . get_label('Edit [0] in [1]', $rules_name, $this->name) . '"><img src="images/edit.png" border="0"></a>';
			echo '<a href="#" onclick="mr.deleteRules(' . $rules_id . ', \'' . get_label('Are you sure you want to delete rules [0]?', $rules_name) . '\')" title="' . get_label('Delete [0] in [1]', $rules_name, $this->name) . '"><img src="images/delete.png" border="0"></a>';
			echo '</td><td>' . $rules_name . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>