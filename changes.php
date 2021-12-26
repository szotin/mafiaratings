<?php

require_once 'include/general_page_base.php';
require_once 'include/snapshot.php';
require_once 'include/scoring.php';

define('ACHIEVEMENTS_COUNT', 100);

class Page extends GeneralPageBase
{
	// private function common_condition()
	// {
		// global $_profile;
		
		// $condition = new SQL(' WHERE (u.flags & ' . USER_FLAG_BANNED . ') = 0 AND u.games > 0');
		// $ccc_id = $this->ccc_filter->get_id();
		// switch ($this->ccc_filter->get_type())
		// {
		// case CCCF_CLUB:
// /*			if ($ccc_id > 0)
			// {
				// $condition->add(' AND u.id IN (SELECT user_id FROM club_users WHERE (flags & ' . USER_CLUB_FLAG_BANNED . ') = 0 AND club_id = ?)', $ccc_id);
			// }
			// else if ($ccc_id == 0 && $_profile != NULL)
			// {
				// $condition->add(' AND u.id IN (SELECT user_id FROM club_users WHERE (flags & ' . USER_CLUB_FLAG_BANNED . ') = 0 AND club_id IN (SELECT club_id FROM club_users WHERE (flags & ' . USER_CLUB_FLAG_BANNED . ') = 0 AND user_id = ?))', $_profile->user_id);
			// }*/
			// if ($ccc_id > 0)
			// {
				// $condition->add(' AND u.club_id = ?', $ccc_id);
			// }
			// else if ($ccc_id == 0 && $_profile != NULL)
			// {
				// $condition->add(' AND u.club_id IN (SELECT club_id FROM club_users WHERE (flags & ' . USER_CLUB_FLAG_BANNED . ') = 0 AND user_id = ?)', $_profile->user_id);
			// }
			// break;
		// case CCCF_CITY:
			// $condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			// break;
		// case CCCF_COUNTRY:
			// $condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			// break;
		// }
		// return $condition;
	// }
	
	protected function show_body()
	{
		global $_page, $_profile;
		
		$time = 0;
		if (isset($_REQUEST['time']))
		{
			$id = (int)$_REQUEST['time'];
		}
		
		$interval = 2;
		if (isset($_REQUEST['interval']))
		{
			$interval = (int)$_REQUEST['interval'];
		}
		
		if ($time > 0)
		{
			list ($time, $json) = Db::record(get_label('snapshot'), 'SELECT time, snapshot FROM snapshots WHERE time <= ? ORDER BY time DESC LIMIT 1', $time);
			$snapshot = new Snapshot($time, $json);
			$snapshot->load_user_details();
			$query = new DbQuery('SELECT time, snapshot FROM snapshots WHERE time < ? ORDER BY time DESC LIMIT ' . $interval, $time);
		}
		else
		{
			$snapshot = new Snapshot(time());
			$snapshot->shot();
			$query = new DbQuery('SELECT time, snapshot FROM snapshots ORDER BY time DESC LIMIT ' . $interval);
		}
		
		$prev_time = 0;
		$prev_snapshot = NULL;
		for ($i = 0; $i < $interval; ++$i)
		{
			$row = $query->next();
			if (!$row)
			{
				$prev_snapshot = new Snapshot($prev_time);
				break;
			}
			list($prev_time, $json) = $row;
		}
		
		if ($prev_snapshot == NULL)
		{
			list($prev_time, $json) = $row;
			$prev_snapshot = new Snapshot($prev_time, $json);
			$prev_snapshot->load_user_details();
		}
		
		echo '<table width="100%"><tr>';
		echo '<td width="300"><table width="100%">';
		$i = 1;
		foreach ($prev_snapshot->top100 as $player)
		{
			echo '<tr><td width="20">' . $i . '</td><td>' . $player->user_name . '</td><td width="80">' . format_rating($player->rating) . '</td></tr>';
			++$i;
		}
		echo '</table></td><td>';
		
		echo '<td valign="top">';
		$diff = $snapshot->compare($prev_snapshot);
		echo '<table width="100%">';
		$i = 0;
		foreach ($diff as $player)
		{
			if ($i++ >= ACHIEVEMENTS_COUNT)
			{
				break;
			}
			
			echo '<tr><td>' . $player->user_name . '</td>';
			echo '<td>' . (isset($player->src) ? $player->src : '') . ' => ' . (isset($player->dst) ? $player->dst : '') . '</td></tr>';
		}
		echo '</table>';
		echo '</td>';
		
		echo '<td width="300"><table width="100%">';
		$i = 1;
		foreach ($snapshot->top100 as $player)
		{
			echo '<tr><td width="20">' . $i . '</td><td>' . $player->user_name . '</td><td width="80">' . format_rating($player->rating) . '</td></tr>';
			++$i;
		}
		echo '</table>';
		echo '</td></tr></table>';
	}
}

$page = new Page();
$page->set_ccc(CCCS_ALL);
$page->run();

?>