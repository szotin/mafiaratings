<?php

require_once 'include/view_game.php';
require_once 'include/image.php';
require_once 'include/user.php';

class Page extends PageBase
{
	private $stats;
	private $user_id;
	private $user_name;
	private $user_flags;

	protected function prepare()
	{
		global $_profile;
		if (!isset($_REQUEST['num']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('player')));
		}
		if (!isset($_SESSION['view_game']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('game')));
		}
		$this->stats = new GamePlayerStats($_SESSION['view_game']->gs, $_REQUEST['num']);
		
		$this->_title = $this->stats->get_title();
		$player = $this->stats->gs->players[$this->stats->player_num];
		$this->user_id = $player->id;
		if ($this->user_id > 0)
		{
			list($this->user_name, $this->user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $this->user_id);
			if ($this->user_name != $player->nick)
			{
				$this->_title .= ' (' . $this->user_name . ')';
			}
		}
		else
		{
			$this->user_name = '';
			$this->user_flags = 0;
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr>';
		if ($this->user_id > 0)
		{
			echo '<td width="1"><a href="user_info.php?id=' . $this->user_id . '&bck=1">';
			show_user_pic($this->user_id, $this->user_flags, ICONS_DIR);
			echo '</td>';
		}
		echo '<td valign="top">' . $this->standard_title() . '</td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr></table>';
	}
	
	protected function show_body()
	{
		$this->stats->output();
	}
}

$page = new Page();
$page->run(get_label('Game statistics'), PERM_ALL);

?>