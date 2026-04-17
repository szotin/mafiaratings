<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Create seating'));
	check_permissions(PERMISSION_USER);

	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td width="220">' . get_label('Players') . ':</td>';
	echo '<td><input type="number" id="form-players" value="20" min="10" max="200" style="width:80px"></td></tr>';

	echo '<tr><td>' . get_label('Tables') . ':</td>';
	echo '<td><input type="number" id="form-tables" value="2" min="1" style="width:80px"></td></tr>';

	echo '<tr><td>' . get_label('Games per player') . ':</td>';
	echo '<td><input type="number" id="form-games" value="10" min="1" style="width:80px"></td></tr>';

	echo '<tr><td>' . get_label('Team size') . ':</td>';
	echo '<td><input type="number" id="form-team-size" value="1" min="1" style="width:80px"></td></tr>';

	echo '</table>';

	echo '<p><b>' . get_label('Players who cannot play together') . '</b></p>';
	echo '<table class="bordered light" width="100%" id="form-pairs-table">';
	echo '<tr class="darker">';
	echo '<td width="48" align="center">';
	echo '<button class="icon" onclick="addPair()" title="' . get_label('Add pair') . '"><img src="images/create.png" border="0"></button>';
	echo '</td>';
	echo '<td align="center">' . get_label('Player 1') . '</td>';
	echo '<td align="center">' . get_label('Player 2') . '</td>';
	echo '</tr>';
	echo '</table>';

?>
	<script>
	var pairCount = 0;

	function addPair()
	{
		var idx = pairCount++;
		var row = '<tr id="form-pair-row-' + idx + '">' +
			'<td class="dark" align="center">' +
			'<button class="icon" onclick="removePair(' + idx + ')" title="<?php echo get_label('Remove'); ?>"><img src="images/delete.png" border="0"></button>' +
			'</td>' +
			'<td align="center"><input type="number" id="form-pair-a-' + idx + '" min="0" style="width:70px" placeholder="#"></td>' +
			'<td align="center"><input type="number" id="form-pair-b-' + idx + '" min="0" style="width:70px" placeholder="#"></td>' +
			'</tr>';
		$('#form-pairs-table').append(row);
	}

	function removePair(idx)
	{
		$('#form-pair-row-' + idx).remove();
	}

	function commit(onSuccess)
	{
		var players = parseInt($('#form-players').val());
		var tables = parseInt($('#form-tables').val());
		var games = parseInt($('#form-games').val());
		var teamSize = parseInt($('#form-team-size').val());

		if (isNaN(players) || players < 10)
		{
			alert('<?php echo get_label('Players must be at least 10.'); ?>');
			return;
		}
		if (players > 200)
		{
			alert('<?php echo get_label('Players must be no more than 200.'); ?>');
			return;
		}
		if (isNaN(tables) || tables < 1)
		{
			alert('<?php echo get_label('Tables must be at least 1.'); ?>');
			return;
		}
		if (isNaN(games) || games < 1)
		{
			alert('<?php echo get_label('Games per player must be at least 1.'); ?>');
			return;
		}
		if (isNaN(teamSize) || teamSize < 1)
		{
			alert('<?php echo get_label('Team size must be at least 1.'); ?>');
			return;
		}
		if (teamSize > 1 && players % teamSize !== 0)
		{
			alert('<?php echo get_label('Players count must be divisible by team size.'); ?>');
			return;
		}
		if ((players * games) % 10 !== 0)
		{
			alert('<?php echo get_label('Players count multiplied by games count must be divisible by 10.'); ?>');
			return;
		}

		var restrictions = [];

		// Add team restrictions automatically when team size > 1.
		if (teamSize > 1)
		{
			for (var i = 0; i < players; i += teamSize)
			{
				var group = [];
				for (var j = 0; j < teamSize; j++)
				{
					group.push(i + j);
				}
				restrictions.push(group);
			}
		}

		// Add manually entered pairs.
		$('#form-pairs-table tr[id^="form-pair-row-"]').each(function()
		{
			var rowId = $(this).attr('id').replace('form-pair-row-', '');
			var a = parseInt($('#form-pair-a-' + rowId).val());
			var b = parseInt($('#form-pair-b-' + rowId).val());
			if (!isNaN(a) && !isNaN(b))
			{
				restrictions.push([a, b]);
			}
		});
		
		console.log(restrictions);

		var request =
		{
			op: 'create'
			, players: players
			, tables: tables
			, games: games
			, team_size: teamSize
			, restrictions: JSON.stringify(restrictions)
		};

		json.post("api/ops/seating.php", request, function(response)
		{
			dlg.close();
			goTo('seating.php?bck=1&hash=' + encodeURIComponent(response.hash));
		});
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
