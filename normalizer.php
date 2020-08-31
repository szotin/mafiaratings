<?php

require_once 'include/general_page_base.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $normalizer;
	private $normalizer_id;
	private $normalizer_name;
	private $normalizer_version;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring normalizer')));
		}
		$normalizer_id = (int)$_REQUEST['id'];
		
		list($this->normalizer_id, $this->normalizer, $this->normalizer_name, $this->normalizer_version, $club_id, $league_id) = Db::record(get_label('scoring normalizer'), 'SELECT s.id, v.normalizer, s.name, s.version, s.club_id, s.league_id FROM normalizers s JOIN normalizer_versions v ON v.normalizer_id = s.id AND v.version = s.version WHERE s.id = ?', $normalizer_id);
		if (is_null($club_id))
		{
			if (is_null($league_id))
			{
				check_permissions(PERMISSION_ADMIN);
			}
			else
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		else 
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if (!is_null($league_id))
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		$this->_title = get_label('Scoring normalizer') . ': ' . $this->normalizer_name;
	}
	
	protected function show_body()
	{
		echo '<p><button id="save" onclick="saveData()" disabled>' . get_label('Save') . '</button></p>';
		echo '<script src="js/normalizer_editor.js"></script>';
		echo '<div id="normalizer-editor"></div>';
	}
	
	protected function js()
	{
		$for = get_label('For') . ' ';
?>
		var data =
		{
			normalizer: <?php echo $this->normalizer; ?>,
			id: <?php echo $this->normalizer_id; ?>,
			name: "<?php echo $this->normalizer_name; ?>",
			version: <?php echo $this->normalizer_version; ?>,
			strings:
			{
				name: "<?php echo get_label('Scoring normalizer name'); ?>",
				version: "<?php echo get_label('Version'); ?>"
			},
		};
		
		function onDataChange(d, isDirty)
		{
			data = d;
			$('#save').prop('disabled', !isDirty);
		}
		
		function saveData()
		{
			console.log(data.normalizer);
			var params =
			{
				op: 'change'
				, normalizer_id: data.id
				, name: data.name
				, normalizer: JSON.stringify(data.normalizer)
			};
			json.post("api/ops/normalizer.php", params, function(response)
			{
				setNormalizerVersion(response.normalizer_version);
			});
			dirty(false);
		}
		
		initNormalizerEditor(data, onDataChange);
<?php
	}
}

$page = new Page();
$page->run('');

?>