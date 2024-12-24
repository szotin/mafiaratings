<?php

require_once 'include/evaluator.php';
require_once 'include/video.php';

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

// $sql = 'SELECT p.user_id, (p.rating_before + p.rating_earned) as rating, nu.name, u.flags, c.id, c.name, c.flags
					// FROM players p
					// JOIN users u ON u.id = p.user_id
					// JOIN names nu ON nu.id = u.name_id AND (nu.langs & 1) <> 0
					// LEFT OUTER JOIN clubs c ON c.id = u.club_id 
					// WHERE p.game_id = (
						// SELECT p1.game_id 
						// FROM players p1 
						// WHERE p1.user_id = p.user_id AND p1.game_end_time <= ?
						// ORDER BY p1.game_end_time DESC, p1.game_id DESC
						// LIMIT 1)
					// ORDER BY rating DESC, p.user_id DESC 
					// LIMIT 100';
// $sql1 = 'SELECT p.user_id, (p.rating_before + p.rating_earned) as rating, nu.name, u.flags, c.id, c.name, c.flags ' .
					// 'FROM players p ' .
					// 'JOIN users u ON u.id = p.user_id ' .
					// 'JOIN names nu ON nu.id = u.name_id AND (nu.langs & 1) <> 0 ' .
					// 'LEFT OUTER JOIN clubs c ON c.id = u.club_id ' .
					// 'WHERE p.game_id = ( ' .
						// 'SELECT p1.game_id ' .
						// 'FROM players p1 ' .
						// 'WHERE p1.user_id = p.user_id AND p1.game_end_time <= ? ' .
						// 'ORDER BY p1.game_end_time DESC, p1.game_id DESC ' .
						// 'LIMIT 1) ' .
					// 'ORDER BY rating DESC, p.user_id DESC ' .
					// 'LIMIT 100';
// echo '<pre>' . $sql . '</pre>';
// echo strlen($sql);
// echo '<pre>' . $sql1 . '</pre>';
// echo strlen($sql1);

// phpinfo();

// try
// {
	// $functions = array(
		// new EvFuncRound(), 
		// new EvFuncFloor(), 
		// new EvFuncCeil(), 
		// new EvFuncLog(), 
		// new EvFuncMin(), 
		// new EvFuncMax(), 
		// new EvFuncParam('var'));

	// //$e = new Evaluator('-var(2) / 12.5 * var(1, 1) + 14 * 2^floor (var(1) / 7)');
	// //$e = new Evaluator('var(0) == var(1) && var(2) == var(3) ? var(4) : 1/2', $functions);
	// // $e = new Evaluator('var(log(2.7182818284591))', $functions);
	// // $e = new Evaluator('var(0) ? 0.7 : (var(1) ? 0.5 : 0)', $functions);
	// $e = new Evaluator('var(0) || var(1) || var(2) ? -0.5 : (var(3) ? -0.7 : 0)', $functions);
	
	// $e->var = array(1, 10, 20, 30, 40);
	// echo '<p>.....................................<br>'.$e->evaluate();
	// // -6 / 12.5 * 10 + 14 * 2^round (4 / 7) = -4.8+14*2=9.2
// }
// catch (Exception $e)
// {
	// echo 'Error: ' . $e->getMessage();
// }

// See: https://developers.google.com/youtube/v3/getting-started
// https://www.googleapis.com/youtube/v3/videos?id=eMjNfVBYI6Y&part=contentDetails&key=AIzaSyAUuFoIXqzN4c08t13tPX_vW2OZ6c8SA2U

	print_json(get_youtube_info('eMjNfVBYI6Y'));
?>