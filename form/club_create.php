<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/club.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('club')));
	
	$langs = $_profile->user_langs;
	$email = $_profile->user_email;

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140" valign="top">'.get_label('Club name').':</td><td>';
	Names::show_control(new Names(0, get_label('club name')));
	echo '</td></tr>';

	// The logo, as in form/tournament_create.php: there is no club to give it to yet, so it is only
	// chosen here - and shown - and is uploaded by commit() once the club is made. The picture is the
	// button that chooses it.
	echo '<tr><td>' . get_label('Logo') . ':</td><td>';
	echo '<input type="file" id="form-logo" accept="image/*" style="display:none" onchange="logoChosen()">';
	echo '<button type="button" class="upload" onclick="$(\'#form-logo\').trigger(\'click\');">';
	echo '<img id="form-logo-img" src="images/' . ICONS_DIR . 'club.png" width="56" height="56"></button>';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', COUNTRY_DETECT, 'form-city');
	echo '</td></tr>';

	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', CITY_DETECT, 'form-country');
	echo '</td></tr>';

	// The league whose defaults - rules and systems - the club starts from. Without one a sub-club
	// starts from its parent, any other club from the defaults of the site.
	echo '<tr><td>' . get_label('System') . ':</td><td>';
	// Made from the list of the clubs of a league, the club starts from that league and joins it.
	$league_id = isset($_REQUEST['league_id']) ? (int)$_REQUEST['league_id'] : 0;
	show_system_league_select('form-system', $league_id);
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Parent club') . ':</td><td>';
	echo '<select id="form-parent">';
	show_option(0, 0, '');
	$query = new DbQuery(
		'SELECT c.id, nc.name'.
		' FROM clubs c'.
		' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
		' WHERE c.parent_id IS NULL AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0'.
		' ORDER BY nc.name');
	while ($row = $query->next())
	{
		list($c_id, $c_name) = $row;
		show_option((int)$c_id, 0, $c_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Languages').':</td><td>';
	langs_checkboxes($langs);
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Web site').':</td><td><input class="longest" id="form-url"> </td></tr>';

	echo '<tr><td>'.get_label('Contact email').':</td><td>';
	echo '<input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '">';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>';
	echo '<input class="longest" id="form-phone">';
	echo '</td></tr>';

	echo '</table>';
?>	
	<script>
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		var request =
		{
			op: 'create'
			, parent_id: $("#form-parent").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, country: $("#form-country").val()
			, city: $("#form-city").val()
			, langs: languages
			, system_league_id: $("#form-system").val()
		};
		nameControl.fillRequest(request);
		json.post("api/ops/club.php", request, function(obj)
		{
			addToLeague(obj, function() { uploadLogo(obj, onSuccess); });
		});
	}

	// The league whose list of clubs this was made from takes it in: at once where whoever made it
	// manages the league, as a request to the league otherwise. The club is made whatever happens.
	function addToLeague(obj, next)
	{
		var leagueId = <?php echo $league_id; ?>;
		if (leagueId <= 0)
		{
			next();
			return;
		}
		json.post('api/ops/league.php', { op: 'add_club', league_id: leagueId, club_id: obj.club_id }, next,
			function(message) { dlg.error(message, null, null, next); return true; });
	}

	// The club is made whatever happens to its logo: a logo that fails to upload is said, and can be
	// changed on the first tab of its management.
	function uploadLogo(obj, onSuccess)
	{
		var logo = document.getElementById('form-logo').files[0];
		if (!logo)
		{
			onSuccess(obj);
			return;
		}
		json.upload('api/ops/club.php',
		{
			op: "change"
			, club_id: obj.club_id
			, logo: logo
		},
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>,
		function() { onSuccess(obj); },
		function(message) { dlg.error(message, null, null, function() { onSuccess(obj); }); return true; });
	}

	function logoChosen()
	{
		var file = document.getElementById('form-logo').files[0];
		if (file && window.URL)
		{
			var url = URL.createObjectURL(file);
			$('#form-logo-img').attr('src', url);
			// The summary beside the form shows it too, where there is one - see create.php.
			$('#wz-sum-logo-img').attr('src', url);
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