<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';

define('PREP_TAB_SCHEME', 0);

class Page extends TournamentPageBase
{
	private $tab;
	private $rounds;
	private $tournament_type;

	protected function prepare()
	{
		parent::prepare();

		$this->tab = PREP_TAB_SCHEME;
		if (isset($_REQUEST['tab']))
		{
			$this->tab = (int)$_REQUEST['tab'];
		}

		$query = new DbQuery('SELECT id, round, misc, players, tables, games FROM events WHERE tournament_id = ? ORDER BY round', $this->id);
		$main_rounds = array();
		$tmp_finals = array();
		while ($row = $query->next())
		{
			if ($row[1] == 0)
			{
				$main_rounds[] = $row;
			}
			else
			{
				$tmp_finals[] = $row;
			}
		}
		$this->rounds = $main_rounds;
		for ($i = count($tmp_finals) - 1; $i >= 0; --$i)
		{
			$this->rounds[] = $tmp_finals[$i];
		}

		list($this->tournament_type) = Db::record(get_label('tournament'), 'SELECT type FROM tournaments WHERE id = ?', $this->id);
	}

	private function showSchemeTab()
	{
		if (empty($this->rounds))
		{
			echo '<p>' . get_label('This tournament has no rounds. Select a tournament type to create rounds automatically.') . '</p>';
		}

		echo '<p>';
		show_tournament_type_select($this->tournament_type, 'tournament-type', 'changeTournamentType()');
		echo '</p>';

		if (empty($this->rounds))
		{
			return;
		}

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td>' . get_label('Round') . '</td>';
		echo '<td>' . get_label('Players') . '</td>';
		echo '<td>' . get_label('Tables') . '</td>';
		echo '<td>' . get_label('Games per player') . '</td>';
		echo '</tr>';

		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc, $players, $tables, $games) = $row;

			$players_val = is_null($players) ? '' : $players;
			$tables_val  = is_null($tables)  ? '' : $tables;
			$games_val   = is_null($games)   ? '' : $games;

			$inp = ' type="number" min="1" style="width:60px;"';
			$is_main = ($round_num == 0) ? '1' : '0';
			echo '<tr data-event-id="' . $event_id . '" data-is-main="' . $is_main . '">';
			echo '<td><b>' . get_round_name($round_num) . '</b></td>';
			echo '<td><input' . $inp . ' data-field="players" value="' . $players_val . '"></td>';
			echo '<td><input' . $inp . ' data-field="tables"  value="' . $tables_val  . '"></td>';
			echo '<td><input' . $inp . ' data-field="games"   value="' . $games_val   . '"></td>';
			echo '</tr>';
		}

		echo '</table></p>';
		echo '<p align="right"><button onclick="applyScheme()">' . get_label('Apply') . '</button></p>';
	}

	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);

		echo '<div class="tab">';
		echo '<button' . ($this->tab == PREP_TAB_SCHEME ? ' class="active"' : '') . ' onclick="goTo({tab:' . PREP_TAB_SCHEME . '})">' . get_label('Scheme') . '</button>';
		echo '</div>';

		switch ($this->tab)
		{
			case PREP_TAB_SCHEME:
				$this->showSchemeTab();
				break;
		}
	}

	protected function js()
	{
?>
		function changeTournamentType()
		{
			json.post("api/ops/tournament.php",
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				type: $('#tournament-type').val(),
			}, refr);
		}

		function applyScheme()
		{
			var rounds = [];
			$('tr[data-event-id]').each(function()
			{
				var row = $(this);
				var round = { event_id: row.data('event-id'), is_main: row.data('is-main') == 1 };
				row.find('input[data-field]').each(function()
				{
					var val = $(this).val();
					round[$(this).data('field')] = val === '' ? 0 : parseInt(val);
				});
				rounds.push(round);
			});
			json.post('api/ops/tournament.php',
			{
				op: 'set_scheme',
				tournament_id: <?php echo $this->id; ?>,
				rounds: JSON.stringify(rounds),
			}, refr);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Preparation'));

?>
