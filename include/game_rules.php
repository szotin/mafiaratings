<?php

require_once 'include/db.php';
require_once 'include/names.php';

define('RULES_FLAG_DEFENSIVE_ROUND', 0x1); // all nominated players speak once again before voting
define('RULES_FLAG_FREE_ROUND', 0x2); // players have a short non moderated discussion every day
define('RULES_FLAG_DAY1_NO_KILL', 0x4); // no one is killed in the first round - winner just speaks
define('RULES_FLAG_NO_CRASH_4', 0x8); // when there are 4 players and votes are 2-2, no on is killed - the night is falling
define('RULES_FLAG_NIGHT_KILL_CAN_NOMINATE', 0x10); // a player killed in night can nominate
define('RULES_FLAG_DAY1_NO_DIFF', 0x20); // voting and killing in the day1 is the same as in any other day. If not set, when only one player is nominated - no voting, no killing.
define('RULES_MASK_VOTING_CANCEL_MASK', 0xc0); // values within this mask specify if voting is canceled when a player have left the table by warnings
define('RULES_VOTING_CANCEL_YES', 0x0); // voting is always canceled when someone is killed by warnings
define('RULES_VOTING_CANCEL_NO', 0x40); // voting is never canceled when someone is killed by warnings
define('RULES_VOTING_CANCEL_BY_NOM', 0x80); // voting is canceled when someone is killed by warnings only if the one was nominated
define('RULES_BEST_PLAYER', 0x100); // moderator chooses best player after the game
define('RULES_BEST_MOVE', 0x200); // moderator chooses best move after the game
define('RULES_GUESS_MAFIA', 0x400); // a player killed first night guesses 3 mafiosi
define('RULES_SIMPLIFIED_CLIENT', 0x800); // moderators must use simplified client (does not work on championships)
define('RULES_ANY_CLIENT', 0x1000); // it is up to moderators whether to use simplified client or not. If both RULES_SIMPLIFIED_CLIENT and RULES_ANY_CLIENT are set, RULES_SIMPLIFIED_CLIENT wins.
define('RULES_MUTE_NEXT', 0x2000); // if a player gets third warning during a day he misses his speech only next day (not this day)
define('RULES_MUTE_CRIT', 0x4000); // a player with 3 warnings is allowed to speak 30 sec in a critical round with 3 or 4 players left.

class GameRules
{
    public $id;
    public $flags;
    public $st_free; // st stands for speech time
    public $spt_free; // spt stands for speech prompt time: time left to the end of the speech when moderator prompts player
    public $st_reg;
    public $spt_reg;
    public $st_killed;
    public $spt_killed;
    public $st_def;
    public $spt_def;
	
	private static function get_param($param_name, $def_value)
	{
		$value = $def_value;
		if (isset($_REQUEST[$param_name]))
		{
			$value = $_REQUEST[$param_name];
			if (!is_numeric($value) || $value <= 0)
			{
				$value = $def_value;
			}
		}
		return $value;
	}
	
	function __construct()
    {
		$this->id = -1;
		$this->flags = RULES_BEST_PLAYER;
		$this->st_free = 300;
		$this->spt_free = 15;
    	$this->st_reg = 60;
    	$this->spt_reg = 10;
    	$this->st_killed = 60;
    	$this->spt_killed = 10;
    	$this->st_def = 30;
    	$this->spt_def = 5;
    }
	
	function init()
	{
		$this->flags = GameRules::get_param('flags', $this->flags);
		$this->st_reg = GameRules::get_param('st_reg', $this->st_reg);
		$this->spt_reg = GameRules::get_param('spt_reg', $this->spt_reg);
		$this->st_killed = GameRules::get_param('st_killed', $this->st_killed);
		$this->spt_killed = GameRules::get_param('spt_killed', $this->spt_killed);
		$this->st_def = GameRules::get_param('st_def', $this->st_def);
		$this->spt_def = GameRules::get_param('spt_def', $this->spt_def);
		$this->st_free = GameRules::get_param('st_free', $this->st_free);
		$this->spt_free = GameRules::get_param('spt_free', $this->spt_free);
	}
	
	function load($id)
	{
		list (
			$this->flags, $this->st_free, $this->st_reg, $this->st_killed, $this->st_def,
			$this->spt_free, $this->spt_reg, $this->spt_killed, $this->spt_def) =
				Db::record(get_label('rules'), 'SELECT flags, st_free, st_reg, st_killed, st_def, spt_free, spt_reg, spt_killed, spt_def FROM rules WHERE id = ?', $id);
		$this->id = $id;
	}
	
	function save()
	{
		$query = new DbQuery(
			'SELECT id FROM rules ' .
				'WHERE flags = ? AND st_free = ? AND st_reg = ? AND st_killed = ? AND st_def = ? ' .
				'AND spt_free = ? AND spt_reg = ? AND spt_killed = ? AND spt_def = ?',
			$this->flags, $this->st_free, $this->st_reg, $this->st_killed, $this->st_def, 
			$this->spt_free, $this->spt_reg, $this->spt_killed, $this->spt_def);
		
		if (!($row = $query->next()))
		{
			Db::begin();
			Db::exec(
				get_label('rules'), 
				'INSERT INTO rules (flags, st_free, st_reg, st_killed, st_def, spt_free, spt_reg, spt_killed, spt_def) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$this->flags, $this->st_free, $this->st_reg, $this->st_killed, $this->st_def, 
				$this->spt_free, $this->spt_reg, $this->spt_killed, $this->spt_def);
			$row = Db::record(get_label('rules'), 'SELECT LAST_INSERT_ID()');
			$log_details = 
				'flags=' . $this->flags . 
				', st_free=' . $this->st_free . 
				"<br>st_reg=" . $this->st_reg . 
				"<br>st_killed=" . $this->st_killed . 
				"<br>st_def=" . $this->st_def . 
				"<br>spt_free=" . $this->spt_free . 
				"<br>spt_reg=" . $this->spt_reg . 
				"<br>spt_killed=" . $this->spt_killed . 
				"<br>spt_def=" . $this->spt_def;
			db_log('rules', 'Created', $log_details, $row[0]);
			Db::commit();
		}
		return $row[0];
	}
	
	function create($club_id, $rules_name)
	{
		Db::begin();
		$rules_id = $this->save();
		
		if ($rules_name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('rules name')));
		}
		check_name($rules_name, get_label('rules name'));
		$query = new DbQuery('SELECT rules_id FROM club_rules WHERE club_id = ? AND name = ?', $club_id, $rules_name);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('rules name'), $rules_name));
		}
		
		$query = new DbQuery('SELECT r.name, c.name FROM club_rules r, clubs c WHERE c.id = r.club_id AND r.club_id = ? AND r.rules_id = ?', $club_id, $rules_id);
		if ($row = $query->next())
		{
			list ($rules_name, $club_name) = $row;
			throw new Exc(get_label('You are already using these rules by the name "[0]".', $rules_name));
		}
		
		list($club_rules_id) = Db::record(get_label('club'), 'SELECT rules_id FROM clubs WHERE id = ?', $club_id);
		if ($club_rules_id == $rules_id)
		{
			throw new Exc(get_label('These rules are not different from the default rules of your club. Please change one of the rules.'));
		}
		
		Db::exec(
			get_label('rules'), 
			'INSERT INTO club_rules (rules_id, club_id, name) VALUES (?, ?, ?)',
			$rules_id, $club_id, $rules_name);
		$log_details = 
			'name=' . $rules_name .
			"<br>rules_id=" . $rules_id;
		db_log('rules', 'Created', $log_details, $rules_id, $club_id);
		
		Db::commit();
		$this->id = $rules_id;
	}
	
	function update($club_id, $rules_id = -1, $rules_name = NULL)
	{
		global $_profile;
	
		Db::begin();
		$new_rules_id = $this->save();
		
		if ($rules_id <= 0)
		{
			$query = new DbQuery('SELECT name FROM club_rules WHERE club_id = ? AND rules_id = ?', $club_id, $new_rules_id);
			if ($row = $query->next())
			{
				list ($name) = $row;
				throw new Exc(get_label('Such rules already exist in your club by the name [0]. Please delete them and then change the default rules.', $name));
			}
				
			Db::exec(get_label('event'), 'UPDATE events e, clubs c SET e.rules_id = ? WHERE e.club_id = c.id AND c.id = ? AND e.rules_id = c.rules_id AND e.start_time + e.duration > UNIX_TIMESTAMP()', $new_rules_id, $club_id);
			Db::exec(get_label('club'), 'UPDATE clubs SET rules_id = ? WHERE id = ?', $new_rules_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'rules_id=' . $new_rules_id;
				db_log('club', 'Changed', $log_details, $club_id, $club_id);
			}
		}
		else
		{
			list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
			$update = NULL;
		
			$log_details = '';
			if ($rules_name != NULL)
			{
				check_name($rules_name, get_label('rules name'));
				$query = new DbQuery('SELECT rules_id FROM club_rules WHERE club_id = ? AND name = ? AND rules_id <> ?', $club_id, $rules_name, $rules_id);
				if ($query->next())
				{
					throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('rules name'), $rules_name));
				}
				
				$update = new DbQuery('UPDATE club_rules SET name = ?', $rules_name);
				$log_details .= "<br>name=" . $rules_name;
			}
			
			if ($rules_id != $new_rules_id)
			{
				$query = new DbQuery('SELECT r.name, c.name FROM club_rules r, clubs c WHERE c.id = r.club_id AND r.club_id = ? AND r.rules_id = ?', $club_id, $new_rules_id);
				if ($row = $query->next())
				{
					list ($name, $club_name) = $row;
					throw new Exc(get_label('You are already using these rules by the name "[0]".', $name));
				}
				
				list($club_rules_id) = Db::record(get_label('club'), 'SELECT rules_id FROM clubs WHERE id = ?', $club_id);
				if ($club_rules_id == $new_rules_id)
				{
					throw new Exc(get_label('These rules are not different from the default rules of your club. Please change one of the rules.'));
				}
				
				Db::exec(get_label('event'), 'UPDATE events SET rules_id = ? WHERE club_id = ? AND rules_id = ? AND start_time + duration > UNIX_TIMESTAMP()', $new_rules_id, $club_id, $rules_id);
				if ($update == NULL)
				{
					$update = new DbQuery('UPDATE club_rules SET rules_id = ?', $new_rules_id);
					$log_details .= "<br>rules_id=" . $new_rules_id;
				}
				else
				{
					$update->add(', rules_id = ?', $new_rules_id);
					$log_details .= "<br>rules_id=" . $new_rules_id;
				}
			}
			
			if ($update != NULL)
			{
				$update->add(' WHERE club_id = ? AND rules_id = ?', $club_id, $rules_id);
				$update->exec(get_label('rules'));
				if (Db::affected_rows() > 0)
				{
					db_log('club', 'Rules changed', $log_details, $club_id, $club_id);
				}
			}
		}
		Db::commit();
		$this->id = $new_rules_id;
		if ($rules_id < 0)
		{
			$_profile->clubs[$club_id]->rules_id = $new_rules_id;
		}
	}
	
	function show_copy_select($club_id)
	{
		$rules_list = array();
		$rules_list[] = array('', 0);
		$query = new DbQuery('SELECT name, rules_id FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$rules_list[] = $row;
		}
		if (count($rules_list) > 1)
		{
			$rules_list[] = array('---', 0);
		}
		$query = new DbQuery('SELECT name, rules_id FROM clubs WHERE (flags & ' . CLUB_FLAG_RETIRED . ') = 0 AND id <> ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$rules_list[] = $row;
		}
		
		echo '<input type="hidden" name="club" value="' . $club_id . '">';
		echo '<table class="transp" width="100%"><tr><td align="right">' . get_label('Copy rules from') . ': <select id="form-copy" onChange="copyRules()">';
		foreach ($rules_list as $r)
		{
			list ($rname, $rid) = $r;
			echo '<option value="' . $rid . '">' . $rname . '</option>';
		}
		echo '</select></td></tr></table>';
?>
		<script>
		function copyRules()
		{
			json.post("rules_ops.php", { 'get': $('#form-copy').val() }, setFormRules);
		}
		</script>
<?php
	}
	
	function show_form()
	{
		$num = 0;
	
		echo '<tr><td class="dark" align="center" width="80">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Do you kill in the first day?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-kill1" id="form-kill1-0">' . get_label('We are using classic rules. We vote and kill only if there is more than one nominated player.');
		echo '<br><input type="radio" name="form-kill1" id="form-kill1-1">' . get_label('We do not kill in the first day. The player who got the most votes speaks the defensive speech instead.');
		echo '<br><input type="radio" name="form-kill1" id="form-kill1-2">' . get_label('Our first day is the same as any other day. Players vote and kill with no exceptions.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Is auto-crash possible when there are only four alive players?') . '</b></p>';
		echo '<p>' . get_label('Auto-crash is a multiple kill during a day. When two or more players get the same amount of votes, other players may decide to get rid of all of them. This is called auto-crash.') . '</p>';
		echo '<p>';
		echo '<input type="radio" name="form-no_crash4" id="form-no_crash4-0">' . get_label('Yes, we are using classic rules. Four players day works as any other day. Auto-crash is allowed.');
		echo '<br><input type="radio" name="form-no_crash4" id="form-no_crash4-1">' . get_label('No, auto-crash with only four players gives civilians too much advantage. If votes are two-by-two we do not do second voting. Nobody is killed, the night is falling.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('If a player is killed by warnings during the day, do you cancel votings?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-vcancel" id="form-vcancel-0">' . get_label('Yes. If a player is killed by warnings, votings get canceled.');
		echo '<br><input type="radio" name="form-vcancel" id="form-vcancel-1">' . get_label('No. We never cancel votings.');
		echo '<br><input type="radio" name="form-vcancel" id="form-vcancel-2">' . get_label('Depends. If a player is killed by warnings, votings get canceled only if this player is nominated.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center" width="80">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('If a player gets third warning during the day, when does he misses his speech?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-mute" id="form-mute-0">' . get_label('The same day if possible.');
		echo '<br><input type="radio" name="form-mute" id="form-mute-1">' . get_label('The next day.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center" width="80">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('If a player has three warnings and has to miss his speech in a critical round with only one mafiosi left, do you let him speak?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-mute-crit" id="form-mute-crit-0">' . get_label('No.');
		echo '<br><input type="radio" name="form-mute-crit" id="form-mute-crit-1">' . get_label('Yes, but we allow him a shorter "defensive" speech.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Do you choose best player?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-bestp" id="form-bestp-0">' . get_label('Yes');
		echo '<br><input type="radio" name="form-bestp" id="form-bestp-1">' . get_label('No');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Do you choose best move?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-bestm" id="form-bestm-0">' . get_label('Yes');
		echo '<br><input type="radio" name="form-bestm" id="form-bestm-1">' . get_label('No');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Does a player killed in the first night guess 3 mafiosi?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-guess3" id="form-guess3-0">' . get_label('Yes');
		echo '<br><input type="radio" name="form-guess3" id="form-guess3-1">' . get_label('No');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Can a player killed in a night nominate players during his/her last speech?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-nightnom" id="form-nightnom-0">' . get_label('No, we are using classic rules. Dead player is dead, he/she can not nominate.');
		echo '<br><input type="radio" name="form-nightnom" id="form-nightnom-1">' . get_label('Yes, why not. If a dead player can speak why he/she can not nominate?');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('How long are your players speaking during normal daytime speech?') . '</b></p>';
		echo '<p><select id="form-st_reg">';
		echo '<option value="20">' . get_label('20 seconds');
		echo '</option><option value="30">' . get_label('30 seconds');
		echo '</option><option value="45">' . get_label('45 seconds');
		echo '</option><option value="60">' . get_label('1 minute');
		echo '</option><option value="120">' . get_label('2 minutes');
		echo '</option><option value="180">' . get_label('3 minutes');
		echo '</option><option value="300">' . get_label('5 minutes');
		echo '</option></select> ' . get_label('and prompt player on') . ' <select id="form-spt_reg">';
		echo '<option value="5">' . get_label('5 seconds');
		echo '</option><option value="10">' . get_label('10 seconds');
		echo '</option><option value="15">' . get_label('15 seconds');
		echo '</option></select> ' . get_label('before the end of the speech') . '</td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('How long are your players speaking their defensive speeches?') . '</b></p>';
		echo '<p>' . get_label('For example if two players are voting winners they have to speak to defend themselves. Or if you do not kill in the first day, how long does distrusted player speak? Or if you have a defensive round, how long do they speak?') . '</p>';
		echo '<p><select id="form-st_def">';
		echo '<option value="20">' . get_label('20 seconds');
		echo '</option><option value="30">' . get_label('30 seconds');
		echo '</option><option value="45">' . get_label('45 seconds');
		echo '</option><option value="60">' . get_label('1 minute');
		echo '</option><option value="120">' . get_label('2 minutes');
		echo '</option><option value="180">' . get_label('3 minutes');
		echo '</option><option value="300">' . get_label('5 minutes');
		echo '</option></select> ' . get_label('and prompt player on') . ' <select id="form-spt_def">';
		echo '<option value="5">' . get_label('5 seconds');
		echo '</option><option value="10">' . get_label('10 seconds');
		echo '</option><option value="15">' . get_label('15 seconds');
		echo '</option></select> ' . get_label('before the end of the speech') . '</td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('How long is the last speech in your club for a killed player?') . '</b></p>';
		echo '<p><select id="form-st_killed">';
		echo '<option value="20">' . get_label('20 seconds');
		echo '</option><option value="30">' . get_label('30 seconds');
		echo '</option><option value="45">' . get_label('45 seconds');
		echo '</option><option value="60">' . get_label('1 minute');
		echo '</option><option value="120">' . get_label('2 minutes');
		echo '</option><option value="180">' . get_label('3 minutes');
		echo '</option><option value="300">' . get_label('5 minutes');
		echo '</option></select> ' . get_label('and prompt player on') . ' <select id="form-spt_killed">';
		echo '<option value="5">' . get_label('5 seconds');
		echo '</option><option value="10">' . get_label('10 seconds');
		echo '</option><option value="15">' . get_label('15 seconds');
		echo '</option></select> ' . get_label('before the end of the speech') . '</td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Do you have a defensive round during the day?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-defround" id="form-defround-0">' . get_label('Yes, all nominated players speak defensive speeches before voting.');
		echo '<br><input type="radio" name="form-defround" id="form-defround-1">' . get_label('No, we are using classic rules. Nominated players speak defensive speeches only in the second voting round, when votes are equal.');
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Do you have a free speeking round at the beginning of a day?') . '</b></p>';
		echo '<p>';
		echo '<input type="radio" name="form-freeround" id="form-freeround-0" onclick="freeRoundClick()">' . get_label('Yes, we start every day with non-moderated discussion when everybody is allowed to speak at any time.');
		echo '<br><input type="radio" name="form-freeround" id="form-freeround-1" onclick="freeRoundClick()">' . get_label('No, we are using classic rules. All speeches are moderated.');
		echo '</p><p>';
		echo '<div id="form-defdur"><select id="form-st_free">';
		echo '<option value="60">' . get_label('1 minute');
		echo '</option><option value="120">' . get_label('2 minutes');
		echo '</option><option value="180">' . get_label('3 minutes');
		echo '</option><option value="300">' . get_label('5 minutes');
		echo '</option><option value="600">' . get_label('10 minutes');
		echo '</option><option value="900">' . get_label('15 minutes');
		echo '</option><option value="1200">' . get_label('20 minutes');
		echo '</option></select> ' . get_label('and prompt on') . ' <select id="form-spt_free">';
		echo '<option value="5">' . get_label('5 seconds');
		echo '</option><option value="10">' . get_label('10 seconds');
		echo '</option><option value="15">' . get_label('15 seconds');
		echo '</option><option value="20">' . get_label('20 seconds');
		echo '</option><option value="30">' . get_label('30 seconds');
		echo '</option><option value="60">' . get_label('1 minute');
		echo '</option></select> ' . get_label('before the end of free discussion') . '</div>';
		echo '</p></td></tr>';

		echo '<tr><td class="dark" align="center" width="80">' . ++$num . '</td>';
		echo '<td class="light"><p><b>' . get_label('Are you using simplified voting?') . '</b></p>';
		echo '<p>' . get_label('When simplified voting is on moderators do not enter voting details. They enter results only. That simplifies and speeds up moderation but looses important stats about voting. Choose it if your moderators are not experienced enough with [0].', PRODUCT_NAME) . '</p>';
		echo '<p>';
		echo '<input type="radio" name="form-client" id="form-client-1">' . get_label('No, we are not using simplified voting.');
		echo '<br><input type="radio" name="form-client" id="form-client-2">' . get_label('Yes, our moderators must use it.');
		echo '<br><input type="radio" name="form-client" id="form-client-0">' . get_label('Up to a moderator.');
		echo '</p></td></tr>';

?>
		<script>
		function generateRules()
		{
			var r =
			{
				flags: <?php echo $this->flags; ?>,
				st_reg: <?php echo $this->st_reg; ?>,
				spt_reg: <?php echo $this->spt_reg; ?>,
				st_def: <?php echo $this->st_def; ?>,
				spt_def: <?php echo $this->spt_def; ?>,
				st_killed: <?php echo $this->st_killed; ?>,
				spt_killed: <?php echo $this->spt_killed; ?>,
				st_free: <?php echo $this->st_free; ?>,
				spt_free: <?php echo $this->spt_free; ?>
			};
			return r;
		}
		
		function freeRoundClick()
		{
			if ($('#form-freeround-0').prop('checked'))
				$('#form-defdur').show();
			else
				$('#form-defdur').hide();
		}
		
		function setFormRules(r)
		{
			if (typeof r == "undefined")
				r = generateRules();
				
			var c;
			switch (r.flags & <?php echo RULES_FLAG_DAY1_NO_DIFF | RULES_FLAG_DAY1_NO_KILL; ?>)
			{
			case <?php echo RULES_FLAG_DAY1_NO_DIFF; ?>:
				c = 2;
				break;
			case <?php echo RULES_FLAG_DAY1_NO_KILL; ?>:
				c = 1;
				break;
			default:
				c = 0;
				break;
			}
			$('#form-kill1-' + c).attr('checked', '');
			
			c = (r.flags & <?php echo RULES_FLAG_NO_CRASH_4; ?>) ? 1 : 0;
			$('#form-no_crash4-' + c).attr('checked', '');

			switch(r.flags & <?php echo RULES_MASK_VOTING_CANCEL_MASK; ?>)
			{
			case <?php echo RULES_VOTING_CANCEL_NO; ?>:
				c = 1;
				break;
			case <?php echo RULES_VOTING_CANCEL_BY_NOM; ?>:
				c = 2;
				break;
			default:
				c = 0;
				break;
			}
			$('#form-vcancel-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_BEST_PLAYER; ?>) ? 0 : 1;
			$('#form-bestp-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_BEST_MOVE; ?>) ? 0 : 1;
			$('#form-bestm-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_GUESS_MAFIA; ?>) ? 0 : 1;
			$('#form-guess3-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_FLAG_NIGHT_KILL_CAN_NOMINATE; ?>) ? 1 : 0;
			$('#form-nightnom-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_FLAG_DEFENSIVE_ROUND; ?>) ? 0 : 1;
			$('#form-defround-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_FLAG_FREE_ROUND; ?>) ? 0 : 1;
			$('#form-freeround-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_MUTE_NEXT; ?>) ? 1 : 0;
			$('#form-mute-' + c).attr('checked', '');

			c = (r.flags & <?php echo RULES_MUTE_CRIT; ?>) ? 1 : 0;
			$('#form-mute-crit-' + c).attr('checked', '');

			if (r.flags & <?php echo RULES_SIMPLIFIED_CLIENT; ?>)
				c = 2;
			else if (r.flags & <?php echo RULES_ANY_CLIENT; ?>)
				c = 0;
			else
				c = 1;
			$('#form-client-' + c).attr('checked', '');
			
			$('#form-st_reg').val(r.st_reg);
			$('#form-spt_reg').val(r.spt_reg);
			$('#form-st_def').val(r.st_def);
			$('#form-spt_def').val(r.spt_def);
			$('#form-st_killed').val(r.st_killed);
			$('#form-spt_killed').val(r.spt_killed);
			$('#form-st_free').val(r.st_free);
			$('#form-spt_free').val(r.spt_free);
			
			freeRoundClick();
		}
		
		function getFormRules(r)
		{
			var flags = 0;

			if ($('#form-kill1-2').prop('checked'))
				flags |= <?php echo RULES_FLAG_DAY1_NO_DIFF; ?>;
			else if ($('#form-kill1-1').prop('checked'))
				flags |= <?php echo RULES_FLAG_DAY1_NO_KILL; ?>;

			if ($('#form-no_crash4-1').prop('checked'))
				flags |= <?php echo RULES_FLAG_NO_CRASH_4; ?>;

			if ($('#form-vcancel-1').prop('checked'))
				flags |= <?php echo RULES_VOTING_CANCEL_NO; ?>;
			else if ($('#form-vcancel-2').prop('checked'))
				flags |= <?php echo RULES_VOTING_CANCEL_BY_NOM; ?>;

			if ($('#form-bestp-0').prop('checked'))
				flags |= <?php echo RULES_BEST_PLAYER; ?>;

			if ($('#form-bestm-0').prop('checked'))
				flags |= <?php echo RULES_BEST_MOVE; ?>;

			if ($('#form-guess3-0').prop('checked'))
				flags |= <?php echo RULES_GUESS_MAFIA; ?>;

			if ($('#form-nightnom-1').prop('checked'))
				flags |= <?php echo RULES_FLAG_NIGHT_KILL_CAN_NOMINATE; ?>;

			if ($('#form-defround-0').prop('checked'))
				flags |= <?php echo RULES_FLAG_DEFENSIVE_ROUND; ?>;

			if ($('#form-freeround-0').prop('checked'))
				flags |= <?php echo RULES_FLAG_FREE_ROUND; ?>;

			if ($('#form-mute-1').prop('checked'))
				flags |= <?php echo RULES_MUTE_NEXT; ?>;

			if ($('#form-mute-crit-1').prop('checked'))
				flags |= <?php echo RULES_MUTE_CRIT; ?>;

				
			if ($('#form-client-0').prop('checked'))
				flags |= <?php echo RULES_ANY_CLIENT; ?>;
			else if ($('#form-client-2').prop('checked'))
				flags |= <?php echo RULES_SIMPLIFIED_CLIENT; ?>;
				
			r['flags'] = flags;
			r['st_reg'] = $('#form-st_reg').val();
			r['spt_reg'] = $('#form-spt_reg').val();
			r['st_def'] = $('#form-st_def').val();
			r['spt_def'] = $('#form-spt_def').val();
			r['st_killed'] = $('#form-st_killed').val();
			r['spt_killed'] = $('#form-spt_killed').val();
			r['st_free'] = $('#form-st_free').val();
			r['spt_free'] = $('#form-spt_free').val();
		}
		</script>
<?php
	}
}

?>