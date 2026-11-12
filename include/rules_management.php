<?php

require_once __DIR__ . '/rules.php';

// The Rules page of the management menu: the rules and the scoring systems of one owner, a tab
// for each kind. The owner is a club (club_scorings.php), a league (league_scorings.php) or the
// site (scorings.php, both ids null). An owner that does not have a kind gets no tab for it.

define('RULES_TAB_RULES', 0);
define('RULES_TAB_SCORINGS', 1);
define('RULES_TAB_NORMALIZERS', 2);
define('RULES_TAB_GAININGS', 3);
define('RULES_TAB_COUNT', 4);

class RulesManagement
{
	private $club_id;
	private $league_id;
	private $tab;

	function __construct($club_id, $league_id)
	{
		$this->club_id = $club_id;
		$this->league_id = $league_id;

		$this->tab = RULES_TAB_RULES;
		if (isset($_REQUEST['view']))
		{
			$this->tab = (int)$_REQUEST['view'];
		}
		if ($this->tab < 0 || $this->tab >= RULES_TAB_COUNT)
		{
			$this->tab = RULES_TAB_RULES;
		}
	}

	// The condition picking the rows of this owner out of a table with club_id and league_id.
	private function owner_condition()
	{
		if ($this->club_id != NULL)
		{
			return new SQL('club_id = ?', $this->club_id);
		}
		if ($this->league_id != NULL)
		{
			return new SQL('league_id = ?', $this->league_id);
		}
		return new SQL('club_id IS NULL AND league_id IS NULL');
	}

	// The arguments mr.createScoringSystem() and mr.createNormalizer() take for this owner:
	// (clubId, leagueId), with -1 for the site.
	private function scoring_owner_args()
	{
		if ($this->club_id != NULL)
		{
			return $this->club_id;
		}
		if ($this->league_id != NULL)
		{
			return 'null, ' . $this->league_id;
		}
		return '-1';
	}

	// And the ones mr.createGainingSystem() takes: (leagueId, clubId).
	private function gaining_owner_args()
	{
		if ($this->club_id != NULL)
		{
			return 'null, ' . $this->club_id;
		}
		if ($this->league_id != NULL)
		{
			return $this->league_id;
		}
		return '-1';
	}

	private function tab_button($tab, $label)
	{
		echo '<button ' . ($this->tab == $tab ? 'class="active" ' : '') . 'onclick="goTo({view:' . $tab . '})">' . $label . '</button>';
	}

	function show()
	{
		echo '<div class="tab">';
		$this->tab_button(RULES_TAB_RULES, get_label('Rules'));
		$this->tab_button(RULES_TAB_SCORINGS, get_label('Tournament points'));
		$this->tab_button(RULES_TAB_NORMALIZERS, get_label('Tournament points normalization'));
		$this->tab_button(RULES_TAB_GAININGS, get_label('Series points'));
		echo '</div>';

		echo '<div class="tabcontent">';
		switch ($this->tab)
		{
			case RULES_TAB_RULES:
				$this->show_rules();
				break;
			case RULES_TAB_SCORINGS:
				$this->show_scorings();
				break;
			case RULES_TAB_NORMALIZERS:
				$this->show_normalizers();
				break;
			case RULES_TAB_GAININGS:
				$this->show_gainings();
				break;
		}
		echo '</div>';
	}

	// The default rules first, then the named ones. The rules of a league are filters and have a
	// dialog of their own; the default rules of the site are the row of the site with no name.
	private function show_rules()
	{
		$is_league = $this->league_id != NULL;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48"><a href="#" onclick="createRules()" title="' . get_label('New rules') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td>' . get_label('Rules name') . '</td></tr>';

		if ($this->club_id != NULL)
		{
			$edit_default = 'editClubRules()';
		}
		else if ($is_league)
		{
			$edit_default = 'mr.editLeagueRules(' . $this->league_id . ')';
		}
		else
		{
			$query = new DbQuery('SELECT id FROM rules WHERE club_id IS NULL AND league_id IS NULL AND name = \'\'');
			$edit_default = ($row = $query->next()) ? 'editRules(' . (int)$row[0] . ')' : NULL;
		}
		echo '<tr><td class="dark" align="center">';
		if ($edit_default != NULL)
		{
			echo '<a href="#" onclick="' . $edit_default . '" title="' . get_label('Edit [0]', get_label('[default]')) . '"><img src="images/edit.png" border="0"></a>';
		}
		echo '</td><td>' . get_label('[default]') . '</td></tr>';

		$query = new DbQuery('SELECT id, name FROM rules WHERE name <> \'\' AND ', $this->owner_condition());
		$query->add(' ORDER BY name');
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			echo '<tr><td class="dark" align="center">';
			echo '<a href="#" onclick="deleteRules(' . $id . ', \'' . addslashes(get_label('Are you sure you want to delete rules [0]?', $name)) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a href="#" onclick="editRules(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td>' . $name . '</td></tr>';
		}
		echo '</table>';
	}

	private function show_scorings()
	{
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48"><a href="#" onclick="mr.createScoringSystem(' . $this->scoring_owner_args() . ')" title="' . get_label('New scoring system') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td>' . get_label('Scoring system name') . '</td></tr>';

		$query = new DbQuery('SELECT id, name, version FROM scorings WHERE ', $this->owner_condition());
		$query->add(' ORDER BY name');
		while ($row = $query->next())
		{
			list ($id, $name, $version) = $row;
			echo '<tr><td class="dark" align="center">';
			echo '<a onclick="mr.deleteScoringSystem(' . $id . ', \'' . addslashes(get_label('Are you sure you want to delete [0]?', $name)) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a onclick="mr.editScoringSystem(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td><a href="javascript:showScoring(' . $id . ', ' . $version . ')">' . $name . '</a></td></tr>';
		}
		echo '</table>';
	}

	private function show_normalizers()
	{
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48"><a href="#" onclick="mr.createNormalizer(' . $this->scoring_owner_args() . ')" title="' . get_label('New scoring normalizer') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td>' . get_label('Scoring normalizer name') . '</td></tr>';

		$query = new DbQuery('SELECT id, name, version FROM normalizers WHERE ', $this->owner_condition());
		$query->add(' ORDER BY name');
		while ($row = $query->next())
		{
			list ($id, $name, $version) = $row;
			echo '<tr><td class="dark" align="center">';
			echo '<a onclick="mr.deleteNormalizer(' . $id . ', \'' . addslashes(get_label('Are you sure you want to delete [0]?', $name)) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a onclick="mr.editNormalizer(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td>' . $name . '</td></tr>';
		}
		echo '</table>';
	}

	private function show_gainings()
	{
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48"><a href="#" onclick="mr.createGainingSystem(' . $this->gaining_owner_args() . ')" title="' . get_label('New gaining system') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td>' . get_label('Gaining system name') . '</td></tr>';

		$query = new DbQuery('SELECT id, name, version FROM gainings WHERE ', $this->owner_condition());
		$query->add(' ORDER BY name');
		while ($row = $query->next())
		{
			list ($id, $name, $version) = $row;
			echo '<tr><td class="dark" align="center">';
			echo '<a onclick="mr.deleteGainingSystem(' . $id . ', \'' . addslashes(get_label('Are you sure you want to delete [0]?', $name)) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a onclick="mr.editGainingSystem(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td><a href="javascript:showGaining(' . $id . ', ' . $version . ')">' . $name . '</a></td></tr>';
		}
		echo '</table>';
	}

	function js()
	{
		$rules_form = $this->league_id != NULL ? 'form/league_rules_edit.php' : 'form/rules_edit.php';
		if ($this->club_id != NULL)
		{
			$create_query = 'create&club_id=' . $this->club_id;
		}
		else if ($this->league_id != NULL)
		{
			$create_query = 'create&league_id=' . $this->league_id;
		}
		else
		{
			$create_query = 'create';
		}
?>
		function editRules(rulesId)
		{
			dlg.form("<?php echo $rules_form; ?>?rules_id=" + rulesId, refr);
		}

		function editClubRules()
		{
			dlg.form("form/rules_edit.php?club_id=<?php echo (int)$this->club_id; ?>", refr);
		}

		function createRules()
		{
			dlg.form("<?php echo $rules_form . '?' . $create_query; ?>", refr);
		}

		function deleteRules(rulesId, message)
		{
			dlg.yesNo(message, null, null, function()
			{
				json.post("api/ops/rules.php", { op: 'delete', rules_id: rulesId }, refr);
			});
		}

		function showScoring(id, version)
		{
			dlg.infoForm("form/scoring_show.php?id=" + id + "&version=" + version);
		}

		function showGaining(id, version)
		{
			dlg.infoForm("form/gaining_show.php?id=" + id + "&version=" + version);
		}
<?php
	}
}

?>
