<?php

require_once 'include/general_page_base.php';
require_once 'include/rules.php';
require_once 'include/game_log.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		if (isset($_REQUEST['convert_games']))
		{
			Db::begin();
			$query = new DbQuery('SELECT id, log, rules FROM games');
			$games = array();
			while ($row = $query->next())
			{
				$games[] = $row;
			}
			
			$count = 1;
			foreach ($games as $row)
			{
				list($id, $log, $rules_code) = $row;
				
				$offset = 0;
				$offset = strpos($log, GAME_PARAM_DELIMITER, $offset) + 1;
				$version  = (int)substr($log, 0, $offset);
				if ($version > 6 && $version <= CURRENT_LOG_VERSION)
				{
					$offset = strpos($log, GAME_PARAM_DELIMITER, $offset);
					$new_log = $version . GAME_PARAM_DELIMITER . $rules_code . substr($log, $offset);
					echo $count . ': ' . $id . '<br>' . $log . '<br>' . $new_log . '<br><br>';
					++$count;
					Db::exec('game', 'UPDATE games SET log = ? WHERE id = ?', $new_log, $id);
				}
			}
			Db::commit();
		}
		else
		{
			$query = new DbQuery('SELECT id, flags FROM rules ORDER BY id');
			while ($row = $query->next())
			{
				list($id, $flags) = $row;
				$rules = convert_old_rules($flags);
				
				echo 'UPDATE tournaments SET rules = "' . $rules . '" WHERE rules_id = ' . $id . ';<br>';
				echo 'UPDATE club_rules SET rules = "' . $rules . '" WHERE rules_id = ' . $id . ';<br>';
				echo 'UPDATE events SET rules = "' . $rules . '" WHERE rules_id = ' . $id . ';<br>';
				echo 'UPDATE games SET rules = "' . $rules . '" WHERE rules_id = ' . $id . ';<br>';
				echo 'UPDATE clubs SET rules = "' . $rules . '" WHERE rules_id = ' . $id . ';<br><br>';
			}
		}
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run('Rules sql');

?>

