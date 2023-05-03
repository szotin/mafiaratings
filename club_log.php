<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/club.php';

define('PAGE_SIZE', LOG_PAGE_SIZE);

class Page extends ClubPageBase
{
	private $objects;
	private $filter_obj;
	private $filter_obj_id;
	private $filter_user_id;
	private $filter_user_name;
	
	protected function prepare()
	{
		parent::prepare();
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		
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
				list($this->filter_user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $this->filter_user_id);
			}
		}
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$filtered = false;
		$condition = new SQL(' WHERE l.club_id = ?', $this->id);
		if ($this->filter_obj != '')
		{
			$condition->add(' AND l.obj = ?', $this->filter_obj);
			$filtered = true;
		}
		if ($this->filter_obj_id > 0)
		{
			$condition->add(' AND l.obj_id = ?', $this->filter_obj_id);
			$filtered = true;
		}
		if ($this->filter_user_id > 0)
		{
			$condition->add(' AND l.user_id = ?', $this->filter_user_id);
			$filtered = true;
		}
		
		echo '<table class="transp" width="100%"><tr>';
		if ($filtered)
		{
			echo '<td width="36"><button class="icon" onclick="unfilter()" title="' . get_label('Remove all filters') . '"><img src="images/no_filter.png"></button></td>';
		}
		echo '<td><select id="obj" onChange="filterObj()">';
		show_option('', $this->filter_obj, 'All objects');
		foreach ($this->objects as $key => $value)
		{
			show_option($key, $this->filter_obj, $key);
		}
		echo '</select> ';
		show_user_input('use', $this->filter_user_name, '', get_label('Show actions of a specific user.'), 'filterUser');
		echo '</td></tr></table>';
		
		list ($count) = Db::record('log', 'SELECT count(*) FROM log l', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT l.id, u.id, u.name, l.time, l.obj, l.obj_id, l.message, l.page, (l.details IS NOT NULL) FROM log l' .
				' LEFT OUTER JOIN users u ON u.id = l.user_id' .
				' LEFT OUTER JOIN clubs c ON c.id = l.club_id',
			$condition);
		$query->add(' ORDER BY l.time DESC, l.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="52"></td>';
		echo '<td width="90">' . get_label('Time') . '</td>';
		echo '<td>' . get_label('Page') . '</td>';
		echo '<td width="100">' . get_label('User') . '</td>';
		echo '<td width="120">' . get_label('Object') . '</td>';
		echo '<td width="120">' . get_label('Action') . '</td>';
		echo '</tr>';

		while ($row = $query->next())
		{
			list($log_id, $user_id, $user_name, $time, $obj, $obj_id, $message, $link, $has_details) = $row;
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
				echo '<a href="club_log.php?bck=1&id=' . $this->id . '&obj=' . $obj . '&obj_id=' . $obj_id . '" title="' . get_label('Show all log records of [0] [1]', $obj, $obj_id) . '"><img src="images/filter.png" width="24"></a>';
			}
			echo '</td>';
			
			echo '<td class="dark">' . format_date('d/m/y H:i', $time, get_timezone()) . '</td>';
			
			echo '<td>' . $link . '</td>';
			
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
		
		$no_filter = '?id=' . $this->id;
		
?>
		function showDetails(id)
		{
			function loaded(text, title)
			{
				dlg.info(text, title);
			}
			html.get("log_details.php?id=" + id, loaded);
		}

		function filterUser(data)
		{
			goTo({user_id:data.id});
		}

		function filterObj()
		{
			goTo({obj:$("#obj").val()});
		}

		function unfilter()
		{
			window.location.replace("<?php echo $no_filter; ?>");
		}
	<?php
	}
}

$page = new Page();
$page->run(get_label('Log'));

?>