<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/currency.php';

define('PAGE_SIZE', CURRENCIES_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;

		check_permissions(PERMISSION_ADMIN);
		
		list ($count) = Db::record(get_label('currency'), 'SELECT count(*) FROM currencies c');
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT c.id, n.name, c.pattern FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0', $_lang);
		$query->add(' ORDER BY n.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td width="56">';
		echo '<button class="icon" onclick="createCurrency()" title="' . get_label('Create currency') . '"><img src="images/create.png" border="0"></button>';
		echo '</td><td>'.get_label('Currency').'</td>';
		echo '<td width="200">'.get_label('Display pattern').'</td></tr>';
		while ($row = $query->next())
		{
			list($id, $name, $pattern) = $row;
			echo '<tr><td class="dark">';
			echo '<button class="icon" onclick="deleteCurrency(' . $id . ')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="editCurrency(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
			echo '</td><td>' . $name . '</td>';
			echo '</td><td>' . format_currency(1000000, $pattern) . '</td></tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>		
		function createCurrency()
		{
			dlg.form("form/currency_create.php", refr);
		}

		function deleteCurrency(id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete currency?'); ?>", null, null, function()
			{
				json.post("api/ops/currency.php", { op: 'delete', currency_id: id }, refr);
			});
		}

		function editCurrency(id)
		{
			dlg.form("form/currency_edit.php?id=" + id, refr);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Currencies'));

?>