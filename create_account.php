<?php

require_once 'include/page_base.php';
require_once 'include/url.php';
require_once 'include/user.php';

class Page extends PageBase
{
	private $name;
	private $email;
	
	protected function show_body()
	{
		echo '<table class="bordered light" width="100%">';
		echo '<tr><td class="dark" width="140">'.get_label('User name').':</td><td><input id="name"></td></tr>';
		echo '<tr><td class="dark">'.get_label('Email').':</td><td><input id="email"></td></tr>';
		echo '</table>';
		echo '<p><input value="'.get_label('Create').'" type="submit" class="btn norm" onclick="mr.createAccount($(\'#name\').val(), $(\'#email\').val())"></p>';
	}
}

$page = new Page();
$page->run(get_label('Create user account'), PERM_ALL);

?>