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
	
	list ($name, $url, $email, $phone, $langs, $flags) =
		Db::record(get_label('league'), 'SELECT name, web_site, email, phone, langs, flags FROM leagues WHERE id = ?', $id);
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('League name') . ':</td><td><input class="longest" id="form-league_name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="8">';
	start_upload_logo_button();
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
	
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id IS NULL ORDER BY name', $id);
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td><select id="form-scoring">';
	while ($row = $query->next())
	{
		list ($scoring_id, $scoring_name) = $row;
		echo '<option value="' . $scoring_id . '"';
		if ($scoring_id == $league->scoring_id)
		{
			echo ' selected';
		}
		echo '>' . $scoring_name . '</option>';
	} 
	echo '</select></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/league.php",
		{
			op: "change"
			, league_id: <?php echo $id; ?>
			, name: $("#form-league_name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, scoring_id: $("#form-scoring").val()
			, langs: languages
		},
		onSuccess);
	}
	
	function uploadLogo(onSuccess)
	{
		json.upload('api/ops/league.php', 
		{
			op: "change"
			, league_id: <?php echo $id; ?>
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
