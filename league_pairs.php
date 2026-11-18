<?php

require_once 'include/seating.php';
require_once 'include/league.php';
require_once 'include/cards.php';

class Page extends LeaguePageBase
{
	protected function prepare()
	{
		parent::prepare();
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
	}

	protected function show_body()
	{
		global $_lang;

		$pairs_map = array();

		$query = new DbQuery(
			'SELECT u1.id, nu1.name, u1.flags,' .
			' u2.id, nu2.name, u2.flags, p.policy' .
			' FROM pairs p' .
			' JOIN users u1 ON u1.id = p.user1_id' .
			' JOIN users u2 ON u2.id = p.user2_id' .
			' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $_lang . ') <> 0' .
			' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $_lang . ') <> 0');
		while ($row = $query->next())
		{
			list ($user1_id, $user1_name, $user1_flags,
			      $user2_id, $user2_name, $user2_flags, $policy) = $row;
			$pair = new stdClass();
			$pair->user1_id   = (int)$user1_id;
			$pair->user1_name = $user1_name;
			$pair->user1_flags = (int)$user1_flags;
			$pair->user2_id   = (int)$user2_id;
			$pair->user2_name = $user2_name;
			$pair->user2_flags = (int)$user2_flags;
			$pair->policy     = (int)$policy;
			$pair->source     = get_label('Global');
			$pairs_map[$user1_id . '_' . $user2_id] = $pair;
		}

		$query = new DbQuery(
			'SELECT u1.id, nu1.name, u1.flags,' .
			' u2.id, nu2.name, u2.flags, p.policy' .
			' FROM league_pairs p' .
			' JOIN users u1 ON u1.id = p.user1_id' .
			' JOIN users u2 ON u2.id = p.user2_id' .
			' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $_lang . ') <> 0' .
			' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $_lang . ') <> 0' .
			' WHERE p.league_id = ?',
			$this->id);
		while ($row = $query->next())
		{
			list ($user1_id, $user1_name, $user1_flags,
			      $user2_id, $user2_name, $user2_flags, $policy) = $row;
			$key = $user1_id . '_' . $user2_id;
			if (isset($pairs_map[$key]))
			{
				$pairs_map[$key]->policy = (int)$policy;
				$pairs_map[$key]->source = get_label('In this league');
			}
			else
			{
				$pair = new stdClass();
				$pair->user1_id   = (int)$user1_id;
				$pair->user1_name = $user1_name;
				$pair->user1_flags = (int)$user1_flags;
				$pair->user2_id   = (int)$user2_id;
				$pair->user2_name = $user2_name;
				$pair->user2_flags = (int)$user2_flags;
				$pair->policy     = (int)$policy;
				$pair->source     = get_label('In this league');
				$pairs_map[$key]  = $pair;
			}
		}

		$pairs = array_values(array_filter($pairs_map, function($p) { return $p->policy != PAIR_POLICY_NOTHING; }));
		usort($pairs, function($a, $b) { return $a->user1_id - $b->user1_id; });

		$user_pic = new Picture(USER_PICTURE);

		// The list of the v3 design, after ratings.php: the button that creates a pair in the toolbar,
		// the table in a sheet, a player in each of the two columns.
		echo '<div class="toolbar"><span class="count">' . get_label('[0] pairs', count($pairs)) . '</span>';
		echo '<a class="btn tb-create" href="#" onclick="createPair(); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create new pair') . '</span></a></div>';
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th scope="col"></th>';
		echo '<th scope="col">' . get_label('Player [0]', 1) . '</th>';
		echo '<th scope="col">' . get_label('Player [0]', 2) . '</th>';
		echo '<th scope="col">' . get_label('Policy') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Where the policy is set') . '</th></tr></thead><tbody>';
		foreach ($pairs as $pair)
		{
			echo '<tr>';
			echo '<td class="tb-btns"><button class="icon" onclick="deletePair(' . $pair->user1_id . ',' . $pair->user2_id . ')" title="' . get_label('Delete pair') . '"><img src="images/delete.png"></button></td>';
			echo '<td><a class="who" href="user_info.php?id=' . $pair->user1_id . '&bck=1">' . card_avatar($user_pic, $pair->user1_id, $pair->user1_name, $pair->user1_flags, '') .
				'<span class="who-txt"><span class="who-name">' . $pair->user1_name . '</span></span></a></td>';
			echo '<td><a class="who" href="user_info.php?id=' . $pair->user2_id . '&bck=1">' . card_avatar($user_pic, $pair->user2_id, $pair->user2_name, $pair->user2_flags, '') .
				'<span class="who-txt"><span class="who-name">' . $pair->user2_name . '</span></span></a></td>';
			echo '<td class="dim">' . get_pair_policy_name($pair->policy) . '</td>';
			echo '<td class="p2 dim">' . $pair->source . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	protected function js()
	{
		parent::js();
?>
		function createPair()
		{
			dlg.form("form/pair_create.php?league_id=<?php echo $this->id; ?>", refr, 600);
		}

		function deletePair(user1Id, user2Id)
		{
			var html = '<p><?php echo get_label('Are you sure you want to delete this pair?'); ?></p>';
			html += '<p><input type="checkbox" id="delete-pair-league-only"> <?php echo get_label('for this league only'); ?></p>';
			dlg.custom(html, "<?php echo get_label('Confirmation'); ?>", null,
			{
				yes: { id: "dlg-yes", text: "<?php echo get_label('Yes'); ?>", click: function()
				{
					var global = $('#delete-pair-league-only').is(':checked') ? 0 : 1;
					$(this).dialog('close');
					json.post("api/ops/seating.php",
					{
						op: "delete_pair"
						, league_id: <?php echo $this->id; ?>
						, user1_id: user1Id
						, user2_id: user2Id
						, global: global
					}, refr);
				}},
				no: { id: "dlg-no", text: "<?php echo get_label('No'); ?>", click: function() { $(this).dialog('close'); } }
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('League pairs'));

?>
