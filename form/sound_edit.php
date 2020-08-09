<?php

require_once '../include/session.php';
require_once '../include/datetime.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('sound')));

	if (!isset($_REQUEST['sound_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('sound')));
	}
	$sound_id = (int)$_REQUEST['sound_id'];
	
	list ($name, $club_id, $user_id) = Db::record(get_label('sound'), 'SELECT name, club_id, user_id FROM sounds WHERE id = ?', $sound_id);
	if (!is_null($club_id))
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	}
	else if (!is_null($user_id))
	{
		check_permissions(PERMISSION_OWNER, $user_id);
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name" value="' . $name . '"> </td></tr>';
	echo '<tr><form method="post" enctype="multipart/form-data"><td colspan="2"><input type="file" id="form-file" accept=".mp3"></form></td></tr>';
	echo '</table>';

?>
	<script>
	const uploadForm = document.querySelector('form');
	uploadForm.addEventListener('submit', e => 
	{
		e.preventDefault()
	});

	function commit(onSuccess)
	{
		var file = document.getElementById("form-file").files[0];
		var params =
		{
			op: 'change'
			, sound_id: <?php echo $sound_id; ?>
			, name: $("#form-name").val()
		};
		
		if (file && file.name)
		{
			params['file'] = file;
		}
		
		json.upload("api/ops/sound.php", params, <?php echo UPLOAD_SOUND_MAX_SIZE; ?>, onSuccess);
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