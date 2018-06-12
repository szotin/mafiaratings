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
	dialog_title(get_label('Create user account'));
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td class="dark" width="140">'.get_label('User name').':</td><td><input id="form-name"></td></tr>';
	echo '<tr><td class="dark">'.get_label('Email').':</td><td><input id="form-email"></td></tr>';
	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/account.php",
		{
			op: 'create'
			, name: $("#form-name").val()
			, email: $("#form-email").val()
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