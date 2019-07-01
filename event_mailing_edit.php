<?php

require_once 'include/session.php';
require_once 'include/event.php';
require_once 'include/timespan.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('event mailing')));

	if (!isset($_REQUEST['mailing_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('mailing')));
	}
	$mailing_id = $_REQUEST['mailing_id'];
	
	list ($event_id, $club_id, $time, $status, $flags, $langs, $type) = 
		Db::record(get_label('mailing'), 'SELECT m.event_id, e.club_id, m.send_time, m.status, m.flags, m.langs, m.type FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);

	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	if ($status != MAILING_WAITING)
	{
		throw new Exc(get_label('Can not change mailing. Some emails are already sent.', get_label('mailing')));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="100">' . get_label('Email type').':</td><td><select id="form-type">';
	show_option(EVENT_EMAIL_INVITE, $type, get_label('Invitation'));
	show_option(EVENT_EMAIL_CANCEL, $type, get_label('Cancel event notification'));
	show_option(EVENT_EMAIL_RESTORE, $type, get_label('Restore event notification'));
	show_option(EVENT_EMAIL_CHANGE_ADDRESS, $type, get_label('Change address notification'));
	show_option(EVENT_EMAIL_CHANGE_TIME, $type, get_label('Change time notification'));
	echo '</select></td></tr>';
	
	echo '<tr><td>'.get_label('Sending time').':</td><td><input value="' . timespan_to_string($time) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-send-time" onkeyup="check()"> ' . get_label('before the event.') . '</td></tr>';
	
	echo '<tr><td>' . get_label('Recipients') . ':</td><td>';
	
	echo '<p><input type="checkbox" id="form-attended" onclick="check()"';
	if ($flags & MAILING_FLAG_TO_ATTENDED)
	{
		echo ' checked';
	}
	echo '> ' . get_label('players who attended the event');
	
	echo '<br><input type="checkbox" id="form-declined" onclick="check()"';
	if ($flags & MAILING_FLAG_TO_DECLINED)
	{
		echo ' checked';
	}
	echo '> ' . get_label('players who declined the event');
	
	echo '<br><input type="checkbox" id="form-others" onclick="check()"';
	if ($flags & MAILING_FLAG_TO_DESIDING)
	{
		echo ' checked';
	}
	echo '> ' . get_label('players who have not decided') . '</p><p>';

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
			op: "change_mailing"
			, mailing_id: "<?php echo $mailing_id; ?>"
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