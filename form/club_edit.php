<?php

require_once '../include/session.php';
require_once '../include/club.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/image.php';
require_once '../include/url.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('club')));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('club')));
	}
	$id = $_REQUEST['id'];
	
	if ($_profile == NULL || !$_profile->is_club_manager($id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$club = $_profile->clubs[$id];

	list ($url, $email, $phone, $fee, $currency_id, $flags) =
		Db::record(get_label('club'), 'SELECT web_site, email, phone, fee, currency_id, flags FROM clubs WHERE id = ?', $id);
	if (is_null($currency_id))
	{
		list($currency_id) = Db::record(get_label('country'), 'SELECT co.currency_id FROM clubs c JOIN cities ci ON ci.id = c.city_id JOIN countries co ON co.id = ci.country_id WHERE c.id = ?', $id);
	}
	$currency_id = (int)$currency_id;
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Club name') . ':</td><td><input class="longest" id="form-club_name" value="' . htmlspecialchars($club->name, ENT_QUOTES) . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="10">';
	start_upload_logo_button($id);
	echo get_label('Change logo') . '<br>';
	$club_pic = new Picture(CLUB_PICTURE);
	$club_pic->set($id, $club->name, $flags);
	$club_pic->show(ICONS_DIR, false);
	end_upload_logo_button(CLUB_PIC_CODE, $id);
	echo '</td>';
	
	echo '</tr>';
	
	echo '<tr><td>' . get_label('Club system') . ':</td><td>';
	echo '<select id="form-parent">';
	show_option(0, $club->parent_id, '');
	$query = new DbQuery('SELECT id, name FROM clubs WHERE parent_id IS NULL AND (flags & ' . CLUB_FLAG_CLOSED . ') = 0 AND id <> ? ORDER BY name', $club->id);
	while ($row = $query->next())
	{
		list($c_id, $c_name) = $row;
		show_option((int)$c_id, $club->parent_id, $c_name);
	}
	echo '</select>';
	echo '</td></tr>';

	echo '<tr><td>'.get_label('Web site').':</td><td><input id="form-url" class="longest" value="' . htmlspecialchars($url, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td valign="top">'.get_label('Languages').':</td><td>';
	langs_checkboxes($club->langs);
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Contact email').':</td><td><input class="longest" id="form-email" value="' . htmlspecialchars($email, ENT_QUOTES) . '"></td></tr>';
	echo '<tr><td>'.get_label('Contact phone(s)').':</td><td><input class="longest" id="form-phone" value="' . htmlspecialchars($phone, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"' . (is_null($fee)?' checked':'') .'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option((int)$cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Country') . ':</td><td>';
	show_country_input('form-country', $club->country, 'form-city');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('City') . ':</td><td>';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</td></tr>';
	
	$rules_list = get_available_rules($club->id);
	$rules_code = $club->rules_code;
	echo '<tr><td>' . get_label('Rules') . ':</td><td><select id="form-rules">';
	foreach ($rules_list as $r)
	{
		if (show_option($r->rules, $rules_code, $r->name))
		{
			$rules_code = '';
		}
	}
	if (!empty($rules_code))
	{
		show_option($rules_code, $rules_code, get_label('Custom...'));
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring system') . ':</td><td><select id="form-scoring">';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $id);
	while ($row = $query->next())
	{		
		list ($scoring_id, $scoring_name) = $row;
		show_option((int)$scoring_id, $club->scoring_id, $scoring_name);
	} 
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Scoring normalizer') . ':</td><td><select id="form-normalizer">';
	show_option(0, is_null($club->normalizer_id) ? 0 : -1, get_label('No scoring normalization'));
	$query = new DbQuery('SELECT id, name FROM normalizers WHERE club_id = ? OR club_id IS NULL ORDER BY name', $id);
	while ($row = $query->next())
	{		
		list ($normalizer_id, $normalizer_name) = $row;
		show_option((int)$normalizer_id, $club->normalizer_id, $normalizer_name);
	} 
	echo '</select></td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	function feeChanged()
	{
		$("#form-fee-unknown").prop('checked', 0);
	}
	
	function feeUnknownClicked()
	{
		if ($("#form-fee-unknown").attr('checked'))
		{
			$("#form-fee").val('');
		}
	}
	
	function commit(onSuccess)
	{
		var languages = mr.getLangs();
		json.post("api/ops/club.php",
		{
			op: "change"
			, club_id: <?php echo $id; ?>
			, parent_id: $("#form-parent").val()
			, name: $("#form-club_name").val()
			, url: $("#form-url").val()
			, email: $("#form-email").val()
			, phone: $("#form-phone").val()
			, fee: ($("#form-fee-unknown").attr('checked') ? -1 : $("#form-fee").val())
			, currency_id: $("#form-currency").val()
			, city: $("#form-city").val()
			, country: $("#form-country").val()
			, scoring_id: $("#form-scoring").val()
			, rules_code: $("#form-rules").val()
			, normalizer_id: $("#form-normalizer").val()
			, langs: languages
		},
		onSuccess);
	}
	
	function uploadLogo(clubId, onSuccess)
	{
		json.upload('api/ops/club.php', 
		{
			op: "change",
			club_id: clubId,
			logo: document.getElementById("upload").files[0]
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
