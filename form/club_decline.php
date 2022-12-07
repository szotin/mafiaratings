<?php

require_once '../include/session.php';
require_once '../include/email.php';
require_once '../include/url.php';
require_once '../include/user.php';
require_once '../include/languages.php';
require_once '../include/email.php';

initiate_session();

try
{
	dialog_title(get_label('Decline club request'));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$id = $_REQUEST['id'];

	list($name, $city_id, $city, $country, $url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone, $club_id, $club_name, $club_flags, $parent_id, $parent_name, $parent_flags) = Db::record(
		get_label('club'),
		'SELECT c.name, c.city_id, ni.name, no.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone, c.club_id, cl.name, cl.flags, c.parent_id, p.name, p.flags FROM club_requests c' .
			' JOIN users u ON c.user_id = u.id' .
			' LEFT OUTER JOIN cities i ON c.city_id = i.id' .
			' LEFT OUTER JOIN countries o ON i.country_id = o.id' .
			' LEFT OUTER JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0' .
			' LEFT OUTER JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
			' LEFT OUTER JOIN clubs p ON c.parent_id = p.id' .
			' LEFT OUTER JOIN clubs cl ON c.club_id = cl.id' .
			' WHERE c.id = ?',
		$_lang, $_lang, $id);
		
	if ($parent_id == NULL)
	{
		check_permissions(PERMISSION_ADMIN);
	}
	else
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $parent_id);
	}
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2" align="center">' . get_club_request_action($name, $club_id, $club_name, $parent_id, $parent_name) . '</td></tr>';
	
	$club_pic = new Picture(CLUB_PICTURE);
	
	echo '<tr><td width="120">' . get_label('User') . ':</td><td><a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td></tr>';
	if ($club_id != NULL)
	{
		echo '<tr><td>' . get_label('Club') . ':</td><td>';
		echo '<table class="transp" width="100%"><tr><td width="50">';
		$club_pic->set($club_id, $club_name, $club_flags);
		$club_pic->show(ICONS_DIR, false, 36);
		echo '</td><td>' . $club_name . '</td></tr></table>';
		echo '</td></tr>';
	}
	else
	{
		echo '<tr><td>' . get_label('Club name') . ':</td><td>' . $name . '</td></tr>';
	}
	if ($parent_name != NULL)
	{
		echo '<tr><td>' . get_label('Club system') . ':</td><td>';
		echo '<table class="transp" width="100%"><tr><td width="50">';
		$club_pic->set($parent_id, $parent_name, $parent_flags);
		$club_pic->show(ICONS_DIR, false, 36);
		echo '</td><td>' . $parent_name . '</td></tr></table>';
		echo '</td></tr>';
	}
	
	if ($club_id == NULL)
	{
		echo '<tr><td>' . get_label('Web site') . ':</td><td><a href="' . $url . '" target="_blank">' . $url . '</a></td></tr>';
		echo '<tr><td>'.get_label('Languages').':</td><td>' . get_langs_str($langs, ', ') . '</td><tr>';
		echo '<tr><td>' . get_label('Country') . ':</td><td>' . $country . '</td></tr>';
		echo '<tr><td>'.get_label('City').':</td><td>' . $city . ', ' . $country . '</td><tr>';
	}
	echo '<tr><td>' . get_label('Reason to decline') . ':</td><td><textarea id="reason" cols="80" rows="8"></textarea></td></tr>';
	echo '</table>';

?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/club.php",
		{
			op: "decline"
			, request_id: <?php echo $id; ?>
			, reason: $("#reason").val()
		},
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