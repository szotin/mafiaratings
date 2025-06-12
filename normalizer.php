<?php

require_once 'include/general_page_base.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $normalizer;
	private $normalizer_id;
	private $normalizer_name;
	private $normalizer_version;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring normalizer')));
		}
		$normalizer_id = (int)$_REQUEST['id'];
		
		list($this->normalizer_id, $this->normalizer, $this->normalizer_name, $this->normalizer_version, $club_id, $league_id) = Db::record(get_label('scoring normalizer'), 'SELECT s.id, v.normalizer, s.name, s.version, s.club_id, s.league_id FROM normalizers s JOIN normalizer_versions v ON v.normalizer_id = s.id AND v.version = s.version WHERE s.id = ?', $normalizer_id);
		if (is_null($club_id))
		{
			if (is_null($league_id))
			{
				check_permissions(PERMISSION_ADMIN);
			}
			else
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		else 
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if (!is_null($league_id))
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		$this->_title = get_label('Scoring normalizer') . ': ' . $this->normalizer_name;
	}
	
	protected function show_body()
	{
		echo '<p><button id="save" onclick="saveData()" disabled>' . get_label('Save') . '</button></p>';
		echo '<script src="js/normalizer_editor.js"></script>';
		echo '<div id="normalizer-editor"></div>';
	}
	
	protected function js()
	{
		$for = get_label('For') . ' ';
?>
		var data =
		{
			normalizer: <?php echo $this->normalizer; ?>,
			id: <?php echo $this->normalizer_id; ?>,
			name: "<?php echo $this->normalizer_name; ?>",
			version: <?php echo $this->normalizer_version; ?>,
			strings:
			{
				name: "<?php echo get_label('Scoring normalizer name'); ?>",
				version: "<?php echo get_label('Version'); ?>",
				policyAdd: "<?php echo get_label('Add new policy.'); ?>",
				policyDel: "<?php echo get_label('Delete policy.'); ?>",
				policies: "<?php echo get_label('Normalization policies'); ?>",
				ruleMultiply: "<?php echo get_label('Multiply the score by a given value'); ?>",
				ruleAverage: "<?php echo get_label('Divide the score by the number of games played (average per game)'); ?>",
				ruleWinPerc: "<?php echo get_label('Multiply the score by the winning rate'); ?>",
				ruleAvPerRound: "<?php echo get_label('Divide the score by the number of rounds (average per round)'); ?>",
				condAll: "<?php echo get_label('For all players'); ?>",
				condMin: "<?php echo get_label('minimum value'); ?>",
				condMax: "<?php echo get_label('maximum value'); ?>",
				condGames: "<?php echo get_label('By number of games played'); ?>",
				condGamesPerc: "<?php echo get_label('By percent of the games played'); ?>",
				condEvents: "<?php echo get_label('By number of rounds playes'); ?>",
				condEventsPerc: "<?php echo get_label('By percent of the rounds played'); ?>",
				condwinPerc: "<?php echo get_label('By winning percentage'); ?>",
				cntMinPre: "<?php echo get_label('For players who played more than or equal to'); ?>",
				cntMaxPre1: "<?php echo get_label(' and less than'); ?>",
				cntMaxPre2: "<?php echo get_label('For players who played less than'); ?>",
				rateMinPre: "<?php echo get_label('For players who has greater than or equal to'); ?>",
				rateMaxPre1: "<?php echo get_label(' and lower than'); ?>",
				rateMaxPre2: "<?php echo get_label('For players who has lower than'); ?>",
				gamesPost: "<?php echo get_label(' games'); ?>",
				gamesPercPost: "<?php echo get_label('% of the games'); ?>",
				roundsPost: "<?php echo get_label(' rounds'); ?>",
				roundsPercPost: "<?php echo get_label('% of the rounds'); ?>",
				winPercPost: "<?php echo get_label('% of the wins'); ?>",
				gamesPercHelp: "<?php echo get_label('Percent is calculated of the number of the games played by the player who played the most games in the tournament.'); ?>",
				roundsPercHelp: "<?php echo get_label('Percent is calculated of the number of the rounds played by the player who played the most rounds in the tournament.'); ?>",
				multiplyVal: "<?php echo get_label('The score in the end is multiplied by'); ?>",
				multiplyMin: "<?php echo get_label('The score in the end is multiplied by a value between'); ?>",
				multiplyMax: "<?php echo get_label('and, depending on the condition value'); ?>",
				multiplyInterp: "<?php echo get_label('change the value depending on the condition'); ?>",
				multiplyInterpHelp: "<?php echo get_label('For example: if you create a policy for players who played from 10 to 20 games, you may set minimum multiplier as 0.2 and maximum multiplier as 0.4. In this case the result for a player who played 10 games will be multiplied by 0.2; 20 games - by 0.4; 15 games - by 0.3; 11 games - by 0.22; etc.'); ?>",
				avTypeNothing: "<?php echo get_label('Simple average'); ?>",
				winRateHelp: "<?php echo get_label('For example if a player played 82 games, won 44 of them, and scored 46.6 points, the final result will be calculated as 46.6 * win_rate = 46.6 * 44 / 82 = 25.0'); ?>",
				
				avGamesTypeAdd: "<?php echo get_label('Add value to the number of games played'); ?>",
				avGamesTypeMin: "<?php echo get_label('Set minimum games'); ?>",
				avGamesTypeMinPercOfMax: "<?php echo get_label('Set minimum games % of the max games played'); ?>",
				avGamesTypeNothingHelp: "<?php echo get_label('For example: if a player played 20 games and scored 12.6 points in sum, the final score is 12.6 / 20 = 0.63. Which is the average points per game.'); ?>",
				avGamesTypeAddHelp: "<?php echo get_label('For example: if you set the value to add to 2, and a player played 8 games and scored 5.4 points, the final score is 5.4 / (8 + 2) = 0.54. This algorithm makes sure that players with low number of games do not have the highest scores.'); ?>",
				avGamesTypeMinHelp: "<?php echo get_label('For example: if you set minimum games to 20, and a player played 8 games and scored 5.4 points, the final score is 5.4 / MAX(8, 20) = 5.4 / 20 = 0.27. This algorithm makes sure that players with low number of games do not have the highest scores.'); ?>",
				avGamesTypeMinPercOfMaxHelp:  "<?php echo get_label('For example: if you set minimum games to 20%, and a player played 8 games, and scored 5.4 points.<br>The player who played the most games in the tournament played 200 games.<br>Then the final score is 5.4 / MAX(8, 200 * 20%) = 5.4 / MAX(8, 40) = 5.4 / 40 = 0.135.'); ?>",
				avGamesNone: "<?php echo get_label('Divide the score to the number of games.'); ?>",
				avGamesAdd: "<?php echo get_label('Divide the score to the number of games plus'); ?>",
				avGamesMin: "<?php echo get_label('Divide the score to the number of games but not less games than'); ?>",
				avGamesMinPercOfMax: "<?php echo get_label('Divide the score to the number of games but not less games than a % of maximum games played'); ?>",
				
				avRoundsTypeAdd: "<?php echo get_label('Add value to the number of rounds played'); ?>",
				avRoundsTypeMin: "<?php echo get_label('Set minimum rounds'); ?>",
				avRoundsTypeNothingHelp: "<?php echo get_label('For example: if a player played 20 rounds and scored 38.2 points in sum, the final score is 38.2 / 20 = 1.91. Which is the average points per round.'); ?>",
				avRoundsTypeAddHelp: "<?php echo get_label('For example: if you set the value to add to 2, and a player played 8 rounds and scored 43.2 points, the final score is 43.2 / (8 + 2) = 4.32. This algorithm makes sure that players with low number of rounds do not have the highest scores.'); ?>",
				avRoundsTypeMinHelp: "<?php echo get_label('For example: if you set minimum rounds to 20, and a player played 8 rounds and scored 43.2 points, the final score is 43.2 / MAX(8, 20) = 43.2 / 20 = 2.16. This algorithm makes sure that players with low number of rounds do not have the highest scores.'); ?>",
				avRoundsNone: "<?php echo get_label('Divide the score to the number of rounds.'); ?>",
				avRoundsAdd: "<?php echo get_label('Divide the score to the number of rounds plus'); ?>",
				avRoundsMin: "<?php echo get_label('Divide the score to the number of rounds but not less games than'); ?>",
			},
		};
		
		function onDataChange(d, isDirty)
		{
			data = d;
			$('#save').prop('disabled', !isDirty);
		}
		
		function saveData()
		{
			console.log(data.normalizer);
			var params =
			{
				op: 'change'
				, normalizer_id: data.id
				, name: data.name
				, normalizer: JSON.stringify(data.normalizer)
			};
			json.post("api/ops/normalizer.php", params, function(response)
			{
				setNormalizerVersion(response.normalizer_version);
			});
			dirty(false);
		}
		
		initNormalizerEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>