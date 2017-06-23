<?php

require_once 'include/general_page_base.php';

function show_row($url, $name, $lang)
{
	echo '<tr><td><a href ="' . $url . '" title="' . get_label('Download [0] - [1]', $name, $lang) . '">';
	echo '<img src="images/download.png" border="0"></a></td>';
	echo '<td>' . $name . '</td><td>' . $lang . '</td></tr>';
}

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><td width="26"></td><td>' . get_label('Product') . '</td><td width="100">' . get_label('Language') . '</td></tr>';
		show_row('downloads/MafiaRatingsSetup.msi', get_label('[0] for Windows 2000/XP/7', PRODUCT_NAME), get_label('multilanguage'));
		show_row('downloads/MafiaRatingsSetup.ru.msi', get_label('[0] for Windows 2000/XP/7', PRODUCT_NAME), get_label('Russian'));
		echo '</table>';
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run(get_label('Download client software'), PERM_ALL);

?>