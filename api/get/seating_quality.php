<?php

require_once '../../include/api.php';
require_once '../../include/seating.php';

define('CURRENT_VERSION', 0);

//-------------------------------------------------------------------------------------------------------
// Quality of one stored seating, as the percentages the seating pages show.
//
// The pages use it to follow an optimization while it runs: they ask for the numbers again after
// each pass and move the quality bar, instead of sending the user off to watch the optimizer's
// own log in another tab. Read-only and cheap - one row by primary key.
//-------------------------------------------------------------------------------------------------------
class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$hash = get_required_param('hash');

		$row = (new DbQuery(
			'SELECT players_score, numbers_score, tables_score,'.
			' players_runs, numbers_runs, tables_runs,'.
			' players_void_runs, numbers_void_runs, tables_void_runs'.
			' FROM seatings WHERE hash = ?', $hash))->next();
		if (!$row)
		{
			throw new Exc(get_label('Seating not found.'));
		}
		list ($players_score, $numbers_score, $tables_score,
		      $players_runs, $numbers_runs, $tables_runs,
		      $players_void, $numbers_void, $tables_void) = $row;

		$quality = seating_quality($hash, (float)$players_score, (float)$numbers_score, (float)$tables_score);

		$this->response['players'] = is_null($quality->players) ? null : round($quality->players, 1);
		$this->response['numbers'] = is_null($quality->numbers) ? null : round($quality->numbers, 1);
		$this->response['tables']  = is_null($quality->tables)  ? null : round($quality->tables, 1);
		$this->response['runs'] = array(
			'players' => (int)$players_runs,
			'numbers' => (int)$numbers_runs,
			'tables'  => (int)$tables_runs);
		// A pass that found nothing counts as void. The optimizer does not write a better
		// seating out on every pass - it keeps improving a copy and saves when a sweep ends -
		// so the score standing still does not mean the pass was wasted, and only the void
		// count tells the two apart.
		$this->response['void_runs'] = array(
			'players' => (int)$players_void,
			'numbers' => (int)$numbers_void,
			'tables'  => (int)$tables_void);
		$this->response['scores'] = array(
			'players' => round((float)$players_score, 2),
			'numbers' => round((float)$numbers_score, 2),
			'tables'  => round((float)$tables_score, 2));
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('hash', 'Seating hash (required).');
		$help->response_param('players', 'How well the players are spread, 0 to 100. null when the measure says nothing - a ten player seating has only one way to seat everyone.');
		$help->response_param('numbers', 'How well the seat numbers are spread, 0 to 100.');
		$help->response_param('tables', 'How well the tables are spread, 0 to 100. null below three tables, where it means nothing.');
		$help->response_param('runs', 'Optimization passes made so far, per measure. Zero means the seating is as it was first generated.');
		$help->response_param('void_runs', 'Of those passes, how many found nothing to improve. Compare across two calls to tell a pass that made progress from one that did not.');
		$help->response_param('scores', 'The raw scores the percentages come from, per measure. Lower is better.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Seating quality', CURRENT_VERSION);

?>
