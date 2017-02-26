<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

class Country
{
	public $id;
	public $label;
	
	function __construct($row)
	{
		list ($this->id, $this->label) = $row;
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
	
	$countries = array();
	
	$query = new DbQuery('SELECT id, name_' . $_lang_code . ' FROM countries');
	if ($term != '')
	{
		$term = '%' . $term . '%';
		$query->add(' WHERE name_en LIKE ? OR name_ru LIKE ?', $term, $term);
	}
	$query->add(' ORDER BY name_' . $_lang_code . ' LIMIT 10');
	
	while ($row = $query->next())
	{
		$countries[] = new Country($row);
	}
	echo json_encode($countries);
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo json_encode(array('error' => $e->getMessage()));
}

?>