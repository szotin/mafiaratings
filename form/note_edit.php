<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';

initiate_session();

try
{
	dialog_title(get_label('Edit note'));

	if (!isset($_REQUEST['note']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('note')));
	}
	$note_id = $_REQUEST['note'];
	
	list ($name, $note, $club_id) = Db::record(get_label('note'), 'SELECT name, value, club_id FROM club_info WHERE id = ?', $note_id);

	echo '<p>' . $name . ':</p><textarea id="form-note" cols="80" rows="4">' . htmlspecialchars($note, ENT_QUOTES) . '</textarea>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/note.php",
			{
				op: 'change'
				, note_id: <?php echo $note_id; ?>
				, note: $("#form-note").val()
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
	echo '<error=' . $e->getMessage() . '>';
}

?>