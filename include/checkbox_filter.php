<?php

// A filter with three answers rather than two: it does not matter, only those that are, only
// those that are not. A checkbox cannot say three things, so this is drawn as a switch with
// three places - the middle one for "it does not matter" - and the state is kept beside it
// rather than in the control. What the page gets out of it is unchanged: checkboxFilterFlags()
// returns the same two bits per filter it always did.
function show_checkbox_filter($labels, $flags, $on_change = NULL)
{
	// A line of its own. The filters share a cell with whatever else a page filters by - a
	// club, a range of dates - and strung after those they run off the end and break wherever
	// they happen to reach, leaving the last of them stranded under something else.
	echo '<div class="switch-row">';

	for($i = 0; $i < count($labels); ++$i)
	{
		$label = $labels[$i];
		// Whoever asked for a handler gets their handler; everybody else gets the page
		// reloaded with the new filter.
		$call = 'cf(' . $i . ', ' . ($on_change == NULL ? 'null' : $on_change) . ')';
		// The switch and the words that say what it is for go out as one piece, and the words
		// work as the switch does, so that there is no doubt which of them they belong to.
		echo ' <span class="switch-item"><span class="tri-filter" id="cf' . $i . '" onclick="' . $call . '"><i></i></span>' .
			'<span class="switch-text" onclick="' . $call . '">' . $label . '</span></span>';
	}
	echo '</div>';
	
?>
	<script>
		function cfSetState(i, s)
		{
			var c = $("#cf" + i);
			c.data('checked', s);
			c.attr('class', 'tri-filter' + (s == 1 ? ' yes' : (s == 2 ? ' no' : '')));
		}
<?php
		for($i = 0; $i < count($labels); ++$i)
		{
			$label = $labels[$i];
			echo "\ncfSetState(" . $i . ', ' . (($flags >> ($i * 2)) & 3) . ');';
		}
?>
	
		function cf(i, onclick)
		{
			var c = $("#cf" + i).data('checked');
			if (++c > 2) c = 0;
			cfSetState(i, c);
			if (onclick != null)
			{
				onclick();
			}
			else
			{
				goTo({filter: checkboxFilterFlags()});
			}
		}
		
		function checkboxFilterFlags()
		{
			var filter = 0;
<?php
			for($i = 0; $i < count($labels); ++$i)
			{
				$label = $labels[$i];
				echo "\nfilter += ($('#cf" . $i . "').data('checked') << " . ($i * 2) . ');';
			}
?>
			return filter;
		}
	</script>
<?php
}

?>
