<?php

require_once 'include/page_base.php';
require_once 'include/timer.php';

class Page extends PageBase
{
	private $club_id;
	private $event_id;
	private $error;
	
	protected function add_headers()
	{
		global $_lang_code;
		echo '<link rel="stylesheet" href="game.css" type="text/css" media="screen" />';
		echo '<script src="js/game.js"></script>';
		echo '<script src="js/game-ui.js"></script>';
		echo '<script src="js/game-' . $_lang_code . '.js"></script>';
	}
	
	// no title to save space for the game
	protected function show_title()
	{
	}
	
	protected function prepare()
	{
		global $_profile;
		
		if (isset($_REQUEST['reset']))
		{
			unset($_SESSION['game' . $_REQUEST['reset']]);
			throw new RedirectExc('game.php');
		}
		
		$this->error = false;
		try
		{
			$this->event_id = -1;
			$this->club_id = -1;
			if (isset($_REQUEST['event']))
			{
				$this->event_id = $_REQUEST['event'];
				list($this->club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $this->event_id);
			}
			else if (isset($_REQUEST['club']))
			{
				$this->club_id = $_REQUEST['club'];
				if (!isset($_profile->clubs[$this->club_id]))
				{
					throw new FatalExc(get_label('Unknown [0]', get_label('club')));
				}
			}
		}
		catch (Exception $e)
		{
			$this->error = true;
			throw $e;
		}
	}
	
	protected function show_body()
	{
		echo '<div id="game-area"></div>';
	}
	
	protected function js_on_load()
	{
		if (!$this->error)
		{
			echo 'mafia.ui.start(mafia.ui.FLAG_ONLINE';
			if (is_mobile())
			{
				echo '| mafia.ui.FLAG_MOBILE';
			}
			echo ', ' . $this->club_id . ', ' . $this->event_id . ');';
		}
	}
}

$page = new Page();
$page->run(get_label('The game'), PERM_ALL);

?>
