<?php

require_once('include/ui.php');

show_header('Vancouver Mafia', false);

$role = 'a';
if (isset($_REQUEST['role']))
{
	$role = $_REQUEST['role'];
}

$page = 0;
if (isset($_REQUEST['page']))
{
	$page = $_REQUEST['page'];
}

$ratings = get_json('ws_ratings.php?club=1&pos=' . ($page * 10) . '&count=10&role=' . $role);

echo '<form method="get" name="viewForm" action="ratings.php">';
echo '<table class="transp" cellpadding="20" cellspacing="0"  width="100%"><tr><td class="back" width="30">';
if ($page > 0)
{
	echo '<a href="ratings.php?role=' . $role . '&page=' . ($page - 1) . '"><img src="images/arrow-left.jpg" width="30" border="0"></a>';
}
$role_names = array('a'=>'All roles', 'r'=>'Red players', 'b'=>'Dark players', 'c'=>'Civilians', 's'=>'Sheriffs', 'm'=>'Mafiosy', 'd'=>'Dons');
echo '</td><td class="back" align="center"><select name="role" onChange = "document.viewForm.submit()">';
foreach ($role_names as $key => $value)
{
	echo '<option value="' . $key . '"';
	if ($key == $role)
	{
		echo ' selected';
	}
	echo '>' . $value . '</option>';
}
echo '</select></td><td class="back" align="right" width="30">';
if (($page + 1) * 10 < $ratings->count)
{
	echo '<a href="ratings.php?role=' . $role . '&page=' . ($page + 1) . '"><img src="images/arrow-right.jpg" width="30" border="0"></a>';
}
echo '</td></tr></table></form>';


echo '<table border="1" cellpadding="20" cellspacing="0"  width="100%">';
echo '<tr><th width="40">#</th>';
echo '<th colspan="2">Player</th>';
echo '<th width="60">Rating</th>';
echo '<th width="60">Games played</th>';
echo '<th width="60">Games won</th>';
echo '</tr>';
foreach ($ratings->ratings	 as $rating)
{
	if (!is_numeric($rating))
	{
		echo '<tr>';
		echo '<td align="center">' . $rating->num . '</td>';
		echo '<td width="50"><a href="' . $rating->user_page . '"><img src="';
		if ($rating->user_image != '')
			echo $rating->user_image;
		else if ($rating->is_male)
			echo 'images/male.png';
		else
			echo 'images/female.png';
		echo '" border="0" width="50" height="50"></a></td>';
		echo '<td>' . $rating->user_name . '</td>';
		echo '<td align="center">' . $rating->rating . '</td>';
		echo '<td align="center">' . $rating->num_games . '</td>';
		echo '<td align="center">' . $rating->games_won . '</td>';
		echo '</tr>';
	}
}
echo '</table>';

show_footer(false);

?>