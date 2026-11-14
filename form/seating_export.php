<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

// Which way the seating should be written out. The file itself is made by seating_export.php in
// the root - this only asks the question and sends the browser there.
//
// The question is worth asking because the three are for three different things: the sheet is
// for a person and a spreadsheet, CSV is the same sheet for the spreadsheets that would rather
// have commas, and the DimTom file is for the tool the seatings are arranged in. See
// seating_to_text() and seating_to_dimtom() in include/seating.php.

try
{
	global $_lang;

	dialog_title(get_label('Export seating'));

	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	if ($event_id <= 0)
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}

	list($club_id, $tournament_id, $misc_str) = Db::record(get_label('event'),
		'SELECT club_id, tournament_id, misc FROM events WHERE id = ?', $event_id);

	// The same list the upload asks for and the same the file page asks for: whoever may replace
	// a seating may certainly read it out.
	check_permissions(
		PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
		PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
		$club_id, $event_id, $tournament_id);

	$misc = is_null($misc_str) ? NULL : json_decode($misc_str);
	if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
	{
		throw new Exc(get_label('There is no seating for this round yet.'));
	}

	// Opened in a tab of its own: the dialog is in the middle of being answered, and following
	// a link out of it would throw the answer away.
	$url = 'https://dimtom.github.io/web_schedule';
	$link = '<a href="' . $url . '" target="_blank">dimtom.github.io/web_schedule</a>';
	$formats = array(
		'tsv' => array(
			get_label('Tab separated (TSV)'),
			get_label('The players by nickname, a block for each game, columns separated by tabs. Opens in a spreadsheet, and can be uploaded back here.')),
		'csv' => array(
			get_label('Comma separated (CSV)'),
			get_label('The same sheet with commas instead of tabs.')),
		'dimtom' => array(
			get_label('DimTom schedule'),
			get_label('The file [0] works with. The players go into it by nickname, so one exported here and brought back keeps everybody where they are.', $link)));

	echo '<table class="dialog_form" width="100%">';
	$checked = ' checked';
	foreach ($formats as $key => $format)
	{
		echo '<tr><td style="padding-bottom:8px">';
		echo '<label><input type="radio" name="form-format" value="' . $key . '"' . $checked . '> <b>' . $format[0] . '</b></label>';
		echo '<div style="color:gray; margin-left:22px">' . $format[1] . '</div>';
		echo '</td></tr>';
		$checked = '';
	}
	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var format = $("input[name='form-format']:checked").val();
		if (!format)
		{
			dlg.error("<?php echo get_label('Please select a format.'); ?>");
			return;
		}
		// The browser is sent to the page that writes the file. What comes back is marked as
		// something to save rather than to show, so this page stays where it is; onSuccess only
		// closes the dialog, since nothing behind it has changed and there is nothing to reload.
		window.location = "seating_export.php?event_id=<?php echo $event_id; ?>&format=" + format;
		onSuccess();
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
