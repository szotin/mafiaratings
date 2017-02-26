<?php

require_once 'include/session.php';
require_once 'include/rating_system.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('rating system')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	if ($_profile == NULL || !$_profile->is_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$club = $_profile->clubs[$club_id];
	$rs = new RatingSystem(-1, $club_id);
	
	
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
		for (var flag = 1; flag < <?php echo RATING_FIRST_AVAILABLE_FLAG; ?>; flag <<= 1)
		{
			params[flag] = $("#form-" + flag).val();
		}
		json.post("rating_system_ops.php", params, onSuccess);
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