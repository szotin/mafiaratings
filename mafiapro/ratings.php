<?php

require_once('include/ui.php');

define('PAGE_SIZE', 10);

$role = 'a';
if (isset($_REQUEST['role']))
{
	$role = $_REQUEST['role'];
}

$p = 0;
if (isset($_REQUEST['p']))
{
	$p = $_REQUEST['p'];
}

$ratings = get_json('ws_ratings.php?club=2&pos=' . ($p * PAGE_SIZE) . '&len=' . PAGE_SIZE . '&role=' . $role);

echo '<form method="get" name="viewForm" action="index.php">';
echo '<input type="hidden" name="page" value="ratings">';
echo '<table class="transp" cellpadding="20" cellspacing="0"  width="100%"><tr><td class="back" width="40">';
if ($p > 0)
{
	echo '<a href="index.php?page=ratings&role=' . $role . '&p=' . ($p - 1) . '"><img src="images/left-arrow.png" width="40" border="0"></a>';
}
$role_names = array('a'=>'Все роли', 'r'=>'Красные', 'b'=>'Черные', 'c'=>'Рядовые мирные', 's'=>'Шерифы', 'm'=>'Рядовые мафиози', 'd'=>'Доны');
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
echo '</select></td><td class="back" align="right" width="40">';
if (($p + 1) * PAGE_SIZE < $ratings->count)
{
	echo '<a href="index.php?page=ratings&role=' . $role . '&p=' . ($p + 1) . '"><img src="images/right-arrow.png" width="40" border="0"></a>';
}
echo '</td></tr></table></form>';


echo '<table border="1" cellpadding="20" cellspacing="0"  width="100%">';
echo '<tr><th width="40">&nbsp;</th>';
echo '<th colspan="2">Игрок</th>';
echo '<th width="60">Рейтинг</th>';
echo '<th width="60">Сыграно игр</th>';
echo '<th width="60">Выиграно игр</th>';
echo '</tr>';
foreach ($ratings->ratings as $rating)
{
	if (!is_numeric($rating))
	{
		echo '<tr>';
		echo '<td align="center" class="highlight">' . $rating->num . '</td>';
		echo '<td width="1"><a href="' . $rating->user_page . '" target="blank"><img src="';
		if ($rating->user_image != '')
		{
			echo $rating->user_image;
		}
		else if ($rating->is_male)
		{
			echo 'images/male.png';
		}
		else
		{
			echo 'images/female.png';
		}
		echo '" border="0"></a></td>';
		echo '<td><a href="' . $rating->user_page . '" target="blank">' . $rating->user_name . '</a></td>';
		echo '<td align="center" class="highlight">' . $rating->rating . '</td>';
		echo '<td align="center" class="highlight">' . $rating->num_games . '</td>';
		echo '<td align="center" class="highlight">' . $rating->games_won . '</td>';
		echo '</tr>';
	}
}
echo '</table>';

if ($p > 0 || ($p + 1) * PAGE_SIZE < $ratings->count)
{
	echo 'Страницы: ';
	$j = 0;
	for ($i = 0; $i < $ratings->count; $i += PAGE_SIZE)
	{
		if ($j == $p)
		{
			echo '<font class="text2" style="background-color:#993300;color:#FFCC99"><b>' . ($j + 1) . '&nbsp;</b></font>';
		}
		else
		{
			echo '<a href="index.php?page=ratings&p=' . $j . '&role=' . $role . '">' . ($j + 1) . '</a>';
		}
		echo '&nbsp;|&nbsp;';
		++$j;
	}
}

?>