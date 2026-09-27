<?php

require_once 'include/general_page_base.php';
require_once 'include/gaining.php';

class Page extends PageBase
{
	private $gaining;
	private $gaining_id;
	private $gaining_name;
	private $gaining_version;
	private $dependants;

	protected function prepare()
	{
		parent::prepare();

		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('gaining system')));
		}
		$gaining_id = (int)$_REQUEST['id'];

		list($this->gaining_id, $this->gaining, $this->gaining_name, $this->gaining_version, $club_id, $league_id) =
			Db::record(get_label('gaining system'),
				'SELECT s.id, v.gaining, s.name, s.version, s.club_id, s.league_id FROM gainings s' .
				' JOIN gaining_versions v ON v.gaining_id = s.id AND v.version = s.version' .
				' WHERE s.id = ?', $gaining_id);
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

		// api/ops/gaining.php overwrites the current version in place while no finished series
		// uses it, and starts a new version once one does. Saying which it will be up front
		// spares the author a surprise.
		list($this->dependants) = Db::record(get_label('series'),
			'SELECT count(*) FROM series WHERE gaining_id = ? AND gaining_version = ? AND start_time + duration < UNIX_TIMESTAMP()',
			$this->gaining_id, $this->gaining_version);
		$this->dependants = (int)$this->dependants;

		$this->_title = get_label('Gaining system') . ': ' . $this->gaining_name;
	}

	protected function show_body()
	{
		echo '<p><button id="save" onclick="saveData()" disabled>' . get_label('Save') . '</button>';
		echo ' <button onclick="viewJson()">' . get_label('View json') . '</button>';
		echo ' <button onclick="editJson()">' . get_label('Edit json') . '</button></p>';

		if ($this->dependants > 0)
		{
			echo '<p><i>' . get_label('[0] finished series already use this version. Saving creates a new version and leaves their results alone.', $this->dependants) . '</i></p>';
		}

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
		// "place" is added by hand: it is set as a plain variable rather than registered as a
		// function, but a formula uses it the same way and an author looks for it here.
		$function_names = array();
		foreach (get_gaining_functions() as $f)
		{
			$function_names[] = $f->id();
		}
		$function_names[] = 'place';
?>
		var data =
		{
			gaining: <?php echo $this->gaining; ?>,
			id: <?php echo $this->gaining_id; ?>,
			name: "<?php echo $this->gaining_name; ?>",
			version: <?php echo $this->gaining_version; ?>,
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
			$('#save').prop('disabled', !isDirty || !isGainingDataCorrect());
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

		function saveData()
		{
			var params =
			{
				op: 'change'
				, gaining_id: data.id
				, name: data.name
				, gaining: JSON.stringify(data.gaining)
			};
			json.post("api/ops/gaining.php", params, function(response)
			{
				setGainingVersion(response.gaining_version);
				dirty(false);
			});
		}

		function viewJson()
		{
			dlg.info(JSON.stringify(data.gaining), 'Json');
		}

		function editJson()
		{
			dlg.form("form/gaining_edit.php?gaining_id=" + data.id, refr, 1200);
		}

		initGainingEditor(data, onDataChange, onPreview);
<?php
	}
}

$page = new Page();
$page->run('');

?>
