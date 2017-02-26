<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

define('DEFINED_NO', 0);
define('DEFINED_CLUB', 1);
define('DEFINED_CITY', 2);
define('DEFINED_REGION', 3);
define('DEFINED_COUNTRY', 4);

class CCC
{
	public $label;
	public $code;
	public $category;
	
	function __construct($label, $code, $category)
	{
		$this->label = $label;
		$this->code = $code;
		$this->category = $category;
	}
}

class CCCContext
{
	public $club_id;
	public $city_id;
	public $region_id;
	public $country_id;
	public $defined;
	
	function __construct($term)
	{
		global $_profile;
		
		$this->defined = DEFINED_NO;
		$this->club_id = -1;
		if ($_profile != NULL)
		{
			$this->city_id = $_profile->city_id;
			$this->country_id = $_profile->country_id;
		}
		else
		{
			$loc = UserLocation::get();
			$this->city_id = $loc->get_city_id();
			$this->country_id = $loc->get_country_id();
		}
		
		if ($term == '')
		{
			return;
		}
		
		$query = new DbQuery('SELECT c.id, i.id, i.country_id, i.near_id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE name = ?', $term);
		if ($row = $query->next())
		{
			list($this->club_id, $this->city_id, $this->country_id, $this->region_id) = $row;
			$this->defined = DEFINED_CLUB;
			return;
		}

		$query = new DbQuery('SELECT id, country_id, near_id FROM cities WHERE name_en = ? OR name_ru = ?', $term, $term);
		if ($row = $query->next())
		{
			list($this->city_id, $this->country_id, $this->region_id) = $row;
			if ($this->region_id == NULL)
			{
				$this->defined = DEFINED_REGION;
				$this->region_id = $this->city_id;
			}
			else
			{
				$this->defined = DEFINED_CITY;
			}
			return;
		}
		
		$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? OR name_ru = ?', $term, $term);
		if ($row = $query->next())
		{
			list($this->country_id) = $row;
			$this->defined = DEFINED_COUNTRY;
		}
	}
}

try
{
	initiate_session();
	
	$flags = 0;
	if (isset($_REQUEST['flags']))
	{
		$flags = $_REQUEST['flags'];
	}

	$term = '';
	if (isset($_REQUEST['term']))
	{
		$term = $_REQUEST['term'];
		if ($term == get_label('All') || $term == get_label('My clubs'))
		{
			$term = '';
		}
	}
	
	$context = new CCCContext($term);
	$ccc = array();
	
	if (($flags & CCCF_NO_ALL) == 0)
	{
		if (($flags & CCCF_NO_CLUBS) == 0)
		{
			$ccc[] = new CCC(get_label('All'), CCCF_CLUB . '-1', '');
		}
		else if (($flags & CCCF_NO_CITIES) == 0)
		{
			$ccc[] = new CCC(get_label('All'), CCCF_CITY . '-1', '');
		}
		else if (($flags & CCCF_NO_COUNTRIES) == 0)
		{
			$ccc[] = new CCC(get_label('All'), CCCF_COUNTRY . '-1', '');
		}
	}
	
	if ($_profile != NULL && ($flags & (CCCF_NO_MY_CLUBS | CCCF_NO_CLUBS)) == 0)
	{
		$ccc[] = new CCC(get_label('My clubs'), CCCF_CLUB . '0', '');
	}
	
	if ($term != '')
	{
		$term = '%' . $term . '%';
	}
	
	// countries
	if (($flags & CCCF_NO_COUNTRIES) == 0)
	{
		$category = get_label('Countries');
		
		$query = new DbQuery('SELECT o.id, o.name_' . $_lang_code . ' FROM countries o');
		if ($term != '' && $context->defined == DEFINED_NO)
		{
			$query->add(' WHERE o.name_ru LIKE(?) OR o.name_en LIKE(?)', $term, $term);
		}
		$query->add(' ORDER BY (SELECT count(*) FROM clubs c JOIN cities t ON t.id = c.city_id WHERE t.country_id = o.id) DESC, o.name_' . $_lang_code . ' LIMIT 8');
		
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			$ccc[] = new CCC($name, CCCF_COUNTRY . $id, $category);
		}
	}
	
	// cities
	if (($flags & CCCF_NO_CITIES) == 0)
	{
		$category = get_label('Cities');
		
		$query = new DbQuery('SELECT i.id, i.name_' . $_lang_code . ' FROM cities i');
		switch ($context->defined)
		{
		case DEFINED_NO:
			if ($term != '')
			{
				$query->add(' WHERE i.name_ru LIKE(?) OR i.name_en LIKE(?)', $term, $term);
			}
			break;
		case DEFINED_CLUB:
		case DEFINED_CITY:
			$query->add(' WHERE (i.id = ? OR i.id = ?)', $context->city_id, $context->region_id);
			break;
		case DEFINED_REGION:
			$query->add(' WHERE (i.id = ? OR i.near_id = ?)', $context->city_id, $context->city_id);
			break;
		case DEFINED_COUNTRY:
			$query->add(' WHERE i.near_id IS NULL AND i.country_id = ?', $context->country_id);
			break;
		}
		$query->add(' ORDER BY (SELECT count(*) FROM clubs WHERE city_id = i.id) DESC, i.name_' . $_lang_code . ' LIMIT 8');
		
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			$ccc[] = new CCC($name, CCCF_CITY . $id, $category);
		}
	}
	
	// clubs
	if (($flags & CCCF_NO_CLUBS) == 0)
	{
		$category = get_label('Clubs');
		$query = new DbQuery('SELECT c.id, c.name FROM clubs c');
		switch ($context->defined)
		{
		case DEFINED_NO:
			if ($term != '')
			{
				$query->add(' WHERE c.name LIKE(?)', $term);
			}
			break;
		case DEFINED_CITY:
			$query->add(' WHERE c.city_id = ?', $context->city_id);
			break;
		case DEFINED_CLUB:
		case DEFINED_REGION:
			$query->add(' WHERE c.city_id IN (SELECT id FROM cities WHERE id = ? OR near_id = ?)', $context->city_id, $context->city_id);
			break;
		case DEFINED_COUNTRY:
			$query->add(' WHERE c.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $context->country_id);
			break;
		}
		$query->add(' ORDER BY (SELECT count(*) FROM games WHERE club_id = c.id) DESC, c.name LIMIT 8');
		
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			$ccc[] = new CCC($name, CCCF_CLUB . $id, $category);
		}
	}
	
	echo json_encode($ccc);
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo json_encode(array('error' => $e->getMessage()));
}

?>