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
// It works in batches and says what is left, because the folder holds a file for every visit
// the site has ever had and one pass would not finish. Open it with ?commit to delete; without
// it, it only counts. The words on the page are in English and not in get_label: the script is
// here for one afternoon, and labels for it would be written into every language file and taken
// back out again when it goes.

// Before anything else: the host's own session folder, read while session.save_path is still
// what the server set it to. The page below starts a session, and starting one points that
// setting at the new folder.
$host_session_path = ini_get('session.save_path');

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

// How many to unlink in one go, and how many to look at. Both are here so that the page
// answers rather than times out; whatever is left is picked up by running it again.
define('DELETE_LIMIT', 20000);
define('SCAN_LIMIT', 200000);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $host_session_path;

		check_permissions(PERMISSION_ADMIN);

		$commit = isset($_REQUEST['commit']);
		$dir = rtrim($host_session_path, '/\\') . '/mafiaratings';

		echo '<p><b>' . htmlspecialchars($dir) . '</b></p>';
		if (!is_dir($dir))
		{
			echo '<p>There is no such folder. Nothing to do, and this script can be deleted.</p>';
			return;
		}

		$dh = opendir($dir);
		if ($dh === false)
		{
			echo '<p>The folder is there but cannot be read.</p>';
			return;
		}

		$found = 0;
		$deleted = 0;
		$failed = 0;
		$scanned = 0;
		while ($scanned < SCAN_LIMIT && false !== ($name = readdir($dh)))
		{
			++$scanned;
			if (strpos($name, 'sess_') !== 0)
			{
				continue;
			}
			++$found;
			if (!$commit || $deleted + $failed >= DELETE_LIMIT)
			{
				continue;
			}
			if (@unlink($dir . '/' . $name))
			{
				++$deleted;
			}
			else
			{
				++$failed;
			}
		}
		$more = ($scanned >= SCAN_LIMIT);
		closedir($dh);

		echo '<p>Session files seen: ' . $found . ($more ? ' (and more beyond the ' . SCAN_LIMIT . ' entries looked at)' : '') . '</p>';
		echo '<p>Deleted: ' . $deleted . '</p>';
		if ($failed > 0)
		{
			echo '<p>Could not be deleted: ' . $failed . ' - these are not ours to remove.</p>';
		}

		if (!$commit)
		{
			echo '<p>Nothing was deleted. <a href="clean_old_sessions.php?commit">Delete them</a>.</p>';
		}
		else if ($more || $found > $deleted + $failed)
		{
			echo '<p><a href="clean_old_sessions.php?commit">Run it again</a> for the next ' . DELETE_LIMIT . '.</p>';
		}
		else
		{
			echo '<p>The folder is empty of sessions. Delete this script from the server and from the repository.</p>';
		}
	}
}

$page = new Page();
$page->run('Clean the old session folder');

?>
