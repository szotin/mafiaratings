<?php

require_once '../../include/api.php';
require_once '../../include/seating.php';

define('CURRENT_VERSION', 0);

//-------------------------------------------------------------------------------------------------------
// What swapping two players in one game of a round would cost, and how it could be paid for.
//
// Asked before the swap is made, so the dialog can say what is about to happen and offer the ways
// out rather than doing it quietly. The swap itself is api/ops/event.php's swap_seating_players,
// which takes one of the plans handed back here.
//-------------------------------------------------------------------------------------------------------
class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;

		$event_id = (int)get_required_param('event_id');
		$user1_id = (int)get_required_param('user1_id');
		$user2_id = (int)get_required_param('user2_id');
		$game = (int)get_required_param('game');

		list($club_id, $tournament_id, $misc_str) = Db::record(get_label('event'),
			'SELECT club_id, tournament_id, misc FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);

		$misc = is_null($misc_str) ? null : json_decode($misc_str);
		if (is_null($misc) || !isset($misc->seating->rounds) || !isset($misc->seating->mapping))
		{
			throw new Exc(get_label('Seating is not generated for this round'));
		}
		$rounds = json_decode(json_encode($misc->seating->rounds), true);
		if (!is_array($rounds) || $game < 1 || $game > count($rounds))
		{
			throw new Exc(get_label('Game [0] is not in this seating.', $game));
		}

		$slot_of = seating_slots_by_user($misc->seating->mapping);
		$slot1 = isset($slot_of[$user1_id]) ? $slot_of[$user1_id] : -1;
		$slot2 = isset($slot_of[$user2_id]) ? $slot_of[$user2_id] : -1;

		// Somebody the seating never named is a substitute: they take a seat rather than trade
		// one, so there is no seat of their own to put back and nothing to compensate.
		if ($slot1 < 0 || $slot2 < 0)
		{
			$names = seating_user_names(array($user1_id, $user2_id), $_lang);
			$this->response['kind'] = 'substitute';
			$this->response['options'] = array();
			$this->response['user1'] = isset($names[$user1_id]) ? $names[$user1_id] : '?';
			$this->response['user2'] = isset($names[$user2_id]) ? $names[$user2_id] : '?';
			// Which of the two is the newcomer, so the dialog can name them the right way round.
			$this->response['substitute_id'] = ($slot1 < 0) ? $user1_id : $user2_id;
			return;
		}

		$pos = seating_seat_positions($rounds);
		$seat1 = isset($pos[$game][$slot1]) ? $pos[$game][$slot1]['seat'] : null;
		$seat2 = isset($pos[$game][$slot2]) ? $pos[$game][$slot2]['seat'] : null;

		if (is_null($seat1) && is_null($seat2))
		{
			throw new Exc(get_label('Neither of these players is in the seating of this round.'));
		}

		if (!is_null($seat1) && !is_null($seat2) && $seat1 === $seat2)
		{
			// Same seat number at different tables. The statistic counts seat numbers, not
			// tables, so it cannot tell the difference and there is nothing to warn about.
			$this->response['kind'] = 'harmless';
			$this->response['options'] = array();
			return;
		}

		// One of them sitting the game out makes this a substitution for that game, which costs
		// each of them a game as well as a seat. There is no accepting that: the seating promises
		// everybody the same number of games.
		$this->response['kind'] = (is_null($seat1) || is_null($seat2)) ? 'absent' : 'numbers';

		$names = seating_user_names(array($user1_id, $user2_id), $_lang);
		$options = find_seating_swap_compensations($rounds, $game, $slot1, $slot2, 4, 3);
		$user_of = array();
		foreach ($slot_of as $uid => $slot)
		{
			$user_of[$slot] = $uid;
		}

		$out = array();
		foreach ($options as $swaps)
		{
			$ids = array();
			foreach ($swaps as $sw)
			{
				foreach (array($sw['slot1'], $sw['slot2']) as $s)
				{
					if (isset($user_of[$s]))
					{
						$ids[$user_of[$s]] = true;
					}
				}
			}
			$more = seating_user_names(array_keys($ids), $_lang);
			$plan = array();
			foreach ($swaps as $sw)
			{
				$u1 = isset($user_of[$sw['slot1']]) ? $user_of[$sw['slot1']] : 0;
				$u2 = isset($user_of[$sw['slot2']]) ? $user_of[$sw['slot2']] : 0;
				$plan[] = array(
					'game' => $sw['game'],
					'user1_id' => $u1,
					'user2_id' => $u2,
					'user1' => isset($more[$u1]) ? $more[$u1] : '?',
					'user2' => isset($more[$u2]) ? $more[$u2] : '?');
			}
			$out[] = $plan;
		}

		$this->response['options'] = $out;
		$this->response['user1'] = isset($names[$user1_id]) ? $names[$user1_id] : '?';
		$this->response['user2'] = isset($names[$user2_id]) ? $names[$user2_id] : '?';
		$this->response['seat1'] = is_null($seat1) ? null : $seat1 + 1;
		$this->response['seat2'] = is_null($seat2) ? null : $seat2 + 1;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER);
		$help->request_param('event_id', 'The event (round) id.');
		$help->request_param('user1_id', 'First user id.');
		$help->request_param('user2_id', 'Second user id.');
		$help->request_param('game', 'The game to swap them in, numbered from 1.');
		$help->response_param('kind', '"harmless" when the seat numbers do not change and the swap can just be made; "substitute" when one of them is not in the seating at all, which is the same; "numbers" when it costs the seat number statistics; "absent" when one of them sits that game out, which costs a game as well and cannot be accepted.');
		$help->response_param('options', 'Plans that put the statistics back, up to four. Each is a list of further swaps, every one with game, user1_id, user2_id and the two names. Empty when none was found.');
		$help->response_param('user1', 'Name of the first player.');
		$help->response_param('user2', 'Name of the second player.');
		$help->response_param('seat1', 'Seat the first player has in that game, from 1, or null when they do not play it.');
		$help->response_param('seat2', 'Same for the second player.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Seating swap options', CURRENT_VERSION);

?>
