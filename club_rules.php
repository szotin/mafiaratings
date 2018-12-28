<?php

require_once 'include/club.php';
require_once 'include/rules.php';

class Page extends ClubPageBase
{
	protected function show_body()
	{
		$view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}
		
		$option = 0;
		if (isset($_REQUEST['option']))
		{
			$option = (int)$_REQUEST['option'];
		}
		
		if ($option > 0)
		{
			list($rules_code) = Db::record(get_label('rules'), 'SELECT rules FROM club_rules WHERE id = ? AND club_id = ?', $option, $this->id);
		}
		else if ($option < 0)
		{
			list($rules_code) = Db::record(get_label('league'), 'SELECT rules FROM league_clubs WHERE league_id = ? AND club_id = ?', -$option, $this->id);
		}
		else
		{
			$rules_code = $this->rules_code;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo '<select id="rules" onchange="rulesChange(' . $view . ')">';
		show_option(0, $option, $this->name);
		$query = new DbQuery('SELECT id, name FROM club_rules WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list($rules_id, $rules_name) = $row;
			show_option($rules_id, $option, $rules_name);
		}
		$query = new DbQuery('SELECT l.id, l.name FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER BY l.name', $this->id);
		while ($row = $query->next())
		{
			list($league_id, $league_name) = $row;
			show_option(-$league_id, $option, $league_name);
		}
		echo '</select>';
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL . ', ' . $option .')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT . ', ' . $option .')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORTEST . ', ' . $option .')"' . ($view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		
		if (is_permitted(PERMISSION_CLUB_MANAGER, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="mr.editRules(' . $this->id;
			if ($option < 0)
			{
				echo ', ' . (-$option);
			}
			else if ($option > 0)
			{
				echo ', undefined, ' . $option;
			}
			echo ')"><img src="images/edit.png" border="0"></button>';
		}
		echo '</td></tr></table></p>';
		
		show_rules($rules_code, $view);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter(view, option)
		{
			refr({ view: view, option: option });
		}
		
		function rulesChange(view)
		{
			filter(view, $("#rules").val());
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>