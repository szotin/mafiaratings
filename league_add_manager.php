<?php

require_once 'include/page_base.php';
require_once 'include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = $_REQUEST['league_id'];

	list($name) = Db::record(get_label('league'), 'SELECT name FROM leagues l WHERE id = ?', $league_id);
	
	dialog_title(get_label('Add league manager'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('User') . ':</td><td>';
	show_user_input('form-user', '', '', get_label('Select user.'), 'onSelect');
	echo '</td></tr>';
	echo '</table>';

?>	
	<script>
	var user = null;
	function onSelect(_user)
	{
		user = _user;
	}
	
	function commit(onSuccess)
	{
		if (user != null)
		{
			json.post("api/ops/league.php",
			{
				op: "add_manager"
				, league_id: <?php echo $league_id; ?>
				, user_id: user.id
			},
			onSuccess);
		}
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>