<?php

require_once 'include/updater.php';
require_once 'include/address.php';

class SetGeoCoordinates extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// UpdateGames.cities
	//-------------------------------------------------------------------------------------------------------
	function cities_task($items_count)
	{
		$last_city_id = 0;
		if (isset($this->vars->city))
		{
			$last_city_id = (int)$this->vars->city;
		}

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
			$last_city_id);
		while ($row = $query->next())
		{
			++$count;
			list($city_id, $city_name, $country_name) = $row;
			$coord = get_address_coordinates($city_name. ', ' . $country_name);
			Db::exec('city', 'UPDATE cities SET lat = ?, lon = ? WHERE id = ?', $coord->lat, $coord->lng, $city_id);
			$this->vars->city = (int)$city_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// UpdateGames.addresses
	//-------------------------------------------------------------------------------------------------------
	function addresses_task($items_count)
	{
		$last_address_id = 0;
		if (isset($this->vars->address))
		{
			$last_address_id = (int)$this->vars->address;
		}

		$count = 0;
		$query = new DbQuery('SELECT id FROM addresses WHERE id > ? ORDER BY id LIMIT ' . $items_count, $last_address_id);
		while ($row = $query->next())
		{
			++$count;
			list($addr_id) = $row;
			load_map_info($addr_id);
			$this->vars->address = (int)$addr_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}
}

$updater = new SetGeoCoordinates();
$updater->run();

?>