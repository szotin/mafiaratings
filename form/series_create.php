<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timespan.php';
require_once '../include/datetime.php';
require_once '../include/security.php';
require_once '../include/picture.php';
require_once '../include/currency.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('sеriеs')));
	
	if (!isset($_REQUEST['league_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('league')));
	}
	
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	$league_id = 0;
	if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
	}

	echo '<table class="dialog_form" width="100%">';
	list($league_name, $league_flags, $league_langs) = Db::record(get_label('league'), 'SELECT name, flags, langs FROM leagues WHERE id = ?', $league_id);
	
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="' . ICON_WIDTH . '">';
	$league_pic = new Picture(LEAGUE_PICTURE);
	$league_pic->set($league_id, $league_name, $league_flags);
	$league_pic->show(ICONS_DIR, false);
	echo '</td><td align="center"><b>' . $league_name . '</b></td></tr></table></td></tr>';
	
	echo '<tr><td width="240">' . get_label('Series name') . ':</td><td><input id="form-name" value=""></td></tr>';
	
	echo '<tr><td>' . get_label('Belongs to series') . ':</td><td><div id="form-series"></div></td></tr>';
	
	$timezone = get_timezone();
	$datetime = get_datetime(time(), $timezone);
	$date = datetime_to_string($datetime, false);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '" onchange="setSeries()">';
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate per player-tournament').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()" checked> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, DEFAULT_CURRENCY, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option($cid, DEFAULT_CURRENCY, $cname);
	}
	echo '</select></td></tr>';
	
	if (is_valid_lang($league_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $league_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes(LANG_ALL, $league_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}

	list($default_gaining_id) = Db::record(get_label('league'), 'SELECT gaining_id FROM leagues WHERE id = ?', $league_id);
	$query = new DbQuery('SELECT id, name FROM gainings WHERE league_id IS NULL OR league_id = ? ORDER BY name', $league_id);
	echo '<tr><td>' . get_label('Gaining system') . ':</td><td><select id="form-gaining">';
	while ($row = $query->next())
	{
		list($gaining_id, $gaining_name) = $row;
		show_option($gaining_id, $default_gaining_id, $gaining_name);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Notes') . ':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
	
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-pin"> ' . get_label('pin to the main page.');
	if ($league_flags & LEAGUE_FLAG_ELITE)
	{
		echo '<br><input type="checkbox" id="form-elite"> ' . get_label('elite series. The tournaments with more than one star become elite tournaments and bring more rating points.');
	}
	echo '</td></tr>';
	
	echo '</table>';
		
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
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
	
	var seriesList = new Object();
	function setSeries()
	{
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		json.post("api/get/series.php",
		{
			ended_after: _end
			, started_before: '+' + strToDate($('#form-start').val())
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
						stars: 0
					};
				}
				
				html += '<tr><td width="80" align="center"><img src="' + s.icon + '" width="48"><br><b>' + s.name + '</td>';
				
				html += '<td><input type="checkbox" id="form-p-' + s.id + '" onclick="seriesParticipantClick(' + s.id + ')"'
				if (sl[s.id].selected)
				{
					html += ' checked';
				}
				html += '> <?php echo get_label('participate'); ?></td>';
				html += '<td align="center"><div id="form-stars-' + s.id + '" class="stars"></div></td></tr>';
			}
			html += '</table>';
			$('#form-series').html(html);
			
			seriesList = sl;
			for (i = 0; i < series.series.length; ++i)
			{
				var s = series.series[i];
				if (seriesList[s.id])
				{
					$("#form-stars-" + s.id).rate(
					{
						max_value: 5,
						step_size: 1,
						initial_value: seriesList[s.id].stars,
					}).on("change", function(ev, data) { starsChanged(this, data.to); });
				}
			}
		});
	}
	setSeries();
	
	function starsChanged(control, stars)
	{
		var seriesId = control.id.substr(control.id.lastIndexOf('-')+1);
		$("#form-p-" + seriesId).prop('checked', true);
		$("#form-f-" + seriesId).prop('disabled', false);
		seriesList[seriesId].stars = stars;
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
			$("#form-f-" + seriesId).prop('checked', false);
		}
		$("#form-f-" + seriesId).prop('disabled', d);
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
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		
		var _flags = 0;
		if ($("#form-pin").attr('checked')) _flags |= <?php echo SERIES_FLAG_PINNED; ?>;
		if ($("#form-elite").attr('checked')) _flags |= <?php echo SERIES_FLAG_ELITE; ?>;
		
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var series = [];
		for (const i in seriesList) 
		{
			var s = seriesList[i];
			if (s.selected)
			{
				series.push({ id: s.id, stars: s.stars });
			}
		}
		series = JSON.stringify(series);
		
		var params =
		{
			op: "create",
			league_id: <?php echo $league_id; ?>,
			parent_series: series,
			name: $("#form-name").val(),
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			gaining_id: $('#form-gaining').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags
		};
		
		json.post("api/ops/series.php", params, onSuccess);
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