<?php
// if(isset($GLOBALS))
// {
	// echo '<pre>';
	// print_r($GLOBALS);
	// echo '</pre>';
// }

//$parsed_url = parse_url('https://www.youtube.com/?v=1nPMX_AXCNU&feature=youtu.be&t=4297');
//$parsed_url = parse_url('https://www.youtube.com?v=1nPMX_AXCNU&feature=youtu.be&t=4297');
//$parsed_url = parse_url('https://youtu.be/1nPMX_AXCNU');
//$parsed_url = parse_url('https://youtu.be/1nPMX_AXCNU?t=4297');
// $parsed_url = parse_url('1nPMX_AXCNU');
// echo '<pre>';
// print_r($parsed_url);
// echo '</pre>';

// if (isset($parsed_url['query']))
// {
	// parse_str($parsed_url['query'], $query);
	// echo '<br>------<pre>';
	// print_r($query);
	// echo '</pre>';
// }

// if (isset($parsed_url['path']))
// {
	// echo '<br>------<pre>';
	// print_r(basename($parsed_url['path']));
	// echo '</pre>';
// }

$sql = 'SELECT p.user_id, (p.rating_before + p.rating_earned) as rating, u.name, u.flags, c.id, c.name, c.flags
					FROM players p
					JOIN users u ON u.id = p.user_id
					LEFT OUTER JOIN clubs c ON c.id = u.club_id 
					WHERE p.game_id = (
						SELECT p1.game_id 
						FROM players p1 
						WHERE p1.user_id = p.user_id AND p1.game_end_time <= ?
						ORDER BY p1.game_end_time DESC, p1.game_id DESC
						LIMIT 1)
					ORDER BY rating DESC, p.user_id DESC 
					LIMIT 100';
$sql1 = 'SELECT p.user_id, (p.rating_before + p.rating_earned) as rating, u.name, u.flags, c.id, c.name, c.flags ' .
					'FROM players p ' .
					'JOIN users u ON u.id = p.user_id ' .
					'LEFT OUTER JOIN clubs c ON c.id = u.club_id ' .
					'WHERE p.game_id = ( ' .
						'SELECT p1.game_id ' .
						'FROM players p1 ' .
						'WHERE p1.user_id = p.user_id AND p1.game_end_time <= ? ' .
						'ORDER BY p1.game_end_time DESC, p1.game_id DESC ' .
						'LIMIT 1) ' .
					'ORDER BY rating DESC, p.user_id DESC ' .
					'LIMIT 100';
echo '<pre>' . $sql . '</pre>';
echo strlen($sql);
echo '<pre>' . $sql1 . '</pre>';
echo strlen($sql1);

?>