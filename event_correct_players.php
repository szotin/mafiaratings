<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';

class Page extends PageBase
{
	private $event;
	private $data;

	protected function prepare()
	{
		if (!isset($_REQUEST['event']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		$this->event = new Event();
		$this->event->load($_REQUEST['event']);
		
		$this->data = array();
		$query = new DbQuery(
			'SELECT i.id, i.name, i.flags, r.nick_name, u.id, u.name FROM registrations r ' .
				'JOIN incomers i ON r.incomer_id = i.id ' .
				'LEFT OUTER JOIN users u ON r.user_id = u.id ' . 
				'WHERE i.event_id = ? AND (i.flags & ' . INCOMER_FLAGS_EXISTING . ') <> 0 ' .
				'AND NOT EXISTS (SELECT s.user_id FROM incomer_suspects s WHERE s.incomer_id = i.id)', 
			$this->event->id);
		while ($row = $query->next())
		{
			$this->data[] = $row;
		}
	}
	
	protected function show_body()
	{
		$this->event->show_details(false);
	
		if (count($this->data) > 0)
		{
			echo '<table class="bordered" width="100%">';
			echo '<tr><td class="dark" colspan="2" align="center">' . get_label('The next players were not recognized. Please specify if you know them:') . '</td></tr>';
			foreach ($this->data as $row)
			{
				list($incomer_id, $incomer_name, $incomer_flags, $nick, $user_id, $user_name) = $row;
				if ($user_id == NULL)
				{
					$user_name = get_label('[unknown]');
					$user_id = 0;
				}
				
				echo '<tr><td>';
				if ($incomer_name == $nick)
				{
					echo $incomer_name;
				}
				else
				{
					echo $nick . ' (' . $incomer_name . ')';
				}
				echo '</td><td width="160" align="center">';
				echo '<input type="text" class="dropdown" id="i' . $incomer_id . '" value="' . $user_name . '"/>';
				echo '<input type="image" class="dropdown-btn" src="images/dropdown.png" onclick="drop(' . $incomer_id . ')"/>';
				echo '</td></tr>';
				
			}
			echo '</table>';
		}
	}
	
	protected function js_on_load()
	{
		echo "var users = {};\n";
		foreach ($this->data as $row)
		{
			list($incomer_id, $incomer_name, $incomer_flags, $nick, $user_id, $user_name) = $row;
			if ($user_id == NULL)
			{
				echo 'users[' . $incomer_id . "] = 0;\n";
			}
			else
			{
				echo 'users[' . $incomer_id . '] = ' . $user_id . ";\n";
			}
		}
?>
		$('[id^=i]').each(function()
		{
			var c = $(this);
			var i = c.attr("id").substring(1);
			c.autocomplete(
			{ 
				source: function(request, response) { $.getJSON("ws_players.php", { term: c.val(), nu: "<?php echo get_label('[unknown]'); ?>" }, response); },
				select: function(event, ui)
				{
					if (ui.item.id != users[i])
					{
						json.post("game_ops.php",
						{
							id: i,
							user: ui.item.id,
							replace_incomer: ""
						}, function() { users[i] = ui.item.id; });
					}
				},
				minLength: 0
			});
		});
<?php	
	}
	
	protected function js()
	{
?>
		function drop(id)
		{
			var e = $("#i" + id);
			e.autocomplete("search", e.val()).select();
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Unknown players'), PERM_ALL);

?>