<?php

require_once('include/ui.php');

define('PAGE_SIZE', 10);

$p = 0;
if (isset($_REQUEST['p']))
{
	$p = $_REQUEST['p'];
}

$adverts = get_json('ws_adverts.php?club=2&pos=' . ($p * PAGE_SIZE) . '&len=' . PAGE_SIZE . '&df=l,%20F%20d,%20Y');

echo '<h4>Новости</h4><hr>';
foreach ($adverts->messages as $message)
{
	echo '<h5>' . $message->date_str . '</h5>';
	echo $message->message;
	echo '<hr>';
}

if ($p > 0 || ($p + 1) * PAGE_SIZE < $adverts->count)
{
	echo 'Страницы: ';
	$j = 0;
	for ($i = 0; $i < $adverts->count; $i += PAGE_SIZE)
	{
		if ($j == $p)
		{
			echo '<font class="text2" style="background-color:#993300;color:#FFCC99"><b>' . ($j + 1) . '&nbsp;</b></font>';
		}
		else
		{
			echo '<a href="index.php?page=adverts&p=' . $j . '">' . ($j + 1) . '</a>';
		}
		echo '&nbsp;|&nbsp;';
		++$j;
	}

	echo '<table class="transp" cellpadding="20" cellspacing="0"  width="100%"><tr><td class="back" width="40">';
	if ($p > 0)
	{
		echo '<a href="index.php?page=adverts&p=' . ($p - 1) . '"><img src="images/left-arrow.png" width="40" border="0"></a>';
	}
	echo '</td><td align="right" width="40">';
	if (($p + 1) * PAGE_SIZE < $adverts->count)
	{
		echo '<a href="index.php?page=adverts&p=' . ($p + 1) . '"><img src="images/right-arrow.png" width="40" border="0"></a>';
	}
	echo '</td></tr></table>';
}

?>