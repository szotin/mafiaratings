<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/page_base.php';

class Page extends InfoPageBase
{
	protected function show_body()
	{
		global $_lang_code;
		
		include_once("include/languages/".$_lang_code."/about.php");
	}
}

$page = new Page();
$page->run(get_label('About Mafia'));

?>