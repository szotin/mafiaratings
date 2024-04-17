<?php

//require_once '../../include/json.php';

define('ROUNDS_FILENAME', 'rounds.json');
define('CURRENT_ROUND_FILENAME', 'current_round.json');

$file = fopen(ROUNDS_FILENAME, "r");
$rounds_str = fread($file, filesize(ROUNDS_FILENAME));
fclose($file);

$file = fopen(CURRENT_ROUND_FILENAME, "r");
$current_rounds_str = fread($file, filesize(CURRENT_ROUND_FILENAME));
fclose($file);

$rounds = json_decode($rounds_str);
$current_rounds = json_decode($current_rounds_str);

$table = 0;
if (isset($_REQUEST['t']))
{
	$table = min(max((int)$_REQUEST['t'], 0), 2);
}

$current_round = $current_rounds[$table];
$game = $rounds->games[$table][$current_round];

echo '<html><head><META content="text/html; charset=utf-8" http-equiv=Content-Type></head><body>';
echo '<table><tr><td colspan="10" align="center">';
echo '<h2>Следующая игра. Cтол ' . ($table + 1) . '. Раунд ' . ($current_round + 1) . '.</h2></td></tr>';
echo '</tr><tr>';
foreach ($game as $player_num)
{
	$player = $rounds->players[$player_num];
	echo '<td align="center"><b>' . $player->name . '</b></td>';
}
echo '</tr><tr>';
foreach ($game as $player_num)
{
	$player = $rounds->players[$player_num];
	echo '<td width="80"><img src="../../pics/user/icons/' . $player->id . '.png" width="80"></td>';
}
echo '</tr><tr>';
for ($num = 1; $num <= 10; ++$num)
{
	$player = $rounds->players[$player_num];
	echo '<td align="center"><b>' . $num . '</b></td>';
}
echo '</tr></table>';
?>
<script type="text/javascript">
setTimeout(function() { window.location.replace(document.URL); }, 10000);
</script>
<?php
echo '</body></html>';

?>