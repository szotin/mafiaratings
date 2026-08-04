<?php

require_once 'include/updater.php';
require_once 'include/game.php';

define('ONE_YEAR', 31536000);
define('ONE_WEEK', 604800);
define('ONE_DAY', 86400);
define('THREE_MONTHS', ONE_DAY * 90);

define('CLUB_IDLE_TIME', 60 * 60 * 24 * 365); // one year

class GarbageCollector extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.incomplete_games
	//-------------------------------------------------------------------------------------------------------
	function incomplete_games_task($items_count)
	{
		if (!isset($this->vars->event_id))
		{
			$this->vars->event_id = 0;
		}
		if (!isset($this->vars->table_num))
		{
			$this->vars->table_num = 0;
		}
		if (!isset($this->vars->game_num))
		{
			$this->vars->game_num = 0;
		}
		
		Db::begin();
		$count = 0;
		$query = new DbQuery(
			'SELECT event_id, table_num, game_num, game'.
			' FROM current_games'.
			' WHERE event_id > ? OR (event_id = ? AND (table_num > ? OR (table_num = ? AND game_num > ?)))'.
			' ORDER BY event_id, table_num, game_num'.
			' LIMIT '.$items_count, 
			$this->vars->event_id, $this->vars->event_id, $this->vars->table_num, $this->vars->table_num, $this->vars->game_num);
		while ($row = $query->next())
		{
			list ($this->vars->event_id, $this->vars->table_num, $this->vars->game_num, $json) = $row;
			$json = json_decode($json);
			
			$start_time = 0;
			if (isset($json->startTime))
			{
				$start_time = $json->startTime;
			}
			
			// We immediatly delete the games that are not started after two weeks. 
			// We also delete all incomplete games that are older than one year. One year is enough to find them and complete.
			if (isset($json->time))
			{
				$timeout = ONE_YEAR;
			}
			else
			{
				$timeout = ONE_WEEK * 2;
			}
			
			if ($start_time + $timeout < time())
			{
				Db::exec('game', 'DELETE FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $this->vars->event_id, $this->vars->table_num, $this->vars->game_num);
				$this->log('Deleted the game '.$this->vars->game_num.' table '.$this->vars->table_num.' from the event '.$this->vars->event_id);
			}
			++$count;
		}
		Db::commit();
		return $count;
	}

	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.no_players_games
	//-------------------------------------------------------------------------------------------------------
	function no_players_games_task($items_count)
	{
		if (!isset($this->vars->game_id))
		{
			$this->vars->game_id = 0;
		}
		
		Db::begin();
		$count = 0;
		$query = new DbQuery(
			'SELECT g.id, COUNT(p.user_id) as uid'.
			' FROM games g'.
			' LEFT OUTER JOIN players p ON p.game_id = g.id'.
			' WHERE g.end_time < UNIX_TIMESTAMP() - '.(ONE_WEEK * 2).' AND g.id > ?'.
			' GROUP BY g.id'.
			' HAVING uid = 0'.
			' ORDER BY g.id'.
			' LIMIT '.$items_count, 
			$this->vars->game_id);
		while ($row = $query->next())
		{
			list ($this->vars->game_id, $c) = $row;
			Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id = ?', $this->vars->game_id);
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $this->vars->game_id);
			Db::exec(get_label('game'), 'DELETE FROM mr_bonus_stats WHERE game_id = ?', $this->vars->game_id);
			Db::exec('game', 'DELETE FROM games WHERE id = ?', $this->vars->game_id);
			$this->log('Deleted the game #'.$this->vars->game_id);
			++$count;
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.emails
	//-------------------------------------------------------------------------------------------------------
	function emails_task($items_count)
	{
		if (!isset($this->vars->user_id))
		{
			$this->vars->user_id = 0;
		}
		if (!isset($this->vars->code))
		{
			$this->vars->code = '';
		}
		
		Db::begin();
		$count = 0;
		$query = new DbQuery(
			'SELECT user_id, code'.
			' FROM emails'.
			' WHERE send_time < UNIX_TIMESTAMP() - '.ONE_YEAR.' AND (user_id > ? OR (user_id = ? AND code > ?))'.
			' ORDER BY user_id, code'.
			' LIMIT '.$items_count, 
			$this->vars->user_id, $this->vars->user_id, $this->vars->code);
		while ($row = $query->next())
		{
			list ($this->vars->user_id, $this->vars->code) = $row;
			Db::exec('email', 'DELETE FROM emails WHERE user_id = ? AND code = ?', $this->vars->user_id, $this->vars->code);
			++$count;
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.events
	//-------------------------------------------------------------------------------------------------------
	function events_task($items_count)
	{
		if (!isset($this->vars->event_id))
		{
			$this->vars->event_id = 0;
		}
		
		$event_id = 0;
		$count = 0;
		Db::begin();
		$query = new DbQuery(
			'SELECT e.id, COUNT(g.id) as gid, COUNT(p.id) as pid, COUNT(v.id) as vid, COUNT(pl.user_id) as plid'.
			' FROM events e'.
			' LEFT OUTER JOIN games g ON g.event_id = e.id'.
			' LEFT OUTER JOIN photo_albums p ON p.event_id = e.id'.
			' LEFT OUTER JOIN videos v ON v.event_id = e.id'.
			' LEFT OUTER JOIN event_places pl ON pl.event_id = e.id'.
			' WHERE e.start_time + e.duration < UNIX_TIMESTAMP() - '.ONE_YEAR.' AND e.id > ? AND e.tournament_id IS NULL'.
			' GROUP BY e.id'.
			' HAVING gid = 0 AND pid = 0 AND vid = 0 AND plid = 0'.
			' ORDER BY e.id'.
			' LIMIT '.$items_count, 
			$this->vars->event_id);
		while ($row = $query->next())
		{
			list ($event_id) = $row;
			Db::exec('game', 'DELETE FROM current_games WHERE event_id = ?', $event_id);
			Db::exec('comment', 'DELETE FROM event_comments WHERE event_id = ?', $event_id);
			Db::exec('points', 'DELETE FROM event_extra_points WHERE event_id = ?', $event_id);
			Db::exec('mailing', 'DELETE FROM event_mailings WHERE event_id = ?', $event_id);
			Db::exec('registration', 'DELETE FROM event_incomers WHERE event_id = ?', $event_id); 
			Db::exec('registration', 'DELETE FROM event_regs WHERE event_id = ?', $event_id); 
			Db::exec('score', 'DELETE FROM event_scores_cache WHERE event_id = ?', $event_id);
			Db::exec('event', 'DELETE FROM events WHERE id = ?', $event_id);
			++$count;
		}
		Db::commit();
		$this->vars->event_id = $event_id;
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.clubs
	//-------------------------------------------------------------------------------------------------------
	function clubs_task($items_count)
	{
		if (!isset($this->vars->club_id))
		{
			$this->vars->club_id = 0;
		}
		
		$club_id = 0;
		$count = 0;
		Db::begin();
		
		$query = new DbQuery(
			'SELECT c.id, c.name FROM clubs c'.
			' WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0'.
			' AND c.id > ?'.
			' AND c.activated < UNIX_TIMESTAMP() - '.CLUB_IDLE_TIME.
			' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.club_id = c.id AND g.start_time > UNIX_TIMESTAMP() - '.CLUB_IDLE_TIME.')'.
			' ORDER BY c.id'.
			' LIMIT '.$items_count, $club_id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name) = $row;
			Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_CLOSED . ' WHERE id = ?', $club_id);
			$this->log('Club "' . $club_name . '" is closed due to more than one year of inactivity (id='.$club_id.')');
			
			// Send notifications
			$query1 = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM club_regs cr'.
				' JOIN users u ON u.id = cr.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE cr.club_id = ? AND (cr.flags & '.USER_PERM_MANAGER.') <> 0 AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0', $club_id);
			while ($row1 = $query1->next())
			{
				list($user_id, $user_name, $user_email, $user_lang) = $row1;
				if (!is_valid_lang($user_lang))
				{
					$user_lang = get_lang($league_langs);
					if (!is_valid_lang($user_lang))
					{
						$user_lang = LANG_RUSSIAN;
					}
				}
				list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email/club_closed.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_id' => new Tag($user_id),
					'user_name' => new Tag($user_name),
					'club_id' => new Tag($club_id),
					'club_name' => new Tag($club_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
			}
			++$count;
		}
		Db::commit();
		$this->vars->club_id = $club_id;
		return $count;
	}
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.log_files
	//-------------------------------------------------------------------------------------------------------
	function log_files_task($items_count)
	{
		if (!isset($this->vars->last_file))
		{
			$this->vars->last_file = '';
		}

		$logs_dir = 'logs';
		$two_weeks_ago = time() - ONE_WEEK * 2;

		if (!is_dir($logs_dir))
		{
			return 0;
		}

		$files = scandir($logs_dir, SCANDIR_SORT_ASCENDING);
		if ($files === false)
		{
			return 0;
		}

		$count = 0;
		foreach ($files as $file)
		{
			if ($file <= $this->vars->last_file)
			{
				continue;
			}

			if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{2}/', $file))
			{
				continue;
			}

			$year  = (int)substr($file, 0, 4);
			$month = (int)substr($file, 5, 2);
			$day   = (int)substr($file, 8, 2);
			$hour  = (int)substr($file, 11, 2);
			$file_time = mktime($hour, 0, 0, $month, $day, $year);

			if ($file_time >= $two_weeks_ago)
			{
				break; // Files are sorted by date, remaining files are even newer
			}

			unlink($logs_dir . '/' . $file);
			$this->log('Deleted log file: ' . $file);
			$this->vars->last_file = $file;
			++$count;

			if ($count >= $items_count)
			{
				break;
			}
		}

		return $count;
	}

	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.log_backup
	//
	// Once every three months: dump the whole `log` table to an SQL script, archive that
	// script together with the updater's own log files into a timestamped zip in bak/, clear
	// the backed-up log rows, and email the admin. The dump is spread across batches/runs
	// (Updater contract); the last batch clears the table, builds the zip and sends the email.
	//-------------------------------------------------------------------------------------------------------
	function log_backup_task($items_count)
	{
		if (!isset($this->vars->lb_phase))
		{
			// First call of this task execution: run only if a backup is due.
			if (!$this->_log_backup_due())
			{
				return 0;
			}
			$this->_log_backup_init();
		}

		if ($this->vars->lb_phase == 'dump')
		{
			$count = $this->_log_backup_dump_batch($items_count);
			if ($count > 0)
			{
				return $count; // more rows to dump on the next batch
			}
			// Dump complete — clear the table, build the archive and notify the admin.
			$this->_log_backup_finalize();
			$this->vars->lb_phase = 'done';
		}
		return 0;
	}

	// Creates $dir (recursively) if it does not exist yet.
	private function _ensure_dir($dir)
	{
		if (!is_dir($dir))
		{
			mkdir($dir, 0755, true);
		}
	}

	// A backup is due if the newest logs_backup_*.zip in bak/ is older than three months
	// (or there is none yet).
	private function _log_backup_due()
	{
		$bak_dir = 'bak';
		$newest = 0;
		if (is_dir($bak_dir))
		{
			foreach (scandir($bak_dir) as $f)
			{
				if (preg_match('/^logs_backup_.*\.zip$/', $f))
				{
					$mtime = filemtime($bak_dir . '/' . $f);
					if ($mtime > $newest)
					{
						$newest = $mtime;
					}
				}
			}
		}
		return (time() - $newest) >= THREE_MONTHS;
	}

	// Sets up the backup: names the files, records the column list and the highest id to back
	// up (rows logged during a multi-run backup keep a higher id and are left for next time),
	// and writes the SQL header + CREATE TABLE so the script restores standalone.
	private function _log_backup_init()
	{
		$bak_dir = 'bak';
		$this->_ensure_dir($bak_dir);

		$base = 'logs_backup_' . date('Y-m-d_H-i-s', time());
		$this->vars->lb_base = $base;
		$this->vars->lb_sql  = $bak_dir . '/' . $base . '.sql';
		$this->vars->lb_zip  = $bak_dir . '/' . $base . '.zip';

		$cols = array();
		$id_index = 0;
		$i = 0;
		$query = new DbQuery('SHOW COLUMNS FROM log');
		while ($row = $query->next())
		{
			if ($row[0] == 'id')
			{
				$id_index = $i;
			}
			$cols[] = $row[0];
			++$i;
		}
		$this->vars->lb_cols = $cols;
		$this->vars->lb_id_index = $id_index;

		list ($max_id) = Db::record('log', 'SELECT IFNULL(MAX(id), 0) FROM log');
		$this->vars->lb_max_id = (int)$max_id;
		$this->vars->lb_last_id = 0;
		$this->vars->lb_rows = 0;

		list (, $create) = Db::record('log', 'SHOW CREATE TABLE log');
		$create = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create, 1);
		$header =
			"-- Backup of the `log` table generated by GarbageCollector on " . date('Y-m-d H:i:s', time()) . "\n" .
			"-- Contains every record with id <= " . $this->vars->lb_max_id . ".\n\n" .
			"SET NAMES utf8;\n\n" .
			$create . ";\n\n";
		file_put_contents($this->vars->lb_sql, $header);

		$this->vars->lb_phase = 'dump';
		$this->log('Started log backup ' . $base . ' (up to id ' . $this->vars->lb_max_id . ').');
	}

	// Appends up to $items_count rows (as one INSERT statement) to the SQL script and returns
	// how many were written. Returns 0 when the snapshot has been fully dumped.
	private function _log_backup_dump_batch($items_count)
	{
		$col_list = '';
		foreach ($this->vars->lb_cols as $c)
		{
			$col_list .= ($col_list === '' ? '' : ', ') . '`' . $c . '`';
		}

		$query = new DbQuery(
			'SELECT ' . $col_list . ' FROM log WHERE id > ? AND id <= ? ORDER BY id LIMIT ' . (int)$items_count,
			$this->vars->lb_last_id, $this->vars->lb_max_id);

		$values = '';
		$count = 0;
		while ($row = $query->next())
		{
			$tuple = '';
			foreach ($row as $v)
			{
				$tuple .= ($tuple === '' ? '' : ',') . (is_null($v) ? 'NULL' : "'" . Db::_escape($v) . "'");
			}
			$values .= ($values === '' ? '' : ",\n") . '(' . $tuple . ')';
			$this->vars->lb_last_id = (int)$row[$this->vars->lb_id_index];
			++$count;
		}

		if ($count > 0)
		{
			$this->_ensure_dir(dirname($this->vars->lb_sql));
			file_put_contents($this->vars->lb_sql,
				'INSERT INTO `log` (' . $col_list . ') VALUES' . "\n" . $values . ";\n", FILE_APPEND);
			$this->vars->lb_rows += $count;
		}
		return $count;
	}

	// Clears the backed-up rows, zips the SQL script together with the updater's own log files,
	// deletes those log files, and emails the admin.
	private function _log_backup_finalize()
	{
		$logs_dir = 'logs';

		// The updater's own log files are named <Class>.<task>.log. Exclude the date-stamped
		// filelog files (YYYY_MM_DD_HH.log) that log_files_task rotates separately.
		$log_files = array();
		if (is_dir($logs_dir))
		{
			foreach (scandir($logs_dir) as $f)
			{
				if (substr($f, -4) === '.log' && !preg_match('/^\d{4}_\d{2}_\d{2}_\d{2}/', $f))
				{
					$log_files[] = $f;
				}
			}
		}

		// This task's own log file (GarbageCollector.log_backup.log) is held open by the
		// updater. Close it so it can be archived and removed; log() reopens a fresh one.
		$this->closeLogFile();

		$this->_ensure_dir(dirname($this->vars->lb_zip));
		$zip = new ZipArchive();
		if ($zip->open($this->vars->lb_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
		{
			$this->error('Could not create log backup archive ' . $this->vars->lb_zip);
			return;
		}
		$zip->addFile($this->vars->lb_sql, basename($this->vars->lb_sql));
		foreach ($log_files as $f)
		{
			$zip->addFile($logs_dir . '/' . $f, $f);
		}
		$ok = $zip->close();

		if (!$ok || !file_exists($this->vars->lb_zip))
		{
			$this->error('Failed to write log backup archive ' . $this->vars->lb_zip);
			return;
		}

		// Archive is on disk — safe to clear the backed-up rows and remove the source files.
		Db::exec('log', 'DELETE FROM log WHERE id <= ?', $this->vars->lb_max_id);
		unlink($this->vars->lb_sql);
		foreach ($log_files as $f)
		{
			unlink($logs_dir . '/' . $f);
		}

		$this->_log_backup_email(basename($this->vars->lb_zip), $this->vars->lb_rows, count($log_files));
		$this->log('Log backup complete: ' . $this->vars->lb_zip . ' (' . $this->vars->lb_rows . ' log rows, ' . count($log_files) . ' updater log files).');
	}

	// Emails the main admin that the backup is ready. English only, sent to the dedicated
	// admin address, so no unsubscribe handling.
	private function _log_backup_email($zip_name, $row_count, $log_count)
	{
		$query = new DbQuery('SELECT email FROM users WHERE id = ' . MAIN_ADMIN_ID);
		if ($row = $query->next())
		{
			list ($admin_email) = $row;
			if (trim($admin_email) == '')
			{
				return;
			}
			$body =
				'<p>Hi, Admin!</p>' .
				'<p>The quarterly log backup is ready.</p>' .
				'<p>Archive: <b>' . $zip_name . '</b> (in the <b>bak</b> directory).<br>' .
				'Backed up ' . $row_count . ' log record(s) and ' . $log_count . ' updater log file(s).<br>' .
				'The <b>log</b> table has been cleared.</p>';
			$text_body =
				"Hi, Admin!\r\n\r\n" .
				"The quarterly log backup is ready.\r\n" .
				"Archive: " . $zip_name . " (in the bak directory).\r\n" .
				"Backed up " . $row_count . " log record(s) and " . $log_count . " updater log file(s).\r\n" .
				"The log table has been cleared.\r\n";
			try
			{
				send_email($admin_email, $body, $text_body, 'Log backup ready');
			}
			catch (\Throwable $e)
			{
				$this->error('Failed to email admin about log backup: ' . $e->getMessage());
			}
		}
	}
}

$updater = new GarbageCollector();
$updater->run();

?>