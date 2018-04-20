<?php

require_once 'include/session.php';
require_once 'include/user.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';
require_once 'include/image.php';
require_once 'include/languages.php';

initiate_session();

try
{
	dialog_title(get_label('Change password'));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark" width="160">' . get_label('Enter old password') . ':</td><td class="light"><input type="password" id="old_pwd"></td></tr>';
	echo '<tr><td class="dark">' . get_label('Enter new password') . ':</td><td class="light"><input type="password" id="new_pwd"></td></tr>';
	echo '<tr><td class="dark">' . get_label('Confirm new password') . ':</td><td class="light"><input type="password" id="confirm_pwd"></td></tr>';
	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("profile_ops.php",
		{
			old_pwd: $("#old_pwd").val(),
			new_pwd: $("#new_pwd").val(),
			confirm_pwd: $("#confirm_pwd").val(),
			change_pwd: ""
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