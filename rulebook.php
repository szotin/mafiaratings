<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang_code;
		include_once("include/languages/".$_lang_code."/rules.php");
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run(get_label('Game rules'));

?>