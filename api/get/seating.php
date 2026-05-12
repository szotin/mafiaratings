<?php

require_once '../../include/api.php';
require_once '../../include/seating.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$players = (int)get_required_param('players');
		$tables  = (int)get_required_param('tables');
		$games   = (int)get_required_param('games');

		$restrictions_raw      = get_optional_param('restrictions', '');
		$table_restrictions_raw = get_optional_param('table_restrictions', '');

		// Parse player restrictions: JSON array of groups, e.g. [[0,1],[2,3]]
		$restrictions = array();
		if ($restrictions_raw !== '')
		{
			$decoded = json_decode($restrictions_raw, true);
			if (!is_array($decoded))
				throw new Exc('Invalid restrictions format. Expected a JSON array of groups, e.g. [[0,1],[2,3]].');
			foreach ($decoded as $group)
			{
				if (is_array($group) && count($group) >= 2)
					$restrictions[] = array_map('intval', $group);
			}
		}

		// Parse table restrictions: JSON array indexed by table, e.g. [[4],null,[2,3]]
		$table_restrictions = array();
		if ($table_restrictions_raw !== '')
		{
			$decoded = json_decode($table_restrictions_raw, true);
			if (!is_array($decoded))
				throw new Exc('Invalid table_restrictions format. Expected a JSON array indexed by table, e.g. [[4],null,[2,3]].');
			foreach ($decoded as $t => $slots)
			{
				$table_restrictions[(int)$t] = (is_array($slots) && count($slots) > 0)
					? array_map('intval', $slots)
					: null;
			}
		}

		// Build SeatingDef, normalize restrictions to get the canonical hash.
		$seatingDef = new SeatingDef($players, $tables, $games, $restrictions);
		$restriction_mapping = $seatingDef->normalizeRestrictions();

		// Get seating from DB (or generate); $create=false so nothing is stored.
		$result = $seatingDef->findSeating(false);
		$seating = $result->seating;

		if (!empty($seating))
		{
			// Build full inverse mapping: normalized_slot → original_slot.
			// Restricted players: invert the normalization mapping.
			$norm_to_orig = array_flip($restriction_mapping);

			// Free original players (not in any restriction group), sorted ascending.
			$orig_free = array_values(array_diff(range(0, $players - 1), array_keys($restriction_mapping)));
			sort($orig_free);

			// Assign free original players to the normalized free slots k, k+1, ...
			$k = count($restriction_mapping);
			foreach ($orig_free as $i => $orig_player)
				$norm_to_orig[$k + $i] = $orig_player;

			// Apply inverse mapping to every seat.
			$mapped = array();
			foreach ($seating as $r => $round)
			{
				$mapped[$r] = array();
				foreach ($round as $t => $table)
				{
					$mapped[$r][$t] = array();
					foreach ($table as $seat)
						$mapped[$r][$t][] = $norm_to_orig[(int)$seat];
				}
			}

			// Apply table restrictions on the original-numbered seating.
			if (!empty($table_restrictions))
			{
				$origDef = new SeatingDef($players, $tables, $games, $restrictions);
				$mapped  = $origDef->applyTableRestrictions($mapped, $table_restrictions);
			}

			$seating = $mapped;
		}

		$this->response['seating']         = $seating;
		$this->response['status']          = $result->status;
		$this->response['seating_version'] = $result->seating_version;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('players', 'Total number of players (required).');
		$help->request_param('tables', 'Number of tables (required).');
		$help->request_param('games', 'Number of games per player (required).');
		$help->request_param('restrictions', 'JSON array of player restriction groups. Players in the same group will never share a table. Indices are 0-based. Example: [[0,1],[2,3]]', '[]');
		$help->request_param('table_restrictions', 'JSON array indexed by table number. Each element is an array of player slot indices forbidden at that table, or null. Example: [[4],null,[2,3]]', '[]');
		$help->response_param('seating', 'Three-dimensional array [round][table][seat] = player_index (0-based, matching the original input indices).');
		$help->response_param('status', '"ok" — exact seating found; "similar" — compatible seating adapted; "new" — initial seating generated.');
		$help->response_param('seating_version', 'Three-part version string "P.T.N" counting optimization passes (players.tables.numbers). "0.0.0" means the seating has not been optimized yet.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Seating', CURRENT_VERSION);

?>
