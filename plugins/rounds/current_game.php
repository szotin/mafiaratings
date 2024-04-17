<?php

//require_once '../include/json.php';

define('CURRENT_ROUND_FILENAME', 'current_round.json');

$file = fopen(CURRENT_ROUND_FILENAME, "r");
$current_rounds_str = fread($file, filesize(CURRENT_ROUND_FILENAME));
fclose($file);

$current_rounds = json_decode($current_rounds_str);

$table = 0;
if (isset($_REQUEST['t']))
{
	$table = min(max((int)$_REQUEST['t'], 0), 2);
}

$current_round = $current_rounds[$table];

echo '<html><head><META content="text/html; charset=utf-8" http-equiv=Content-Type></head><body>';
echo '<h2>Cтол ' . ($table + 1) . '. Раунд ' . ($current_round + 1) . '.</h2>';
?>
<script type="text/javascript">
setTimeout(function() { window.location.replace(document.URL); }, 10000);
</script>
<?php
echo '</body></html>';

?>