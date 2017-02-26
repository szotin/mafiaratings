<?php

require_once('include/ui.php');

define('PAGE_SIZE', 18);

$p = 0;
if (isset($_REQUEST['p']))
{
	$p = $_REQUEST['p'];
}

$id = $_REQUEST['id'];

$album = get_json('ws_album.php?id=' . $id . '&pos=' . ($p * PAGE_SIZE) . '&len=' . PAGE_SIZE);

echo '<h4>Фото и видео</h4><hr>Вы в папке: ' . $album->name;
echo '<br><a href="index.php?page=foto">&lt;&lt; назад в корень</a> из папки ' . $album->name . '<br>';

$column_count = 0;
$photo_count = 0;
foreach ($album->photos as $photo)
{
	if ($column_count == 0)
	{
		if ($photo_count == 0)
		{
			echo '<table class="transp" width="100%">';
		}
		else
		{
			echo '</tr>';
		}
		echo '<tr>';
	}
		
	echo '<td width="33%" align="center" valign="top"><a href="#" onclick="open_window(488,725,\'' . $photo->url;
	echo '\');" target="blank"><img src="' . $photo->tnail_url . '" border="0" width="120"></a><br><br><br></td>';
	
	++$photo_count;
	++$column_count;
	if ($column_count >= 3)
	{
		$column_count = 0;
	}
}

if ($photo_count > 0)
{
	if ($column_count > 0)
	{
		echo '<td class="light" colspan="' . (3 - $column_count) . '">&nbsp;</td>';
	}
	echo '</tr></table>';
}

echo '<a href="index.php?page=foto">&lt;&lt; назад в корень</a> из папки ' . $album->name . '<br>';
if ($p > 0 || ($p + 1) * PAGE_SIZE < $album->photo_count)
{
	echo 'Страницы: ';
	$j = 0;
	for ($i = 0; $i < $album->photo_count; $i += PAGE_SIZE)
	{
		if ($j == $p)
		{
			echo '<font class="text2" style="background-color:#993300;color:#FFCC99"><b>' . ($j + 1) . '&nbsp;</b></font>';
		}
		else
		{
			echo '<a href="index.php?page=album&id=' . $id . '&p=' . $j . '">' . ($j + 1) . '</a>';
		}
		echo '&nbsp;|&nbsp;';
		++$j;
	}

	echo '<table class="transp" cellpadding="20" cellspacing="0"  width="100%"><tr><td class="back" width="40">';
	if ($p > 0)
	{
		echo '<a href="index.php?page=album&id=' . $id . '&p=' . ($p - 1) . '"><img src="images/left-arrow.png" width="40" border="0"></a>';
	}
	echo '</td><td align="right" width="40">';
	if (($p + 1) * PAGE_SIZE < $album->photo_count)
	{
		echo '<a href="index.php?page=album&id=' . $id . '&p=' . ($p + 1) . '"><img src="images/right-arrow.png" width="40" border="0"></a>';
	}
	echo '</td></tr></table>';
}

?>