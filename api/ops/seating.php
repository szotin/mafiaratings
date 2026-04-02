<?php

require_once '../../include/api.php';
require_once '../../include/seating.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// add_pair
	//-------------------------------------------------------------------------------------------------------
	function add_pair_op()
	{
		// TODO: implement
	}

	function add_pair_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Add a pair policy between two players in a tournament.');
		$help->request_param('tournament_id', 'Tournament ID.');
		$help->request_param('user1_id', 'First player user ID.');
		$help->request_param('user2_id', 'Second player user ID.');
		$help->request_param('policy', 'Pair policy: ' . PAIR_POLICY_SEPARATE . ' = separate, ' . PAIR_POLICY_AVOID . ' = avoid, ' . PAIR_POLICY_BALANCED . ' = balanced, ' . PAIR_POLICY_WELCOME . ' = welcome.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Sitting Operations', CURRENT_VERSION);

?>
