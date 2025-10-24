<?php

require_once 'include/event.php';
require_once 'include/club.php';

define('COMMENTS_WIDTH', 300);

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile,$_lang;
		
		if ($_profile != NULL && ($this->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->start_time + $this->duration)
		{
			echo '<table class="transp" width="100%"><tr>';
			echo '<td><input type="submit" value="'.get_label('Attend').'" class="btn norm" onclick="attend()">';
			echo '<input type="submit" value="'.get_label('Pass').'" class="btn norm" onclick="decline()"></td>';
			echo '</tr></table>';
		}
		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '<table class="bordered" width="100%"><tr>';
		echo '<td align="center" class="dark"><p>' . format_date($this->start_time, $this->timezone, true) . '<br>';
		if ($this->address_url == '')
		{
			echo get_label('At [0]', addr_label($this->address, $this->city, $this->country));
		}
		else
		{
			echo get_label('At [0]', '<a href="' . $this->address_url . '" target="_blank">' . addr_label($this->address, $this->city, $this->country) . '</a>');
		}
		if ($this->notes != '')
		{
			echo '<br>';
			echo $this->notes;
		}
		echo '</p>';
		if ($this->langs != LANG_RUSSIAN)
		{
			echo '<p>' . get_label('Language') . ': ' . get_langs_str($this->langs, ', ') . '</p>';
		}
		if (!is_null($this->currency_pattern) && !is_null($this->fee))
		{
			echo '<p>' . get_label('Admission rate') . ': '.format_currency($this->fee, $this->currency_pattern).'</p>';
		}
		echo '</td></tr></table>';
		
		$attendance = array();
		$coming = 0;
		$min_coming = 0;
		$max_coming = 0;
		$declined = 0;
		$query = new DbQuery(
			'SELECT u.id, nu.name, eu.nickname, eu.coming_odds, eu.people_with_me, u.flags, eu.late, eu.flags, e.tournament_id, tu.flags, e.club_id, cu.flags' . 
			' FROM event_regs eu' . 
			' JOIN events e ON e.id = eu.event_id ' .
			' JOIN users u ON eu.user_id = u.id' . 
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
			' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
			' WHERE e.id = ? ORDER BY eu.coming_odds DESC, eu.late, eu.people_with_me DESC, nu.name', $this->id);
		while ($row = $query->next())
		{
			$odds = $row[3];
			if (is_null($odds))
			{
				$odds = 100;
			}
			$bringing = $row[4];
			if (is_null($bringing))
			{
				$bringing = 0;
			}
			if ($odds >= 100)
			{
				$odds = 100;
				$min_coming += 1 + $bringing;
				$max_coming += 1 + $bringing;
				$coming += 1 + $bringing;
			}
			else if ($odds > 0)
			{
				$max_coming += 1 + $bringing;
				$coming += (1 + $bringing) * $odds / 100;
			}
			else
			{
				++$declined;
			} 
			$row[3] = $odds;
			$row[4] = $bringing;
			$attendance[] = $row;
		}
		
		$event_reg_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
	
		if (BRIEF_ATTENDANCE)
		{
			$found = false;
			$col = 0;
			foreach ($attendance as $a)
			{
				list($user_id, $name, $nickname, $odds, $bringing, $user_flags, $late, $event_reg_flags, $tournament_id, $tournament_reg_flags, $club_id, $club_reg_flags) = $a;
				if ($odds > 0)
				{
					if ($col == 0)
					{
						if (!$found)
						{
							$found = true;
							echo '<table class="bordered" width="100%">';
							echo '<tr class="darker"><td colspan="6" align="left"><b>';
							if ($max_coming == 0)
							{
								echo get_label('No players attended yet.');
							}
							else if ($max_coming != $min_coming)
							{
								echo get_label('Players coming: [0]-[1]. Most likely: [2].', $min_coming, $max_coming, number_format($coming,0));
							}
							else
							{
								echo get_label('Players coming: [0].', $min_coming, $max_coming, number_format($coming,0));
							}
							echo '</b></td>';
						}
						echo '</tr><tr>';
					}
					
					echo '<td width="16.66%" ';
					if ($odds < 50)
					{
						echo 'class="dark"';
					}
					else if ($odds < 100)
					{
						echo 'class="light"';
					}
					else
					{
						echo 'class="lighter"';
					}
					echo 'align="center">';
					$event_reg_pic->
						set($user_id, $nickname, $event_reg_flags, 'e' . $this->id)->
						set($user_id, $name, $tournament_reg_flags, 't' . $tournament_id)->
						set($user_id, $name, $club_reg_flags, 'c' . $club_id)->
						set($user_id, $name, $user_flags);
					$event_reg_pic->show(ICONS_DIR, true, 50);
					if (empty($nickname))
					{
						echo '<br>' . $name;
					}
					else
					{
						echo '<br>' . $nickname;
						if (!empty($name) && $name != $nickname)
						{
							echo ' (' . $name . ')';
						}
					}
					if ($bringing > 0)
					{
						echo ' + ' . $bringing; 
					}
					if ($odds < 100)
					{
						echo ' (' . $odds . '%)';
					}
					if ($late != 0)
					{
						echo '<br>' . event_late_str($late);
					}
					echo '</td>';
					++$col;
					if ($col == 6)
					{
						$col = 0;
					}
				}
			}
			if ($found)
			{
				if ($col > 0)
				{
					echo '<td class="dark" colspan="' . (6 - $col) . '"></td>';
				}
				echo '</tr></table>';
			}
			
			$found = false;
			$col = 0;
			foreach ($attendance as $a)
			{
				list($user_id, $name, $nickname, $odds, $bringing, $user_flags, $late, $event_reg_flags, $tournament_id, $tournament_reg_flags, $club_id, $club_reg_flags) = $a;
				if ($odds <= 0)
				{
					if ($col == 0)
					{
						if (!$found)
						{
							$found = true;
							echo '<table class="bordered" width="100%">';
							echo '<tr class="darker"><td><b>' . get_label('[0] can not come', $declined) . ':</b></td></tr></table><table width="100%" class="bordered"><tr>';
						}
						else
						{
							echo '</tr><tr>';
						}
					}
					
					echo '<td width="16.66%" align="center">';
					$event_reg_pic->
						set($user_id, $nickname, $event_reg_flags, 'e' . $this->id)->
						set($user_id, $name, $tournament_reg_flags, 't' . $tournament_id)->
						set($user_id, $name, $club_reg_flags, 'c' . $club_id)->
						set($user_id, $name, $user_flags);
					$event_reg_pic->show(ICONS_DIR, true, 50);
					echo '<br>' . $name . '</td>';
					++$col;
					if ($col == 6)
					{
						$col = 0;
					}
				}
			}
			if ($found)
			{
				if ($col > 0)
				{
					echo '<td colspan="' . (6 - $col) . '"></td>';
				}
				echo '</tr></table>';
			}
		}
		else
		{
			echo '<table class="bordered" width="100%">';
			echo '<tr class="darker"><td colspan="3" align="center"><b>';
			if ($max_coming == 0)
			{
				echo get_label('No players attended yet.');
			}
			else if ($max_coming != $min_coming)
			{
				echo get_label('Players coming: [0]-[1]. Most likely: [2].', $min_coming, $max_coming, number_format($coming,0));
			}
			else
			{
				echo get_label('Players coming: [0].', $min_coming, $max_coming, number_format($coming,0));
			}
			echo '</b></td></tr>';

			foreach ($attendance as $a)
			{
				list($user_id, $name, $nickname, $odds, $bringing, $user_flags, $late, $event_reg_flags, $tournament_id, $tournament_reg_flags, $club_id, $club_reg_flags) = $a;
				if ($odds > 50)
				{
					echo '<tr class="lighter">';
				}
				else if ($odds > 0)
				{
					echo '<tr class="light">';
				}
				else
				{
					echo '<tr>';
				}
				
				echo '<td width="50">';
				$event_reg_pic->
					set($user_id, $nickname, $event_reg_flags, 'e' . $this->id)->
					set($user_id, $name, $tournament_reg_flags, 't' . $tournament_id)->
					set($user_id, $name, $club_reg_flags, 'c' . $club_id)->
					set($user_id, $name, $user_flags);
				$event_reg_pic->show(ICONS_DIR, true, 50);
				echo '</td><td><a href="user_info.php?id=' . $user_id . '&bck=1">' . cut_long_name($name, 80) . '</a></td><td width="280" align="center"><b>';
				echo event_odds_str($odds, $bringing, $late) . '</b></td></tr>';
			}
			
			echo '</table>';
		}
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
	}
	
	
	protected function js_on_load()
	{
		echo 'mr.showComments("event", ' . $this->id . ", 5)\n";
		if (isset($_REQUEST['attend']))
		{
			echo 'attend();';
		}
		else if (isset($_REQUEST['decline']))
		{
			echo 'decline();';
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		function attend()
		{
			mr.attendEvent(<?php echo $this->id; ?>, "event_info.php?id=<?php echo $this->id; ?>");
		}
		
		function decline()
		{
			mr.passEvent(<?php echo $this->id; ?>, "event_info.php?id=<?php echo $this->id; ?>", "<?php echo get_label('Thank you for letting us know. See you next time.'); ?>");
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
