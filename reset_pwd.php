<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/page_base.php';
require_once 'include/email.php';

class Page extends PageBase
{
	protected function show_body()
	{
		echo '<form action="javascript:mr.resetPassword()"><p>'.get_label('Please enter your login name').':<br>';
		echo '<input id="name"><br>';
		echo get_label('And account email').':<br>';
		echo '<input id="email"></p>';
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Reset password').'"></p></form>';
	}
}

$page = new Page();
$page->run(get_label('Reset password'));

?>