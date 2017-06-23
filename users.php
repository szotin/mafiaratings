<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define('PAGE_SIZE', 20);

class Page extends GeneralPageBase
{
	private $filter;

	protected function prepare()
	{
		global $_profile, $_page;
	
		parent::prepare();
		
		$this->filter = NULL;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = $_REQUEST['filter'];
		}
		
		if (isset($_REQUEST['ban']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['ban']);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Banned', NULL, $_REQUEST['ban']);
			}
			throw new RedirectExc('?filter=' . $this->filter . '&page=' . $_page . '&ccc=' . $this->ccc_filter->get_code());
		}
		else if (isset($_REQUEST['unban']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['unban']);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Unbanned', NULL, $_REQUEST['unban']);
			}
			throw new RedirectExc('?filter=' . $this->filter . '&page=' . $_page . '&ccc=' . $this->ccc_filter->get_code());
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL();
		$sep = ' WHERE ';
		if ($this->filter != NULL)
		{
			$condition->add($sep . 'u.name LIKE ?', $this->filter . '%');
			$sep = ' AND ';
		}
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($sep . 'u.id IN (SELECT user_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND club_id = ?)', $ccc_id);
				$sep = ' AND ';
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($sep . 'u.id IN (SELECT user_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND club_id IN (SELECT club_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND user_id = ?))', $_profile->user_id);
				$sep = ' AND ';
			}
			break;
		case CCCF_CITY:
			$condition->add($sep . 'u.city_id IN (SELECT id FROM cities WHERE id = ? OR near_id = ?)', $ccc_id, $ccc_id);
			$sep = ' AND ';
			break;
		case CCCF_COUNTRY:
			$condition->add($sep . 'u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			$sep = ' AND ';
			break;
		}
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="52"></td>';
		echo '<td>' . get_label('User name') . '</td><td width="160">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery('SELECT u.id, u.name, u.flags FROM users u', $condition);
		$query->add(' ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list($id, $name, $flags) = $row;
		
			echo '<tr class="light"><td class="dark">';
			$ref = '<a href ="?page=' . $_page . '&ccc=' . $this->ccc_filter->get_code();
			if ($this->filter != NULL)
			{
				$ref .= '&filter=' . $this->filter;
			}
			if ($flags & U_FLAG_BANNED)
			{
				echo $ref . '&unban=' . $id . '" title="' .get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></a>';
			}
			else
			{
				echo $ref . '&ban=' . $id . '" title="' .get_label('Ban [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
				echo ' <a href ="edit_user.php?id=' . $id . '&bck=1" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			}
			echo '</td>';
			
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			
			echo '<td>';
			$sep = '';
			if ($flags & U_PERM_ADMIN)
			{
				echo $sep . get_label('admin');
				$sep = ', ';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function show_filter_fields()
	{
		echo '<form action="javascript:filter()">' . get_label('Filter') . ':&nbsp;<input id="filter" value="' . $this->filter . '" onChange="onChange="filter()"></form>';
	}
	
	protected function get_filter_js()
	{
		return '+ "&filter=" + $("#filter").val()';
	}
}

$page = new Page();
$page->run(get_label('[0] users', PRODUCT_NAME), U_PERM_ADMIN);

?>