<?php

require_once 'include/session.php';
require_once 'include/scoring_system.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('scoring system')));

	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$system = new ScoringSystem($_REQUEST['id']);
	if ($_profile == NULL || !$_profile->is_manager($system->club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$system->show_edit_form();
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			id: <?php echo $system->id; ?>,
			name: $("#form-name").val(),
			digits: $("#form-digits").val(),
			update: ''
		};
		for (var flag = 1; flag < <?php echo SCORING_FIRST_AVAILABLE_FLAG; ?>; flag <<= 1)
		{
			params[flag] = $("#form-" + flag).val();
		}
		json.post("scoring_system_ops.php", params, onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>