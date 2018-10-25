<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_ADMIN);
		$var = '_SESSION';
		if (isset($_REQUEST['var']))
		{
			$var = $_REQUEST['var'];
		}
		
		echo '<form method="get" name="varSelector"><select name="var" onChange="document.varSelector.submit()">';
		foreach ($GLOBALS as $v => $val)
		{
			show_option($v, $var, $v);
		}
		echo '</select></form><br>';
		
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td>$' . $var . '</td></tr>';
		
		echo '<tr><td><pre>';
		print_r($GLOBALS[$var]);
		echo '</td></tr>';
		
		echo '</table>';
	}
}

$page = new Page();
$page->run('Vars for ' . get_server_url());

?>