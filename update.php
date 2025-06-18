<?php

require_once 'include/updater.php';
require_once 'include/address.php';

class UpdateGames extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	protected function initState()
	{
		$this->state->city_id = 0;
		$this->setTask('cities');
	}
	
	private function cities($items_count)
	{
		$count = 0;
		$query = new DbQuery(
			'SELECT ct.id, nct.name, ncr.name'.
			' FROM cities ct'.
			' JOIN countries cr ON cr.id = ct.country_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & 1) <> 0' .
			' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & 1) <> 0' .
			' WHERE ct.id > ?'.
			' ORDER BY ct.id'.
			' LIMIT ' . $items_count, 
			$this->state->city_id);
		while ($row = $query->next())
		{
			++$count;
			list($city_id, $city_name, $country_name) = $row;
			$coord = get_address_coordinates($city_name. ', ' . $country_name);
			Db::exec('city', 'UPDATE cities SET lat = ?, lon = ? WHERE id = ?', $coord->lat, $coord->lng, $city_id);
			$this->state->city_id = $city_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($count <= 0)
		{
			$this->setTask('addresses');
			$this->state->address_id = 0;
			unset($this->state->city_id);
		}
		return $count;
	}
	
	private function addresses($items_count)
	{
		$count = 0;
		$query = new DbQuery('SELECT id FROM addresses WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->state->address_id);
		while ($row = $query->next())
		{
			++$count;
			list($addr_id) = $row;
			load_map_info($addr_id);
			$this->state->address_id = $addr_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($count <= 0)
		{
			$this->setTask(END_RUNNING);
		}
		return $count;
	}
	
	protected function update($items_count)
	{
		switch ($this->state->task)
		{
		case 'cities':
			return $this->cities($items_count);
		case 'addresses':
			return $this->addresses($items_count);
		}
		$this->setTask(END_RUNNING);
		return 0;
	}
	
	// protected function initState()
	// {
		// if (!isset($_REQUEST['series_id']))
		// {
			// throw new Exc('Unknown series');
		// }
		// $this->state->tournament_id = 0;
		// $this->state->series_id = (int)$_REQUEST['series_id'];
	// }
	
	// private function processTournament($id, $name, $time, $stars, $lat, $lon, $city_name)
	// {
		// $user_list = '';
		// $delimiter = '';
		// $count = 0;
		// $query = new DbQuery('SELECT user_id FROM tournament_places WHERE tournament_id = ?', $id);
		// while ($row = $query->next())
		// {
			// list ($user_id) = $row;
			// $user_list .= $delimiter . $user_id;
			// $delimiter = ',';
			// ++$count;
		// }
		
		// if (empty($user_list))
		// {
			// return;
		// }
		
		// $td = 0;
		// $tdc = 0;
		// $rating = 0;
		// $rating20 = 0;
		// $count20 = 0;
		// $query = new DbQuery(
			// 'SELECT u.rating, c.lat, c.lon'.
			// ' FROM users u'.
			// ' JOIN cities c ON c.id = u.city_id' .
			// ' WHERE u.id IN (' . $user_list . ')'.
			// ' ORDER BY u.rating DESC');
		// while ($row = $query->next())
		// {
			// list($user_rating, $user_lat, $user_lon) = $row;
			// $distance = get_distance1($lat, $lon, $user_lat, $user_lon, 'mi');
			
			// $td += $distance;
			// $tdc += min(log(1 + $distance/600, 2), 3);
			
			// $rating += max($user_rating, 0);
			// if ($count20 < 20)
			// {
				// $rating20 += max($user_rating, 0);
				// ++$count20;
			// }
		// }
		// $this->log(date('m/d/o', $time) . "\t" . $city_name . "\t" . $name . "\t" . $stars . "\t" . $count . "\t" . $td . "\t" . $tdc . "\t" . $rating . "\t" . $rating20);
	// }

	// protected function update($items_count)
	// {
		// $count = 0;
		// $query = new DbQuery(
			// 'SELECT t.id, t.name, t.start_time, s.stars, ct.lat, ct.lon, nct.name'.
			// ' FROM series_tournaments s'.
			// ' JOIN tournaments t ON t.id = s.tournament_id'.
			// ' JOIN addresses a ON a.id = t.address_id' .
			// ' JOIN cities ct ON ct.id = a.city_id' .
			// ' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & 1) <> 0' .
			// ' WHERE t.id > ? AND s.series_id = ?'.
			// ' LIMIT ' . $items_count, 
			// $this->state->tournament_id, $this->state->series_id);
		// while ($row = $query->next())
		// {
			// ++$count;
			// list($id, $name, $time, $stars, $lat, $lon, $city_name) = $row;
			// $this->processTournament($id, $name, $time, $stars, $lat, $lon, $city_name);
			// $this->state->tournament_id = $id;
		// }
		// if ($count <= 0)
		// {
			// $this->setTask(END_RUNNING);
		// }
		// return $count;
	// }
}

$updater = new UpdateGames();
$updater->run();

?>