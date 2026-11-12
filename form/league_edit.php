<?php

require_once '../include/session.php';
require_once '../include/league.php';
require_once '../include/image.php';
require_once '../include/url.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('league')));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$id = $_REQUEST['id'];
	
	if ($_profile == NULL || !$_profile->is_league_manager($id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	list ($name, $url, $email, $phone, $langs, $flags, $league_gaining_id) =
		Db::record(get_label('league'), 'SELECT name, web_site, email, phone, langs, flags, gaining_id FROM leagues WHERE id = ?', $id);
	$league_gaining_id = (int)$league_gaining_id;
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('League name') . ':</td><td><input class="longest" id="form-league_name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="8">';
	start_upload_logo_button($id);
	echo get_label('Change logo') . '<br>';
	$league_pic = new Picture(LEAGUE_PICTURE);
	$league_pic->set($id, $name, $flags);
	$league_pic->show(ICONS_DIR, false);
	end_upload_logo_button(LEAGUE_PIC_CODE, $id);
	echo '</td>';
	
	echo '</tr>';

	echo '<tr><td>'.get_label('Web site').':</td><td><input id="form-url" class="longest" value="' . htmlspecialchars($url, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td valign="top">'.get_label('Languages').':</td><td>';
	langs_checkboxes($langs);
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Contact email').':</td><td><input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '"></td></tr>';
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td><input class="longest" id="form-phone" value="' . htmlspecialchars($phone, ENT_QUOTES) . '"></td></tr>';
	
	// The scoring system and the normalizer of the league are its defaults, set in Rules of its
	// management menu.

	$query = new DbQuery('SELECT id, name FROM gainings WHERE (league_id IS NULL AND club_id IS NULL) OR league_id = ? ORDER BY name', $id);
	echo '<tr><td>' . get_label('Gaining system') . ':</td><td><select id="form-gaining">';
	while ($row = $query->next())
	{
		list($gaining_id, $gaining_name) = $row;
		show_option((int)$gaining_id, $league_gaining_id, $gaining_name);
	}
	echo '</select></td></tr>';
	
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<tr><td colspan="2">';
		echo '<input type="checkbox" id="form-elite"';
		if ($flags & LEAGUE_FLAG_GLOBAL)
		{
			echo ' checked';
		}
		echo  '> ' . get_label('global league. The default rules and scoring systems of a global league are offered to every club, and it can create elite series that bring more rating points.');
		echo '</td></tr>';
	}
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		let flags = 0;
		if ($("#form-elite").attr('checked')) flags |= <?php echo LEAGUE_FLAG_GLOBAL; ?>;
		let languages = mr.getLangs();
		json.post("api/ops/league.php",
		{
			op: "change"
			, league_id: <?php echo $id; ?>
			, name: $("#form-league_name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, gaining_id: $("#form-gaining").val()
			, langs: languages
			, flags: flags
		},
		onSuccess);
	}
	
	function uploadLogo(leagueId, onSuccess)
	{
		json.upload('api/ops/league.php', 
		{
			op: "change"
			, league_id: leagueId
			, logo: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
		onSuccess);
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
