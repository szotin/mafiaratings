<?php

require_once 'include/general_page_base.php';
require_once 'include/systems.php';
require_once 'include/gaining.php';

class Page extends PageBase
{
	private $gaining;
	private $gaining_id;
	private $gaining_name;
	private $gaining_version;
	private $current_version;
	private $versions;
	private $used_versions;

	protected function prepare()
	{
		parent::prepare();

		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('gaining system')));
		}
		$gaining_id = (int)$_REQUEST['id'];

		list($this->gaining_id, $this->gaining_name, $this->current_version, $club_id, $league_id) =
			Db::record(get_label('gaining system'),
				'SELECT id, name, version, club_id, league_id FROM gainings WHERE id = ?', $gaining_id);
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
		$this->versions = get_system_versions('gaining', $this->gaining_id);
		$this->gaining_version = get_requested_system_version($this->versions, $this->current_version);
		list($this->gaining) = Db::record(get_label('gaining system'),
			'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?',
			$this->gaining_id, $this->gaining_version);
		
		// Which versions some series already uses. Saving one of those changes results that
		// exist, so it is what the warning is about, and what makes the New version button worth
		// offering. What uses a gaining system is the series, not the tournaments inside it.
		$this->used_versions = get_used_system_versions('gaining', $this->gaining_id, array('series'));

		$this->_title = get_label('Gaining system') . ': ' . $this->gaining_name;
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
		echo ' <button id="new-version" onclick="newVersion()"' . (isset($this->used_versions[$this->gaining_version]) ? '' : ' disabled') . '>' . get_label('New version') . '</button>';
		// And a version nothing is on can go - as long as it is not the only one there is.
		echo ' <button id="delete-version" onclick="deleteVersion()"' . (!isset($this->used_versions[$this->gaining_version]) && count($this->versions) > 1 ? '' : ' disabled') . '>' . get_label('Delete version') . '</button>';
		echo ' <button onclick="editJson()">' . get_label('Edit json') . '</button>';
		echo '</p>';

		echo '<script src="' . versioned_asset('js/gaining_editor.js') . '"></script>';
		echo '<div id="gaining-editor"></div>';

		// The preview evaluates whatever is in the editor right now through the real Evaluator,
		// so it is also where a broken formula shows itself.
		// h3 carries no margin of its own (common.css), so the heading needs one here to stand off
		// the editor above it.
		echo '<h3 style="margin-top: 24px;">' . get_label('Preview') . '</h3>';
		// Stars and the number of players are the same for everybody in the table below, which is
		// why they can be set here. A player's own result cannot: the table holds every place at
		// once, and the first place and the last one do not share a result. Formulas that use it
		// get a note instead.
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td width="25%"><input type="number" style="width: 45px;" step="1" min="1" max="10" id="form-stars" value="2" onchange="refreshGainingPreview()"> ' . get_label('stars') . '</td>';
		echo '<td width="25%"><input type="number" style="width: 45px;" step="1" min="2" id="form-players" value="30" onchange="refreshGainingPreview()"> ' . get_label('players') . '</td>';
		echo '<td align="right"><input type="checkbox" id="form-series" onclick="refreshGainingPreview()"> ' . get_label('for series of tournaments') . '</td></tr>';
		echo '</table></p>';
		echo '<p><i id="form-score-note" style="display: none;">' . get_label('The points depend on the result each player brought from the competition, so the table below counts it as zero.') . '</i></p>';
		echo '<div id="form-gaining"></div>';
	}

	protected function js()
	{
		// What the help button next to every formula offers, taken from the evaluator's own
		// function list so that it cannot drift away from what get_gaining_points() provides.
		$function_names = array();
		foreach (get_gaining_functions() as $f)
		{
			$function_names[] = $f->id();
		}
?>
		var data =
		{
			gaining: <?php echo $this->gaining; ?>,
			id: <?php echo $this->gaining_id; ?>,
			name: "<?php echo $this->gaining_name; ?>",
			version: <?php echo $this->gaining_version; ?>,
			versions: [<?php echo implode(',', $this->versions); ?>],
			functions: "<?php echo implode(',', $function_names); ?>",
			strings:
			{
				name: "<?php echo get_label('Gaining system name'); ?>",
				version: "<?php echo get_label('Version'); ?>",
				points: "<?php echo get_label('Points for a tournament'); ?>",
				pointsHelp: "<?php echo get_label('What one tournament of the series brings a player who took a given place in it.'); ?>",
				seriesPointsUse: "<?php echo get_label('use a different formula when what is scored is a subseries rather than a tournament'); ?>",
				maxTournaments: "<?php echo get_label('Tournaments counted'); ?>",
				allTournaments: "<?php echo get_label('every tournament of the series counts'); ?>",
				countBest: "<?php echo get_label('count only the best'); ?>",
				countBestPost: "<?php echo get_label('tournaments of each player'); ?>",
				globals: "<?php echo get_label('Values calculated once'); ?>",
				globalsHelp: "<?php echo get_label('Calculated once for the whole competition. Order matters - each one may use those above it.'); ?>",
				vars: "<?php echo get_label('Values calculated per player'); ?>",
				varsHelp: "<?php echo get_label('Recalculated for every player, after their place and score are known. Order matters here too.'); ?>",
				varAdd: "<?php echo get_label('Add value.'); ?>",
				varDel: "<?php echo get_label('Delete value.'); ?>",
				moveUp: "<?php echo get_label('Move up.'); ?>",
				moveDown: "<?php echo get_label('Move down.'); ?>",
				table: "<?php echo get_label('Table'); ?>",
				tableHelp: "<?php echo get_label('The numbers table(...) looks up. Indices start at 0 and are clamped to the nearest existing one.'); ?>",
				tableAdd: "<?php echo get_label('Add a table.'); ?>",
				tableDrop: "<?php echo get_label('Delete the table.'); ?>",
				entryDel: "<?php echo get_label('Delete entry.'); ?>",
				entryAddRow: "<?php echo get_label('add a row of numbers'); ?>",
				entryAddTable: "<?php echo get_label('add a nested table'); ?>",
			},
		};

		function onDataChange(d, isDirty)
		{
			data = d;
			var incorrect = !isGainingDataCorrect();
			$('#save').prop('disabled', incorrect || !isDirty);
			// A new version is only worth having while something already uses this one, and this
			// one can only go while nothing does - and never if it is the only one left.
			$('#new-version').prop('disabled', !isVersionUsed(data.version));
			$('#delete-version').prop('disabled', isVersionUsed(data.version) || data.versions.length < 2);
		}

		// Whether the player's own result reaches the formula the preview is about to evaluate.
		// Only one of the two formulas is used at a time - the same choice get_gaining_points()
		// makes - so a system whose subseries formula reads the result says nothing about the
		// table of an ordinary tournament. The values are evaluated either way.
		function scoreMatters(g)
		{
			var series = $('#form-series').attr('checked');
			var text = (series && typeof g.seriesPoints != "undefined") ? g.seriesPoints : g.points;
			text = text ? text : '';
			for (var key in g.globals) { text += ' ' + g.globals[key]; }
			for (var key in g.vars) { text += ' ' + g.vars[key]; }
			return /(^|[^A-Za-z0-9_])score([^A-Za-z0-9_]|$)/.test(text);
		}

		function onPreview(d)
		{
			$('#form-score-note').css('display', scoreMatters(d.gaining) ? '' : 'none');
			if (!isGainingDataCorrect())
			{
				return;
			}
			var params =
			{
				gaining: JSON.stringify(d.gaining)
				, stars: $("#form-stars").val()
				, players: $("#form-players").val()
			};
			if ($('#form-series').attr('checked'))
			{
				params['series'] = true;
			}
			http.post("form/gaining_table.php", params, function(html)
			{
				$("#form-gaining").html(html);
			});
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
			setGainingVersion(version);
			dirty(true);
		}
		
		function postData()
		{
			var params =
			{
				op: 'change'
				, gaining_id: data.id
				, name: data.name
				, gaining: JSON.stringify(data.gaining)
				, gaining_version: data.version
			};
			json.post("api/ops/gaining.php", params, function(response)
			{
				// A save that added a version gives the picker one more entry. This has to happen
				// before setGainingVersion, which is what moves data.version.
				if (data.versions.indexOf(response.gaining_version) < 0)
				{
					data.versions.push(response.gaining_version);
				}
				unsavedVersion = 0;
				setGainingVersion(response.gaining_version);
				dirty(false);
			});
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
					, gaining_id: data.id
					, gaining_version: data.version
				};
				json.post("api/ops/gaining.php", params, function(response)
				{
					dirty(false);
					goTo({ version: response.current_version });
				});
			});
		}
		
		function editJson()
		{
			dlg.form("form/system_json.php?type=gaining&id=" + data.id + "&version=" + data.version, null, 1200);
		}
		
		// What that dialog calls with the json it was given. It saves nothing - this is the
		// editor being handed other contents, and Save is still what writes them.
		function applyJson(json)
		{
			data.gaining = json;
			initGainingEditor(data, onDataChange, onPreview);
			refreshGainingPreview();
			dirty(true);
		}

		initGainingEditor(data, onDataChange, onPreview);
<?php
	}
}

$page = new Page();
$page->run('');

?>
