<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/user.php';

class Page extends OptionsPageBase
{
	protected function show_body()
	{
		echo '<form action="javascript:mr.changePassword()">';
		echo '<table class="bordered" width="100%">';
		echo '<tr><td class="dark" width="160">' . get_label('Enter old password') . ':</td><td class="light"><input type="password" id="old_pwd"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Enter new password') . ':</td><td class="light"><input type="password" id="new_pwd"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Confirm new password') . ':</td><td class="light"><input type="password" id="confirm_pwd"></td></tr>';
		echo '</table>';
		echo '<p><input value="'.get_label('Change').'" type="submit" class="btn norm"> </p></form>';
	}
}

$page = new Page();
$page->run(get_label('Change password'), PERM_USER);

?>