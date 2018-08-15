<?php

require_once 'include/session.php';
require_once 'include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Attend event'));

	if ($_profile == NULL)
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	$id = $_REQUEST['id'];
	
	$odds = 100;
	$friends = 0;
	$late = 0;
	$query = new DbQuery('SELECT coming_odds, people_with_me, late FROM event_users WHERE event_id = ? AND user_id = ?', $id, $_profile->user_id);
	if ($row = $query->next())
	{
		list ($odds, $friends, $late) = $row;
	}
	if ($odds == 0)
	{
		$odds = 100;
	}
	
	list($event_flags) = Db::record(get_label('event'), 'SELECT flags FROM events WHERE id = ?', $id);
	
	$nickname = NULL;
	$query = new DbQuery('SELECT nick_name FROM registrations WHERE event_id = ? AND user_id = ?', $id, $_profile->user_id);
	if ($row = $query->next())
	{
		list ($nickname) = $row;
	}

	echo '<table class="dialog_form" width="100%">';
	if ($event_flags & EVENT_FLAG_TOURNAMENT)
	{
		echo '<tr id="nick_tr"><td>'.get_label('My nickname for this event is').':</td><td>';
		nick_name_chooser($_profile->user_id, $_profile->user_name, $nickname);
		echo '</td></tr></table>';
?>	
		<script>
		function commit(onSuccess)
		{
			json.post("api/ops/event.php",
				{
					op: "attend"
					, event_id: <?php echo $id; ?>
					, odds: 100
					, friends: 0
					, nick: $("#nick").val()
					, late: 0
				},
				onSuccess);
		}
		</script>
<?php
	}
	else
	{
		echo '<tr><td width="210">'.get_label('The chance that I am coming is').':</td><td>';
		echo '<select id="odds" onChange = "oddsChanged()">';
		for ($i = 0; $i <= 100; $i += 10)
		{
			show_option($i, $odds, $i . '%');
		}
		echo '</select>';
		echo '<div id="slider" style="margin: 10px 10px 10px 0px;"></div>';
		echo '</td></tr>';
		
		echo '<tr id="friends_tr"><td>'.get_label('I am bringing').':</td><td><select id="friends">';
		for ($i = 0; $i <= 10; ++$i)
		{
			show_option($i, $friends, $i);
		}
		echo '</select>&nbsp;&nbsp;'.get_label('friends with me.').'</td></tr>';

		echo '<tr id="late_tr"><td>' . get_label('I will be there') . ':</td><td><select id="late">';
		show_option(0, $late, get_label('On time'));
		show_option(30, $late, get_label('[0] minutes late', 30));
		show_option(60, $late, get_label('1 hour late'));
		show_option(90, $late, get_label('[0] and a half hours late', '1'));
		show_option(120, $late, get_label('[0] hours late', '2'));
		show_option(150, $late, get_label('[0] and a half hours late', '2'));
		show_option(180, $late, get_label('[0] hours late', '3'));
		show_option(-1, $late, get_label('More than 3 hours late'));
		echo '</select></td></tr>';
		
		echo '<tr id="nick_tr"><td>'.get_label('My nickname for this event is').':</td><td>';
		nick_name_chooser($_profile->user_id, $_profile->user_name, $nickname);
		echo '</td></tr>';
		echo '</table>';
?>	
		<script>
		function oddsChanged()
		{
			var odds = $('#odds').val();
			if (odds < 100)
			{
				$('#nick_tr').hide();
			}
			else
			{
				$('#nick_tr').show();
			}
			if (odds > 0)
			{
				$('#friends_tr').show();
				$('#late_tr').show();
			}
			else
			{
				$('#friends_tr').hide();
				$('#late_tr').hide();
			}
		}
		
		$(function()
		{
			$( "#slider" ).slider({
				value: $('#odds').val(),
				min: 0,
				max: 100,
				step: 10,
				range: "min",
				slide: function(event, ui)
				{
					$( "#odds" ).val(ui.value);
					oddsChanged();
				}
			});
			oddsChanged();
		});	
		
		function commit(onSuccess)
		{
			json.post("api/ops/event.php",
				{
					op: "attend"
					, event_id: <?php echo $id; ?>
					, odds: $("#odds").val()
					, friends: $("#friends").val()
					, nick: $("#nick").val()
					, late: $("#late").val()
				},
				onSuccess);
		}
		</script>
<?php
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}
?>
