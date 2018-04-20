<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

class City
{
	public $id;
	public $label;
	public $country;
	
	function __construct($row)
	{
		list ($this->id, $this->label, $this->country) = $row;
	}
}

try
{
	initiate_session();
	
	$term = '';
	if (isset($_REQUEST['term']))
	{
		$term = $_REQUEST['term'];
	}
	
	$country_id = -1;
	if (isset($_REQUEST['cid']))
	{
		$country_id = $_REQUEST['cid'];
	}
	else if (isset($_REQUEST['cname']))
	{
		$cname = $_REQUEST['cname'];
		$query = new DbQuery('SELECT country_id FROM country_names WHERE name = ? ORDER BY country_id LIMIT 1', $cname);
		if ($row = $query->next())
		{
			list($country_id) = $row;
		}
	}
	
	$cities = array();

	$delim = ' WHERE ';
	$query = new DbQuery('SELECT DISTINCT i.id, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ' FROM city_names n JOIN cities i ON i.id = n.city_id JOIN countries o ON o.id = i.country_id');
	if ($country_id > 0)
	{
		$query->add($delim . 'i.country_id = ?', $country_id);
		$delim = ' AND ';
	}
	if ($term != '')
	{
		$term = '%' . $term . '%';
		$query->add($delim . 'n.name LIKE(?)', $term);
	}
	$query->add(' ORDER BY i.name_' . $_lang_code . ' LIMIT 10');
	
	while ($row = $query->next())
	{
		$cities[] = new City($row);
	}
	echo json_encode($cities);
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo json_encode(array('error' => $e->getMessage()));
}

?>