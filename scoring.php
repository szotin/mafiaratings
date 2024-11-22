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
		list($tournaments_count) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0', $this->scoring_id, $this->scoring_version);
		list($events_count) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . EVENT_FLAG_FINISHED . ') <> 0', $this->scoring_id, $this->scoring_version);
		$this->dependants = $tournaments_count + $events_count;
		
		$this->_title = get_label('Scoring system') . ': ' . $this->scoring_name;
		
		$this->langs_array = '[';
		$delim = '';
		for ($lang = get_next_lang(LANG_NO); $lang != LANG_NO; $lang = get_next_lang($lang))
		{
			$this->langs_array .= $delim . '"' . get_lang_code($lang) . '"';
			$delim = ',';
		}
		$this->langs_array .= ']';
	}
	
	protected function show_body()
	{
		echo '<p><button id="save" onclick="saveData(0)" disabled>' . get_label('Save') . '</button> <button id="save" onclick="viewJson()">' . get_label('View json') . '</button>';
		if ($this->dependants > 0)
		{
			echo ' <button id="overwrite" onclick="overwriteData()" disabled>' . get_label('Overwrite current version') . '</button>';
		}
		echo '</p>';
		
		echo '<ul id="lang-menu" style="display:none;position:absolute;text-align:left;z-index:2147483647;">';
		$lang = LANG_NO;
		while (($lang = get_next_lang($lang)) != LANG_NO)
		{
			$lang_code = get_lang_code($lang);
			echo '<li><a href="javascript:seAddLang(\'' . $lang_code . '\')">';
			echo '<img src="images/' . $lang_code . '.png" width="20">';
			echo '</a></li>';
		}
		echo '</ul>';
		
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
				shotPointsFiim: "<?php echo get_label('Depending on number of times player was killed first night by FIIM rules'); ?>",
				bonusPoints: "<?php echo get_label('Depending on bonus points given by a referee'); ?>",
				points: "<?php echo get_label('Points'); ?>",
				minDif: "<?php echo get_label('min difficulty percent'); ?>",
				maxDif: "<?php echo get_label('max difficulty percent'); ?>",
				minNight1: "<?php echo get_label('min first night kill percentage/count'); ?>",
				maxNight1: "<?php echo get_label('max first night kill percentage/count'); ?>",
				sorting: "<?php echo get_label('When the scores are the same'); ?>",
				higher: "<?php echo get_label('higher'); ?>",
				lower: "<?php echo get_label('lower'); ?>",
				theOne: "<?php echo get_label('The winner is the one who has'); ?>",
				sumOf: "<?php echo get_label('sum of'); ?>",
				name: "<?php echo get_label('Scoring system name'); ?>",
				percent: "<?php echo get_label('First night killing %'); ?>",
				dependingOnPercent: "<?php echo get_label('depending on the perentage of the kills rather than the absolute value'); ?>",
				lostOnly: "<?php echo get_label('taking only the lost games'); ?>",
				version: "<?php echo get_label('Version'); ?>",
				extraPointsWeight: "<?php echo get_label('Multiply bonus points to'); ?>",
				counters: "<?php echo get_label('Counters'); ?>",
				counterAdd: "<?php echo get_label('Add counter.'); ?>",
				counterDel: "<?php echo get_label('Delete counter.'); ?>",
				noMvp: "<?php echo get_label('not taken into account in the definition of MVP'); ?>",
				yesMvp: "<?php echo get_label('taken into account in the definition of MVP'); ?>",
				customMvp: "<?php echo get_label('taken into account in the definition of MVP but calculated differently'); ?>",
				mvpPoints: "<?php echo get_label('MVP points'); ?>",
				policyName: "<?php echo get_label('Policy name'); ?>",
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
				<?php echo '"' . SCORING_FLAG_WORST_MOVE . '": "' . $for . get_label('removed auto-bonus') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_SURVIVE . '": "' . $for . get_label('surviving the game') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_KILLED_FIRST_NIGHT . '": "' . $for . get_label('being killed the first night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_KILLED_NIGHT . '": "' . $for . get_label('being killed in the night') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_FIRST_LEGACY_3 . '": "' . $for . get_label('guessing [0] mafia (after being killed the first night)', 3) . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_FIRST_LEGACY_2 . '": "' . $for . get_label('guessing [0] mafia (after being killed the first night)', 2) . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_FIRST_LEGACY_1 . '": "' . $for . get_label('guessing [0] mafia (after being killed the first night)', 1) . '"'; ?>,
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
				<?php echo '"' . SCORING_FLAG_EXTRA_POINTS . '": "' . $for . get_label('actions in the game rated by the referee') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_TEAM_KICK_OUT . '": "' . $for . get_label('making the opposite team win') . '"'; ?>
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
			},
			langs: <?php echo $this->langs_array; ?>
		};
		
		function onDataChange(d, isDirty)
		{
			data = d;
			var dsb = !isDirty || !isScoringDataCorrect();
			$('#save').prop('disabled', dsb);
			$('#overwrite').prop('disabled', dsb);
		}
		
		function saveData(ov)
		{
			console.log(data.scoring);
			var params =
			{
				op: 'change'
				, scoring_id: data.id
				, name: data.name
				, scoring: JSON.stringify(data.scoring)
				, overwrite: ov
			};
			json.post("api/ops/scoring.php", params, function(response)
			{
				setScoringVersion(response.scoring_version);
				dirty(false);
			});
		}
		
		function viewJson()
		{
			dlg.info(JSON.stringify(data.scoring), 'Json');
		}
		
		function overwriteData()
		{
			dlg.yesNo("<?php echo get_label('The results of the existing tournaments will change. Are you sure you want to overwrite the current version?'); ?>", null, null, function()
			{
				saveData(1);
			});
		}
		
		initScoringEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>