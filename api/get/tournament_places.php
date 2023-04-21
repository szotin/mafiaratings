<?php

require_once '../../include/api.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_profile;
		
		$condition = new SQL(' WHERE TRUE');

        $tournament_id = (int)get_required_param('tournament_id');
		if ($tournament_id > 0)
		{
			$condition->add(' AND t.tournament_id = ?', $tournament_id);
		}
		
		list($count) = Db::record('tournament_places', 'SELECT count(*) FROM tournament_places t', $condition);
        $this->response['count'] = (int)$count;

		$players = array();
        $query = new DbQuery(
            'SELECT t.user_id, t.place, t.games_count FROM tournament_places t', $condition);
        $query->add(' ORDER BY t.place');

        $this->show_query($query);
        while ($row = $query->next())
        {
            $place = new stdClass();
            list ($place->user_id, $place->place, $place->games_count) = $row;
            $place->user_id = (int)$place->user_id;
            $place->place = (int)$place->place;
            $place->games_count = (int)$place->games_count;
          
            $places[] = $place;
        }
		
		$this->response['places'] = $places;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="tournament_places.php?tournament_id=11">' . PRODUCT_URL . '/api/get/tournament_places.php?tournament_id=11</a> returns the tournament with id 11.', '-');
		

        $help->response_param('count', 'Total number of players in tournament.');
		$param = $help->response_param('players', 'The array of players. Every player has the following attributes: player_id, place, games_count');
			
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Tournament places', CURRENT_VERSION);

?>