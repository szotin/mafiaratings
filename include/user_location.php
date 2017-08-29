<?php

require_once 'include/session.php';
require_once 'include/location.php';

class UserLocation
{
	private $city_id;
	private $city_evidence;
	private $region_id;
	private $country_id;
	private $country_evidence;
	
	function __construct()
	{
		$location = Location::get();
		
		$query = new DbQuery('SELECT i.id, i.area_id, o.id FROM cities i JOIN countries o ON i.country_id = o.id WHERE i.name_en = ? AND o.code = ?', $location->city, $location->country_code);
		if ($row = $query->next())
		{
			list($this->city_id, $this->region_id, $this->country_id) = $row;
			if ($this->region_id == NULL)
			{
				$this->region_id = $this->city_id;
			}
			$this->city_evidence = true;
			$this->country_evidence = true;
			return;
		}
		
		$query = new DbQuery('SELECT id FROM countries WHERE code = ?', $location->country_code);
		if ($row = $query->next())
		{
			list($this->country_id) = $row;
			$this->country_evidence = true;
			$this->city_evidence = false;
			
			$query = new DbQuery(
				'SELECT i.id, i.area_id, count(*) as club_count FROM clubs c' .
					' JOIN cities i ON c.city_id = i.id' .
					' WHERE i.country_id = ?' .
					' GROUP BY i.id ORDER BY club_count DESC LIMIT 1',
				$this->country_id);
			if ($row = $query->next())
			{
				list($this->city_id, $this->region_id, $club_count) = $row;
				if ($this->region_id == NULL)
				{
					$this->region_id = $this->city_id;
				}
			}
			else
			{
				$this->city_id = 0;
				$this->region_id = 0;
			}
			return;
		}
		
		$this->country_evidence = false;
		$this->city_evidence = false;
		$query = new DbQuery(
			'SELECT i.id, i.area_id, i.country_id, count(*) as club_count FROM clubs c' .
			' JOIN cities i ON c.city_id = i.id' .
			' GROUP BY i.id ORDER BY club_count DESC LIMIT 1');
		if ($row = $query->next())
		{
			list($this->city_id, $this->region_id, $this->country_id, $club_count) = $row;
			if ($this->region_id == NULL)
			{
				$this->region_id = $this->city_id;
			}
		}
		else
		{
			$this->city_id = 0;
			$this->region_id = 0;
			$this->country_id = 0;
		}
	}
	
	static function get()
	{
		if (!isset($_SESSION['user_location']))
		{
			$_SESSION['user_location'] = new UserLocation();
		}
		return $_SESSION['user_location'];
	}
	
	function get_city_id($evident = false)
	{
		if ($evident && !$this->city_evidence)
		{
			return 0;
		}
		return $this->city_id;
	}
	
	function get_region_id($evident = false)
	{
		if ($evident && !$this->city_evidence)
		{
			return 0;
		}
		return $this->region_id;
	}
	
	function get_country_id($evident = false)
	{
		if ($evident && !$this->country_evidence)
		{
			return 0;
		}
		return $this->country_id;
	}
	
	function is_city_evidence() { return $this->city_evidence; }
	function is_region_evidence() { return $this->city_evidence; }
	function is_country_evidence() { return $this->country_evidence; }
}

?>