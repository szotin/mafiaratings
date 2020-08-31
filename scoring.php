<?php

require_once 'include/general_page_base.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $scoring;
	private $scoring_id;
	private $scoring_name;
	private $scoring_version;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
		}
		$scoring_id = (int)$_REQUEST['id'];
		
		list($this->scoring_id, $this->scoring, $this->scoring_name, $this->scoring_version, $club_id, $league_id) = Db::record(get_label('scoring system'), 'SELECT s.id, v.scoring, s.name, s.version, s.club_id, s.league_id FROM scorings s JOIN scoring_versions v ON v.scoring_id = s.id AND v.version = s.version WHERE s.id = ?', $scoring_id);
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
		$this->_title = get_label('Scoring system') . ': ' . $this->scoring_name;
	}
	
	protected function show_body()
	{
		echo '<p><button id="save" onclick="saveData()" disabled>' . get_label('Save') . '</button></p>';
		echo '<script src="js/scoring_editor.js"></script>';
		echo '<div id="scoring-editor"></div>';
	}
	
	protected function js()
	{
		$for = get_label('For') . ' ';
?>
		var data =
		{
			scoring: <?php echo $this->scoring; ?>,
			id: <?php echo $this->scoring_id; ?>,
			name: "<?php echo $this->scoring_name; ?>",
			version: <?php echo $this->scoring_version; ?>,
			strings:
			{
				civ: "<?php echo get_label('civilian'); ?>",
				sheriff: "<?php echo get_label('sheriff'); ?>",
				maf: "<?php echo get_label('mafia'); ?>",
				don: "<?php echo get_label('don'); ?>",
				roleErr: "<?php echo get_label('Please select at least one role.'); ?>",
				actionErr: "<?php echo get_label('Please select at least one game action.'); ?>",
				policyAdd: "<?php echo get_label('Add new policy.'); ?>",
				policyDel: "<?php echo get_label('Delete policy.'); ?>",
				statPoints: "<?php echo get_label('Static points'); ?>",
				difPoints: "<?php echo get_label('Depending on the game difficulty'); ?>",
				shotPoints: "<?php echo get_label('Depending on number of times player was killed first night'); ?>",
				shotPointsFigm: "<?php echo get_label('Depending on number of times player was killed first night by FIGM rules'); ?>",
				points: "<?php echo get_label('points'); ?>",
				minDif: "<?php echo get_label('min difficulty'); ?>",
				maxDif: "<?php echo get_label('max difficulty'); ?>",
				minNight1: "<?php echo get_label('min kill rate'); ?>",
				maxNight1: "<?php echo get_label('max kill rate'); ?>",
				sorting: "<?php echo get_label('When the scores are the same'); ?>",
				higher: "<?php echo get_label('higher'); ?>",
				lower: "<?php echo get_label('lower'); ?>",
				theOne: "<?php echo get_label('The winner is the one who has'); ?>",
				sumOf: "<?php echo get_label('sum of'); ?>",
				name: "<?php echo get_label('Scoring system name'); ?>",
				version: "<?php echo get_label('Version'); ?>"
			},
			sections:
			{
				"main": "<?php echo get_label('Main points'); ?>",
				"legacy": "<?php echo get_label('Legacy points'); ?>",
				"extra": "<?php echo get_label('Extra points'); ?>",
				"penalty": "<?php echo get_label('Penalty points'); ?>",
				"night1": "<?php echo get_label('Points for being killed first night'); ?>"
			},
			matters:
			{
				<?php echo '"' . SCORING_FLAG_PLAY . '": "' . $for . get_label('playing the game') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_WIN . '": "' . $for . get_label('winning') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_LOSE . '": "' . $for . get_label('loosing') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_CLEAR_WIN . '": "' . $for . get_label('clear winning (all day-kills were from the opposite team)') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_CLEAR_LOSE . '": "' . $for . get_label('clear loosing (all day-kills were from the player\'s team)') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_BEST_PLAYER . '": "' . $for . get_label('being the best player') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_BEST_MOVE . '": "' . $for . get_label('the best move') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SURVIVE . '": "' . $for . get_label('surviving the game') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_KILLED_FIRST_NIGHT . '": "' . $for . get_label('being killed the first night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_KILLED_NIGHT . '": "' . $for . get_label('being killed in the night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_FIRST_LEGACY_3 . '": "' . $for . get_label('guessing [0] mafia (after being killed the first night)', 3) . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_FIRST_LEGACY_2 . '": "' . $for . get_label('guessing [0] mafia (after being killed the first night)', 2) . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_WARNINGS_4 . '": "' . $for . get_label('getting 4 warnigs') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_KICK_OUT . '": "' . $for . get_label('beign kicked out from the game') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SURRENDERED . '": "' . $for . get_label('surrender (leaving the game by accepting the loss)') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_ALL_VOTES_VS_MAF . '": "' . $for . get_label('voting against mafia only (should participate in at least 3 votings)') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_ALL_VOTES_VS_CIV . '": "' . $for . get_label('voting against civilians only (should participate in at least 3 votings)') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING . '": "' . $for . get_label('sheriff being killed the next day after don found them') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT . '": "' . $for . get_label('sheriff being found by don the first night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT . '": "' . $for . get_label('sheriff being killed the first night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_BLACK_CHECKS . '": "' . $for . get_label('the first three checks of the sheriff being black') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_RED_CHECKS . '": "' . $for . get_label('the first three checks of the sheriff being red') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_EXTRA_POINTS . '": "' . $for . get_label('actions in the game rated by the moderator') . '"'; ?>
			},
			sorting:
			{
				"m": "<?php echo get_label('main points'); ?>",
				"g": "<?php echo get_label('legacy points'); ?>",
				"e": "<?php echo get_label('extra points'); ?>",
				"p": "<?php echo get_label('penalty points'); ?>",
				"n": "<?php echo get_label('points for being killed first night'); ?>",
				"w": "<?php echo get_label('number of wins'); ?>",
				"s": "<?php echo get_label('number of special role wins'); ?>",
				"k": "<?php echo get_label('times being killed first night'); ?>"
			}
		};
		
		function onDataChange(d, isDirty)
		{
			console.log(isDirty);
			data = d;
			$('#save').prop('disabled', !isDirty || !isScoringDataCorrect());
		}
		
		function saveData()
		{
			console.log(data.scoring);
			var params =
			{
				op: 'change'
				, scoring_id: data.id
				, name: data.name
				, scoring: JSON.stringify(data.scoring)
			};
			json.post("api/ops/scoring.php", params, function(response)
			{
				setScoringVersion(response.scoring_version);
			});
			dirty(false);
		}
		
		initScoringEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>