<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/email_template.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	private $filter;

	protected function show_body()
	{
		global $_profile;
		if (isset($_REQUEST['delete']))
		{
			if (isset($_REQUEST['tid']))
			{
				$id = $_REQUEST['tid'];
				$query = new DbQuery('SELECT club_id FROM email_templates WHERE id = ?', $id);
				if ($row = $query->next())
				{
					$club_id = $row[0];
					if ($_profile == NULL || !$_profile->is_club_manager($club_id))
					{
						throw new FatalExc(get_label('No permissions'));
					}
					Db::begin();
					Db::exec(get_label('email template'), 'DELETE FROM email_templates WHERE id = ?', $id);
					db_log('email_template', 'Deleted', NULL, $id, $club_id);
					Db::commit();
				}
			}
			else
			{
				$id = $_REQUEST['delete'];
				list ($tname) = Db::record(get_label('email template'), 'SELECT name FROM email_templates WHERE id = ?', $id);
				echo '<form method="get">';
				echo '<input type="hidden" name="tid" value="' . $id . '">';
				echo '<input type="hidden" name="id" value="' . $this->id . '">';
				echo get_label('Are you sure you want to delete template [0]?', $tname);
				echo '<p><input type="submit" value="' . get_label('Yes') . '" name="delete" class="btn norm"><input type="submit" value="' . get_label('No') . '" class="btn norm"></p></form>';
			}
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52">';
		echo '<a href ="create_email_template.php?club=' . $this->id . '&bck=1" title="' . get_label('New template') . '">';
		echo '<img src="images/create.png" border="0">';
		echo '</a></td><td>'.get_label('Template name').'</td><td width="200">' . get_label('Default for') . '</td></tr>';
		
		$query = new DbQuery('SELECT e.id, e.name, e.default_for FROM email_templates e WHERE e.club_id = ? ORDER BY e.name', $this->id);
		while ($row = $query->next())
		{
			list($id, $name, $default_for) = $row;
			
			echo '<tr><td class="dark">';
			echo '<a href="?id=' . $this->id . '&delete=' . $id . '" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo ' <a href="edit_email_template.php?id=' . $id . '&bck=1" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td>' . $name . '</td>';
			echo '</td><td>';
			switch ($default_for)
			{
				case EMAIL_DEFAULT_FOR_INVITE:
					echo get_label('Inviting');
					break;
				case EMAIL_DEFAULT_FOR_CANCEL:
					echo get_label('Canceling');
					break;
				case EMAIL_DEFAULT_FOR_CHANGE_ADDRESS:
					echo get_label('Changing address');
					break;
				case EMAIL_DEFAULT_FOR_CHANGE_TIME:
					echo get_label('Changing time');
					break;
				default:
					break;
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Emails'), USER_CLUB_PERM_MANAGER);

?>