<?php

require_once 'include/db.php';
require_once 'include/constants.php';

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

list($games_count) = Db::record(null, 'SELECT count(*) FROM games WHERE event_id = ? AND (flags & ' . (GAME_FLAG_RATING | GAME_FLAG_CANCELED) . ') = ' . GAME_FLAG_RATING, $id);

$params = $_GET;
$query_str = http_build_query($params);
$dest = ($games_count > 0 ? 'event_standings' : 'event_info') . '.php';
header('Location: ' . $dest . ($query_str ? '?' . $query_str : ''));
exit;

?>
