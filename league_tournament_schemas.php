<?php

require_once 'include/league.php';
require_once 'include/pages.php';
require_once 'include/tournament_schema.php';

// The tournament schemas of a league: ready-made ways to set up a tournament, which a tournament
// of a club takes on the Scheme tab of its preparation page - see include/tournament_schema.php.
class Page extends LeaguePageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);

		echo '<p><button onclick="copySchemas()">' . get_label('Copy from another league') . '</button></p>';

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48"><a href="#" onclick="createSchema()" title="' . get_label('New tournament schema') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td>' . get_label('Tournament schema name') . '</td>';
		echo '<td>' . get_label('Rounds') . '</td></tr>';

		$query = new DbQuery('SELECT id, name, scheme FROM league_tournament_schemas WHERE league_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list($id, $name, $schema) = $row;
			echo '<tr><td class="dark" align="center" nowrap>';
			echo '<a href="#" onclick="deleteSchema(' . $id . ', \'' . addslashes(get_label('Are you sure you want to delete [0]?', $name)) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a href="#" onclick="editSchema(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td>' . htmlspecialchars($name, ENT_QUOTES) . '</td>';
			echo '<td>' . tournament_schema_summary($schema) . '</td></tr>';
		}
		echo '</table>';
	}

	protected function js()
	{
		parent::js();
?>
		function createSchema()
		{
			dlg.form("form/tournament_schema_edit.php?league_id=<?php echo $this->id; ?>", refr, 800);
		}

		function editSchema(schemaId)
		{
			dlg.form("form/tournament_schema_edit.php?schema_id=" + schemaId, refr, 800);
		}

		function deleteSchema(schemaId, message)
		{
			dlg.yesNo(message, null, null, function()
			{
				json.post("api/ops/tournament_schema.php", { op: 'delete', schema_id: schemaId }, refr);
			});
		}

		// The form posts the copy itself and hands back what it answered: the names that were not
		// copied, because this league already has schemas by them, are worth saying.
		function copySchemas()
		{
			dlg.form("form/tournament_schema_copy.php?league_id=<?php echo $this->id; ?>", function(data)
			{
				if (data && data.skipped && data.skipped.length > 0)
				{
					var names = '';
					for (var name of data.skipped)
					{
						names += '<br>' + $('<div>').text(name).html();
					}
					dlg.info('<?php echo addslashes(get_label('Not all the schemas were copied: this league already has schemas with these names, and they are kept.')); ?>' + names, '<?php echo addslashes(get_label('Copy from another league')); ?>', null, refr);
				}
				else
				{
					refr();
				}
			}, 400);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Tournament schemas'));

?>
