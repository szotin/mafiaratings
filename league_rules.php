<?php

require_once 'include/league.php';
require_once 'include/rules.php';

define('SHOW_RULES_FILTER', 0);
define('SHOW_DEFAULT_RULES', 1);
define('SHOW_COUNT', 2);

class Page extends LeaguePageBase
{
	protected function prepare()
	{
		parent::prepare();
		
		$this->show = SHOW_RULES_FILTER;
		if (isset($_REQUEST['show']))
		{
			$this->show = (int)$_REQUEST['show'];
		}
		$this->show = min(max($this->show, 0), SHOW_COUNT - 1);
		
		$this->view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
		}
	}
	
	private function show_rules_filter()
	{
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_FULL . '})"' . ($this->view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_SHORT . '})"' . ($this->view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		
		if (is_permitted(PERMISSION_LEAGUE_MANAGER, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="mr.editLeagueRules(' . $this->id . ')"><img src="images/edit.png" border="0"></button>';
		}
		echo '</td></tr></table></p>';
		
		show_rules($this->rules_filter, $this->view);
	}
	
	private function show_default_rules()
	{
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_FULL . '})"' . ($this->view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_SHORT . '})"' . ($this->view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_SHORTEST . '})"' . ($this->view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		
		if (is_permitted(PERMISSION_LEAGUE_MANAGER, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="editRules()"><img src="images/edit.png" border="0"></button>';
		}
		echo '</td></tr></table></p>';
		
		show_rules($this->default_rules, $this->view);
	}

	protected function show_body()
	{
		if (are_rules_configurable($this->rules_filter))
		{
			echo '<div class="tab">';
			echo '<button ' . ($this->show == SHOW_RULES_FILTER ? 'class="active" ' : '') . 'onclick="goTo({show:' . SHOW_RULES_FILTER . '})">' . get_label('Rules filter') . '</button>';
			echo '<button ' . ($this->show == SHOW_DEFAULT_RULES ? 'class="active" ' : '') . 'onclick="goTo({show:' . SHOW_DEFAULT_RULES . '})">' . get_label('Default rules') . '</button>';
			echo '</div>';
			
			switch ($this->show)
			{
			case SHOW_RULES_FILTER:
				$this->show_rules_filter();
				break;
			case SHOW_DEFAULT_RULES:
				$this->show_default_rules();
				break;
			}
		}
		else
		{
			$this->show_rules_filter();
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		function editRules()
		{
			dlg.form("form/rules_edit.php?league_id=<?php echo $this->id; ?>", refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>