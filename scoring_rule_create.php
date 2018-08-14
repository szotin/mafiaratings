<?php

require_once 'include/session.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Scoring rule'));

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
	
	list ($name, $club_id) = Db::record(get_label('scoring system'), 'SELECT name, club_id FROM scorings WHERE id = ?', $id);
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
	echo '<tr><td width="100">' . get_label('Category') . ':</td><td><select id="form-category">';
	for ($i = 0; $i < SCORING_CATEGORY_COUNT; ++$i)
	{
		show_option($i, $category, ScoringRule::category_label($i));
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>'.get_label('Matter').':</td><td><select id="form-matter">';
	for ($i = 0; $i < SCORING_MATTER_COUNT; ++$i)
	{
		show_option($i, SCORING_MATTER_WIN, ScoringRule::matter_label($i));
	}
	echo '</select></td></tr>';
	
	echo '<td>'.get_label('Players').':</td><td>';
	echo '<table class="transp" width="100%"><tr>';
	echo '<td width="25%"><input type="checkbox" id="form-civ" checked>' . get_label('Civilians');
	echo '</td><td width="25%"><input type="checkbox" id="form-sheriff" checked>' . get_label('Sheriffs');
	echo '</td><td width="25%"><input type="checkbox" id="form-maf" checked>' . get_label('Mafia');
	echo '</td><td width="25%"><input type="checkbox" id="form-don" checked>' . get_label('Dons');
	echo '</td></tr></table>';
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Policy') . ':</td><td><select id="form-policy" onChange="policyChange()">';
	for ($i = 0; $i < SCORING_POLICY_COUNT; ++$i)
	{
		show_option($i, SCORING_POLICY_STATIC, ScoringRule::policy_label($i));
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Points') . ':</td><td id="form-points-holder"></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function policyChange()
	{
		switch (parseInt($("#form-policy").val()))
		{
			case <?php echo SCORING_POLICY_STATIC; ?>:
				$("#form-points-holder").html("<input id='form-points' value='0'>");
				$("#form-points").spinner({ step: 0.1, max: 100, min: -100, page: 1 });
				break;
			case <?php echo SCORING_POLICY_GAME_DIFFICULTY; ?>:
				$("#form-points-holder").html(
					'<table width="100%" class="transp">' +
					'<tr><td width="240"><?php echo get_label('When game difficulty is lower or equal'); ?></td><td width="100"><input id="form-min-dep" value="0"> %</td><td><?php echo get_label('add'); ?> <input id="form-min-points" value="0"> <?php echo get_label('points'); ?></td></tr>' +
					'<tr><td colspan="3"><p><?php echo get_label('When game difficulty is between these values, the points gradually change depending on the difficulty.'); ?></p></td></tr>' +
					'<tr><td><?php echo get_label('When game difficulty is higher or equal'); ?></td><td><input id="form-max-dep" value="100"> %</td><td><?php echo get_label('add'); ?> <input id="form-max-points" value="0"> <?php echo get_label('points'); ?></td></tr>' +
					'</table>');
				$("#form-min-points").spinner({ step: 0.1, max: 100, min: -100, page: 1 });
				$("#form-max-points").spinner({ step: 0.1, max: 100, min: -100, page: 1 });
				$("#form-min-dep").spinner({ step: 1, max: 100, min: 0, page: 10 });
				$("#form-max-dep").spinner({ step: 1, max: 100, min: 0, page: 10 });
				break;
			case <?php echo SCORING_POLICY_FIRST_NIGHT_KILLING; ?>:
				$("#form-points-holder").html(
					'<table width="100%" class="transp">' +
					'<tr><td width="320"><?php echo get_label('When the player first night killing percentage is lower or equal'); ?></td><td><input id="form-min-dep" value="0"> %</td><td><input id="form-min-points" value="0"> <?php echo get_label('points'); ?></td></tr>' +
					'<tr><td colspan="3"><p><?php echo get_label('When the percentage is between these values, the points gradually change depending on the first night killing rate.'); ?></p></td></tr>' +
					'<tr><td><?php echo get_label('When the player first night killing percentage is higher or equal'); ?></td><td><input id="form-max-dep" value="100"> %</td><td><input id="form-max-points" value="0"> <?php echo get_label('points'); ?></td></tr>' +
					'</table>');
				$("#form-min-points").spinner({ step: 0.1, max: 100, min: -100, page: 1 });
				$("#form-max-points").spinner({ step: 0.1, max: 100, min: -100, page: 1 });
				$("#form-min-dep").spinner({ step: 1, max: 100, min: 0, page: 10 });
				$("#form-max-dep").spinner({ step: 1, max: 100, min: 0, page: 10 });
				break;
		}
	}
	
	policyChange();
	
	function commit(onSuccess)
	{
		var role_flags = 0;
		if ($("#form-civ").prop('checked'))
			role_flags |= <?php echo SCORING_ROLE_FLAGS_CIV; ?>;
		if ($("#form-sheriff").prop('checked'))
			role_flags |= <?php echo SCORING_ROLE_FLAGS_SHERIFF; ?>;
		if ($("#form-maf").prop('checked'))
			role_flags |= <?php echo SCORING_ROLE_FLAGS_MAF; ?>;
		if ($("#form-don").prop('checked'))
			role_flags |= <?php echo SCORING_ROLE_FLAGS_DON; ?>;
		
		var params;
		if ($("#form-points").length > 0)
		{
			params =
			{
				op: 'create_rule'
				, scoring_id: <?php echo $id; ?>
				, matter: $("#form-matter").val()
				, category: $("#form-category").val()
				, roles: role_flags
				, policy: $("#form-policy").val()
				, min_dep: 0
				, min_points: $("#form-points").val()
				, max_dep: 0
				, max_points: $("#form-points").val()
			};
		}
		else
		{
			params =
			{
				op: 'create_rule'
				, scoring_id: <?php echo $id; ?>
				, matter: $("#form-matter").val()
				, category: $("#form-category").val()
				, roles: role_flags
				, policy: $("#form-policy").val()
				, min_dep: $("#form-min-dep").val() / 100
				, min_points: $("#form-min-points").val()
				, max_dep: $("#form-max-dep").val() / 100
				, max_points: $("#form-max-points").val()
			};
		}
		json.post("api/ops/scoring.php", params, onSuccess);
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