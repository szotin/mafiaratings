<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';
require_once '../include/series.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['club_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	$club_id = (int)$_REQUEST['club_id'];
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	$club = $_profile->clubs[$club_id];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value=""></td></tr>';
	

	$scoring_id = $club->scoring_id;
	$normalizer_id = $club->normalizer_id;
	if (is_null($normalizer_id))
	{
		$normalizer_id = 0;
	}
	
	echo '<tr><td>' . get_label('Series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	$normalizer_id = 0; // set it to null because long term is not checked
	$normalizer_version = 0;
	list($scoring_version) = Db::record(get_label('scoring system'), 'SELECT version FROM scorings WHERE id = ?', $scoring_id);
	
	$datetime = get_datetime(time(), $club->timezone);
	$date = datetime_to_string($datetime, false);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '" onchange="setSeries()">';
	echo '</td></tr>';
	
	$addr_id = -1;
	$scoring_options = '{}';
	$query = new DbQuery('SELECT address_id, scoring_options FROM tournaments WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
	$row = $query->next();
	if ($row = $query->next())
	{
		list($addr_id, $scoring_options) = $row;
		$addr_id = (int)$addr_id;
	}
	else
	{
		$query = new DbQuery('SELECT address_id, scoring_options FROM events WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
		if ($row = $query->next())
		{
			list($addr_id, $scoring_options) = $row;
			$addr_id = (int)$addr_id;
		}
	}
	
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	show_option(-1, $addr_id, get_label('New address'));
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option((int)$row[0], $addr_id, $row[1]))
		{
			$selected_address = $row[1];
		}
	}
	echo '</select><div id="form-new_addr_div">';
//	echo '<button class="icon" onclick="mr.createAddr(' . $club_id . ')" title="' . get_label('Create [0]', get_label('address')) . '"><img src="images/create.png" border="0"></button>';
	echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	show_country_input('form-country', $club->country, 'form-city');
	echo ' ';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</td></tr>';
	

	echo '<tr><td>'.get_label('Admission rate').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()" checked> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $club->currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option((int)$cid, $club->currency_id, $cname);
	}
	echo '</select></td></tr>';
	
	// The rules, the scoring and the awards are set on the Rules tab of the preparation page,
	// and the number of players on its Scheme. A new tournament starts with the club's rules
	// and scoring - no normalizer, it is not a long-term one yet - and the scoring options of the
	// club's last tournament.
	$rules_code = upgrade_rules_code($club->rules_code);

	if (is_valid_lang($club->langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes(LANG_ALL, $club->langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
	
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-manual_scoring"> ' . get_label('scoring is entered manually instead of calculating it from games results.') . '<br>';
	if (is_permitted(PERMISSION_ADMIN))
	{
		echo '<input type="checkbox" id="form-pin"> ' . get_label('pin to the main page.') . '<br>';
	}
	echo '<input type="checkbox" id="form-hide"> ' . get_label('hide from the main page.') . '<br>';
	echo '</td></tr>';
	
	echo '</table>';
	
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	var seriesList = new Object();
	function setSeries()
	{
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: '+' + strToDate($('#form-start').val())
			, started_before: _end
			, club_id: <?php echo $club_id; ?>
			// Only the series this tournament can be part of: one where tournaments are played,
			// and not one that is private to somebody else.
			, can_enter: <?php echo SERIES_FLAG_PLAYS_TOURNAMENTS; ?>
		},
		function(series)
		{
			var html = '<table class="dialog_form" width="100%"><tr>';
			var sl = new Object();
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (seriesList[s.id])
				{
					sl[s.id] = seriesList[s.id];
				}
				else
				{
					sl[s.id] =
					{
						id: s.id,
						selected: false,
						stars: 0,
						stage: <?php echo SERIES_STAGE_BASE; ?>
					};
				}
				
				// Not every series rates its tournaments, and not every series is a ladder. A
				// series that does neither asks nothing beyond whether the tournament is in it.
				//
				// The ladder is the shape of the competition, so only a manager of the series is
				// shown it. For anybody else the tournament lands on the base stage, and the op
				// ignores a stage sent by them anyway.
				var hasStars = (s.max_stars > 0);
				var hasStages = (s.stages > 1);
				var mayEditStage = (hasStages && s.is_manager);

				html += '<tr><td width="80" align="center"><img src="' + s.icon + '" width="48"><br><b>' + s.name + '</td>';

				html += '<td><input type="checkbox" id="form-p-' + s.id + '" onclick="seriesParticipantClick(' + s.id + ')"'
				if (sl[s.id].selected)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('participate'); ?>';

				html += '</td>';

				html += '<td align="center" nowrap>';
				if (mayEditStage)
				{
					html += '<select id="form-stage-' + s.id + '" onchange="stageChanged(' + s.id + ')">';
					for (var j = 0; j < s.stage_names.length; ++j)
					{
						html += '<option value="' + s.stage_names[j].stage + '"';
						if (s.stage_names[j].stage == sl[s.id].stage)
						{
							html += ' selected';
						}
						html += '>' + s.stage_names[j].name + '</option>';
					}
					html += '</select>';
					if (hasStars)
					{
						html += '<br>';
					}
				}
				if (hasStars)
				{
					html += '<div id="form-stars-' + s.id + '" class="stars"></div>';
				}
				html += '</td></tr>';
			}
			html += '</table>';
			$('#form-series').html(html);

			seriesList = sl;
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (s.max_stars < 1)
				{
					continue; // no star control was drawn for it
				}
				$("#form-stars-" + s.id).rate(
				{
					max_value: s.max_stars,
					step_size: 1,
					initial_value: seriesList[s.id].stars,
				}).on("change", function(ev, data) { starsChanged(this, data.to); });
			}
		});
	}
	setSeries();
	
	function starsChanged(control, stars)
	{
		var seriesId = control.id.substr(control.id.lastIndexOf('-')+1);
		$("#form-p-" + seriesId).prop('checked', true);
		seriesList[seriesId].stars = stars;
		seriesList[seriesId].selected = true;
	}
	
	function stageChanged(seriesId)
	{
		seriesList[seriesId].stage = $("#form-stage-" + seriesId).val();
		$("#form-p-" + seriesId).prop('checked', true);
		seriesList[seriesId].selected = true;
	}

	function seriesParticipantClick(seriesId)
	{
		var d = false;
		if (!$("#form-p-" + seriesId).attr('checked'))
		{
			d = true;
			$("#form-stars-" + seriesId).rate("setValue", 0);
			$("#form-p-" + seriesId).prop('checked', false);
		}
		seriesList[seriesId].selected = !d;
	}
	
	function onMinDateChange()
	{
		$('#form-end').attr("min", $('#form-start').val());
		var f = new Date($('#form-start').val());
		var t = new Date($('#form-end').val());
		if (f > t)
		{
			$('#form-end').val($('#form-start').val());
		}
		setSeries();
	}
	
	var oldAddressValue = "<?php echo $selected_address; ?>";
	function newAddressChange()
	{
		var text = $("#form-new_addr").val();
		if ($("#form-name").val() == oldAddressValue)
		{
			$("#form-name").val(text);
		}
		oldAddressValue = text;
	}
	
	function addressClick()
	{
		var text = '';
		if ($("#form-addr_id").val() <= 0)
		{
			$("#form-new_addr_div").show();
		}
		else
		{
			$("#form-new_addr_div").hide();
			text = $("#form-addr_id option:selected").text();
		}
		
		if ($("#form-name").val() == oldAddressValue)
		{
			$("#form-name").val(text);
		}
		oldAddressValue = text;
	}
	addressClick();
	
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
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		// What a new tournament starts with of what is set elsewhere now: MVP, best red and best
		// black, its games streamed, the roles shown in the stream, and anybody may register.
		var _flags = <?php echo TOURNAMENT_FLAG_AWARD_MVP | TOURNAMENT_FLAG_AWARD_RED | TOURNAMENT_FLAG_AWARD_BLACK | TOURNAMENT_FLAG_STREAMING; ?>;
		if ($("#form-manual_scoring").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_MANUAL_SCORE; ?>;
		if ($("#form-pin").length > 0 && $("#form-pin").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_PINNED; ?>;
		if ($("#form-hide").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_HIDE_FROM_MAIN_PAGE; ?>;
		
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var series = [];
		for (const i in seriesList) 
		{
			var s = seriesList[i];
			if (s.selected)
			{
				var _s = { id: s.id, stars: s.stars, stage: s.stage };
				series.push(_s);
			}
		}
		series = JSON.stringify(series);
		
		var params =
		{
			op: "create",
			club_id: <?php echo $club_id; ?>,
			parent_series: series,
			name: $("#form-name").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			address_id: _addr,
			scoring_id: <?php echo (int)$scoring_id; ?>,
			scoring_version: <?php echo (int)$scoring_version; ?>,
			scoring_options: <?php echo json_encode($scoring_options); ?>,
			normalizer_id: 0,
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags,
			rules_code: <?php echo json_encode($rules_code); ?>,
		};
		
		if (_addr > 0)
		{
			params['address_id'] = _addr;
		}
		else
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
	
		json.post("api/ops/tournament.php", params, onSuccess);
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