<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/url.php';

class Page extends OptionsPageBase
{
	protected function show_body()
	{
		global $_profile;
	
		echo '<form action="javascript:changeEmail()"><p id="11"></p>';
		echo '<table class="bordered" width="100%">';
		echo '<tr><td class="dark" width="200">' . get_label('Email') . ':</td><td class="light"><input id="email" value="' . $_profile->user_email . '" ></td></tr>';
		echo '</table>';
		echo '<p><input value="'.get_label('Change').'" type="submit" class="btn norm" id="change" disabled> </p></form>';
	}
	
	protected function js_on_load()
	{
?>
		$("#email").keyup( function () { $("#change").removeAttr("disabled"); });
<?php
	}
	
	protected function js()
	{
?>
		function changeEmail()
		{
			if ($("#email").val() != "")
			{
				dlg.yesNo(
					"<?php echo get_label('<p>Changing your email address deactivates your account. You will need to activate it back using your new email address.</p><p>Do you want to change it?</p>'); ?>", null, null,
					function()
					{
						json.post("profile_ops.php", { email: $("#email").val(), change_email: "" }, logout);
					});
			}
			else
			{
				dlg.error("<?php echo get_label('Please enter [0].', get_label('email address')); ?>");
			}
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Change email'), PERM_USER);

?>