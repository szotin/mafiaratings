<?php

require_once '../include/session.php';
require_once '../include/event.php';
require_once '../include/timespan.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('event mailing')));

	if (!isset($_REQUEST['events']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$events = $_REQUEST['events'];
	
	$langs = 0;
	$event_ids = explode(',', $events);
	if (count($event_ids) <= 0)
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	
	$langs = 0;
	foreach ($event_ids as $event_id)
	{
		list($club_id, $tournament_id, $lgs) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, languages FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		$langs |= $lgs;
	}
	
	$type = -1;
	if (isset($_REQUEST['type']))
	{
		$type = $_REQUEST['type'];
	}
	
	echo '<table class="bordered" width="100%">';
	switch ($type)
	{
		case EVENT_EMAIL_INVITE:
			echo '<tr><td class="light" colspan="2"><center><p>' . get_label('Event is succesfuly created.') . '</p><p>' . get_label('Do you want to send invitations to your club members?') . '<p></center></td></tr>';
			break;
		case EVENT_EMAIL_CANCEL:
			echo '<tr><td class="light" colspan="2"><center><p>' . get_label('The event is canceled.') . '</p><p>' . get_label('Do you want to send notifications to your club members?') . '<p></center></td></tr>';
			break;
		case EVENT_EMAIL_CHANGE_ADDRESS:
			echo '<tr><td class="light" colspan="2"><center><p>' . get_label('Address of the event is changed.') . '</p><p>' . get_label('Do you want to send notifications to your club members?') . '<p></center></td></tr>';
			break;
		case EVENT_EMAIL_CHANGE_TIME:
			echo '<tr><td class="light" colspan="2"><center><p>' . get_label('Time of the event is changed.') . '</p><p>' . get_label('Do you want to send notifications to your club members?') . '<p></center></td></tr>';
			break;
		case EVENT_EMAIL_RESTORE:
			echo '<tr><td class="light" colspan="2"><center><p>' . get_label('The event is restored.') . '</p><p>' . get_label('Do you want to send notifications to your club members?') . '<p></center></td></tr>';
			break;
	}
	
	echo '<tr><td width="100">' . get_label('Email type').':</td><td><select id="form-type">';
	show_option(EVENT_EMAIL_INVITE, $type, get_label('Invitation'));
	show_option(EVENT_EMAIL_CANCEL, $type, get_label('Cancel event notification'));
	show_option(EVENT_EMAIL_RESTORE, $type, get_label('Restore event notification'));
	show_option(EVENT_EMAIL_CHANGE_ADDRESS, $type, get_label('Change address notification'));
	show_option(EVENT_EMAIL_CHANGE_TIME, $type, get_label('Change time notification'));
	echo '</select></td></tr>';
	
	echo '<tr><td>'.get_label('Sending time').':</td><td><input value="' . timespan_to_string(TIMESPAN_DAY * 3) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-send-time" onkeyup="check()"> ' . get_label('before the event.') . '</td></tr>';
	
	echo '<tr><td>' . get_label('Recipients') . ':</td><td>';
	
	echo '<p><input type="checkbox" id="form-attended" onclick="check()" checked> ' . get_label('players who attended the event');
	echo '<br><input type="checkbox" id="form-declined" onclick="check()"> ' . get_label('players who declined the event');
	echo '<br><input type="checkbox" id="form-others" onclick="check()" checked> ' . get_label('players who have not decided') . '</p><p>';

	echo get_label('Players who can speak:') . '<br>';
	langs_checkboxes($langs, LANG_ALL, NULL, '<br>', 'form-', 'check()');
	echo '</p></td></tr>';
	
	echo '</table>';

?>
	<script>
	function getFlags()
	{
		var _flags = 0;
		if ($("#form-attended").attr('checked')) _flags |= <?php echo MAILING_FLAG_TO_ATTENDED; ?>;
		if ($("#form-declined").attr('checked')) _flags |= <?php echo MAILING_FLAG_TO_DECLINED; ?>;
		if ($("#form-others").attr('checked')) _flags |= <?php echo MAILING_FLAG_TO_DESIDING; ?>;
		return _flags;
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: "create_mailing"
			, events: "<?php echo $events; ?>"
			, type: $("#form-type").val()
			, flags: getFlags()
			, langs: mr.getLangs('form-')
			, time: strToTimespan($("#form-send-time").val())
		},
		onSuccess);
	}
	
	function check()
	{
		$("#dlg-ok").button("option", "disabled", ($("#form-send-time").val()) <= 0 || mr.getLangs('form-') == 0 || getFlags() == 0);
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