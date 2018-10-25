<?php

require_once 'include/session.php';
require_once 'include/game_rules.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('rules')));
	
	if (!isset($_REQUEST['club']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$club = $_profile->clubs[$_REQUEST['club']];
	
	$rules_id = -1;
	if (isset($_REQUEST['id']))
	{
		$rules_id = $_REQUEST['id'];
	}
	
	if ($_profile == NULL || !$_profile->is_club_manager($club->id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$name = '';
	if (isset($_REQUEST['name']))
	{
		$name = $_REQUEST['name'];
	}
	else if ($rules_id > 0)
	{
		list ($name) =
			Db::record(get_label('rules'), 'SELECT name FROM club_rules WHERE club_id = ? AND rules_id = ?', $club->id, $rules_id);
	}
	
	$rules = new GameRules();
	if ($rules_id > 0)
	{
		$rules->load($rules_id);
	}
	else
	{
		$rules->load($club->rules_id);
	}
	
	$rules->show_copy_select($club->id);
	echo '<table class="bordered" width="100%">';
	if ($rules_id > 0)
	{
		echo '<tr><td width="100">' . get_label('Rules name') . ':</td><td><input id="form-name" value="' . $name . '"></td></tr>';
	}
	$rules->show_form();
	echo '</table>';
	
?>	
	<script>
	$(function() { setFormRules(); });
	
	function commit(onSuccess)
	{
		var params =
		{
			op: 'change'
			, club_id: 
<?php
			echo $club->id;
			if ($rules_id > 0)
			{
				echo ', name: $("#form-name").val(), rules_id: ' . $rules_id;
			}
?>
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