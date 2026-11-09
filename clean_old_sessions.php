<?php

// A one-off. Delete it from the server and from the repository once it has been run.
//
// The site used to keep its session files in a subdirectory of the host's own session folder,
// with the garbage collector switched off - so nothing ever deleted one of them. The folder
// filled the partition it sits on, and people trying to log in were met with "No space left on
// device" instead. include/session.php now keeps sessions beside the site, on this account's
// own disk, and sweeps them as it goes; but the old folder is on a path no FTP account can
// reach, and every file left in it belongs to a session that has already been abandoned. This
// empties it from inside the server, where the path is reachable and the files belong to the
// user this code runs as.
//
// It deletes for twenty seconds and then reloads itself, carrying the running total, until the
// folder is empty - one click does the whole job, however many files there are, and the page
// says how far it has got while it works. The first version deleted a fixed twenty thousand
// per click and reported only what that one click had seen, which in a folder of millions is
// the same two numbers every time and looks like a page doing nothing at all.
//
// There is a second way out below: where the server lets a page start a program, find(1) does
// the same work in the background, far faster than PHP can unlink one file at a time.
//
// The words on the page are in English and not in get_label: the script is here for one
// afternoon, and labels for it would be written into every language file and taken back out
// again when it goes.

// Before anything else: the host's own session folder, read while session.save_path is still
// what the server set it to. The page below starts a session, and starting one points that
// setting at the new folder.
$host_session_path = ini_get('session.save_path');

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

// How long one request spends deleting before it hands over to the next. Short enough that no
// web server gives up on it, long enough that the reloads are not the bulk of the work.
define('TIME_BUDGET', 20.0);

// How far to count before saying "more than this". Counting a folder is as slow as emptying it
// when it holds millions of files, and the exact number is of no interest.
define('COUNT_LIMIT', 100000);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $host_session_path;

		check_permissions(PERMISSION_ADMIN);
		@set_time_limit(90);

		$dir = rtrim($host_session_path, '/\\') . '/mafiaratings';

		// The only folder this page is allowed to touch is the one the server named plus our
		// own subdirectory of it. Said out loud so that a session.save_path that is empty or
		// unexpected ends the page instead of pointing the deleting somewhere else.
		if (strpos($host_session_path, '/') !== 0 || strpos($host_session_path, '/sessions') === false)
		{
			echo '<p>The session path the server sets is <b>' . htmlspecialchars($host_session_path) .
				'</b>, which is not the host session folder this page expects. Nothing has been done.</p>';
			return;
		}

		echo '<p><b>' . htmlspecialchars($dir) . '</b></p>';
		if (!is_dir($dir))
		{
			echo '<p>There is no such folder. Nothing to do, and this script can be deleted ' .
				'from the server and from the repository.</p>';
			return;
		}

		$this->show_facts($dir);

		$total = 0;
		if (isset($_REQUEST['total']))
		{
			$total = (int)$_REQUEST['total'];
		}

		if (isset($_REQUEST['background']))
		{
			$this->run_in_background($dir);
			return;
		}

		if (!isset($_REQUEST['commit']))
		{
			$this->show_count($dir);
			echo '<p><a href="clean_old_sessions.php?commit"><b>Delete them</b></a> - this page ' .
				'then deletes for ' . (int)TIME_BUDGET . ' seconds at a time and comes back by ' .
				'itself until the folder is empty. Leave it open; closing it stops the work after ' .
				'the stretch it is in.</p>';
			echo '<p>Or <a href="clean_old_sessions.php?background">hand the whole folder to ' .
				'find(1)</a>, which empties it in the background and far faster, where the server ' .
				'allows a page to start a program.</p>';
			return;
		}

		// Delete what we can reach in the time allowed. The folder is read and unlinked in the
		// same pass: a huge folder cannot be held in memory as a list, and an entry the reading
		// skips because of the deleting going on underneath it is picked up by the next pass.
		$dh = opendir($dir);
		if ($dh === false)
		{
			echo '<p>The folder is there but cannot be read.</p>';
			return;
		}

		$started = microtime(true);
		$deleted = 0;
		$failed = 0;
		$reason = '';
		$seen_any = false;
		while (false !== ($name = readdir($dh)))
		{
			if (strpos($name, 'sess_') !== 0)
			{
				continue;
			}
			$seen_any = true;
			if (@unlink($dir . '/' . $name))
			{
				++$deleted;
			}
			else
			{
				++$failed;
				if ($reason == '')
				{
					$e = error_get_last();
					$reason = isset($e['message']) ? $e['message'] : 'no reason given';
				}
			}
			if (microtime(true) - $started > TIME_BUDGET)
			{
				break;
			}
		}
		closedir($dh);

		$total += $deleted;
		$seconds = round(microtime(true) - $started, 1);

		echo '<p>Deleted in this stretch: ' . $deleted . ' in ' . $seconds . ' seconds.<br>';
		echo 'Deleted since you started: <b>' . $total . '</b></p>';

		if ($failed > 0)
		{
			echo '<p>Could not be deleted: ' . $failed . ' - ' . htmlspecialchars($reason) . '</p>';
		}

		// Nothing of the sort left in the folder - and nothing failing either, or we would be
		// reloading for ever over the same files.
		if (!$seen_any || ($deleted == 0 && $failed > 0))
		{
			if (!$seen_any)
			{
				echo '<p><b>The folder is empty of sessions.</b> Delete this script from the ' .
					'server and from the repository.</p>';
			}
			else
			{
				echo '<p>Nothing here can be deleted by this page. Try ' .
					'<a href="clean_old_sessions.php?background">find(1)</a>, or the host.</p>';
			}
			return;
		}

		$next = 'clean_old_sessions.php?commit&total=' . $total;
		echo '<p>Going again. <a href="' . $next . '">Now</a>, if the page does not do it itself.</p>';
		echo '<script>setTimeout(function() { location.href = ' . json_encode($next) . '; }, 300);</script>';
	}

	// What the folder is and whether it is ours to empty, said once at the top: where a page
	// cannot delete a file, this is where the answer is.
	private function show_facts($dir)
	{
		$owner = @fileowner($dir);
		$me = function_exists('posix_getuid') ? @posix_getuid() : @getmyuid();
		$perms = @fileperms($dir);
		$free = @disk_free_space($dir);

		echo '<p>';
		if ($perms !== false)
		{
			echo 'Permissions: ' . substr(sprintf('%o', $perms), -4) . '. ';
		}
		if ($owner !== false)
		{
			echo 'Owned by uid ' . $owner . ($me !== false ? ', this page runs as uid ' . $me : '') . '. ';
		}
		echo 'Writable by us: ' . (is_writable($dir) ? 'yes' : '<b>no</b>') . '. ';
		if ($free !== false)
		{
			echo 'Free on that partition: ' . number_format($free / 1024 / 1024, 1) . ' MB.';
		}
		echo '</p>';
	}

	// How many are left, counted only as far as it is worth counting.
	private function show_count($dir)
	{
		$dh = opendir($dir);
		if ($dh === false)
		{
			echo '<p>The folder is there but cannot be read.</p>';
			return;
		}
		$found = 0;
		$cut_short = false;
		$started = microtime(true);
		while ($found < COUNT_LIMIT && false !== ($name = readdir($dh)))
		{
			if (strpos($name, 'sess_') === 0)
			{
				++$found;
			}
			if (microtime(true) - $started > TIME_BUDGET)
			{
				$cut_short = true;
				break;
			}
		}
		closedir($dh);

		// Said as "more than" wherever the counting stopped before the folder did, so that a
		// number which is only as far as we got is not read as the whole of it.
		if ($found >= COUNT_LIMIT || $cut_short)
		{
			echo '<p>Session files: <b>more than ' . number_format($found) . '</b>.</p>';
		}
		else
		{
			echo '<p>Session files: <b>' . number_format($found) . '</b>.</p>';
		}
	}

	// The same work handed to the system, which does it in one program instead of a million
	// calls from PHP. -maxdepth 1 and the name pattern keep it inside the folder and off
	// anything that is not a session file, and nohup lets it outlive the request that started
	// it, since it takes longer than any page may.
	private function run_in_background($dir)
	{
		if (!function_exists('exec'))
		{
			echo '<p>This server does not let a page start a program. Use ' .
				'<a href="clean_old_sessions.php?commit">the other way</a>.</p>';
			return;
		}

		$cmd = 'nohup find ' . escapeshellarg($dir) .
			' -maxdepth 1 -type f -name \'sess_*\' -delete > /dev/null 2>&1 &';
		$output = array();
		$code = 0;
		@exec($cmd, $output, $code);

		echo '<p>Started: <code>' . htmlspecialchars($cmd) . '</code><br>';
		echo 'It returned ' . (int)$code . '.</p>';
		if (count($output) > 0)
		{
			echo '<p>' . htmlspecialchars(implode("\n", $output)) . '</p>';
		}
		echo '<p>It goes on after this page is gone. ' .
			'<a href="clean_old_sessions.php">Reload</a> in a minute or two to see the count fall; ' .
			'when it reaches nothing, delete this script from the server and from the repository.</p>';
	}
}

$page = new Page();
$page->run('Clean the old session folder');

?>
