<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';

define('PAGE_SIZE', LOG_PAGE_SIZE);

class Page extends GeneralPageBase
{
	private $objects;
	private $filter_obj;
	private $filter_obj_id;
	private $filter_user_id;
	private $filter_user_name;
	
	protected function prepare()
	{
		parent::prepare();
		global $_lang;
		
		check_permissions(PERMISSION_ADMIN);
		$this->objects = prepare_log_objects();
		
		$this->filter_obj = '';
		$this->filter_obj_id = 0;
		$this->filter_user_id = 0;
		$this->filter_user_name = '';
		if (isset($_REQUEST['obj']))
		{
			$this->filter_obj = $_REQUEST['obj'];
			if (isset($_REQUEST['obj_id']))
			{
				$this->filter_obj_id = $_REQUEST['obj_id'];
			}
		}
		
		if (isset($_REQUEST['user_id']))
		{
			$this->filter_user_id = (int)$_REQUEST['user_id'];
			if ($this->filter_user_id > 0)
			{
				list($this->filter_user_name) = Db::record(get_label('user'), 
					'SELECT nu.name'.
					' FROM users u'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' WHERE u.id = ?', $this->filter_user_id);
			}
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$condition = new SQL();
		$delim = ' WHERE ';
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('records')));
		echo '<td align="right" width="400"><select id="obj" onChange="filterObjects()">';
		show_option('', $this->filter_obj, 'All objects');
		foreach ($this->objects as $key => $value)
		{
			show_option($key, $this->filter_obj, $key);
		}
		echo '</select> ';
		show_user_input('user_filter', $this->filter_user_name, '', get_label('Show actions of a specific user.'), 'filterUser');
		if ($this->filter_obj != '' || $this->filter_obj_id > 0 || $this->filter_user_id > 0 || $ccc_filter->get_value() != NULL)
		{
			echo '</td><td width="32" align="right"><button class="icon" onclick="unfilter()" title="' . get_label('Remove all filters') . '"><img src="images/no_filter.png"></button></td>';
		}
		echo '</td></tr></table></p>';
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($delim . 'l.club_id = ?', $ccc_id);
				$delim = ' AND ';
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($delim . 'l.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
				$delim = ' AND ';
			}
			break;
		case CCCF_CITY:
			$condition->add($delim . 'l.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			$delim = ' AND ';
			break;
		case CCCF_COUNTRY:
			$condition->add($delim . 'l.club_id IN (SELECT c.id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE i.country_id = ?)', $ccc_id);
			$delim = ' AND ';
			break;
		}
		if ($this->filter_obj != '')
		{
			$condition->add($delim . 'l.obj = ?', $this->filter_obj);
			$delim = ' AND ';
		}
		if ($this->filter_obj_id > 0)
		{
			$condition->add($delim . 'l.obj_id = ?', $this->filter_obj_id);
			$delim = ' AND ';
		}
		if ($this->filter_user_id > 0)
		{
			$condition->add($delim . 'l.user_id = ?', $this->filter_user_id);
			$delim = ' AND ';
		}
		
		list ($count) = Db::record('log', 'SELECT count(*) FROM log l', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT l.id, u.id, nu.name, l.time, l.obj, l.obj_id, l.ip, l.message, l.page, (l.details IS NOT NULL), c.id, c.name, lg.id, lg.name FROM log l' .
				' LEFT OUTER JOIN users u ON u.id = l.user_id' .
				' LEFT OUTER JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = l.club_id' .
				' LEFT OUTER JOIN leagues lg ON lg.id = l.league_id',
			$condition);
		$query->add(' ORDER BY l.time DESC, l.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="52"></td>';
		echo '<td width="90">' . get_label('Time') . '</td>';
		echo '<td width="100">' . get_label('User') . '</td>';
		echo '<td width="70">' . get_label('IP') . '</td>';
		echo '<td width="120">' . get_label('Club') . '/' . get_label('League') . '</td>';
		echo '<td width="120">' . get_label('Object') . '</td>';
		echo '<td>' . get_label('Message') . '</td>';
		echo '</tr>';

		while ($row = $query->next())
		{
			list($log_id, $user_id, $user_name, $time, $obj, $obj_id, $ip, $message, $page, $has_details, $club_id, $club_name, $league_id, $league_name) = $row;
			echo '<tr>';
			
			echo '<td>';
			if ($has_details)
			{
				echo '<button class="icon" onclick="showDetails(' . $log_id . ')" title="' . get_label('Show details') . '"><img src="images/details.png" width="24"></button>';
			}
			else
			{
				echo '<img src="images/transp.png" width="24">';
			}
			if ($obj_id != NULL)
			{
				echo '<a href="log.php?bck=1&obj=' . $obj . '&obj_id=' . $obj_id . '" title="' . get_label('Show all log records of [0] [1]', $obj, $obj_id) . '"><img src="images/filter.png" width="24"></a>';
			}
			echo '</td>';
			
			echo '<td class="dark">';
			echo format_date('d/m/y H:i', $time, get_timezone());
			echo '</td>';
			
			echo '<td>';
			if ($user_id != NULL)
			{
				echo '<a href="user_info.php?bck=1&id=' . $user_id . '">' . $user_name . '</a>';
			}
			else
			{
				echo '&nbsp;';
			}
			echo '</td>';
			
			echo '<td>';
			if ($ip == '127.0.0.1')
			{
				echo $ip;
			}
			else if ($ip != '')
			{
				echo '<a href="http://www.infobyip.com/ip-' . $ip . '.html" target="_blank">' . $ip . '</a>';
			}
			else
			{
				echo '&nbsp;';
			}
			echo '</td>';
			
			echo '<td>';
			if ($club_id != NULL)
			{
				echo '<a href="club_main.php?bck=1&id=' . $club_id . '">' . $club_name . '</a>';
			}
			else if ($league_id != NULL)
			{
				echo '<a href="league_main.php?bck=1&id=' . $league_id . '">' . $league_name . '</a>';
			}
			echo '</td>';
			
			echo '<td>';
			if ($obj_id != NULL)
			{
				if (isset($this->objects[$obj]) && $this->objects[$obj] != NULL)
				{
					echo '<a href="' . $this->objects[$obj] . $obj_id . '">' . $obj . '&nbsp;' . $obj_id . '</a>';
				}
				else
				{
					echo $obj . '&nbsp;' . $obj_id;
				}
			}
			else
			{
				echo $obj;
			}
			echo '</td>';
			
			echo '<td>' . short_log_message($message) . '</td>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
		parent::js();
		
?>		
		function showDetails(id)
		{
			function loaded(text, title)
			{
				dlg.info(text, title, 1000);
			}
			html.get("log_details.php?id=" + id, loaded);
		}
		
		function filterObjects()
		{
			goTo({obj: $("#obj").val(), page: undefined});
		}

		function filterUser(data)
		{
			goTo({user_id: data.id, page: undefined});
		}

		function unfilter()
		{
			window.location.replace("?");
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Log'));

?>