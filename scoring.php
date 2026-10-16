<?php

require_once 'include/general_page_base.php';
require_once 'include/systems.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $scoring;
	private $scoring_id;
	private $used_versions;
	private $scoring_name;
	private $scoring_version;
	private $current_version;
	private $versions;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
		}
		$scoring_id = (int)$_REQUEST['id'];
		
		list($this->scoring_id, $this->scoring_name, $this->current_version, $club_id, $league_id) = Db::record(get_label('scoring system'), 'SELECT id, name, version, club_id, league_id FROM scorings WHERE id = ?', $scoring_id);
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
		// Any version can be opened, not only the current one.
		$this->versions = get_system_versions('scoring', $this->scoring_id);
		$this->scoring_version = get_requested_system_version($this->versions, $this->current_version);
		list($this->scoring) = Db::record(get_label('scoring system'),
			'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?',
			$this->scoring_id, $this->scoring_version);
		
		// Which versions some tournament or round has already been scored on. Saving one of those
		// changes results that exist, so it is what the warning is about, and what makes the New
		// version button worth offering.
		$this->used_versions = get_used_system_versions('scoring', $this->scoring_id, array('tournaments', 'events'));
		
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
		// The same four buttons in the same order as the other two system editors: save what is
		// on screen, start a version to put it in instead, drop a version nothing is on, edit
		// the json by hand.
		echo '<p>';
		echo '<button id="save" onclick="saveData()" disabled>' . get_label('Save') . '</button>';
		// A new version is only worth having while something already uses the one on screen.
		// Otherwise it can be changed in place without touching any competition.
		echo ' <button id="new-version" onclick="newVersion()"' . (isset($this->used_versions[$this->scoring_version]) ? '' : ' disabled') . '>' . get_label('New version') . '</button>';
		// And a version nothing is on can go - as long as it is not the only one there is.
		echo ' <button id="delete-version" onclick="deleteVersion()"' . (!isset($this->used_versions[$this->scoring_version]) && count($this->versions) > 1 ? '' : ' disabled') . '>' . get_label('Delete version') . '</button>';
		echo ' <button onclick="editJson()">' . get_label('Edit json') . '</button>';
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
		
		echo '<script src="' . versioned_asset('js/scoring_editor.js') . '"></script>';
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
			versions: [<?php echo implode(',', $this->versions); ?>],
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
				noWeight: "<?php echo get_label('not multiplied to round coefficient'); ?>",
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
				<?php echo '"' . SCORING_FLAG_TECH_FOULS . '": "' . $for . get_label('getting 2 technical fouls') . '"'; ?>,
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
				<?php echo '"' . SCORING_FLAG_TEAM_KICK_OUT . '": "' . $for . get_label('making the opposite team win') . '"'; ?>,
				<?php echo '"' . SCORING_FLAG_TIE . '": "' . $for . get_label('tie') . '"'; ?>
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
			var incorrect = !isScoringDataCorrect();
			$('#save').prop('disabled', incorrect || !isDirty);
			// A new version is only worth having while something already uses this one, and this
			// one can only go while nothing does - and never if it is the only one left.
			$('#new-version').prop('disabled', !isVersionUsed(data.version));
			$('#delete-version').prop('disabled', isVersionUsed(data.version) || data.versions.length < 2);
		}
		
		// The version New version picked, until a save puts it there. It is in the picker but
		// not in the database, so there is nothing to delete and nothing to come back to.
		var unsavedVersion = 0;
		
		// The versions something already uses. A version that was just added is not one of
		// them: it is a second old and nobody has had a chance to use it yet.
		var usedVersions = [<?php echo implode(',', array_keys($this->used_versions)); ?>];
		
		function isVersionUsed(version)
		{
			return usedVersions.indexOf(version) >= 0;
		}
		
		// Opening another version is a page load, so anything unsaved would be lost.
		function selectVersion(version)
		{
			if (version == data.version)
			{
				return;
			}
			if (dirty())
			{
				dlg.yesNo("<?php echo get_label('The changes are not saved yet and will be lost. Open another version anyway?'); ?>", null, null, function()
				{
					goTo({ version: version });
				});
				return;
			}
			goTo({ version: version });
		}
		
		// Save writes the version on screen, in place. Which is the whole point while nothing
		// uses it, and worth a word of warning once something does.
		function saveData()
		{
			if (isVersionUsed(data.version))
			{
				dlg.yesNo("<?php echo get_label('This version of the system is already used in competitions. Your changes may change the tables of the tournaments and series that already exist. If you do not want to break anything, we recommend pressing New version first - then your changes will not touch the existing competitions. If you are sure of what you are doing, press Yes.'); ?>", null, null, function()
				{
					postData();
				});
				return;
			}
			postData();
		}
		
		function postData()
		{
			var params =
			{
				op: 'change'
				, scoring_id: data.id
				, name: data.name
				, scoring: JSON.stringify(data.scoring)
				, scoring_version: data.version
			};
			json.post("api/ops/scoring.php", params, function(response)
			{
				// A save that added a version gives the picker one more entry. This has to happen
				// before setScoringVersion, which is what moves data.version.
				if (data.versions.indexOf(response.scoring_version) < 0)
				{
					data.versions.push(response.scoring_version);
				}
				unsavedVersion = 0;
				setScoringVersion(response.scoring_version);
				dirty(false);
			});
		}
		
		// A new version is a number and nothing else until it is saved: what is on screen stays
		// as it is and becomes something to save, now into a version no competition is on.
		function newVersion()
		{
			var version = 1;
			for (var i = 0; i < data.versions.length; ++i)
			{
				if (data.versions[i] >= version)
				{
					version = data.versions[i] + 1;
				}
			}
			data.versions.push(version);
			unsavedVersion = version;
			setScoringVersion(version);
			dirty(true);
		}
		
		// Dropping the version that was never saved is just going back to what is there. Any
		// other one is a deletion, and the page comes back on whatever version is current then.
		function deleteVersion()
		{
			if (data.version == unsavedVersion)
			{
				dirty(false);
				goTo({ version: undefined });
				return;
			}
			dlg.yesNo("<?php echo get_label('Delete this version? This can not be undone.'); ?>", null, null, function()
			{
				var params =
				{
					op: 'delete_version'
					, scoring_id: data.id
					, scoring_version: data.version
				};
				json.post("api/ops/scoring.php", params, function(response)
				{
					dirty(false);
					goTo({ version: response.current_version });
				});
			});
		}
		
		function editJson()
		{
			dlg.form("form/system_json.php?type=scoring&id=" + data.id + "&version=" + data.version, null, 1200);
		}
		
		// What that dialog calls with the json it was given. It saves nothing - this is the
		// editor being handed other contents, and Save is still what writes them.
		function applyJson(json)
		{
			data.scoring = json;
			initScoringEditor(data, onDataChange);
			dirty(true);
		}
		
		initScoringEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>