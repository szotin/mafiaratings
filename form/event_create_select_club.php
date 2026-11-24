<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/picture.php';
require_once '../include/cards.php';

initiate_session();

try
{
	dialog_title(get_label('Select club'));
	check_permissions(PERMISSION_USER);
	
	$now = isset($_REQUEST['now']) && $_REQUEST['now'] ? 'true' : 'false';
	
	$club_count = 0;
		
	if (is_permitted(PERMISSION_ADMIN))
	{
		$query = new DbQuery('SELECT c.id, n.name, c.flags FROM clubs c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0 ORDER BY n.name');
	}
	else
	{
		$query = new DbQuery('SELECT c.id, nc.name, c.flags FROM club_regs cu JOIN clubs c ON c.id = cu.club_id JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0 WHERE cu.user_id = ? AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0 AND (cu.flags & ' . USER_PERM_REFEREE . ') <> 0 ORDER BY nc.name', $_profile->user_id);
	}				
	
	// The clubs as the cards of the screens before the game (see .pick in new_look.css), with a
	// filter by name over them: an administrator is offered every club there is.
	$club_pic = new Picture(CLUB_PICTURE);
	$cards = '';
	while ($row = $query->next())
	{
		list ($club_id, $club_name, $club_flags) = $row;
		$club_pic->set($club_id, $club_name, $club_flags);
		$cards .= '<a class="pick" href="#" data-name="' . htmlspecialchars(mb_strtolower($club_name, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '" onclick="clubSelected(' . $club_id . '); return false;">' .
			card_logo($club_pic, $club_name) . '<span class="pick-title">' . $club_name . '</span></a>';
		++$club_count;
	}
	if ($club_count == 0)
	{
		throw new Exc(get_label('You are not allowed to create events in any of the clubs.'));
	}
	if ($club_count > 8)
	{
		echo '<div class="field grow pick-filter">' . '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>' . '<input type="search" id="form-club-filter" placeholder="' . get_label('Club') . '" oninput="filterClubs(this.value)" autocomplete="off"></div>';
	}
	echo '<div class="pick-grid small" id="form-clubs">' . $cards . '</div>';
	?>	
	<script>
	function filterClubs(text)
	{
		text = text.trim().toLowerCase();
		$('#form-clubs .pick').each(function()
		{
			$(this).toggle(text == '' || this.getAttribute('data-name').indexOf(text) >= 0);
		});
	}

	function clubSelected(clubId)
	{
		dlg.close();
		mr.createEvent(clubId, <?php echo $now; ?>);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>