<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

class ApiPage extends ControlApiPageBase
{
	protected function prepare_response()
	{
		global $_lang_code;
		
		$term = '';
		if (isset($_REQUEST['term']))
		{
			$term = $_REQUEST['term'];
		}
		
		$country_id = -1;
		if (isset($_REQUEST['country_id']))
		{
			$country_id = $_REQUEST['country_id'];
		}
		else if (isset($_REQUEST['country_name']))
		{
			$country_name = $_REQUEST['country_name'];
			$query = new DbQuery('SELECT country_id FROM country_names WHERE name = ? ORDER BY country_id LIMIT 1', $country_name);
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
			$city = new stdClass();
			list ($city->id, $city->label, $city->country) = $row;
			$this->response[] = $city;
		}
	}

	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>term</dt>
			<dd>Search string. Only the cities with the matching names are returned. For example: <a href="city.php?term=ro">/api/control/city.php?term=ro</a> returns the cites with "ro" in their names.</dd>
		<dt>country_id</dt>
			<dd>Country id. Only the cities from this country are returned. For example: <a href="city.php?country_id=1">/api/control/city.php?country_id=1</a> returns only Canadian cites.</dd>
		<dt>country_name</dt>
			<dd>Country name. Only the cities from this country are returned. For example: <a href="city.php?country_name=россия">/api/control/city.php?country_name=россия</a> returns only Russian cites.</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('City List');

?>