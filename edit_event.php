<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/image.php';
require_once 'include/email_template.php';

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
		
		if ($_profile == NULL || !$_profile->is_manager($this->event->club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}

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
			$this->event->duration = $_REQUEST['duration'];
		}
		if (isset($_REQUEST['rules']))
		{
			$this->event->rules_id = $_REQUEST['rules'];
		}
		if (isset($_REQUEST['scoring']))
		{
			$this->event->scoring_id = $_REQUEST['scoring'];
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
		echo '<tr><td class="dark" width="80">'.get_label('Event name').':</td><td><input name="name" value="' . htmlspecialchars($this->event->name, ENT_QUOTES) . '"></td>';
		
		echo '<td width="100" align="center" valign="top" rowspan="9">';
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
		
		echo '<tr><td class="dark">'.get_label('Duration').':</td><td><select name="duration">';
		for ($i = 1; $i <= 12; ++$i)
		{
			show_option($i * 3600, $this->event->duration, $i);
		}
		for ($i = 24; $i <= 120; $i += 24)
		{
			show_option($i * 3600, $this->event->duration, $i);
		}
		echo '</select> '.get_label('hours').'</td></tr>';
			
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
		
		$query = new DbQuery('SELECT rules_id, name FROM club_rules WHERE club_id = ? ORDER BY name', $this->event->club_id);
		while ($row = $query->next())
		{
			$custom_rules = true;
			echo '<tr><td class="dark">' . get_label('Game rules') . ':</td><td><select id="rules" name="rules"><option value="' . $club->rules_id . '"';
			if ($club->rules_id == $this->event->rules_id)
			{
				echo ' selected';
				$custom_rules = false;
			}
			echo '>' . get_label('[default]') . '</option>';
			do
			{
				list ($rules_id, $rules_name) = $row;
				echo '<option value="' . $rules_id . '"';
				if ($custom_rules && $rules_id == $this->event->rules_id)
				{
					echo ' selected';
				}
				echo '>' . $rules_name . '</option>';
			} while ($row = $query->next());
			echo '</select> <a href ="javascript:mr.createRules(' . $club->id . ', rulesCreated)" title="' . get_label('Create [0]', get_label('rules')) . '"><img src="images/rules.png" border="0"></a></td></tr>';
		}
		
		$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $this->event->club_id);
		echo '<tr><td class="dark">' . get_label('Scoring system') . ':</td><td><select name="scoring">';
		while ($row = $query->next())
		{
			list ($scoring_id, $scoring_name) = $row;
			echo '<option value="' . $scoring_id . '"';
			if ($scoring_id == $this->event->scoring_id)
			{
				echo ' selected';
			}
			echo '>' . $scoring_name . '</option>';
		} 
		echo '</select></td></tr>';
		
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
		
		echo '<input type="checkbox" name="tournament" value="1"';
		if (($this->event->flags & EVENT_FLAG_TOURNAMENT) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('official tournament').'</td></tr>';
		
		echo '</table>';
		
		if (($this->event->flags & EVENT_FLAG_CANCELED) != 0)
		{
			echo '<input type="hidden" name="canceled" value="1">';
		}
		
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Save').'" name="update">';
		echo '<input type="submit" class="btn norm" value="'.get_label('Cancel').'" name="cancel">';
		echo '</p></form>';
		
		show_upload_script(EVENT_PIC_CODE, $this->event->id);
	}
}

$page = new Page();
$page->run(get_label('Change event'), UC_PERM_MANAGER);

?>

<script>
function rulesCreated(data)
{
	var r = $('#rules');
	r.html(r.html() + '<option value=' + data.id + '>' + data.name + '</option>');
	r.val(data.id);
}
</script>