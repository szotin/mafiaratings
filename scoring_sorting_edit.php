<?php

require_once 'include/session.php';
require_once 'include/scoring.php';

initiate_session();

function show_sorting_select($id, $value)
{
	echo '<select id="' . $id . '" onChange="reorganizeSelects()">';
	show_option('', $value, '');
	show_option(SCORING_SORTING_ADDITIONAL_POINTS, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_ADDITIONAL_POINTS, false));
	show_option('-' . SCORING_SORTING_ADDITIONAL_POINTS, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_ADDITIONAL_POINTS, true));
	show_option(SCORING_SORTING_MAIN_POINTS, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_MAIN_POINTS, false));
	show_option('-' . SCORING_SORTING_MAIN_POINTS, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_MAIN_POINTS, true));
	show_option(SCORING_SORTING_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_WIN, false));
	show_option('-' . SCORING_SORTING_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_WIN, true));
	show_option(SCORING_SORTING_LOOSE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_LOOSE, false));
	show_option('-' . SCORING_SORTING_LOOSE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_LOOSE, true));
	show_option(SCORING_SORTING_CLEAR_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_CLEAR_WIN, false));
	show_option('-' . SCORING_SORTING_CLEAR_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_CLEAR_WIN, true));
	show_option(SCORING_SORTING_CLEAR_LOOSE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_CLEAR_LOOSE, false));
	show_option('-' . SCORING_SORTING_CLEAR_LOOSE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_CLEAR_LOOSE, true));
	show_option(SCORING_SORTING_SPECIAL_ROLE_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SPECIAL_ROLE_WIN, false));
	show_option('-' . SCORING_SORTING_SPECIAL_ROLE_WIN, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SPECIAL_ROLE_WIN, true));
	show_option(SCORING_SORTING_BEST_PLAYER, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_BEST_PLAYER, false));
	show_option('-' . SCORING_SORTING_BEST_PLAYER, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_BEST_PLAYER, true));
	show_option(SCORING_SORTING_BEST_MOVE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_BEST_MOVE, false));
	show_option('-' . SCORING_SORTING_BEST_MOVE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_BEST_MOVE, true));
	show_option(SCORING_SORTING_SURVIVE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SURVIVE, false));
	show_option('-' . SCORING_SORTING_SURVIVE, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SURVIVE, true));
	show_option(SCORING_SORTING_KILLED_FIRST_NIGHT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KILLED_FIRST_NIGHT, false));
	show_option('-' . SCORING_SORTING_KILLED_FIRST_NIGHT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KILLED_FIRST_NIGHT, true));
	show_option(SCORING_SORTING_KILLED_NIGHT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KILLED_NIGHT, false));
	show_option('-' . SCORING_SORTING_KILLED_NIGHT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KILLED_NIGHT, true));
	show_option(SCORING_SORTING_GUESSED_3, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_GUESSED_3, false));
	show_option('-' . SCORING_SORTING_GUESSED_3, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_GUESSED_3, true));
	show_option(SCORING_SORTING_GUESSED_2, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_GUESSED_2, false));
	show_option('-' . SCORING_SORTING_GUESSED_2, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_GUESSED_2, true));
	show_option(SCORING_SORTING_WARNINGS_4, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_WARNINGS_4, false));
	show_option('-' . SCORING_SORTING_WARNINGS_4, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_WARNINGS_4, true));
	show_option(SCORING_SORTING_KICK_OUT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KICK_OUT, false));
	show_option('-' . SCORING_SORTING_KICK_OUT, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_KICK_OUT, true));
	show_option(SCORING_SORTING_SURRENDERED, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SURRENDERED, false));
	show_option('-' . SCORING_SORTING_SURRENDERED, $value, ScoringSystem::get_sorting_item_label(SCORING_SORTING_SURRENDERED, true));
	echo '</select>';
}

try
{
	dialog_title(get_label('When the points are equal the next player wins:'));

	if (!isset($_REQUEST['scoring']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$id = (int)$_REQUEST['scoring'];
	
	$category = SCORING_CATEGORY_MAIN;
	if (isset($_REQUEST['category']))
	{
		$category = (int)$_REQUEST['category'];
	}
	
	list ($club_id, $sorting) = Db::record(get_label('scoring system'), 'SELECT club_id, sorting FROM scorings WHERE id = ?', $id);
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if ($club_id == NULL)
	{
		if (!$_profile->is_admin())
		{
			throw new FatalExc(get_label('No permissions'));
		}
	}
	else if (!$_profile->is_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	$pos = 0;
	for ($i = 0; $i < 10; ++$i)
	{
		if ($pos >= strlen($sorting))
		{
			$value = '';
		}
		else
		{
			$value = $sorting[$pos++];
			if ($value == '-')
			{
				$value .= $sorting[$pos++];
			}
		}
			
		
		echo '<tr><td width="50">' . ($i + 1) . '.</td><td>';
		show_sorting_select('form-sort' . $i, $value);
		echo '</td></tr>';
	}
	echo '</table>';
	
?>	
	<script>
	function findNonEmpty(first)
	{
		for (var i = first; i < 10; ++i)
		{
			var s = $("#form-sort" + i);
			if (s.val() != '')
			{
				return s;
			}
		}
		return null;
	}
	
	function reorganizeSelects()
	{
		for (var i = 0; i < 10; ++i)
		{
			var s1 = $("#form-sort" + i);
			if (s1.val() == '')
			{
				var s2 = findNonEmpty(i + 1);
				if (s2 == null)
				{
					break;
				}
				s1.val(s2.val());
				s2.val('');
			}
		}
	}
	
	function commit(onSuccess)
	{
		var sorting = "";
		for (var i = 0; i < 10; ++i)
		{
			var s = $("#form-sort" + i);
			if (s.val() == '')
			{
				break;
			}
			sorting += s.val();
		}
		params =
		{
			id: <?php echo $id; ?>
			, update_sorting: sorting
		};
		json.post("scoring_ops.php", params, onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>