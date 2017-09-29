<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/email.php';
require_once 'include/editor.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = $_REQUEST['id'];
	list($user_name, $user_flags, $user_langs) = Db::record(get_label('user'), 'SELECT u.name, u.flags, u.languages FROM users u WHERE u.id = ?', $user_id);
	
	dialog_title(get_label('Edit [0]', $user_name));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="100" valign="top" class="dark">'.get_label('Languages').':</td><td class="light">';
	langs_checkboxes($user_langs);
	echo '</td></tr>';
	
	echo '<tr><td valign="top" class="dark">' . get_label('Permissions') . ':</td><td class="light">';
	echo '<input type="checkbox" id="form-admin" value="1"' . ((($user_flags & U_PERM_ADMIN) != 0) ? ' checked' : '') . '> '.get_label('Admin').'<br>';
	echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
	if ((($user_flags & U_FLAG_MALE) != 0))
	{
		echo '<input type="radio" name="male" id="form-male" value="1" checked/>'.get_label('male').'<br />';
		echo '<input type="radio" name="male" value="0" />'.get_label('female');
	}
	else
	{
		echo '<input type="radio" name="male" id="form-male" value="1"/>'.get_label('male').'<br />';
		echo '<input type="radio" name="male" value="0" checked/>'.get_label('female');
	}
	echo '</td></tr></table>';
	
?>	
	<script>
	
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("user_ops.php",
		{
			id: <?php echo $user_id; ?>,
			male: ($("#form-male").attr("checked") ? 1 : 0),
			admin: ($("#form-admin").attr("checked") ? 1 : 0),
			langs: languages,
			update: ""
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