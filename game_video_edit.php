<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';

initiate_session();

try
{
	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];
	
	list($video) = Db::record(get_label('game'), 'SELECT video FROM games WHERE id = ?', $id);
		
	dialog_title(get_label('Set game [0] video', $id));
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">'.get_label('Video URL').':</td><td><input id="form-video" size="65" value="' . $video . '" title="' . get_label('Paste the youtube video URL here.') . '"></td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("game_ops.php",
		{
			id: <?php echo $id; ?>,
			set_video: $("#form-video").val()
		},
		onSuccess);
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