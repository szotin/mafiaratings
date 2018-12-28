<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/image.php';
require_once 'include/email_template.php';
require_once 'include/scoring.php';
require_once 'include/timespan.php';

class Page extends PageBase
{
	private $event;

	protected function prepare()
	{
		global $_profile;
		
		$template_for = EMAIL_DEFAULT_FOR_NOTHING;
		if (isset($_POST['cancel']))
		{
			redirect_back();
			return;
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$this->event = new Event();
		$this->event->load($_REQUEST['id']);
		check_permissions(PERMISSION_CLUB_MANAGER, $this->event->club_id);

//		$this->event->init($this->event->club_id, -1);
		
		if (isset($_REQUEST['addr_id']))
		{
			$addr_id = $_REQUEST['addr_id'];
			if ($addr_id > 0)
			{
				if ($addr_id != $this->event->addr_id)
				{
					list($timezone, $this->event->addr, $this->event->addr_url, $this->event->addr_flags) =
						Db::record(
							get_label('address'), 
							'SELECT c.timezone, a.address, a.map_url, a.flags from addresses a' .
								' JOIN cities c ON a.city_id = c.id' .
								' WHERE a.id = ?',
							$addr_id);
					$this->event->addr_id = $addr_id;
					$this->event->set_datetime($this->event->timestamp, $timezone);
					$template_for = EMAIL_DEFAULT_FOR_CHANGE_ADDRESS;
				}
			}
			else
			{
				list($timezone) = Db::record(get_label('city'), 'SELECT timezone FROM cities WHERE id = ?', $city_id);
				$this->event->set_datetime($this->event->timestamp, $timezone);
			}
		}
		
		if (isset($_REQUEST['day']))
		{
			if ($this->event->day != $_REQUEST['day'])
			{
				$template_for = EMAIL_DEFAULT_FOR_CHANGE_TIME;
			}
			$this->event->day = $_REQUEST['day'];
		}
		if (isset($_REQUEST['month']))
		{
			if ($this->event->month != $_REQUEST['month'])
			{
				$template_for = EMAIL_DEFAULT_FOR_CHANGE_TIME;
			}
			$this->event->month = $_REQUEST['month'];
		}
		if (isset($_REQUEST['year']))
		{
			if ($this->event->year != $_REQUEST['year'])
			{
				$template_for = EMAIL_DEFAULT_FOR_CHANGE_TIME;
			}
			$this->event->year = $_REQUEST['year'];
		}
		if (isset($_REQUEST['name']))
		{
			$this->event->name = trim($_REQUEST['name']);
		}
		if (isset($_REQUEST['price']))
		{
			$this->event->price = trim($_REQUEST['price']);
		}
		if (isset($_REQUEST['hour']))
		{
			if ($this->event->hour != $_REQUEST['hour'])
			{
				$template_for = EMAIL_DEFAULT_FOR_CHANGE_TIME;
			}
			$this->event->hour = $_REQUEST['hour'];
		}
		if (isset($_REQUEST['minute']))
		{
			if ($this->event->minute != $_REQUEST['minute'])
			{
				$template_for = EMAIL_DEFAULT_FOR_CHANGE_TIME;
			}
			$this->event->minute = $_REQUEST['minute'];
		}
		if (isset($_REQUEST['notes']))
		{
			$this->event->notes = stripslashes($_REQUEST['notes']);
		}
		if (isset($_REQUEST['duration']))
		{
			$this->event->duration = string_to_timespan($_REQUEST['duration']);
			if ($this->event->duration <= 0)
			{
				throw new Exc(get_label('Incorrect duration format.'));
			}
		}
		if (isset($_REQUEST['rules']))
		{
			$this->event->rules_code = $_REQUEST['rules'];
		}
		if (isset($_REQUEST['scoring_id']))
		{
			$this->event->scoring_id = $_REQUEST['scoring_id'];
		}
		if (isset($_REQUEST['scoring_weight']))
		{
			$this->event->scoring_weight = $_REQUEST['scoring_weight'];
		}
		if (isset($_REQUEST['planned_games']))
		{
			$this->event->planned_games = $_REQUEST['planned_games'];
		}
		$this->event->langs = get_langs($this->event->langs);
		
		date_default_timezone_set($this->event->timezone);
		$this->event->timestamp = mktime($this->event->hour, $this->event->minute, 0, $this->event->month, $this->event->day, $this->event->year);
		
		if (isset($_POST['update']))
		{
			if (isset($_REQUEST['reg_att']))
			{
				$this->event->flags |= EVENT_FLAG_REG_ON_ATTEND;
			}
			else
			{
				$this->event->flags &= ~EVENT_FLAG_REG_ON_ATTEND;
			}
			if (isset($_REQUEST['pwd_req']))
			{
				$this->event->flags |= EVENT_FLAG_PWD_REQUIRED;
			}
			else
			{
				$this->event->flags &= ~EVENT_FLAG_PWD_REQUIRED;
			}
			if (isset($_REQUEST['all_mod']))
			{
				$this->event->flags |= EVENT_FLAG_ALL_MODERATE;
			}
			else
			{
				$this->event->flags &= ~EVENT_FLAG_ALL_MODERATE;
			}
			if (isset($_REQUEST['canceled']))
			{
				$this->event->flags |= EVENT_FLAG_CANCELED;
				$template_for = EMAIL_DEFAULT_FOR_CANCEL;
			}
			else
			{
				$this->event->flags &= ~EVENT_FLAG_CANCELED;
			}
			if ($_profile->is_admin())
			{
				if (isset($_REQUEST['tournament']))
				{
					$this->event->flags |= EVENT_FLAG_TOURNAMENT;
				}
				else
				{
					$this->event->flags &= ~EVENT_FLAG_TOURNAMENT;
				}
			}
			
			if (isset($_REQUEST['rounds-changed']))
			{
				$this->event->clear_rounds();
				$round = 0;
				while (true)
				{
					$prefix = 'round' . $round;
					if (!isset($_REQUEST[$prefix . '_name']))
					{
						break;
					}
					$this->event->add_round($_REQUEST[$prefix . '_name'], $_REQUEST[$prefix . '_scoring'], $_REQUEST[$prefix . '_scoring_weight'], $_REQUEST[$prefix . '_planned_games']);
					++$round;
				}
			}
			
			$this->event->update();
			throw new RedirectExc('create_event_mailing.php?msg=1&events=' . $this->event->id . '&template=' . $email_template_id . '&for=' . $template_for);
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$club = $_profile->clubs[$this->event->club_id];
		
		echo '<form method="post" name="editForm" action="edit_event.php">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<table class="bordered light" width="100%">';
		echo '<tr><td class="dark" width="160">'.get_label('Event name').':</td><td><input name="name" value="' . htmlspecialchars($this->event->name, ENT_QUOTES) . '"></td>';
		
		echo '<td width="100" align="center" valign="top" rowspan="12">';
		$this->event->show_pic(ICONS_DIR);
		echo '<p>';
		show_upload_button();
		echo '</p></td>';
		echo '</td></tr>';
		
		if ($this->event->timestamp > time())
		{
			echo '<tr><td class="dark">'.get_label('Date').':</td><td>';
			show_date_controls($this->event->day, $this->event->month, $this->event->year);
			echo '</td></tr>';
			
			echo '<tr><td class="dark">'.get_label('Time').':</td><td>';
			show_time_controls($this->event->hour, $this->event->minute);
			echo '</td></tr>';
		}
		else
		{
			echo '<tr><td class="dark">' . get_label('Date') . ':</td><td>' . get_label('[0]/[1]/[2] - can not be changed because the event has already started.', $this->event->day, $this->event->month, $this->event->year) . '</td></tr>';
			echo '<tr><td class="dark">' . get_label('Time') . ':</td><td>' . get_label('[0]:[1] - can not be changed because the event has already started.', $this->event->hour, $this->event->minute) . '</td></tr>';
			
			echo '<input type="hidden" name="day" value="' . $this->event->day . '">';
			echo '<input type="hidden" name="month" value="' . $this->event->month . '">';
			echo '<input type="hidden" name="year" value="' . $this->event->year . '">';
			echo '<input type="hidden" name="hour" value="' . $this->event->hour . '">';
			echo '<input type="hidden" name="minute" value="' . $this->event->minute . '">';
		}
		
		echo '<tr><td class="dark">'.get_label('Duration').':</td><td><input value="' . timespan_to_string($this->event->duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" name="duration" id="duration" onkeyup="checkDuration()"></td></tr>';
			
		echo '<tr><td class="dark" valign="top">'.get_label('Address').':</td><td>';
		echo '<select name="addr_id"">';
		$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $this->event->club_id);
		$addr_selected = false;
		while ($row = $query->next())
		{
			show_option($row[0], $this->event->addr_id, $row[1]);
		}
		echo '</select></td></tr>';
		
		echo '<tr><td class="dark">'.get_label('Admission rate').':</td><td><input name="price" value="' . $this->event->price . '"></td></tr>';
		
		$query = new DbQuery('SELECT rules, name FROM club_rules WHERE club_id = ? ORDER BY name', $this->event->club_id);
		$custom_rules = true;
		while ($row = $query->next())
		{
			$custom_rules = true;
			echo '<tr><td class="dark">' . get_label('Game rules') . ':</td><td><select id="rules" name="rules">';
			if (show_option($club->rules_code, $this->event->rules_code, $club->name))
			{
				$custom_rules = false;
			}

			while ($row = $query->next())
			{
				list ($rules_code, $rules_name) = $row;
				if (show_option($rules_code, $this->event->rules_code, $rules_name))
				{
					$custom_rules = false;
				}
			}
			if ($custom_rules)
			{
				show_option($this->event->rules_code, $this->event->rules_code, get_label('Custom'));
			}
			echo '</select></td></tr>';
		}
		
		echo '<tr><td class="dark">' . get_label('Rounds') . ':</td><td><table width="100%" class="transp">';
		echo '<tr><td width="48"><a href="javascript:addRound()" title="' . get_label('Add round') . '"><img src="images/create.png"></a></td>';
		echo '<td width="90">' . get_label('Name') . '</td>';
		echo '<td>' . get_label('Scoring system') . '</td>';
		echo '<td width="70">' . get_label('Scoring weight') . '</td>'; 
		echo '<td width="70" align="center">' . get_label('Planned games count') . '</td></tr>';
		echo '<tr><td></td>';
		echo '<td>' . get_label('Main round') . '</td>';
		echo '<td>';
		show_scoring_select($this->event->club_id, $this->event->scoring_id, '', get_label('Scoring system'), 'scoring_id', false);
		echo '</td>';
		echo '<td><input id="scoring_weight" name="scoring_weight" value="' . $this->event->scoring_weight . '"></td>';
		echo '<td><input id="planned_games" name="planned_games" value="' . ($this->event->planned_games > 0 ? $this->event->planned_games : '') . '"></td></tr>';
		echo '</table><span id="rounds"></span></td></tr>';
		
		if (is_valid_lang($club->langs))
		{
			echo '<input type="hidden" name="langs" value="' . $club->langs . '">';
		}
		else
		{
			echo '<tr><td class="dark" valign="top">'.get_label('Languages').':</td><td>';
			langs_checkboxes($this->event->langs, $club->langs);
			echo '</td></tr>';
		}
		
		echo '<tr><td class="dark" valign="top">' . get_label('Notes') . ':</td><td><textarea name="notes" cols="80" rows="4">' . htmlspecialchars($this->event->notes, ENT_QUOTES) . '</textarea></td></tr>';
		

		echo '<tr><td class="dark">&nbsp;</td><td>';
		echo '<input type="checkbox" name="reg_att" value="1"';
		if (($this->event->flags & EVENT_FLAG_REG_ON_ATTEND) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('allow users to register for the event when they click Attend button').'<br>';
		
		echo '<input type="checkbox" name="pwd_req" value="1"';
		if (($this->event->flags & EVENT_FLAG_PWD_REQUIRED) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('user password is required when moderator is registering him for this event.').'<br>';
		
		echo '<input type="checkbox" name="all_mod" value="1"';
		if (($this->event->flags & EVENT_FLAG_ALL_MODERATE) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('everyone can moderate games.').'<br>';
		
		if ($_profile->is_admin())
		{
			echo '<input type="checkbox" name="tournament" value="1"';
			if (($this->event->flags & EVENT_FLAG_TOURNAMENT) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('official tournament').'</td></tr>';
		}
		
		echo '</table>';
		
		if (($this->event->flags & EVENT_FLAG_CANCELED) != 0)
		{
			echo '<input type="hidden" name="canceled" value="1">';
		}
		
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Save').'" id="update" name="update">';
		echo '<input type="submit" class="btn norm" value="'.get_label('Cancel').'" name="cancel">';
		echo '</p></form>';
		
		show_upload_script(EVENT_PIC_CODE, $this->event->id);
	}
	
	protected function js()
	{
		parent::js();
		$separator = '';
		echo "var rounds = [\n";
		foreach ($this->event->rounds as $round)
		{
			echo $separator . '{ name: "' . $round->name . '", scoring_id: ' . $round->scoring_id . ', scoring_weight: ' . $round->scoring_weight . ', planned_games: ' . $round->planned_games . ' }';
			$separator = ",\n";
		}
		echo "];\n";
		
		echo "var roundRow = '<tr>";
		echo '<td width="48"><a href="javascript:deleteRound({num})" title="' . get_label('Delete round') . '"><img src="images/delete.png"></a></td>';
		echo '<td width="90"><input name="round{num}_name" id="round{num}_name" class="short" onchange="setRoundValues({num})"></td>';
		echo '<td>';
		show_scoring_select($this->event->club_id, 0, 'setRoundValues({num})', get_label('Scoring system'), 'round{num}_scoring', false);
		echo '</td>';
		echo '<td width="70"><input id="round{num}_scoring_weight" name="round{num}_scoring_weight" onchange="setRoundValues({num})"></td>';
		echo '<td width="70"><input id="round{num}_planned_games" name="round{num}_planned_games" onchange="setRoundValues({num})"></td>';
		echo "</tr>';\n";
?>
		var roundsChanged = false;
		
		function checkDuration()
		{
			if (strToTimespan($("#duration").val()) > 0)
				$('#update').removeAttr('disabled');
			else
				$('#update').attr('disabled','disabled');
		}
	
		function refreshRounds()
		{
			var html = '<table width="100%" class="transp">';
			for (var i = 0; i < rounds.length; ++i)
			{
				html += roundRow.replace(new RegExp('\\{num\\}', 'g'), i);
			}
			html += '</table>';
			if (roundsChanged)
			{
				html += '<input type="hidden" name="rounds-changed" value="1">';
			}
			$('#rounds').html(html);
			
			for (var i = 0; i < rounds.length; ++i)
			{
				var round = rounds[i];
				$('#round' + i + '_name').val(round.name);
				$('#round' + i + '_scoring').val(round.scoring_id);
				$('#round' + i + '_scoring_weight').spinner({ step:0.1, max:100, min:0.1, change:setAllRoundValues }).width(30).val(round.scoring_weight);
				$('#round' + i + '_planned_games').spinner({ step:1, max:1000, min:0, change:setAllRoundValues }).width(30).val(round.planned_games > 0 ? round.planned_games : '');
			}
		}
	
		function addRound()
		{
			rounds.push({ name: "", scoring_id: <?php echo $this->event->scoring_id; ?>, scoring_weight: 1, planned_games: 0});
			roundsChanged = true;
			refreshRounds();
		}
	
		function deleteRound(roundNumber)
		{
			rounds = rounds.slice(0, roundNumber).concat(rounds.slice(roundNumber + 1));
			roundsChanged = true;
			refreshRounds();
		}
		
		function setRoundValues(roundNumber)
		{
			var round = rounds[roundNumber];
			round.name = $('#round' + roundNumber + '_name').val();
			round.scoring_id = $('#round' + roundNumber + '_scoring').val();
			round.scoring_weight = $('#round' + roundNumber + '_scoring_weight').val();
			round.planned_games = $('#round' + roundNumber + '_planned_games').val();
			if (round.planned_games == 0)
			{
				$('#round' + roundNumber + '_planned_games').val('');
			}
			else if (isNaN(round.planned_games))
			{
				round.planned_games = 0;
			}
		}
		
		function setAllRoundValues()
		{
			for (var i = 0; i < rounds.length; ++i)
			{
				setRoundValues(i);
			}
		}
		
		function eventGamesChange()
		{
			if ($('#planned_games').val() <= 0)
			{
				$('#planned_games').val('');
			}
		}
<?php	
	}
	
	protected function js_on_load()
	{
?>
		$('#scoring_weight').spinner({ step:0.1, max:100, min:0.1 }).width(30);
		$('#planned_games').spinner({ step:1, max:1000, min:0, change:eventGamesChange }).width(30);
		refreshRounds();
<?php
	}
}

$page = new Page();
$page->run(get_label('Change event'));

?>