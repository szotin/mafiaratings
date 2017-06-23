<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/email.php';
require_once 'include/city.php';
require_once 'include/country.php';

initiate_session();

try
{
	dialog_title(get_label('Account activation'));
	
	echo '<table class="dialog_form" width="100%"><tr><td colspan="2" class="light">';
	echo '<p>' . get_label('Hi [0]! You have to activate your account to start using [1].', $_profile->user_name, PRODUCT_NAME) . '</p>';
	echo '<p>' . get_label('Please use your real email. It is used in the activation process.') . '</p>';
	echo '<tr><td width="100">' . get_label('Email') . ':</td><td><input id="form-email" value="' . $_profile->user_email . '"></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("profile_ops.php",
			{
				email: $("#form-email").val(),
				activate: ""
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