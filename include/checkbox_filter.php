<?php

function show_checkbox_filter($labels, $flags, $on_change)
{
	for($i = 0; $i < count($labels); ++$i)
	{
		$label = $labels[$i];
		echo ' <input type="checkbox" id="cf' . $i . '" onclick="cf(' . $i . ', ' . $on_change . ')"> ' . $label;
	}
	
?>
	<script>
		function cfSetState(i, s)
		{
			var c = $("#cf" + i);
			c.data('checked', s);
			if (s == 1) c.prop('indeterminate', false).prop('checked', true);
			else if (s == 2) c.prop('indeterminate', false).prop('checked', false);
			else c.prop('indeterminate', true);
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
			onclick();
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