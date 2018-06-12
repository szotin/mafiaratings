<?php

require_once('include/ui.php');

define('PAGE_SIZE', 18);

$p = 0;
if (isset($_REQUEST['p']))
{
	$p = $_REQUEST['p'];
}

try
{
	$albums = get_json('api/get/albums.php?club=2&pos=' . ($p * PAGE_SIZE) . '&len=' . PAGE_SIZE);

	
	echo '<h4>Фото и видео</h4><hr>';

	$column_count = 0;
	$albums_count = 0;
	foreach ($albums->albums as $album)
	{
		if ($column_count == 0)
		{
			if ($albums_count == 0)
			{
				echo '<table class="transp" width="100%">';
			}
			else
			{
				echo '</tr>';
			}
			echo '<tr>';
		}
			
		echo '<td width="33%" align="center"><a href="index.php?page=album&id=' . $album->id;
		echo '"><img src="' . $album->icon_url . '" border="0" width="120" height="180"><br>Папка: ' . $album->name . '</a></td>';
		
		++$albums_count;
		++$column_count;
		if ($column_count >= 3)
		{
			$column_count = 0;
		}
	}

	if ($albums_count > 0)
	{
		if ($column_count > 0)
		{
			echo '<td class="light" colspan="' . (3 - $column_count) . '">&nbsp;</td>';
		}
		echo '</tr></table>';
	}


	if ($p > 0 || ($p + 1) * PAGE_SIZE < $albums->count)
	{
		echo 'Страницы: ';
		$j = 0;
		for ($i = 0; $i < $albums->count; $i += PAGE_SIZE)
		{
			if ($j == $p)
			{
				echo '<font class="text2" style="background-color:#993300;color:#FFCC99"><b>' . ($j + 1) . '&nbsp;</b></font>';
			}
			else
			{
				echo '<a href="index.php?page=foto&p=' . $j . '">' . ($j + 1) . '</a>';
			}
			echo '&nbsp;|&nbsp;';
			++$j;
		}

		echo '<table class="transp" cellpadding="20" cellspacing="0"  width="100%"><tr><td class="back" width="40">';
		if ($p > 0)
		{
			echo '<a href="index.php?page=foto&p=' . ($p - 1) . '"><img src="images/left-arrow.png" width="40" border="0"></a>';
		}
		echo '</td><td align="right" width="40">';
		if (($p + 1) * PAGE_SIZE < $albums->count)
		{
			echo '<a href="index.php?page=foto&p=' . ($p + 1) . '"><img src="images/right-arrow.png" width="40" border="0"></a>';
		}
		echo '</td></tr></table>';
	}
}
catch (Exception $e)
{
	echo $e->getMessage();
}

?>