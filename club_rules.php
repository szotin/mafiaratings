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
		
		$this->edit_url = null;
		if ($option > 0)
		{
			list($rules_id, $rules_code) = Db::record(get_label('rules'), 'SELECT id, rules FROM club_rules WHERE id = ? AND club_id = ?', $option, $this->id);
			$this->edit_url = 'form/rules_edit.php?rules_id=' . $rules_id;
		}
		else if ($option < 0)
		{
			$query = new DbQuery('SELECT rules FROM league_clubs WHERE league_id = ? AND club_id = ?', -$option, $this->id);
			if ($row = $query->next())
			{
				list($rules_code) = $row;
				$this->edit_url = 'form/rules_edit.php?league_id=' . (-$option) . '&club_id=' . $this->id;
			}
			else
			{
				list($rules_code) = Db::record(get_label('league'), 'SELECT default_rules FROM leagues WHERE id = ?', -$option);
			}
		}
		else
		{
			$rules_code = $this->rules_code;
			$this->edit_url = 'form/rules_edit.php?club_id=' . $this->id;
		}
		
		$rules = get_available_rules($this->id, $this->name, $this->rules_code);
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo '<select id="rules" onchange="rulesChange(' . $view . ')">';
		foreach ($rules as $r)
		{
			show_option($r->id, $option, $r->name);
		}
		echo '</select>';
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL . ', ' . $option .')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT . ', ' . $option .')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORTEST . ', ' . $option .')"' . ($view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		
		if ($this->edit_url != null && is_permitted(PERMISSION_CLUB_MANAGER, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="editRules()"><img src="images/edit.png" border="0"></button>';
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
			goTo({ view: view, option: option });
		}
		
		function rulesChange(view)
		{
			filter(view, $("#rules").val());
		}
		
		function editRules()
		{
			dlg.form("<?php echo $this->edit_url; ?>", refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>