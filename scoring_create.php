<?php

require_once 'include/session.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Scoring system'));

	$club_id = -1;
	if (isset($_REQUEST['club']))
	{
		$club_id = $_REQUEST['club'];
	}
	if ($_profile == NULL || !$_profile->is_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if ($club_id < 0 && !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$rs = new ScoringSystem(-1, $club_id);
	$rs->show_edit_form();
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			name: $("#form-name").val(),
			digits: $("#form-digits").val(),
			club: <?php echo $club_id; ?>,
			create: ''
		};
		for (var flag = 1; flag < <?php echo SCORING_FIRST_AVAILABLE_FLAG; ?>; flag <<= 1)
		{
			params[flag] = $("#form-" + flag).val();
		}
		json.post("scoring_ops.php", params, onSuccess);
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