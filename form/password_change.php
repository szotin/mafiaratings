<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/image.php';
require_once '../include/languages.php';

initiate_session();

try
{
	dialog_title(get_label('Change password'));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark" width="160">' . get_label('Enter old password') . ':</td><td class="light"><input type="password" id="old_pwd"></td></tr>';
	echo '<tr><td class="dark">' . get_label('Enter new password') . ':</td><td class="light"><input type="password" id="pwd1"></td></tr>';
	echo '<tr><td class="dark">' . get_label('Confirm new password') . ':</td><td class="light"><input type="password" id="pwd2"></td></tr>';
	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/account.php",
		{
			op: 'password_change'
			, old_pwd: $("#old_pwd").val()
			, pwd1: $("#pwd1").val()
			, pwd2: $("#pwd2").val()
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