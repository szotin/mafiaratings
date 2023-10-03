<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/image.php';
require_once '../include/languages.php';

initiate_session();

try
{
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('User')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list (
				$name, $club_id,
				$user_event_name, $event_user_flags, 
				$tournament_id, $tournament_user_flags,
				$user_club_id, $club_user_flags,
				$user_name, $user_flags) = 
		Db::record(get_label('user'), 
				'SELECT e.name, e.club_id, eu.nickname, eu.flags, tu.tournament_id, tu.flags, cu.club_id, cu.flags, nu.name, u.flags' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN events e ON e.id = ?' .
				' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = e.id' .
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
				' WHERE u.id = ?', $event_id, $user_id);
				
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		dialog_title(get_label('Custom [0] photo for [1]', $user_name, $name));

		$secondary_id = 'e' . $event_id;
		$reset_pic = 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));
		$user_pic = new Picture(USER_EVENT_PICTURE, $reset_pic);
		$user_pic->
			set($user_id, $user_event_name, $event_user_flags, $secondary_id)->
			set($user_id, $user_name, $tournament_user_flags, 't' . $tournament_id)->
			set($user_id, $user_name, $club_user_flags, 'c' . $user_club_id)->
			set($user_id, $user_name, $user_flags);
			
		$attribute = ', event_id: ' . $event_id;
		$code = USER_EVENT_PIC_CODE;
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		list (
				$name, $club_id,
				$tournament_user_flags,
				$user_club_id, $club_user_flags,
				$user_name, $user_flags) = 
		Db::record(get_label('user'), 
				'SELECT t.name, t.club_id, tu.flags, cu.club_id, cu.flags, nu.name, u.flags' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN tournaments t ON t.id = ?' .
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = t.id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = t.club_id' .
				' WHERE u.id = ?', $tournament_id, $user_id);
				
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		dialog_title(get_label('Custom [0] photo for [1]', $user_name, $name));

		$secondary_id = 't' . $tournament_id;
		$code = USER_TOURNAMENT_PIC_CODE;
		$attribute = ', tournament_id: ' . $tournament_id;

		$reset_pic = 
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE));
		$user_pic = new Picture(USER_TOURNAMENT_PICTURE, $reset_pic);
		$user_pic->
			set($user_id, $user_name, $tournament_user_flags, $secondary_id)->
			set($user_id, $user_name, $club_user_flags, 'c' . $user_club_id)->
			set($user_id, $user_name, $user_flags);
	}
	else if (isset($_REQUEST['club_id']))
	{
		$user_club_id = $club_id = (int)$_REQUEST['club_id'];
		list (
				$name, 
				$club_user_flags,
				$user_name, $user_flags) = 
		Db::record(get_label('user'), 
				'SELECT c.name, cu.flags, nu.name, u.flags' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN clubs c ON c.id = ?' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = c.id' .
				' WHERE u.id = ?', $club_id, $user_id);
				
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		dialog_title(get_label('Custom [0] photo for [1]', $user_name, $name));

		$secondary_id = 'c' . $user_club_id;
		$code = USER_CLUB_PIC_CODE;
		$attribute = ', club_id: ' . $club_id;

		$reset_pic = new Picture(USER_PICTURE);
		$user_pic = new Picture(USER_CLUB_PICTURE, $reset_pic);
		$user_pic->
			set($user_id, $user_name, $club_user_flags, $secondary_id)->
			set($user_id, $user_name, $user_flags);
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
		dialog_title(get_label('[0] photo', $user_name));
		
		list($user_name, $user_flags) = Db::record(get_label('user'), 
			'SELECT nu.name, u.flags'.
			' FROM users u'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE u.id = ?', $user_id);
		$reset_pic = new Picture(USER_PICTURE);
		$user_pic = new Picture(USER_PICTURE);
		$reset_pic->set(0, '', 0);
		$user_pic->set($user_id, $user_name, $user_flags);

		$secondary_id = NULL;
		$code = USER_PIC_CODE;
		$attribute = '';
	}
	
	$reset_icon_url = $reset_pic->url(ICONS_DIR);
	$reset_tnail_url = $reset_pic->url(TNAILS_DIR);
	
	echo '<table class="dialog_form" width="100%"><tr height="240"><td align="center">';
	$user_pic->show(TNAILS_DIR, false);
	echo '</td><td align="center" width="48" valign="top">';
	start_upload_logo_button($user_id);
	echo get_label('Upload photo');
	$user_pic->custom_title = get_label('Upload custom picture for [0].', $user_name);
	$user_pic->show(ICONS_DIR, false);
	$image_code = end_upload_logo_button($code, $user_id, $secondary_id);
	echo '<div id="reset"><p><button class="upload" onclick="resetPhoto()">' . get_label('Reset photo');
	$reset_pic->show(ICONS_DIR, false);
	echo '</button></p></div>';
	echo '</td></tr></table><script>';
	if (!$user_pic->has_image(true))
	{
		echo '$("#reset").hide();';
	}
?>
	
	function uploadLogo(userId, onSuccess)
	{
		json.upload('api/ops/user.php', 
		{
			op: "custom_photo"
			<?php echo $attribute; ?>
			, user_id: userId
			, picture: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
		function()
		{
			$("#reset").fadeIn();
			if (onSuccess)
				onSuccess();
		});
	}
	
	function resetPhoto()
	{
		dlg.yesNo("<?php echo get_label("Are you sure you want to reset user photo?"); ?>", null, null, function()
		{
			json.post("api/ops/user.php",
			{
				op: "custom_photo"
				<?php echo $attribute; ?>
				, user_id: <?php echo $user_id; ?>
			},
			function()
			{
				var d = (new Date()).getTime();
				$("img[code=<?php echo $image_code; ?>]").each(function()
				{
					let url = $(this).attr('origin');
					console.log(url);
					
					let pos = url.lastIndexOf('<?php echo TNAILS_DIR; ?>');
					if (pos >= 0)
						url = "<?php echo $reset_tnail_url; ?>";
					else
						url = "<?php echo $reset_icon_url; ?>";
					$(this).attr('src', url);
					console.log(url);
				});
				$("#reset").fadeOut();
			});
		});
	}
<?php
	echo '</script><ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>