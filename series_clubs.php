<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/club_cards.php';

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
			'SELECT c.id, nc.name, c.flags, ni.name, (SELECT count(*) FROM games g WHERE g.club_id = c.id) FROM clubs c' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = c.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' WHERE c.id IN (SELECT club_id FROM series_clubs WHERE series_id = ?)', $this->id);
		if (count($permanent) > 0)
		{
			$query->add(' AND c.id NOT IN (' . implode(',', array_keys($permanent)) . ')');
		}
		$query->add(' ORDER BY nc.name');
		while ($row = $query->next())
		{
			$clubs[] = $row;
		}

		// The permanent ones are read in the same shape, so the list reads as one set of clubs.
		$permanent_clubs = array();
		if (count($permanent) > 0)
		{
			$query = new DbQuery(
				'SELECT c.id, nc.name, c.flags, ni.name, (SELECT count(*) FROM games g WHERE g.club_id = c.id) FROM clubs c' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
				' WHERE c.id IN (' . implode(',', array_keys($permanent)) . ')');
			$query->add(' ORDER BY nc.name');
			while ($row = $query->next())
			{
				$permanent_clubs[] = $row;
			}
		}
		// Invited clubs first: they are the ones somebody chose to put here, and the ones with a
		// button to act on. The clubs that come with the owner follow.
		$clubs = array_merge($clubs, $permanent_clubs);

		// The explanation and the buttons of a manager in the toolbar of the lists: transferring the
		// series beside the words, adding a club at the end of the line.
		echo '<div class="toolbar"><div class="tb-line tb-start"><span class="tb-note">' . get_label('Clubs of this list can enter their tournaments into the series. It gives them no rights to manage the series.') . '</span>';
		if ($is_manager)
		{
			echo '<button onclick="mr.transferSeries(' . $this->id . ')" title="' . get_label('Give the series to another club or league.') . '">' . get_label('Transfer the series') . '</button>';
			echo '<a class="btn tb-create" href="#" onclick="mr.addSeriesClub(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add [0]', get_label('club')) . '</span></a>';
		}
		echo '</div></div>';
		
		// The tiles of clubs.php (include/club_cards.php). A club on the series by ownership says so
		// in a chip and cannot be removed; any other one carries the remove button.
		$tiles = array();
		foreach ($clubs as $row)
		{
			list ($club_id, $club_name, $club_flags, $city_name, $games) = $row;
			$club = new stdClass();
			$club->id = (int)$club_id;
			$club->name = $club_name;
			$club->flags = $club_flags;
			$club->city = $city_name;
			$club->games = $games;
			if (isset($permanent[$club->id]))
			{
				$club->extra = '<span class="tile-meta"><span class="chip pending">' . ($this->owner->is_league() ? get_label('League club') : get_label('Owner')) . '</span></span>';
			}
			else if ($is_manager)
			{
				$club->buttons = '<button class="icon" onclick="mr.removeSeriesClub(' . $this->id . ', ' . $club->id . ', \'' .
					get_label('Are you sure you want to remove [0] from [1]?', $club_name, $this->name) . '\')" title="' .
					get_label('Remove [0] from [1]', $club_name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
			}
			$tiles[] = $club;
		}
		club_tiles_show($tiles, get_label('No clubs found.'));
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>
