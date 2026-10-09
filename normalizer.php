<?php

require_once 'include/general_page_base.php';
require_once 'include/systems.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $normalizer;
	private $normalizer_id;
	private $used_versions;
	private $normalizer_name;
	private $normalizer_version;
	private $current_version;
	private $versions;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring normalizer')));
		}
		$normalizer_id = (int)$_REQUEST['id'];
		
		list($this->normalizer_id, $this->normalizer_name, $this->current_version, $club_id, $league_id) = Db::record(get_label('scoring normalizer'), 'SELECT id, name, version, club_id, league_id FROM normalizers WHERE id = ?', $normalizer_id);
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
		$this->versions = get_system_versions('normalizer', $this->normalizer_id);
		$this->normalizer_version = get_requested_system_version($this->versions, $this->current_version);
		list($this->normalizer) = Db::record(get_label('scoring normalizer'),
			'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?',
			$this->normalizer_id, $this->normalizer_version);
		
		// Which versions some tournament has already been normalized on. Saving one of those
		// changes results that exist, so it is what the warning is about, and what makes the New
		// version button worth offering.
		$this->used_versions = get_used_system_versions('normalizer', $this->normalizer_id, array('tournaments'));

		$this->_title = get_label('Scoring normalizer') . ': ' . $this->normalizer_name;
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
		echo ' <button id="new-version" onclick="newVersion()"' . (isset($this->used_versions[$this->normalizer_version]) ? '' : ' disabled') . '>' . get_label('New version') . '</button>';
		// And a version nothing is on can go - as long as it is not the only one there is.
		echo ' <button id="delete-version" onclick="deleteVersion()"' . (!isset($this->used_versions[$this->normalizer_version]) && count($this->versions) > 1 ? '' : ' disabled') . '>' . get_label('Delete version') . '</button>';
		echo ' <button onclick="editJson()">' . get_label('Edit json') . '</button>';
		echo '</p>';
		echo '<script src="' . versioned_asset('js/normalizer_editor.js') . '"></script>';
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
			versions: [<?php echo implode(',', $this->versions); ?>],
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
			setNormalizerVersion(version);
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
					, normalizer_id: data.id
					, normalizer_version: data.version
				};
				json.post("api/ops/normalizer.php", params, function(response)
				{
					dirty(false);
					goTo({ version: response.current_version });
				});
			});
		}
		
		function editJson()
		{
			dlg.form("form/system_json.php?type=normalizer&id=" + data.id + "&version=" + data.version, null, 1200);
		}
		
		// What that dialog calls with the json it was given. It saves nothing - this is the
		// editor being handed other contents, and Save is still what writes them.
		function applyJson(json)
		{
			data.normalizer = json;
			initNormalizerEditor(data, onDataChange);
			dirty(true);
		}
		
		function postData()
		{
			var params =
			{
				op: 'change'
				, normalizer_id: data.id
				, name: data.name
				, normalizer: JSON.stringify(data.normalizer)
				, normalizer_version: data.version
			};
			json.post("api/ops/normalizer.php", params, function(response)
			{
				// A save that added a version gives the picker one more entry. This has to happen
				// before setNormalizerVersion, which is what moves data.version.
				if (data.versions.indexOf(response.normalizer_version) < 0)
				{
					data.versions.push(response.normalizer_version);
				}
				unsavedVersion = 0;
				setNormalizerVersion(response.normalizer_version);
				// Only once it is actually saved: a post that failed leaves something to save.
				dirty(false);
			});
		}
		
		initNormalizerEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>