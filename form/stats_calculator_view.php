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
	dialog_title(get_label('View [0] code', get_label('stats calculator')));
	
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

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120" valign="top">' . get_label('Name') . ':</td><td>' . htmlspecialchars($name, ENT_QUOTES) . '</td></tr>';
	
	echo '<tr><td valign="top">' . get_label('Description') . ':</td><td>' . $description . '</td></tr>';
	echo '<tr><td valign="top">' . get_label('Javascript code') . ':</td><td><textarea id="form-code" cols="93" rows="32">' . $code . '</textarea></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		onSuccess();
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo '<error=' . $e->getMessage() . '>';
}

?>
