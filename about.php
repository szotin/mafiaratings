<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/page_base.php';
require_once 'include/languages.php';

class Page extends PageBase
{
	protected function show_body()
	{
		global $_lang;
		
		include_once("include/languages/" . get_lang_code($_lang) . "/about.php");
	}
}

$page = new Page();
$page->run(get_label('About Mafia'));

?>