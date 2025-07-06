<?php

require_once 'include/general_page_base.php';
require_once 'include/gaining.php';

class Page extends GeneralPageBase
{
	private $place;
	
	protected function prepare()
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('gaining system')));
		}
		$this->gaining_id = (int)$_REQUEST['id'];
		
		if (isset($_REQUEST['version']))
		{
			$this->gaining_version = (int)$_REQUEST['version'];
			list($this->gaining, $name, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? AND v.version = ?', $this->gaining_id, $this->gaining_version);
		}
		else
		{
			list($this->gaining, $name, $this->gaining_version, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, v.version, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? ORDER BY version DESC LIMIT 1', $this->gaining_id);
			$this->gaining_version = (int)$this->gaining_version;
		}
		$this->gaining = json_decode($this->gaining);
		
		$this->players = 30;
		if (isset($_REQUEST['players']))
		{
			$this->players = (int)$_REQUEST['players'];
		}
		
		$this->stars = 2;
		if (isset($_REQUEST['stars']))
		{
			$this->stars = (double)$_REQUEST['stars'];
		}
		
		$this->place = 0;
		if (isset($_REQUEST['place']))
		{
			$this->place = (int)$_REQUEST['place'];
		}
		
		$this->series = false;
		if (isset($_REQUEST['series']))
		{
			$this->series = (bool)$_REQUEST['series'];
		}
		
		$this->title = get_label('Gaining system [0]. Version [1].', $name, $this->gaining_version);
	}
	
	protected function show_body()
	{
		echo '<table class="transp" width="100%">';
		echo '<tr><td width="300"><input type="number" style="width: 45px;" step="1" min="1" max="10" id="form-stars" value="' . $this->stars . '" onChange="onChangeParams()"> ' . get_label('stars') . '</td>';
		echo '<td><input type="number" style="width: 45px;" step="1" min="10" id="form-players" value="' . $this->players . '" onChange="onChangeParams()"> ' . get_label('players') . '</td>';
		echo '<td align="right"><input type="checkbox" id="form-series" onClick="onChangeParams()"> ' . get_label('for series of tournaments') . '</td></tr>';
		echo '</table>';
		
		echo '<p><div id="form-gaining"></div></p>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function onChangeParams()
		{
			var params = 
			{
				gaining_id: <?php echo $this->gaining_id; ?>
				, gaining_version: <?php echo $this->gaining_version; ?>
				, stars: $("#form-stars").val()
				, players: $("#form-players").val()
			};
			if ($('#form-series').attr('checked'))
			{
				params['series'] = true;
			}
			http.post("form/gaining_table.php", params, function(html)
			{
				$("#form-gaining").html(html);
			});
		}
		
		onChangeParams();
<?php
	}
}

$page = new Page();
$page->run(get_label('Gaining system'));

?>