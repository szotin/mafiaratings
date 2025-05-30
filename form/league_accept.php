<?php

require_once '../include/session.php';
require_once '../include/league.php';

initiate_session();

try
{
	dialog_title(get_label('Accept league request'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$id = $_REQUEST['id'];
	
	list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone) = Db::record(
		get_label('league'),
		'SELECT l.name, l.web_site, l.langs, l.user_id, nu.name, u.email, u.def_lang, u.flags, l.email, l.phone'.
		' FROM league_requests l' .
		' JOIN users u ON l.user_id = u.id' .
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE l.id = ?', $id);
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('User') . ':</td><td><a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td></tr>';
	echo '<tr><td>' . get_label('League name') . ':</td><td><input class="longest" id="form-name" value="' . $name . '"></td></tr>';
	echo '<tr><td>' . get_label('Web site') . ':</td><td><a href="' . $url . '" target="_blank">' . $url . '</a></td></tr>';
	echo '<tr><td>'.get_label('Languages').':</td><td>' . get_langs_str($langs, ', ') . '</td><tr>';
	echo '<tr><td>'.get_label('Contact email').':</td><td><a href="mailto:' . $email . '">' . $email . '</a></td><tr>';
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>' . $phone . '</td><tr>';
	
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<tr><td colspan="2">';
		echo '<input type="checkbox" id="form-elite"> ' . get_label('elite league. Elite leagues can create elite series that bring more rating points.');
		echo '</td></tr>';
	}
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		let flags = 0;
		if ($("#form-elite").attr('checked')) flags |= <?php echo LEAGUE_FLAG_ELITE; ?>;
		
		json.post("api/ops/league.php",
		{
			op: "accept"
			, request_id: <?php echo $id; ?>
			, name: $("#form-name").val()
			, flags: flags
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