<?php

require_once('include/ui.php');

show_header('Vancouver Mafia');

$upcoming = get_json('api/get/upcoming.php?club=1&df=D%20F%20d%20H:i&lang=en');

echo '<table border="1" cellpadding="20" cellspacing="0"  width="100%">';
echo '<tr><td>Looking for volonteers for designing this web site!!!</td>';
echo '<tr><td>MafiaWorld and <a href="https://mafiaratings.com">MafiaRatings</a> are now separated. MafiaWorld represents Vancouver Mafia Club. <a href="https://mafiaratings.com">MafiaRatings</a> represents inter-club ratings site.</td>';

foreach ($upcoming->events as $event)
{
	echo '<tr><td>';
	echo '<table class="transp" cellpadding="20" cellspacing="0" width="100%"><tr>';
	echo '<td valign="top" rowspan="2"><a href="' . $event->addr_url . '" target="blank">' . $event->addr . '<br>';
	if ($event->addr_image != '')
	{
		echo '<img src="' . $event->addr_image . '">';
	}
	echo '</a></td>';
	echo '<td align="right" valign="top">';
	echo 'We are playing at: <b><a href="' . $event->page . '" target="blank">' . $event->date_str . '</a></b><p>' . $event->notes . '</p></td>';
	echo '</tr><tr><td valign="bottom" align="center">';
	echo '<a href="' . $event->attend_page . '" target="blank">Attend</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $event->decline_page . '" target="blank">Decline</a></td></tr>';
	echo '</table>';
	echo '</td></tr>';
}

echo '</table>';

show_footer();

?>