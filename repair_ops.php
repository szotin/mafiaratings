<?php

require_once 'include/session.php';
require_once 'include/image.php';
require_once 'include/game_stats.php';

ob_start();
$result = array();
	
try
{
	initiate_session();
	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	
	if (!$_profile->is_admin())
	{
		throw new Exc(get_label('No permissions'));
	}
	
	if (isset($_REQUEST['lock']))
	{
		lock_site(true);
	}
	else if (isset($_REQUEST['unlock']))
	{
		lock_site(false);
	}
	else if (isset($_REQUEST['addr_icons']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('address', 'SELECT count(*) FROM addresses WHERE (flags & ' . ADDR_ICON_MASK . ') <> 0');
			$result['count'] = $count;
			lock_site(true);
		}
		$c = 0;
		$query = new DbQuery('SELECT id FROM addresses WHERE (flags & ' . ADDR_ICON_MASK . ') <> 0 AND id > ? ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id) = $row;
			$last_id = $id;
			++$c;
			try
			{
				build_pic_tnail(ADDRESS_PICS_DIR, $id);
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
	else if (isset($_REQUEST['user_icons']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('user', 'SELECT count(*) FROM users WHERE (flags & ' . U_ICON_MASK . ') <> 0');
			$result['count'] = $count;
			lock_site(true);
		}
		$c = 0;
		$query = new DbQuery('SELECT id FROM users WHERE (flags & ' . U_ICON_MASK . ') <> 0 AND id > ? ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id) = $row;
			$last_id = $id;
			++$c;
			try
			{
				build_pic_tnail(USER_PICS_DIR, $id);
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
	else if (isset($_REQUEST['club_icons']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('club', 'SELECT count(*) FROM clubs WHERE (flags & ' . CLUB_ICON_MASK . ') <> 0');
			$result['count'] = $count;
			lock_site(true);
		}
		$c = 0;
		$query = new DbQuery('SELECT id FROM clubs WHERE (flags & ' . CLUB_ICON_MASK . ') <> 0 AND id > ? ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id) = $row;
			$last_id = $id;
			++$c;
			try
			{
				build_pic_tnail(CLUB_PICS_DIR, $id);
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
	else if (isset($_REQUEST['album_icons']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('album', 'SELECT count(*) FROM photo_albums WHERE (flags & ' . ALBUM_ICON_MASK . ') <> 0');
			$result['count'] = $count;
			lock_site(true);
		}
		$c = 0;
		$query = new DbQuery('SELECT id FROM photo_albums WHERE (flags & ' . ALBUM_ICON_MASK . ') <> 0 AND id > ? ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id) = $row;
			$last_id = $id;
			++$c;
			try
			{
				build_pic_tnail(ALBUM_PICS_DIR, $id);
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
	else if (isset($_REQUEST['photo_icons']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('photo', 'SELECT count(*) FROM photos');
			$result['count'] = $count;
			lock_site(true);
		}
		$c = 0;
		$query = new DbQuery('SELECT id FROM photos WHERE id > ? ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id) = $row;
			$last_id = $id;
			++$c;
			try
			{
				build_photo_tnail($id);
			}
			catch (Exception $e)
			{
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
	else if (isset($_REQUEST['stats']))
	{
		$last_id = 0;
		if (isset($_REQUEST['last_id']))
		{
			$last_id = $_REQUEST['last_id'];
		}
		else
		{
			list ($count) = Db::record('games', 'SELECT count(*) FROM games WHERE result > 0 AND result < 3');
			$result['count'] = $count;
			lock_site(true);
			
			Db::begin();
			Db::exec(get_label('don'), 'DELETE FROM dons');
			Db::exec(get_label('mafioso'), 'DELETE FROM mafiosos');
			Db::exec(get_label('sheriff'), 'DELETE FROM sheriffs');
			Db::exec(get_label('player'), 'DELETE FROM players');
			Db::exec(get_label('user'), 'UPDATE users SET games_moderated = 0, rating = ' . USER_INITIAL_RATING . ', games = 0, games_won = 0');
			Db::commit();
		}
		$c = 0;
		$query = new DbQuery('SELECT id, log FROM games WHERE id > ? AND result > 0 AND result < 3 ORDER BY id LIMIT 10', $last_id);
		while ($row = $query->next())
		{
			list($id, $log) = $row;
			$last_id = $id;
			++$c;
			try
			{
				Db::begin();
				$gs = new GameState();
				$gs->init_existing($row[0], $row[1]);
				if ($gs->error != NULL)
				{
					echo '<a href="view_game.php?id="' . $id . '" target="_blank">Game ' . $id . '</a> error: ' . $gs->error . '<br>';
				}
				save_game_results($gs);
				Db::commit();
			}
			catch (Exception $e)
			{
				Db::rollback();
				echo $e->getMessage() . '<br>';
			}
		}
		$result['recs'] = $c;
		$result['last_id'] = $last_id;
		if ($c <= 0)
		{
			lock_site(false);
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
	lock_site(false);
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>