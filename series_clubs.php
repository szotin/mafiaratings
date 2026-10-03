<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends SeriesPageBase
{
	protected function show_body()
	{
		global $_lang;

		$is_manager = is_series_manager($this->owner, $this->id);

		// Two kinds of club on one list. The permanent ones are here because of who owns the
		// series - the owning club, or every club of the owning league - and cannot be taken off
		// it. The invited ones are in series_clubs and can.
		$permanent = get_series_permanent_clubs($this->owner);

		// An invitation that has become permanent is left out here, so the club shows once rather
		// than twice. It happens on its own: a club invited to a series of a league can join that
		// league afterwards, and then it is on the series twice over.
		$clubs = array();
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, ni.name FROM clubs c' .
			' JOIN cities i ON i.id = c.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' WHERE c.id IN (SELECT club_id FROM series_clubs WHERE series_id = ?)', $this->id);
		if (count($permanent) > 0)
		{
			$query->add(' AND c.id NOT IN (' . implode(',', array_keys($permanent)) . ')');
		}
		$query->add(' ORDER BY c.name');
		while ($row = $query->next())
		{
			$clubs[] = $row;
		}

		// The permanent ones are read in the same shape, so the list reads as one set of clubs.
		$permanent_clubs = array();
		if (count($permanent) > 0)
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, c.flags, ni.name FROM clubs c' .
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
				' WHERE c.id IN (' . implode(',', array_keys($permanent)) . ')');
			$query->add(' ORDER BY c.name');
			while ($row = $query->next())
			{
				$permanent_clubs[] = $row;
			}
		}
		// Invited clubs first: they are the ones somebody chose to put here, and the ones with a
		// button to act on. The clubs that come with the owner follow.
		$clubs = array_merge($clubs, $permanent_clubs);

		// The button shares the line with the explanation, pushed to the right. Its cell is kept as
		// narrow as its content (width 1 plus nowrap) so the text gets the rest of the line.
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo get_label('Clubs of this list can enter their tournaments into the series. It gives them no rights to manage the series.');
		echo '</td>';
		if ($is_manager)
		{
			echo '<td width="1" align="right" nowrap><button onclick="mr.transferSeries(' . $this->id . ')" title="' .
				get_label('Give the series to another club or league.') . '">' . get_label('Transfer the series') . '</button></td>';
		}
		echo '</tr></table></p>';

		$club_pic = new Picture(CLUB_PICTURE);
		$column_count = 0;
		$clubs_count = 0;

		echo '<table class="bordered" width="100%">';
		if ($is_manager)
		{
			echo '<tr><td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';
			echo '<table class="transp" width="100%">';
			echo '<tr><td align="left" class="light wide"><img src="images/transp.png" height="26"></td></tr>';
			echo '<tr><td align="center"><a href="#" onclick="mr.addSeriesClub(' . $this->id . ')">' . get_label('Add [0]', get_label('club'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr></table>';
			echo '</td>';
			++$column_count;
			++$clubs_count;
		}

		foreach ($clubs as $row)
		{
			list ($club_id, $club_name, $club_flags, $city_name) = $row;
			$club_id = (int)$club_id;
			if ($column_count == 0)
			{
				if ($clubs_count > 0)
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';
			// One cell, because the two things that can stand here never stand together: a club on
			// the series by ownership says so and cannot be removed, any other one carries the
			// remove button. A second column would only push the caption off centre.
			//
			// The height is the height of button.icon, so every card's header is the same whichever
			// of the two it holds, and a cell is middle-aligned by default, which puts the caption
			// in the centre both ways. No spacer image: a 1x1 scaled to 26 high is 26 wide as well,
			// and that is what was pushing the caption to the right and standing it on the bottom.
			echo '<table class="transp" width="100%"><tr class="darker">';
			if (isset($permanent[$club_id]))
			{
				echo '<td align="center" style="padding:2px; height: 28px;">';
				echo '<b>' . ($this->owner->is_league() ? get_label('League club') : get_label('Owner')) . '</b></td>';
			}
			else
			{
				echo '<td align="left" style="padding:2px; height: 28px;">';
				if ($is_manager)
				{
					echo '<button class="icon" onclick="mr.removeSeriesClub(' . $this->id . ', ' . $club_id . ', \'' .
						get_label('Are you sure you want to remove [0] from [1]?', $club_name, $this->name) . '\')" title="' .
						get_label('Remove [0] from [1]', $club_name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
				}
				else
				{
					echo '&nbsp;';
				}
				echo '</td>';
			}
			echo '</tr>';

			echo '<tr><td align="center"><a href="club_main.php?bck=1&id=' . $club_id . '">';
			echo '<b>' . $club_name . '</b><br>';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false);
			echo '<br></a>' . $city_name;
			echo '</td></tr></table>';
			echo '</td>';

			++$clubs_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}

		if ($clubs_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>
