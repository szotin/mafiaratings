<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $club_id, $user_id, $sound_id = 0)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('sound name')));
		}

		check_name($name, get_label('sound name'));
		
		$query = new DbQuery('SELECT name FROM sounds WHERE name = ?', $name);
		if ($sound_id >= 0)
		{
			$query->add(' AND id <> ?', $sound_id);
		}
		
		if (is_null($club_id))
		{
			if (is_null($user_id))
			{
				$query->add(' AND club_id IS NULL AND user_id IS NULL');
			}
			else
			{
				$query->add(' AND club_id IS NULL AND (user_id IS NULL OR user_id = ?)', $user_id);
			}
		}
		else if (is_null($user_id))
		{
			$query->add(' AND (club_id = ? OR (club_id IS NULL AND user_id IS NULL))', $club_id);
		}
		else
		{
			$query->add(' AND (club_id IS NULL OR club_id = ?) AND (user_id IS NULL OR user_id =?)', $club_id, $user_id);
		}
		
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Sound name'), $name));
		}
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$user_id = (int)get_optional_param('user_id', 0);
		$club_id = (int)get_optional_param('club_id', 0);
		$name = get_required_param('name');
		
		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if ($user_id > 0)
			{
				check_permissions(PERMISSION_OWNER, $user_id);
			}
			else
			{
				$user_id = NULL;
			}
		}
		else if ($user_id > 0)
		{
			$club_id = NULL;
			check_permissions(PERMISSION_OWNER, $user_id);
		}
		else
		{
			$club_id = NULL;
			$user_id = NULL;
			check_permissions(PERMISSION_ADMIN);
		}
		
		if (!isset($_FILES['file']))
		{
			throw new Exc(get_label('Please select a sound to upload.'));
		}
		$file = $_FILES['file'];
		
		$src_filename = $file['name'];
		if ($src_filename == '')
		{
			throw new Exc(get_label('Please select a sound to upload.'));
		}
		
		if ($file['error'])
		{
			throw new Exc(get_label('Unable to upload [0]. File is too big.', $src_filename));
		}

		$tmp_filename = $file['tmp_name'];
		if (!is_uploaded_file($tmp_filename))
		{
			throw new Exc(get_label('Failed to upload [0].', $src_filename));
		}
		
		try
		{
			Db::begin();
			$this->check_name($name, $club_id, $user_id);
			Db::exec(
				get_label('sound'),
				'INSERT INTO sounds (name, club_id, user_id) VALUES (?, ?, ?)', 
				$name, $club_id, $user_id);
			list ($sound_id) = Db::record(get_label('sound'), 'SELECT LAST_INSERT_ID()');
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_SOUND, 'created', $log_details, $sound_id, $club_id);
			
			$sound_dir = '../../' . SOUNDS_DIR;
			if (!is_dir($sound_dir))
			{
				mkdir($sound_dir);
			}
			
			if (!rename($tmp_filename, $sound_dir . $sound_id . '.mp3'))
			{
				throw new Exc(get_label('Unable to move uploaded file [0] to the destination path', $src_filename));
			}
			
			Db::commit();
		}
		catch (Exception $e)
		{
			unlink($tmp_filename);
			throw $e;
		}
		$this->response['sound_id'] = $sound_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create sound.');
		$help->request_param('club_id', 'Club id to create sound for. Club manager permission is required to create a sound in the club.', 'sound is created for a user or globally shared (when user_id is also missing - admin permission is required in this case)');
		$help->request_param('user_id', 'User id to create sound for. The user must be the logged-in user or an admin. If both user_id and club_id are set, the sound is created both for the user and the club. Other club managers can edit/delete this sound in this case.', 'sound is created for a club or globally shared (when club_id is also missing - admin permission is required in this case)');
		$help->request_param('name', 'Name of the sound as it appears in the list of sounds in the game.');
		$help->request_param('file', 'File to be uploaded for multicast multipart/form-data.');

		$help->response_param('sound_id', 'Newly created sound id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$sound_id = (int)get_required_param('sound_id');
		list ($club_id, $user_id, $old_name) = Db::record(get_label('sound'), 'SELECT club_id, user_id, name FROM sounds WHERE id = ?', $sound_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else if (!is_null($user_id))
		{
			check_permissions(PERMISSION_OWNER, $user_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		$name = get_optional_param('name', $old_name);
		
		$tmp_filename = NULL;
		if (isset($_FILES['file']))
		{
			$file = $_FILES['file'];
		
			$src_filename = $file['name'];
			if ($src_filename != '')
			{
				if ($file['error'])
				{
					throw new Exc(get_label('Unable to upload [0]. File is too big.', $src_filename));
				}

				$tmp_filename = $file['tmp_name'];
				if (!is_uploaded_file($tmp_filename))
				{
					throw new Exc(get_label('Failed to upload [0].', $src_filename));
				}
			}
		}
		
		if (is_null($tmp_filename) && $name == $old_name)
		{
			return;
		}
		
		try
		{
			$log_details = new stdClass();
			
			Db::begin();
			if ($name != $old_name)
			{
				Db::exec(get_label('sound'), 'UPDATE sounds SET name = ? WHERE id = ?', $name, $sound_id);
				$log_details->name = $name;
			}
			
			if (!is_null($tmp_filename))
			{
				$filename = '../../' . SOUNDS_DIR . $sound_id . '.mp3';
				unlink($filename);
				if (!rename($tmp_filename, $filename))
				{
					throw new Exc(get_label('Unable to move uploaded file [0] to the destination path', $src_filename));
				}
				$log_details->uploaded = true;
			}
			
			db_log(LOG_OBJECT_SOUND, 'changed', $log_details, $sound_id, $club_id);
			Db::commit();
		}
		catch (Exception $e)
		{
			unlink($tmp_filename);
			throw $e;
		}
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change sound.');
		$help->request_param('sound_id', 'Sound id. Editing sound with id = 1 is not allowed. It will fail because sound 1 is reserved for "no sound".');
		$help->request_param('name', 'Sound name.', 'remains the same.');
		$help->request_param('file', 'File to be uploaded for multicast multipart/form-data.', 'remains the same.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$sound_id = (int)get_required_param('sound_id');
		if ($sound_id <= 3)
		{
			throw new Exc(get_label('Unable to delete one of the default sounds.'));
		}
		
		list ($club_id, $user_id) = Db::record(get_label('sound'), 'SELECT club_id, user_id FROM sounds WHERE id = ?', $sound_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else if (!is_null($user_id))
		{
			check_permissions(PERMISSION_OWNER, $user_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET prompt_sound_id = NULL WHERE prompt_sound_id = ?', $sound_id);
		Db::exec(get_label('club'), 'UPDATE clubs SET end_sound_id = NULL WHERE end_sound_id = ?', $sound_id);
		Db::exec(get_label('user'), 'UPDATE game_settings SET prompt_sound_id = NULL WHERE prompt_sound_id = ?', $sound_id);
		Db::exec(get_label('user'), 'UPDATE game_settings SET end_sound_id = NULL WHERE end_sound_id = ?', $sound_id);
		Db::exec(get_label('sound'), 'DELETE FROM sounds WHERE id = ?', $sound_id);
		db_log(LOG_OBJECT_SOUND, 'deleted', NULL, $sound_id, $club_id);
		Db::commit();
		
		unlink('../../' . SOUNDS_DIR . $sound_id . '.mp3');
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete sound.');
		$help->request_param('sound_id', 'Sound id. This id must be greater than 3. Because ids 1,2, and 3 are reserved for system-wide default sound: 1 - no sound; 2 - prompt sound; 3 - end of speech sound. So deleting a sound with id lower than 3 fails. Though sounds 2 and 3 can be edited.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// set_def_sound
	//-------------------------------------------------------------------------------------------------------
	function set_def_sound_op()
	{
		global $_profile;
		
		$club_id = (int)get_optional_param('club_id', 0);
		$prompt_sound_id = (int)get_optional_param('prompt_sound_id', -1);
		$end_sound_id = (int)get_optional_param('end_sound_id', -1);
		$user_id = (int)get_optional_param('user_id', $_profile->user_id);
		
		check_permissions(PERMISSION_OWNER, $user_id);
		
		if ($club_id <= 0)
		{
			list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM game_settings WHERE user_id = ?', $user_id);
			
			if ($prompt_sound_id >= 0)
			{
				if ($prompt_sound_id == 0)
				{
					$prompt_sound_id = NULL;
				}
				
				if ($end_sound_id >= 0)
				{
					if ($end_sound_id == 0)
					{
						$end_sound_id = NULL;
					}
					Db::begin();
					if ($count > 0)
					{
						Db::exec(get_label('user'), 'UPDATE game_settings SET prompt_sound_id = ?, end_sound_id = ? WHERE user_id = ?', $prompt_sound_id, $end_sound_id, $user_id);
					}
					else
					{
						Db::exec(get_label('user'), 'INSERT INTO game_settings (user_id, l_autosave, g_autosave, flags, prompt_sound_id, end_sound_id) VALUES (?, ?, ?, ?, ?, ?)', $user_id, 10, 60, 0, $prompt_sound_id, $end_sound_id);
					}
					Db::commit();
				}
				else
				{
					Db::begin();
					if ($count > 0)
					{
						Db::exec(get_label('club'), 'UPDATE game_settings SET prompt_sound_id = ? WHERE user_id = ?', $prompt_sound_id, $user_id);
					}
					else
					{
						Db::exec(get_label('user'), 'INSERT INTO game_settings (user_id, l_autosave, g_autosave, flags, prompt_sound_id) VALUES (?, ?, ?, ?, ?)', $user_id, 10, 60, 0, $prompt_sound_id);
					}
					Db::commit();
				}
			}
			else if ($end_sound_id >= 0)
			{
				if ($end_sound_id == 0)
				{
					$end_sound_id = NULL;
				}
				
				Db::begin();
				if ($count > 0)
				{
					Db::exec(get_label('club'), 'UPDATE game_settings SET end_sound_id = ? WHERE user_id = ?', $end_sound_id, $user_id);
				}
				else
				{
					Db::exec(get_label('user'), 'INSERT INTO game_settings (user_id, l_autosave, g_autosave, flags, end_sound_id) VALUES (?, ?, ?, ?, ?)', $user_id, 10, 60, 0, $end_sound_id);
				}
				Db::commit();
			}
		}
		else if ($prompt_sound_id >= 0)
		{
			if ($prompt_sound_id == 0 || $prompt_sound_id == 2) // 2 is default for prompt sound, so there is no need to set it explicitly
			{
				$prompt_sound_id = NULL;
			}
			
			if ($end_sound_id >= 0)
			{
				if ($prompt_sound_id == 0 || $end_sound_id == 3) // 3 is default for end sound, so there is no need to set it explicitly
				{
					$end_sound_id = NULL;
				}
			
				Db::begin();
				Db::exec(get_label('club'), 'UPDATE clubs SET prompt_sound_id = ?, end_sound_id = ? WHERE id = ?', $prompt_sound_id, $end_sound_id, $club_id);
				$log_details = new stdClass();
				$log_details->prompt_sound_id = $prompt_sound_id;
				$log_details->end_sound_id = $end_sound_id;
				db_log(LOG_OBJECT_CLUB, 'changed', $log_details, $club_id, $club_id);
				Db::commit();
			}
			else
			{
				Db::begin();
				Db::exec(get_label('club'), 'UPDATE clubs SET prompt_sound_id = ? WHERE id = ?', $prompt_sound_id, $club_id);
				$log_details = new stdClass();
				$log_details->prompt_sound_id = $prompt_sound_id;
				db_log(LOG_OBJECT_CLUB, 'changed', $log_details, $club_id, $club_id);
				Db::commit();
			}
		}
		else if ($end_sound_id >= 0)
		{
			if ($prompt_sound_id == 0 || $end_sound_id == 3) // 3 is default for end sound, so there is no need to set it explicitly
			{
				$end_sound_id = NULL;
			}
			
			Db::begin();
			Db::exec(get_label('club'), 'UPDATE clubs SET end_sound_id = ? WHERE id = ?', $end_sound_id, $club_id);
			$log_details = new stdClass();
			$log_details->end_sound_id = $end_sound_id;
			db_log(LOG_OBJECT_CLUB, 'changed', $log_details, $club_id, $club_id);
			Db::commit();
		}
	}
	
	function set_def_sound_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Set default game sounds for a club or the user.');
		$help->request_param('club_id', 'Club id to set default game sounds for.', 'the default sound is set for the current user.');
		$help->request_param('prompt_sound_id', 'Sound id for the ten second prompt before the end of the speech.', 'remains the same');
		$help->request_param('end_sound_id', 'Sound id for the the end of the speech.', 'remains the same');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Sound Operations', CURRENT_VERSION);

?>