<?php

require_once('include/ui.php');
$upcoming = get_json('api/get/upcoming.php?club=2&len=7&df=D%20F%20d%20H:i');

echo '<table border="1" cellpadding="20" cellspacing="0"  width="100%">';

foreach ($upcoming->events as $event)
{
	echo '<tr><td>';
	echo '<table class="transp" cellpadding="20" cellspacing="0" width="100%"><tr><td valign="top">';
	echo 'Играем: <b><a href="' . $event->page . '" target="blank">' . $event->date_str . '</a></b><p>' . $event->notes . '</p>';
	if (count($event->coming) > 0)
	{
		$yes = array();
		$maybe = array();
		$no = array();
		foreach ($event->coming as $coming)
		{
			$str = '<a href="' . PRODUCT_URL . '/user_info.php?id=' . $coming->id . '" target="blank">' . $coming->name . '</a>';
			if ($coming->odds > 50)
			{
				$yes[] = $str;
			}
			else if ($coming->odds > 0)
			{
				$maybe[] = $str;
			}
			else
			{
				$no[] = $str;
			}
		}
		
		$delim1 = '';
		echo '<p>';
		if (count($yes) > 0)
		{
			$delim2 = 'Приходят: ';
			foreach ($yes as $str)
			{
				echo $delim2 . $str;
				$delim2 = ', ';
			}
			$delim1 = '<br>';
		}
		
		if (count($maybe) > 0)
		{
			echo $delim1;
			$delim2 = 'Может быть приходят: ';
			foreach ($maybe as $str)
			{
				echo $delim2 . $str;
				$delim2 = ', ';
			}
			$delim1 = '<br>';
		}
		
		if (count($no) > 0)
		{
			echo $delim1;
			$delim2 = 'Не приходят: ';
			foreach ($no as $str)
			{
				echo $delim2 . $str;
				$delim2 = ', ';
			}
		}
	}
	echo '</td><td align="center" valign="top" rowspan="2" width="1"><a href="' . $event->addr_url . '" target="blank">' . $event->addr . '<br>';
	if ($event->addr_image != '')
	{
		echo '<img src="' . $event->addr_image . '" border="0">';
	}
	echo '</a></td></tr><tr><td valign="bottom" align="center">';
	echo '<a href="' . $event->attend_page . '" target="blank">Прихожу</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $event->decline_page . '" target="blank">Не прихожу</a></td></tr>';
	echo '</table>';
	echo '</td></tr>';
}

echo '</table>';

?>