<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';
require_once 'include/currency.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_FUTURE', 0x0001);
define('FLAG_FILTER_PAST', 0x0002);
define('FLAG_FILTER_PAYED', 0x0004);
define('FLAG_FILTER_NOT_PAYED', 0x0008);
define('FLAG_FILTER_EMPTY', 0x0010);
define('FLAG_FILTER_NOT_EMPTY', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NOT_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		show_checkbox_filter(array(get_label('future tournaments'), get_label('payed tournaments'), get_label('unplayed tournaments'), get_label('canceled tournaments')), $this->filter);
		echo '</td></tr></table></p>';
		
		$condition = new SQL(
			' FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN leagues l ON l.id = s.league_id' .
			' LEFT OUTER JOIN currencies cu ON cu.id = s.currency_id' .
			' WHERE st.series_id = ?', $this->id);
			
		if ($this->filter & FLAG_FILTER_FUTURE)
		{
			$condition->add(' AND t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		if ($this->filter & FLAG_FILTER_PAST)
		{
			$condition->add(' AND t.start_time < UNIX_TIMESTAMP()');
		}
		if ($this->filter & FLAG_FILTER_PAYED)
		{
			$condition->add(' AND (st.flags & '. SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0');
		}
		if ($this->filter & FLAG_FILTER_NOT_PAYED)
		{
			$condition->add(' AND (st.flags & '. SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') <> 0');
		}
		if ($this->filter & FLAG_FILTER_EMPTY)
		{
			$condition->add(' AND NOT EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0)');
		}
		if ($this->filter & FLAG_FILTER_NOT_EMPTY)
		{
			$condition->add(' AND (EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) OR EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0))');
		}
		if ($this->filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NOT_CANCELED)
		{
			$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
		}
		
		$order_by = ' ORDER BY t.start_time + t.duration, t.id';
		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.address, a.flags, ni.name, st.flags, st.fee, s.fee, cu.pattern, t.num_players,' .
			' (SELECT count(user_id) FROM tournament_places WHERE tournament_id = t.id) as players',
			$condition);
		$query->add($order_by);

		$tournaments = array();
		$delim = '';
		$cs_tournaments = '';
		$first_month_tournament = NULL;
		$currency_pattern = NULL;
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, $tournament->languages,
				$tournament->addr_id, $tournament->addr, $tournament->addr_flags, $tournament->city,
				$tournament->series_tournament_flags, $tournament->series_tournament_fee, $tournament->series_fee, $currency_pattern,
				$tournament->num_players, $tournament->players_count) = $row;
			$m = format_date('F Y', $tournament->time, $tournament->timezone);
			if ($first_month_tournament == NULL || $first_month_tournament->month != $m)
			{
				$tournament->month = $m;
				$tournament->month_count = 1;
				$first_month_tournament = $tournament;
			}
			else
			{
				++$first_month_tournament->month_count;
			}
			
			$tournament->series = array();
			$tournaments[] = $tournament;
			$cs_tournaments .= $delim . $tournament->id;
			$delim = ',';
		}
		
		if (is_null($currency_pattern))
		{
			$currency_pattern = '$#';
		}
		
		// Get tournaments series
		if ($cs_tournaments != '')
		{
			$query = new DbQuery(
				'SELECT st.tournament_id, st.stars, s.id, s.name, s.flags, l.id, l.name, l.flags' .
				' FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN tournaments t ON t.id = st.tournament_id' .
				' JOIN leagues l ON l.id = s.league_id' .
				' WHERE st.tournament_id IN (' . $cs_tournaments . ') ' . $order_by . ', s.id');
			$current_tournament = 0;
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags) = $row;
				while ($current_tournament < count($tournaments) && $tournaments[$current_tournament]->id != $tournament_id)
				{
					++$current_tournament;
				}
				if ($current_tournament < count($tournaments))
				{
					if ($series_id == $this->id)
					{
						$tournaments[$current_tournament]->stars = $stars;
					}
					else
					{
						$series = new stdClass();
						$series->stars = $stars;
						$series->id = $series_id;
						$series->name = $series_name;
						$series->flags = $series_flags;
						$series->league_id = $league_id;
						$series->league_name = $league_name;
						$series->league_flags = $league_flags;
						$tournaments[$current_tournament]->series[] = $series;
					}
				}
			}
		}

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td width="100" rowspan="2"></td>';
		echo '<td colspan="4" rowspan="2" align="center">' . get_label('Tournament') . '</td>';
		echo '<td colspan="2" align="center">' . get_label('Players') . '</td>';
		echo '<td colspan="2" align="center">' . get_label('Payment') . '</td>';
		
		echo '<tr class="th-long darker">';
		echo '<td width="60" align="center">' . get_label('Expected') . '</td>';
		echo '<td width="60" align="center">' . get_label('Real') . '</td>';
		echo '<td width="60" align="center">' . get_label('Expected') . '</td>';
		echo '<td width="60" align="center">' . get_label('Real') . '</td>';
		echo '</tr>';
		
		$now = time();
		$num = 0;
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
		$total_expected = 0;
		$total = 0;
		foreach ($tournaments as $tournament)
		{
			echo '<tr>';
			
			if (isset($tournament->month))
			{
				echo '<td rowspan="' . $tournament->month_count . '" class="dark" width="100" align="center"><b>' . $tournament->month . '</b></td>';
			}
			$main_class = $now <= $tournament->time ? ' class="dark"' : '';
			
			echo '<td align="center" width="30"' . $main_class . '><b>' . ++$num . '</b></td>';
			echo '<td width="20"><button class="icon" onclick="paymentClicked(' . $tournament->id . ')" title="' . get_label('Edit tournament payment.') . '"><img src="images/edit.png"></button></td>';
			echo '<td' . $main_class . '><table width="100%" class="transp"><tr>';
			echo '<td width="80" align="center" valign="center">';
			$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
			$tournament_pic->show(ICONS_DIR, true, 60, 60, NULL, ($tournament->series_tournament_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED) ? 'not_payed.png' : NULL);
			echo '</td>';
			echo '<td><b><a href="tournament_standings.php?bck=1&id=' . $tournament->id . '">' . $tournament->name;
			echo '</a></b>';
			if (isset($tournament->stars))
			{
				echo '<br><font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($tournament->stars) . '</font>';
			}
			echo '</td>';
			foreach ($tournament->series as $series)
			{
				echo '<td width="64" align="center" valign="center">';
				echo '<font style="color:#B8860B; font-size:14px;">' . tournament_stars_str($series->stars) . '</font>';
				echo '<br><a href="series_standings.php?bck=1&id=' . $series->id . '">';
				$series_pic->set($series->id, $series->name, $series->flags)->set($series->league_id, $series->league_name, $series->league_flags);
				$series_pic->show(ICONS_DIR, false, 32);
				echo '</a></td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td width="160" valign="center" class="dark"s>';
			echo '<table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			$club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
			$club_pic->show(ICONS_DIR, false, 40);
			echo '</td>';
			echo '<td><b>' . $tournament->city  . '</b><br>' . format_date('F d, Y', $tournament->time, $tournament->timezone) . '</td>';
			echo '</tr></table></td>';
			
			echo '<td align="center">' . $tournament->num_players . '</td>';
			echo '<td align="center"><a href="tournament_standings.php?bck=1&id=' . $tournament->id . '">' . $tournament->players_count . '</a></td>';

			$total_expected += $tournament->num_players * $tournament->series_fee;
			echo '<td align="center">' . format_currency($tournament->num_players * $tournament->series_fee, $currency_pattern, false) . '</td>';
			if (!is_null($tournament->series_fee) && $now > $tournament->time)
			{
				if (is_null($tournament->series_tournament_fee))
				{
					$payment = $tournament->players_count * $tournament->series_fee;
				}
				else
				{
					$payment = $tournament->series_tournament_fee;
				}
				if ($tournament->series_tournament_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED)
				{
					echo '<td align="center" class="darker"><s>' . format_currency($payment, $currency_pattern, false) . '</s></td>';
				}
				else
				{
					echo '<td align="center" class="darker">' . format_currency($payment, $currency_pattern, false) . '</td>';
					$total += $payment;
				}
			}
			else
			{
				echo '<td class="dark"></td>';
			}
			echo '</tr>';
		}
		echo '<tr class="th-long darker"><td colspan="7" align="center">' . get_label('Total') .':</td>';
		echo '<td align="center">' . format_currency($total_expected, $currency_pattern, false) . '</td>';
		echo '<td align="center" class="darkest">' . format_currency($total, $currency_pattern, false) . '</td>';
		echo '</tr></table>';
	}
	
	
	protected function js()
	{
?>		
		function paymentClicked(id)
		{
			dlg.form("form/tournament_payment.php?tournament_id=" + id + "&series_id=<?php echo $this->id; ?>", refr, 400);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>