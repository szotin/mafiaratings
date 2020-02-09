<?php

define('GAMES_FILTER_VIDEO', 0x0001);
define('GAMES_FILTER_NO_VIDEO', 0x0002);
define('GAMES_FILTER_TOURNAMENT', 0x0004);
define('GAMES_FILTER_NO_TOURNAMENT', 0x0008);
define('GAMES_FILTER_RATING', 0x0010);
define('GAMES_FILTER_NO_RATING', 0x0020);
define('GAMES_FILTER_CANCELED', 0x0040);
define('GAMES_FILTER_NO_CANCELED', 0x0080);
define('GAMES_FILTER_ALL', 0);

define('GAMES_FILTER_MASK_VIDEO', 0x0003);
define('GAMES_FILTER_MASK_TOURNAMENT', 0x000c);
define('GAMES_FILTER_MASK_RATING', 0x0030);
define('GAMES_FILTER_MASK_CANCELED', 0x00c0);

function show_games_filter($filter, $on_change, $filter_flags = 0)
{
	$gfv = 0;
	if (($filter_flags & GAMES_FILTER_NO_VIDEO) == 0)
	{
		echo ' <input type="checkbox" id="gfv" onclick="ts(this, ' . $on_change . ')"> ' . get_label('games with video');
		switch ($filter & GAMES_FILTER_MASK_VIDEO)
		{
			case GAMES_FILTER_VIDEO:
				$gfv = 1;
				break;
			case GAMES_FILTER_NO_VIDEO:
				$gfv = 2;
				break;
		}
	}
	else
	{
		echo '<input type="hidden" id="gfv" value="0">';
	}
	
	$gft = 0;
	if (($filter_flags & GAMES_FILTER_NO_TOURNAMENT) == 0)
	{
		echo ' <input type="checkbox" id="gft" onclick="ts(this, ' . $on_change . ')"> ' . get_label('tournament games');
		switch ($filter & GAMES_FILTER_MASK_TOURNAMENT)
		{
			case GAMES_FILTER_TOURNAMENT:
				$gft = 1;
				break;
			case GAMES_FILTER_NO_TOURNAMENT:
				$gft = 2;
				break;
		}
	}
	else
	{
		echo '<input type="hidden" id="gft" value="0">';
	}
	
	$gfr = 0;
	if (($filter_flags & GAMES_FILTER_NO_RATING) == 0)
	{
		echo ' <input type="checkbox" id="gfr" onclick="ts(this, ' . $on_change . ')"> ' . get_label('rating games');
		switch ($filter & GAMES_FILTER_MASK_RATING)
		{
			case GAMES_FILTER_RATING:
				$gfr = 1;
				break;
			case GAMES_FILTER_NO_RATING:
				$gfr = 2;
				break;
		}
	}
	else
	{
		echo '<input type="hidden" id="gfr" value="0">';
	}
	
	$gfc = 0;
	if (($filter_flags & GAMES_FILTER_NO_CANCELED) == 0)
	{
		echo ' <input type="checkbox" id="gfc" onclick="ts(this, ' . $on_change . ')"> ' . get_label('canceled games');
		switch ($filter & GAMES_FILTER_MASK_CANCELED)
		{
			case GAMES_FILTER_CANCELED:
				$gfc = 1;
				break;
			case GAMES_FILTER_NO_CANCELED:
				$gfc = 2;
				break;
		}
	}
	else
	{
		echo '<input type="hidden" id="gfc" value="0">';
	}
	
?>
	<script>
		function gfSetState(c, s)
		{
			c.data('checked', s);
			if (s == 1) c.prop('indeterminate', false).prop('checked', true);
			else if (s == 2) c.prop('indeterminate', false).prop('checked', false);
			else c.prop('indeterminate', true);
		}
	
		gfSetState($('#gfv'), <?php echo $gfv; ?>);
		gfSetState($('#gft'), <?php echo $gft; ?>);
		gfSetState($('#gfr'), <?php echo $gfr; ?>);
		gfSetState($('#gfc'), <?php echo $gfc; ?>);
	
		function ts(cb, onclick)
		{
			var c = $(cb).data('checked');
			if (++c > 2) c = 0;
			gfSetState($(cb), c);
			onclick();
		}
		
		function getGamesFilter()
		{
			return $("#gfv").data('checked') + ($("#gft").data('checked') << 2) + ($("#gfr").data('checked') << 4) + ($("#gfc").data('checked') << 6);
		}
	</script>
<?php
}

function get_games_filter_condition($filter)
{
	$condition = new SQL();

	switch ($filter & GAMES_FILTER_MASK_VIDEO)
	{
		case GAMES_FILTER_VIDEO:
			$condition->add(' AND g.video_id IS NOT NULL');
			break;
		case GAMES_FILTER_NO_VIDEO:
			$condition->add(' AND g.video_id IS NULL');
			break;
	}

	switch ($filter & GAMES_FILTER_MASK_TOURNAMENT)
	{
		case GAMES_FILTER_TOURNAMENT:
			$condition->add(' AND g.tournament_id IS NOT NULL');
			break;
		case GAMES_FILTER_NO_TOURNAMENT:
			$condition->add(' AND g.tournament_id IS NULL');
			break;
	}

	switch ($filter & GAMES_FILTER_MASK_RATING)
	{
		case GAMES_FILTER_RATING:
			$condition->add(' AND (g.flags & ' . GAME_FLAG_FUN . ') = 0');
			break;
		case GAMES_FILTER_NO_RATING:
			$condition->add(' AND (g.flags & ' . GAME_FLAG_FUN . ') <> 0');
			break;
	}

	switch ($filter & GAMES_FILTER_MASK_CANCELED)
	{
		case GAMES_FILTER_CANCELED:
			$condition->add(' AND g.canceled <> 0');
			break;
		case GAMES_FILTER_NO_CANCELED:
			$condition->add(' AND g.canceled = 0');
			break;
	}
	return $condition;
}

?>