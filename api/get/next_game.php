<?php

require_once '../../include/api.php';
require_once '../../include/game.php';
require_once '../../include/datetime.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_profile, $_lang;
		
		
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('event_id', 'Tournament stage id.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('number', 'Game number of the tournament starting from 1.', 'current game number is calculated automatically by mafiaratings.');
		// $param = $help->response_param('game', 'Game state.');
			// $param->sub_param('id', 'Game id.');
			// $param->sub_param('name', 'Game name.');
			// $players = $param->sub_param('players', 'Players.');
				// $players->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				// $players->sub_param('name', 'Player nickname.');
				// $players->sub_param('number', 'Number in the game.');
				// $players->sub_param('photoUrl', 'A link to the user photo. If user is missing - a link to a transparent image.');
				// $players->sub_param('hasPhoto', 'True - if a player has custom photo. False - when player did not upload photo, or when id<=0, which means there is no player.');
				// $players->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
				// $players->sub_param('role', 'One of: "town", "sheriff", "maf", or "don".');
				// $players->sub_param('warnings', 'Number of warnings.');
				// $players->sub_param('isSpeaking', 'A boolean which is true when the player is speaking.');
				// $players->sub_param('state', 'Player state - "dead" or "alive".');
				// $players->sub_param('deathRoound', 'If player state is "dead" it is set to the roound number when they died.');
				// $players->sub_param('deathType', 'If player state is "dead" it is set to the type of their death. One of: "voting", "shooting", "warnings", "giveUp", or "kickOut".');
				// $players->sub_param('checkedByDon', 'If a player was checked by the don it contains the roound number when it happened.');
				// $players->sub_param('checkedBySheriff', 'If a player was checked by the sheriff it contains the roound number when it happened.');
			// $moderator = $param->sub_param('moderator', 'Moderator.');
				// $moderator->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				// $moderator->sub_param('name', 'Moderator nickname.');
				// $moderator->sub_param('photoUrl', 'A link to the moderator photo.');
				// $moderator->sub_param('hasPhoto', 'True - if a moderator has custom photo. False - when moderator did not upload photo, or when id<=0, which means there is no moderator yet.');
				// $moderator->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
 			// $param->sub_param('phase', 'Current game phase - "day" or "night".');
 			// $param->sub_param('state', 'Contains more detailed information about the game phase - which part of the day or night. One of:<ul><li>"notStarted" - when the game is not started yet.</li><li>"starting" - night before shooting, or day before any speaches.</li><li>"arranging" - mafia is arranging in night 0.</li><li>"speaking" - normal day speaches.</li><li>"nightKillSpeaking" - a player gives their last speach after being night-shooted.</li><li>"voting" - voting phase.</li><li>"nomineeSpeaking" - 30-sec speach after splitting the table.</li><li>"shooting" - mafia is shooting.</li><li>"donChecking" - don is checking.</li><li>"sheriffChecking" - sheriff is checking.</li><li>"mafiaWon" - game over mafia won.</li><li>"townWon" - game over town won.</li><li>"unknown" - something strange happening.</li></ul>');
 			// $param->sub_param('roound', 'Current roound number. Game starts with night-0; then day-0; then night-1; day-1; etc.');
 			// $param->sub_param('nominees', 'Array of players currently nominated. It is set only in the day phase.');
 			// $param->sub_param('votingCanceled', 'Boolean which is true when votings were canceled (most likely because someone was mod-killed). It is set only in the day phase.');
				
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Currently Played Game', CURRENT_VERSION);

?>