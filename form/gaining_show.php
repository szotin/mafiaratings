<?php

require_once '../include/session.php';
require_once '../include/scoring.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('gaining system')));
	}
	$gaining_id = (int)$_REQUEST['id'];
	
	if (isset($_REQUEST['version']))
	{
		$gaining_version = (int)$_REQUEST['version'];
		list($gaining, $name, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? AND v.version = ?', $gaining_id, $gaining_version);
	}
	else
	{
		list($gaining, $name, $gaining_version, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, v.version, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? ORDER BY version DESC LIMIT 1', $gaining_id);
		$gaining_version = (int)$gaining_version;
	}
	$gaining = json_decode($gaining);
	
	$players = 30;
	if (isset($_REQUEST['players']))
	{
		$players = (int)$_REQUEST['players'];
	}
	
	$stars = 2;
	if (isset($_REQUEST['stars']))
	{
		$stars = (double)$_REQUEST['stars'];
	}
	
	$place = 0;
	if (isset($_REQUEST['place']))
	{
		$place = (int)$_REQUEST['place'];
	}
	
	dialog_title(get_label('Gaining system [0]. Version [1].', $name, $gaining_version));
	
	echo '<table class="transp" width="100%">';
	echo '<tr><td><input type="number" style="width: 45px;" step="1" min="1" max="10" id="form-stars" value="' . $stars . '" onChange="onChangeParams()"> ' . get_label('stars') . '</td>';
	echo '<td><input type="number" style="width: 45px;" step="1" min="10" id="form-players" value="' . $players . '" onChange="onChangeParams()"> ' . get_label('players') . '</td></tr>';
	echo '</table>';
	
	$points = get_gaining_points($gaining, $stars, $players);
	echo '<p><div id="form-gaining">';
	echo '<table class="bordered light" width="100%">';
	echo '<tr class="darker"><td width="100"><b>' . get_label('Place') . '</b></td><td><b>' . get_label('Points') . '</b></td></tr>';
	for ($p = 0; $p < count($points); ++$p)
	{
		echo '<tr';
		echo ($p == $place - 1 ? ' class="darker"' : '');
		echo '><td>' .($p + 1) . '</td><td>' . $points[$p] . '</td></tr>';
	}
	echo '</table>';
	echo '</div></p>';
	
?>
	<script>
	function onChangeParams()
	{
		json.post("api/get/gaining_points.php",
		{
			gaining_id: <?php echo $gaining_id; ?>
			, gaining_version: <?php echo $gaining_version; ?>
			, stars: $("#form-stars").val()
			, players: $("#form-players").val()
		},
		function(obj)
		{
			var html = '<table class="bordered light" width="100%"><tr class="darker"><td width="100"><b><?php echo get_label('Place'); ?></b></td><td><b><?php echo get_label('Points'); ?></b></td></tr>';
			for (var i = 0; i < obj.points.length; ++i)
			{
				html += '<tr';
				html += (i == <?php echo ($place - 1); ?> ? ' class="darker"' : '');
				html += '><td>' + (i + 1) + '</td><td>' + obj.points[i] + '</td></tr>';
			}
			html += '</table>';
			$("#form-gaining").html(html);
		});
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>