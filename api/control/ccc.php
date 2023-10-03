<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('COUNT_LIMIT', 8);

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
		
		$query = new DbQuery('SELECT c.id, i.id, i.country_id, i.area_id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE c.name = ?', $term);
		if ($row = $query->next())
		{
			list($this->club_id, $this->city_id, $this->country_id, $this->region_id) = $row;
			$this->defined = DEFINED_CLUB;
			return;
		}

		$query = new DbQuery('SELECT c.id, c.country_id, c.area_id FROM cities c JOIN names n ON n.id = c.name_id WHERE n.name = ? LIMIT ' . COUNT_LIMIT, $term);
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
		
		$query = new DbQuery('SELECT c.id FROM countries c JOIN names cn ON cn.id = c.name_id WHERE name = ? LIMIT ' . COUNT_LIMIT, $term);
		if ($row = $query->next())
		{
			list($this->country_id) = $row;
			$this->defined = DEFINED_COUNTRY;
		}
	}
}

class ApiPage extends ControlApiPageBase
{
	protected function prepare_response()
	{
		global $_profile, $_lang;
		
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
		
		if (($flags & CCCF_NO_ALL) == 0)
		{
			if (($flags & CCCF_NO_CLUBS) == 0)
			{
				$this->response[] = new CCC(get_label('All'), CCCF_CLUB . '-1', '');
			}
			else if (($flags & CCCF_NO_CITIES) == 0)
			{
				$this->response[] = new CCC(get_label('All'), CCCF_CITY . '-1', '');
			}
			else if (($flags & CCCF_NO_COUNTRIES) == 0)
			{
				$this->response[] = new CCC(get_label('All'), CCCF_COUNTRY . '-1', '');
			}
		}
		
		if ($_profile != NULL && ($flags & (CCCF_NO_MY_CLUBS | CCCF_NO_CLUBS)) == 0)
		{
			$this->response[] = new CCC(get_label('My clubs'), CCCF_CLUB . '0', '');
		}
		
		if ($term != '')
		{
			$term = '%' . $term . '%';
		}
		
		// countries
		if (($flags & CCCF_NO_COUNTRIES) == 0)
		{
			$category = get_label('Countries');
			
			$query = new DbQuery('SELECT o.id, n.name FROM countries o JOIN names n ON n.id = o.name_id');
			switch ($context->defined)
			{
			case DEFINED_CLUB:
				$query->add(' WHERE o.id = (SELECT i.country_id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE c.id = ?) AND (n.langs & '.$_lang.') <> 0', $context->club_id);
				break;
			case DEFINED_CITY:
			case DEFINED_REGION:
				$query->add(' WHERE o.id = (SELECT i.country_id FROM cities i WHERE i.id = ?) AND (n.langs & '.$_lang.') <> 0', $context->city_id);
				break;
			case DEFINED_COUNTRY:
				$query->add(' WHERE o.id = ? AND (n.langs & '.$_lang.') <> 0', $context->country_id);
				break;
			default:
				if ($term != '')
				{
					$query->add(' WHERE n.name LIKE(?)', $term, $term);
				}
				else
				{
					$query->add(' WHERE (n.langs & '.$_lang.') <> 0');
				}
				break;
			}
			$query->add(' ORDER BY (SELECT count(*) FROM clubs c JOIN cities t ON t.id = c.city_id WHERE t.country_id = o.id) DESC, n.name LIMIT ' . COUNT_LIMIT);
			
			while ($row = $query->next())
			{
				list ($id, $name) = $row;
				$this->response[] = new CCC($name, CCCF_COUNTRY . $id, $category);
			}
		}
		
		// cities
		if (($flags & CCCF_NO_CITIES) == 0)
		{
			$category = get_label('Cities');
			
			$query = new DbQuery('SELECT i.id, n.name FROM cities i JOIN names n ON n.id = i.name_id');
			switch ($context->defined)
			{
			case DEFINED_CLUB:
				$query->add(' WHERE i.id = (SELECT city_id FROM clubs WHERE id = ?) AND (n.langs & '.$_lang.') <> 0', $context->club_id);
				break;
			case DEFINED_CITY:
				$query->add(' WHERE (i.id = ? OR i.id = ?) AND (n.langs & '.$_lang.') <> 0', $context->city_id, $context->region_id);
				break;
			case DEFINED_REGION:
				$query->add(' WHERE (i.id = ? OR i.area_id = ?) AND (n.langs & '.$_lang.') <> 0', $context->city_id, $context->city_id);
				break;
			case DEFINED_COUNTRY:
				$query->add(' WHERE i.area_id = i.id AND i.country_id = ? AND (n.langs & '.$_lang.') <> 0', $context->country_id);
				break;
			default:
				if ($term != '')
				{
					$query->add(' WHERE n.name LIKE(?)', $term, $term);
				}
				else
				{
					$query->add(' WHERE (n.langs & '.$_lang.') <> 0');
				}
				break;
			}
			$query->add(' ORDER BY (SELECT count(*) FROM clubs WHERE city_id = i.id) DESC, n.name LIMIT ' . COUNT_LIMIT);
			
			while ($row = $query->next())
			{
				list ($id, $name) = $row;
				$this->response[] = new CCC($name, CCCF_CITY . $id, $category);
			}
		}
		
		// clubs
		if (($flags & CCCF_NO_CLUBS) == 0)
		{
			$category = get_label('Clubs');
			$query = new DbQuery('SELECT c.id, c.name FROM clubs c');
			switch ($context->defined)
			{
			case DEFINED_CITY:
				$query->add(' WHERE c.city_id = ?', $context->city_id);
				break;
			case DEFINED_CLUB:
			case DEFINED_REGION:
				$query->add(' WHERE c.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $context->city_id, $context->city_id);
				break;
			case DEFINED_COUNTRY:
				$query->add(' WHERE c.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $context->country_id);
				break;
			default:
				if ($term != '')
				{
					$query->add(' WHERE c.name LIKE(?)', $term);
				}
				break;
			}
			$query->add(' ORDER BY (SELECT count(*) FROM games WHERE club_id = c.id) DESC, c.name LIMIT ' . COUNT_LIMIT);
			
			while ($row = $query->next())
			{
				list ($id, $name) = $row;
				$this->response[] = new CCC($name, CCCF_CLUB . $id, $category);
			}
		}
	}
	
	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>term</dt>
			<dd>
				Search string. Only the countries/cities/clubs with the matching names are returned. For example <a href="ccc.php?term=ma"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?term=ma</a> returns only the objects containing "ma" in their name.
				If "term" matches one of the object names, this object is used to filter sub-objects. For example, country is used to filter cities and clubs; city is used to filter clubs. For example:
				<ul>
					<li><a href="ccc.php?term=canada"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?term=canada</a> - check cities and clubs. They all are Canadian.</li>
					<li><a href="ccc.php?term=moscow"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?term=moscow</a> - check clubs. They all are from Moscow.</li>
				</ul>
			</dd>
		<dt>flags</dt>
			<dd>
				Integer containing bit flag combination of different options.
				<ul>
					<li>1 - don't return clubs. For example: <a href="ccc.php?flags=1"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?flags=1</a> returns cities and countries but no clubs.</li>
					<li>2 - don't return cities. For example: <a href="ccc.php?flags=3"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?flags=3</a> returns countries only. Because 3 is a combination of 1 and 2 (3=1+2)</li>
					<li>4 - don't return countries. For example: <a href="ccc.php?flags=6"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?flags=6</a> returns clubs only. Because 6 is a combination of 2 and 4 (6=2+4)</li>
					<li>8 - don't include "All" item. For example: <a href="ccc.php?flags=8"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?flags=8</a></li>
					<li>16 - don't include "My clubs" item. For example: <a href="ccc.php?flags=17"><?php echo PRODUCT_URL; ?>/api/control/ccc.php?flags=17</a> returns no clubs and also no "My clubs" item. Because 17 is a combination of 1 and 16 (17=1+16)</li>
				</ul>
<?php
	}
}

$page = new ApiPage();
$page->run('Country-City-Club List');

?>