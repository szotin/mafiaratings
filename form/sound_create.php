<?php

require_once '../include/session.php';
require_once '../include/datetime.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('sound')));
	
	$club_id = 0;
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}
	
	$user_id = 0;
	if (isset($_REQUEST['user_id']))
	{
		$user_id = (int)$_REQUEST['user_id'];
	}
	
	if ($user_id > 0)
	{
		check_permissions(PERMISSION_OWNER, $user_id);
	}
	
	if ($club_id > 0)
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	}
	else if ($user_id <= 0)
	{
		check_permissions(PERMISSION_ADMIN);
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name"> </td></tr>';

    // echo '<input type="file" name="files[]" multiple />';
    // echo '<input type="submit" value="Upload File" name="submit" />';
	
	echo '<tr><form method="post" enctype="multipart/form-data"><td colspan="2"><input type="file" id="form-file" accept=".mp3" onchange="fileChanged()"></form></td></tr>';
	echo '</table>';

?>
	<script>
	const uploadForm = document.querySelector('form');
	uploadForm.addEventListener('submit', e => 
	{
		e.preventDefault()
	});
	
	function fileChanged()
	{
		let file = document.getElementById("form-file").files[0];
		console.log(file);
		if ($("#form-name").val() == "")
		{
			if (file && file.name)
			{
				var name = file.name;
				var p = name.indexOf('/');
				if (p >= 0)
				{
					name = name.substring(p + 1);
				}
				
				p = name.indexOf('\\');
				if (p >= 0)
				{
					name = name.substring(p + 1);
				}
				
				p = name.indexOf('.');
				if (p >= 0)
				{
					name = name.substring(0, p);
				}
				
				$("#form-name").val(name);
			}
		}
	}

	function commit(onSuccess)
	{
		json.upload("api/ops/sound.php",
		{
			op: 'create'
			<?php 
				if ($club_id > 0) echo ', club_id: ' . $club_id; 
				if ($user_id > 0) echo ', user_id: ' . $user_id; 
			?>
			, name: $("#form-name").val()
			, file: document.getElementById("form-file").files[0]
		},
		<?php echo UPLOAD_SOUND_MAX_SIZE; ?>,
		onSuccess);
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