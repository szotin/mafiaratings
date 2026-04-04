<?php

require_once 'include/seating.php';
require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function prepare()
	{
		parent::prepare();
		check_permissions(PERMISSION_ADMIN);
	}

	protected function show_body()
	{
		global $_lang;

		$user_pic = new Picture(USER_PICTURE);

		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="dark"><th width="32"><button class="icon" onclick="createPair()" title="' . get_label('Create new pair') . '"><img src="images/create.png"></button></th>';
		echo '<th width="200">' . get_label('Player [0]', 1) . '</th>';
		echo '<th width="200">' . get_label('Player [0]', 2) . '</th>';
		echo '<th>' . get_label('Policy') . '</th></tr>';

		$query = new DbQuery(
			'SELECT u1.id, nu1.name, u1.flags,' .
			' u2.id, nu2.name, u2.flags, p.policy' .
			' FROM pairs p' .
			' JOIN users u1 ON u1.id = p.user1_id' .
			' JOIN users u2 ON u2.id = p.user2_id' .
			' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $_lang . ') <> 0' .
			' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $_lang . ') <> 0' .
			' WHERE p.policy <> ' . PAIR_POLICY_NOTHING .
			' ORDER BY u1.id');
		while ($row = $query->next())
		{
			list ($user1_id, $user1_name, $user1_flags,
			      $user2_id, $user2_name, $user2_flags, $policy) = $row;
			echo '<tr>';
			echo '<td><button class="icon" onclick="deletePair(' . (int)$user1_id . ',' . (int)$user2_id . ')" title="' . get_label('Delete pair') . '"><img src="images/delete.png"></button></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->set((int)$user1_id, $user1_name, (int)$user1_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . (int)$user1_id . '&bck=1">' . $user1_name . '</a></td></tr></table></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->set((int)$user2_id, $user2_name, (int)$user2_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . (int)$user2_id . '&bck=1">' . $user2_name . '</a></td></tr></table></td>';

			echo '<td align="center">' . get_pair_policy_name($policy) . '</td>';
			echo '</tr>';
		}
		echo '</table></p>';
	}

	protected function js()
	{
		parent::js();
?>
		function createPair()
		{
			dlg.form("form/pair_create.php", refr, 600);
		}

		function deletePair(user1Id, user2Id)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete this pair?'); ?>", null, null, function()
			{
				json.post("api/ops/seating.php",
				{
					op: "delete_pair"
					, user1_id: user1Id
					, user2_id: user2Id
				}, refr);
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Pairs'));

?>
