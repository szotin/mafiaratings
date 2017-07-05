<?php

require_once 'include/ws.php';

class Rating
{
	public $num;
	public $user_id;
	public $user_name;
	public $user_image;
	public $user_page;
	public $rating;
	public $num_games;
	public $games_won;
	public $is_male;
	
	function __construct($row, $num)
	{
		list($this->user_id, $this->user_name, $user_flags, $this->rating, $this->num_games, $this->games_won) = $row;
		$this->num = $num;
		$base = get_server_url() . '/';
		$this->user_page = $base . 'user_info.php?id=' . $this->user_id;
		$this->is_male = (($user_flags & U_FLAG_MALE) != 0);
		$this->user_image = '';
		if (($user_flags & U_ICON_MASK) != 0)
		{
			$this->user_image = $base . USER_PICS_DIR . TNAILS_DIR . $this->user_id . '.png?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
		}
	}
}

class Ratings
{
	public $count;
	public $ratings;
	
	// note that role is not working. Fix later.
	function __construct($pos, $len, $role)
	{
		global $club_id;
		if ($club_id <= 0)
		{
			$condition = new SQL(' FROM users u WHERE');
		}
		else
		{
			$condition = new SQL(' FROM users u JOIN user_clubs c ON c.user_id = r.user_id WHERE c.club_id = ? AND', $club_id);
		}
		// $condition->add(' r.role = ? AND type_id = ?', $role, $type_id);
		
		list($this->count) = Db::record(get_label('rating'), 'SELECT count(*) ', $condition);
		
		$query = new DbQuery('SELECT u.id, u.name, u.flags, u.rating, u.games, u.games_won', $condition);
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won, u.user_id DESC LIMIT ' . $pos . ',' . $len);

		$num = $pos + 1;
		$this->ratings = array();
		while ($row = $query->next())
		{
			$rating = new Rating($row, $num++);
			$this->ratings[] = $rating;
		}
	}
}

$pos = 0;
if (isset($_REQUEST['pos']))
{
	$pos = $_REQUEST['pos'];
}

$len = 15;
if (isset($_REQUEST['len']))
{
	$len = $_REQUEST['len'];
}

$role = 0;
if (isset($_REQUEST['role']))
{
	$role = $_REQUEST['role'];
	switch($role)
	{
		case 'a';
			$role = 0;
			break;
		case 'r';
			$role = 1;
			break;
		case 'b';
			$role = 2;
			break;
		case 'c';
			$role = 3;
			break;
		case 's';
			$role = 4;
			break;
		case 'm';
			$role = 5;
			break;
		case 'd';
			$role = 6;
			break;
	}
}

try
{
	echo json_encode(new Ratings($pos, $len, $role));
}
catch (Exception $e)
{
	send_error($e);
}

?>