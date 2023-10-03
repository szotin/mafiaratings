<?php

require_once '../include/session.php';
require_once '../include/tournament.php';

initiate_session();

try
{
	dialog_title(get_label('Tournament payment'));
	
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	
	if (!isset($_REQUEST['series_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('series')));
	}
	
	$tournament_id = (int)$_REQUEST['tournament_id'];
	$series_id = (int)$_REQUEST['series_id'];
	list($league_id, $currency_name, $series_fee, $series_flags, $tournament_fee, $expected_players_count, $players_count) = Db::record(get_label('tournament'), 
		'SELECT s.league_id, nc.name, s.fee, st.flags, st.fee, t.expected_players_count, (SELECT count(*) FROM tournament_places tp WHERE tp.tournament_id = t.id) as count'.
		' FROM series_tournaments st'.
		' JOIN series s ON s.id = st.series_id'.
		' JOIN tournaments t ON t.id = st.tournament_id'.
		' LEFT OUTER JOIN currencies c ON c.id = s.currency_id'.
		' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
		' WHERE st.series_id = ? AND st.tournament_id = ?', $series_id, $tournament_id);
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	if (!is_null($tournament_fee))
	{
		$payment = $tournament_fee;
	}
	else if (is_null($series_fee))
	{
		$payment = '';
	}
	else if ($players_count > 0)
	{
		$payment = $series_fee * $players_count;
	}
	else
	{
		$payment = $series_fee * $expected_players_count;
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>' . get_label('Payement') . ':</td><td><input type="number" style="width: 45px;" min="0" step="' . ($series_fee > 0 ? $series_fee : 1) . '" value="' . $payment . '" id="form-payment"> ' . $currency_name . '</td></tr>';
	echo '<tr><td colspan="2"><input id="form-not-payed" onchange="uChange(2)" type="checkbox"' . (($series_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED) ? ' checked' : '') . '> ' . get_label('not payed') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/tournament.php",
		{
			op: 'payment'
			, tournament_id: <?php echo $tournament_id; ?>
			, series_id: <?php echo $series_id; ?>
			, payment: $('#form-payment').val()
			, not_payed: ($('#form-not-payed').attr('checked') ? 1 : 0)
		},
		onSuccess);
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