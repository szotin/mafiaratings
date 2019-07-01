<?php

require_once '../include/session.php';
require_once '../include/club.php';
require_once '../include/address.php';
require_once '../include/country.php';
require_once '../include/city.php';
require_once '../include/image.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('stats calculator')));
	
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}

	$id = 0;
	if (isset($_REQUEST['id']))
	{
		$id = (int)$_REQUEST['id'];
	}
	
	if ($id > 0)
	{
		list ($name, $description, $code, $owner_id, $published) = 
			Db::record(get_label('stats calculator'), 'SELECT name, description, code, owner_id, published FROM stats_calculators WHERE id = ?', $id);
	}
	else
	{
		$name = '';
		$description = '';
		$published = 0;
		$owner_id = $_profile->user_id;
		$code = "var mafiaWins;\n\nfunction reset()\n{\n\tmafiaWins = 0;\n}\n\nfunction proceedGame(game, num)\n{\n\tif(game.winner == 'maf')\n\t{\n\t\t++mafiaWins;\n\t}\n}\n\nfunction complete()\n{\n\treturn 'Mafia wins: ' + mafiaWins;\n}";
	}

	check_permissions(PERMISSION_OWNER, $owner_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120" valign="top">' . get_label('Name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td valign="top">' . get_label('Description') . ':</td><td><textarea id="form-description" cols="93" rows="5">' . htmlspecialchars($description, ENT_QUOTES) . '</textarea></td></tr>';
	echo '<tr><td valign="top">' . get_label('Javascript code') . ':</td><td><textarea id="form-code" cols="93" rows="32">' . $code . '</textarea></td></tr>';
	
	echo '<tr><td colspan="2"><input type="checkbox" id="form-published"';
	if ($published)
	{
		echo ' checked';
	}
	echo '> ' . get_label('publish for others') . '</td></tr>';
	echo '</table>';
	
	if ($id > 0)
	{
?>	
		<script>
		function commit(onSuccess)
		{
			var params = 
			{
				op: "change"
				, id: <?php echo $id; ?>
				, name: $("#form-name").val()
				, description: $("#form-description").val()
				, code: $("#form-code").val()
				, published: ($("#form-published").prop('checked') ? 1 : 0)
			};
			json.post("api/ops/stats_calculator.php", params, onSuccess);
		}
		</script>
<?php
	}
	else
	{
?>	
		<script>
		function commit(onSuccess)
		{
			var params = 
			{
				op: "create"
				, name: $("#form-name").val()
				, description: $("#form-description").val()
				, code: $("#form-code").val()
				, published: ($("#form-published").prop('checked') ? 1 : 0)
			};
			json.post("api/ops/stats_calculator.php", params, onSuccess);
		}
		</script>
<?php
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo '<error=' . $e->getMessage() . '>';
}

?>
