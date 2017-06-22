<?php
require_once 'include/session.php';

initiate_session();
echo '<html><head><title>'.get_label('Email tags').'</title><META content="text/html; charset=utf-8" http-equiv=Content-Type></head><body style="{background-color:#aaaaaa; color:#303030;}">';

echo '<h3>'.get_label('Email tags').'</h3><table class="transp" width="100%"><tr><td width="150">'.get_label('Unsubscribe button').'</td><td><b>[unsub]</b>'.get_label('Button text').'<b>[/unsub]</b></td></tr>';
if (isset($_REQUEST['e']))
{
	echo '<tr><td>'.get_label('Accept button').'</td><td><b>[accept]</b>'.get_label('Button text').'<b>[/accept]</b></td></tr>';
	echo '<tr><td>'.get_label('Decline button').'</td><td><b>[decline]</b>'.get_label('Button text').'<b>[/decline]</b></td></tr>';
	echo '<tr><td>'.get_label('Event name').'</td><td><b>[ename]</b></td></tr>';
	echo '<tr><td>'.get_label('Event id').'</td><td><b>[eid]</b></td></tr>';
	echo '<tr><td>'.get_label('Event date').'</td><td><b>[edate]</b></td></tr>';
	echo '<tr><td>'.get_label('Event time').'</td><td><b>[etime]</b></td></tr>';
	echo '<tr><td>'.get_label('Event address').'</td><td><b>[addr]</b></td></tr>';
	echo '<tr><td>'.get_label('Event address URL').'</td><td><b>[aurl]</b></td></tr>';
	echo '<tr><td>'.get_label('Event address id').'</td><td><b>[aid]</b></td></tr>';
	echo '<tr><td>'.get_label('Event address image').'</td><td><b>[aimage]</b></td></tr>';
	echo '<tr><td>'.get_label('Event notes').'</td><td><b>[notes]</b></td></tr>';
	echo '<tr><td>'.get_label('Event languages').'</td><td><b>[langs]</b></td></tr>';
}
echo '<tr><td>'.get_label('User name').'</td><td><b>[uname]</b></td></tr>';
echo '<tr><td>'.get_label('User id').'</td><td><b>[uid]</b></td></tr>';
echo '<tr><td>'.get_label('User email').'</td><td><b>[email]</b></td></tr>';
echo '<tr><td>'.get_label('User points').'</td><td><b>[points]</b></td></tr>';
echo '<tr><td>'.get_label('Club name').'</td><td><b>[cname]</b></td></tr>';
echo '<tr><td>'.get_label('Club id').'</td><td><b>[cid]</b></td></tr>';
echo '<tr><td>'.get_label('Email code').'</td><td><b>[code]</b></td></tr>';
echo '</table></body></html>';

?>