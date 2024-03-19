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
	echo '<tr><td width="300"><input type="number" style="width: 45px;" step="1" min="1" max="10" id="form-stars" value="' . $stars . '" onChange="onChangeParams()"> ' . get_label('stars') . '</td>';
	echo '<td><input type="number" style="width: 45px;" step="1" min="10" id="form-players" value="' . $players . '" onChange="onChangeParams()"> ' . get_label('players') . '</td>';
	echo '<td align="right"><input type="checkbox" id="form-series" onClick="onChangeParams()"> ' . get_label('for series of tournaments') . '</td></tr>';
	echo '</table>';
	
	echo '<p><div id="form-gaining"></div></p>';
	
?>
	<script>
	function onChangeParams()
	{
		var params = 
		{
			gaining_id: <?php echo $gaining_id; ?>
			, gaining_version: <?php echo $gaining_version; ?>
			, stars: $("#form-stars").val()
			, players: $("#form-players").val()
		};
		if ($('#form-series').attr('checked'))
		{
			params['series'] = true;
		}
		http.post("form/gaining_table.php", params, function(html)
		{
			$("#form-gaining").html(html);
		});
	}
	
	onChangeParams();
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