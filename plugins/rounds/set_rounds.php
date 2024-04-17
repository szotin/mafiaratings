<?php

//require_once '../include/json.php';

define('CURRENT_ROUND_FILENAME', 'current_round.json');

$file = fopen(CURRENT_ROUND_FILENAME, "r");
$current_rounds_str = fread($file, filesize(CURRENT_ROUND_FILENAME));
fclose($file);

$current_rounds = json_decode($current_rounds_str);

echo '<html><head><META content="text/html; charset=utf-8" http-equiv=Content-Type><script src="../../js/jquery.min.js"></script></head><body>';

echo '<table>';
for ($t = 0; $t < count($current_rounds); ++$t)
{
	echo '<tr><td>Стол ' . ($t+1) . '. Раунд:</td><td><input type="number" min="1" style="width: 45px;" id="table-' . $t . '" value="'.($current_rounds[$t]+1).'" onchange="roundChanged('.$t.')"></td></tr>';
}
?>
<script type="text/javascript">
function roundChanged(t)
{
	$.post('set_round.php?t=' + t + '&r=' + ($("#table-" + t).val()-1)).success(function(data, textStatus, response)
	{
	}).error(function(response)
	{
		console.log(response);
	});
}
</script>
<?php
echo '</body></html>';



?>