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
		
		$countries = array();
		
		$query = new DbQuery('SELECT DISTINCT c.id, c.name_' . $_lang_code . ' FROM country_names n JOIN countries c ON c.id = n.country_id');
		if ($term != '')
		{
			$term = '%' . $term . '%';
			$query->add(' WHERE n.name LIKE ?', $term);
		}
		$query->add(' ORDER BY name_' . $_lang_code . ' LIMIT 10');
		
		while ($row = $query->next())
		{
			$country = new stdClass();
			list ($country->id, $country->label) = $row;
			$this->response[] = $country;
		}
	}

	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>term</dt>
			<dd>Search string. Only the countries with the matching names are returned. For example <a href="country.php?term=сия">/api/control/user.php?term=сия</a> returns only the contries containing "сия" in their name.</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('Country List');

?>