<?php

require_once 'include/session.php';
require_once 'include/game_rules.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('rules')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club = $_profile->clubs[$_REQUEST['club']];
	if ($_profile == NULL || !$_profile->is_manager($club->id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$rules = new GameRules();
	$rules->load($club->rules_id);
	
	$rules->show_copy_select($club->id);
	echo '<table class="bordered" width="100%">';
	echo '<tr><td width="100">' . get_label('Rules name') . ':</td><td><input id="form-name"></td></tr>';
	$rules->show_form();
	echo '</table>';
	
?>	
	<script>
	$(function() { setFormRules(); });
	
	function commit(onSuccess)
	{
		var params =
		{
			op: 'create'
			, club_id: <?php echo $club->id; ?>
			, name: $("#form-name").val()
		};
		getFormRules(params);
		json.post("api/ops/rules.php", params, onSuccess);
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