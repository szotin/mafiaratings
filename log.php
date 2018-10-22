<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';

define("PAGE_SIZE", 60);
class Page extends GeneralPageBase
{
	private $objects;
	private $obj_filter;
	
	protected function prepare()
	{
		parent::prepare();
		$this->objects = prepare_log_objects();
		
		$this->obj_filter = '';
		if (isset($_REQUEST['obj']))
		{
			$this->obj_filter = $_REQUEST['obj'];
		}
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL();
		$delim = ' WHERE ';
		
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
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
		if ($this->obj_filter != '')
		{
			$condition->add($delim . 'l.obj = ?', $this->obj_filter);
			$delim = ' AND ';
		}
		
		list ($count) = Db::record('log', 'SELECT count(*) FROM log l', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT l.id, u.id, u.name, l.time, l.obj, l.obj_id, l.ip, l.message, c.id, c.name, l.page, (l.details IS NOT NULL) FROM log l' .
				' LEFT OUTER JOIN users u ON u.id = l.user_id' .
				' LEFT OUTER JOIN clubs c ON c.id = l.club_id',
			$condition);
		$query->add(' ORDER BY l.time DESC, l.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="100">' . get_label('Time') . '</td>';
		echo '<td width="160">' . get_label('User') . '</td>';
		echo '<td width="70">' . get_label('IP') . '</td>';
		echo '<td width="200">' . get_label('Club') . '</td>';
		echo '<td width="60">' . get_label('Object') . '</td>';
		echo '<td>' . get_label('Message') . '</td>';
		echo '</tr>';

		while ($row = $query->next())
		{
			list($log_id, $user_id, $user_name, $time, $obj, $obj_id, $ip, $message, $club_id, $club_name, $page, $has_details) = $row;
			echo '<tr>';
			
			echo '<td class="dark">';
			if ($page != '')
			{
				echo '<a href="' . $page . '">' . format_date('d/m/y H:i', $time, get_timezone()) . '</a>';
			}
			else
			{
				echo format_date('d/m/y H:i', $time, get_timezone());
			}
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
			else
			{
				echo '&nbsp;';
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
			
			echo '<td>';
			if ($has_details)
			{
				echo '<a href="#" onclick="showDetails(' . $log_id . ')">' . $message . '</a>';
			}
			else
			{
				echo $message;
			}
			echo '</td>';
		}
		echo '</table>';
	}
	
	protected function show_filter_fields()
	{
		echo '<select id="obj" onChange="filter()">';
		show_option('', $this->obj_filter, 'All objects');
		foreach ($this->objects as $key => $value)
		{
			show_option($key, $this->obj_filter, $key);
		}
		echo '</select>';
	}
	
	protected function get_filter_js()
	{
		return '+ "&obj=" + $("#obj").val()';
	}
}

$page = new Page();
$page->set_ccc(CCCS_ALL);
$page->run(get_label('Log'), PERMISSION_ADMIN);

?>

<script>
function showDetails(id)
{
	function loaded(text, title)
	{
		dlg.info(text, title);
	}
	html.get("log_details.php?id=" + id, loaded);
}
</script>