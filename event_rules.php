<?php

require_once 'include/event.php';
require_once 'include/rules.php';

class Page extends EventPageBase
{
	protected function show_body()
	{
		$view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL .')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT .')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORTEST .')"' . ($view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		echo '</td>';
		if (is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id, $this->tournament_id))
		{
			echo '<td align="right"><button class="icon" onclick="editRules()"><img src="images/edit.png"></button></td>';
		}
		echo '</tr></table></p>';
		
		show_rules($this->rules_code, $view);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter(view)
		{
			goTo({ view: view });
		}
		
		function editRules()
		{
			dlg.form("form/rules_edit.php?event_id=<?php echo $this->id; ?>", refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>