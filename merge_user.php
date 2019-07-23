<?php

require_once 'include/user.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 40);

class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		check_permissions(PERMISSION_OWNER, $this->id);
	}
	
	protected function show_body()
	{
		global $_page;
	
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users WHERE id <> ? AND email = ?', $this->id, $this->email);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="56">';
		echo '<button class="icon" onclick="mergeAllUsers(' . $this->id . ')" title="' . get_label('Merge all these users with their stats and access rights to this account.') . '"><img src="images/merge.png"></button>';
		echo '<button class="icon" onclick="deleteAllUsers(' . $this->id . ')" title="' . get_label('Delete all these users with their stats and ratings.') . '"><img src="images/delete.png"></button>';
		echo '</td><td width="38"></td><td>'.get_label('Players with the same email').'</td><td width="100">Rating</td><td width="100">'.get_label('Games played').'</td></tr>';

		$query = new DbQuery('SELECT id, name, rating, games, flags FROM users WHERE id <> ? AND email = ?', $this->id, $this->email);
		$query->add(' ORDER BY games DESC, rating DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($id, $name, $rating, $games, $flags) = $row;
			echo '<tr>';
			
			echo '<td>';
			echo '<button class="icon" onclick="mergeUser(' . $id . ')" title="' . get_label('Merge all [0] games, stats and access rights to your account.', $name) . '"><img src="images/merge.png"></button>';
			echo '<button class="icon" onclick="deleteUser(' . $id . ')" title="' . get_label('Delete [0] with all their stats and ratings.', $name) . '"><img src="images/delete.png"></button>';
			echo '</td>';
			
			echo '<td>';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, 36);
			echo '</td>';
			
			echo '<td><a href="merge_user.php?bck=1&id=' . $id . '">' . cut_long_name($name, 80) . '</a></td>';
			echo '<td>' . $rating . '</td>';
			echo '<td>' . $games . '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function mergeUser(id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to merge this user into [0]?', $this->name); ?>", null, null, function()
			{
				json.post("api/ops/user.php", { op: 'merge', src_id: id, dst_id: <?php echo $this->id; ?> }, refr);
			});
		}
		
		function deleteUser(id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete this user?'); ?>", null, null, function()
			{
				json.post("api/ops/user.php", { op: 'delete', user_id: id }, refr);
			});
		}
		
		function mergeAllUsers(id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to merge all these users into [0]?', $this->name); ?>", null, null, function()
			{
				json.post("api/ops/user.php", { op: 'merge_all', user_id: id }, refr);
			});
		}
		
		function deleteAllUsers(id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete these all users?'); ?>", null, null, function()
			{
				json.post("api/ops/user.php", { op: 'delete_all', user_id: id }, refr);
			});
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Merge'));

?>