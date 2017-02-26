<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';

define('MANAGER_COLUMNS', 5);
define('MANAGER_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);

class Page extends ClubPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('About [0]', $this->name);
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
	
		$is_manager = false;
		if ($_profile != NULL)
		{
			$is_manager = $_profile->is_manager($this->id);
		}
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$terminated_count = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? GROUP BY result', $this->id);
		while ($row = $query->next())
		{
			switch ($row[0])
			{
				case 0:
					$playing_count = $row[1];
					break;
				case 1:
					$civils_win_count = $row[1];
					break;
				case 2:
					$mafia_win_count = $row[1];
					break;
				case 3:
					$terminated_count = $row[1];
					break;
			}
		}
		$games_count = $civils_win_count + $mafia_win_count + $playing_count + $terminated_count;
		
		if ($games_count > 0)
		{
			echo '<table width="100%"><tr><td valign="top">';
		}
	
		// info
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><a href="club_adverts.php?bck=1&id=' . $this->id . '"><b>' . get_label('Information') . '</b></a></td></tr>';
		echo '<tr><td width="200">'.get_label('City').':</td><td>' . $this->city . ', ' . $this->country	 . '</td></tr>';
		if ($this->url != '')
		{
			echo '<tr><td>'.get_label('Web site').':</td><td><a href="' . $this->url . '" target = "blank">' . $this->url . '</a></td></tr>';
		}
		if ($this->email != '')
		{
			echo '<tr><td>'.get_label('Contact email').':</td><td><a href="mailto:' . $this->email . '">' . $this->email . '</a></td></tr>';
		}
		if ($this->phone != '')
		{
			echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>' . $this->phone . '</td></tr>';
		}
		
		echo '<tr><td>'.get_label('Languages').':</td><td>' . get_langs_str($this->langs, ', ') . '</td></tr>';
		if ($this->price != '')
		{
			echo '<tr><td>'.get_label('Admission rate').':</td><td>' . $this->price . '</td></tr>';
		}
		
		$first_note = true;
		$query = new DbQuery('SELECT id, name, value FROM club_info WHERE club_id = ? ORDER BY pos', $this->id);
		while ($row = $query->next())
		{
			list($note_id, $note_name, $note_value) = $row;
			$note_name = htmlspecialchars($note_name);
			echo '<tr><td valign="top">';
			if ($is_manager)
			{
				echo '<table class="transp" width="100%"><tr><td class="dark">';
				echo '<button class="icon" onclick="mr.editNote(' . $note_id . ')" title="' . get_label('Edit note [0]', $note_name) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.deleteNote(' . $note_id . ', \'' . get_label('Are you sure you want to delete the note?') . '\')" title="' . get_label('Delete note [0]', $note_name) . '"><img src="images/delete.png" border="0"></button>';
				if (!$first_note)
				{
					echo '<button class="icon" onclick="mr.upNote(' . $note_id . ')" title="' . get_label('Move note [0] up', $note_name) . '"><img src="images/up.png" border="0"></button>';
				}
				$first_note = false;
				echo '</td></tr><tr><td>' . $note_name . ':</td></tr></table>';
			}
			else
			{
				echo $note_name . ':';
			}
			echo '</td><td>' . $note_value . '</td></tr>';
		}
		if ($is_manager)
		{
			echo '<tr><td valign="top">';
			echo '<table class="transp" width="100%"><tr><td class="dark">';
			echo '<button class="icon" onclick="mr.createNote(' . $this->id . ')" title="' . get_label('Create [0]', get_label('note')) . '"><img src="images/create.png" border="0"></button>';
			echo '</td></tr></table></td><td>&nbsp;</td></tr>';
			echo '<script src="ckeditor/ckeditor.js"></script>';
		}
		echo '</table>';
		
		$query = new DbQuery('SELECT u.id, u.name, u.flags FROM user_clubs c JOIN users u ON u.id = c.user_id WHERE c.club_id = ? AND (c.flags & ' . UC_PERM_MANAGER . ') <> 0', $this->id);
		if ($row = $query->next())
		{
			$managers_count = 0;
			$columns_count = 0;
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="' . MANAGER_COLUMNS . '"><b>' . get_label('Managers') . '</b></td></tr>';
			do
			{
				list($manager_id, $manager_name, $manager_flags) = $row;
				if ($columns_count == 0)
				{
					if ($managers_count > 0)
					{
						echo '</tr>';
					}
					echo '<tr>';
				}
				echo '<td width="' . MANAGER_COLUMN_WIDTH . '%" align="center">';
				echo '<a href="user_info.php?bck=1&id=' . $manager_id . '">' . $manager_name . '<br>';
				show_user_pic($manager_id, $manager_flags, ICONS_DIR);
				echo '</a></td>';
				
				++$columns_count;
				++$managers_count;
				if ($columns_count >= MANAGER_COLUMNS)
				{
					$columns_count = 0;
				}
				
			} while ($row = $query->next());
			
			if ($columns_count > 0)
			{
				echo '<td colspan="' . (MANAGER_COLUMNS - $columns_count) . '">&nbsp;</td>';
			}
			echo '</tr></table></p>';
		}
	
		if ($games_count > 0)
		{
			// stats
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="2"><a href="club_games.php?bck=1&id=' . $this->id . '"><b>' . get_label('Stats') . '</b></a></td></tr>';
			echo '<tr><td width="200">'.get_label('Games played').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
			if ($civils_win_count + $mafia_win_count > 0)
			{
				echo '<tr><td>'.get_label('Mafia won in').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
				echo '<tr><td>'.get_label('Civilians won in').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($terminated_count > 0)
			{
				echo '<tr><td>'.get_label('Games terminated').':</td><td>' . $terminated_count . ' (' . number_format($terminated_count*100.0/($terminated_count + $civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($playing_count > 0)
			{
				echo '<tr><td>'.get_label('Still playing').'</td><td>' . $playing_count . '</td></tr>';
			}
			
			if ($civils_win_count + $mafia_win_count > 0)
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.club_id = ?', $this->id);
				echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
				
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ?', $this->id);
				echo '<tr><td>'.get_label('People moderated').':</td><td>' . $counter . '</td></tr>';
				
				list ($a_game, $s_game, $l_game) = Db::record(
					get_label('game'),
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE result > 0 AND result < 3 AND club_id = ?', 
					$this->id);
				echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
			}
			echo '</table></p>';
			
			// ratings
			echo '</td><td width="240" valign="top">';
			
			$query = new DbQuery(
				'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags ' . 
					'FROM users u, club_ratings r WHERE u.id = r.user_id AND r.club_id = ?' .
					' AND r.role = 0 AND type_id = 1 ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT 10',
				$this->id);
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><a href="club_ratings.php?bck=1&id=' . $this->id . '"><b>' . get_label('Best players') . '</b></a></td></tr>';
			$number = 1;
			while ($row = $query->next())
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

				echo '<td width="20" align="center">' . $number . '</td>';
				echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
				show_user_pic($id, $flags, ICONS_DIR, 50, 50);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . $rating . '</td>';
				echo '</tr>';
				
				++$number;
			}
			echo '</table>';
			
			echo '</td></tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Club'), PERM_ALL);

?>