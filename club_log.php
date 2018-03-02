<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/club.php';

define("PAGE_SIZE", 60);
class Page extends ClubPageBase
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
		
		$this->_title = get_label('[0] log', $this->name);
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		echo '<select id="obj" onChange="filter()">';
		show_option('', $this->obj_filter, 'All objects');
		foreach ($this->objects as $key => $value)
		{
			show_option($key, $this->obj_filter, $key);
		}
		echo '</select>';
		
		$condition = new SQL(' WHERE l.club_id = ?', $this->id);
		if ($this->obj_filter != '')
		{
			$condition->add(' AND l.obj = ?', $this->obj_filter);
		}
		
		list ($count) = Db::record('log', 'SELECT count(*) FROM log l', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT l.id, u.id, u.name, l.time, l.obj, l.obj_id, l.message, c.id, c.name, l.page, (l.details IS NOT NULL) FROM log l' .
				' LEFT OUTER JOIN users u ON u.id = l.user_id' .
				' LEFT OUTER JOIN clubs c ON c.id = l.club_id',
			$condition);
		$query->add(' ORDER BY l.time DESC, l.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="120">' . get_label('Time') . '</td>';
		echo '<td>' . get_label('User') . '</td>';
		echo '<td width="200">' . get_label('Object') . '</td>';
		echo '<td width="200">' . get_label('Message') . '</td>';
		echo '</tr>';

		while ($row = $query->next())
		{
			list($log_id, $user_id, $user_name, $time, $obj, $obj_id, $message, $page, $has_details) = $row;
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
	
	protected function js()
	{
?>
		function showDetails(id)
		{
			function loaded(text, title)
			{
				dlg.info(text, title);
			}
			html.get("log_details.php?id=" + id, loaded);
		}

		function filter()
		{
			window.location.replace("?id=" + <?php echo $this->id; ?> + "&obj=" + $("#obj").val());
		}
	<?php
	}
}

$page = new Page();
$page->run(NULL, UC_PERM_MANAGER);

?>