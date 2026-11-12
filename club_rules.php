<?php

require_once 'include/club.php';
require_once 'include/rules.php';

// The rulebook of a club: any of the rules the club can play by, as get_available_rules() offers
// them - its own first. Picked by where they come from ("club", "r<id>", "l<id>"), so that two
// that come out the same are still told apart.
class Page extends ClubPageBase
{
	protected function show_body()
	{
		$view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}
		
		$rules = get_available_rules($this->id, $this->name, $this->rules_code);
		$option = isset($_REQUEST['option']) ? $_REQUEST['option'] : 'club';
		$current = $rules[0];
		foreach ($rules as $r)
		{
			if ($r->id == $option)
			{
				$current = $r;
			}
		}
		$option = $current->id;
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo '<select id="rules" onchange="rulesChange(' . $view . ')">';
		foreach ($rules as $r)
		{
			show_option($r->id, $option, $r->name);
		}
		echo '</select>';
		$js_option = htmlspecialchars(json_encode($option), ENT_QUOTES);
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL . ', ' . $js_option . ')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT . ', ' . $js_option . ')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORTEST . ', ' . $js_option . ')"' . ($view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		echo '</td></tr></table></p>';
		
		show_rules($current->rules, $view);
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
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>
