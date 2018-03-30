<?php

require_once 'include/session.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Scoring system'));

	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$id = (int)$_REQUEST['id'];
	
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
	echo '<tr><td width="200">'.get_label('Scoring system name').':</td><td><input id="form-name" value="' . $name . '"></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var params =
		{
			id: <?php echo $id; ?>,
			name: $("#form-name").val(),
			update: ''
		};
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