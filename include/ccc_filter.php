<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/user_location.php';

class CCCFilter
{
	private $name;
	private $flags;
	private $type;
	private $id;
	private $value;
	
	function get_type() { return $this->type; }
	function get_id() { return $this->id; }
	function get_code() { return $this->type . $this->id; }
	function get_flags() { return $this->flags; }
	
	function get_value()
	{
		if ($this->id >= 0)
		{
			return $this->value;
		}
		return NULL;
	}
	
	function __construct($name, $filter_str, $flags = 0)
	{
		global $_profile, $_lang;
	
		if (isset($_REQUEST[$name]))
		{
			$filter_str = $_REQUEST[$name];
		}
		
		$this->name = $name;
		$this->flags = $flags;
		$this->type = substr($filter_str, 0, 1);
		$this->id = substr($filter_str, 1);
		
		$this->value = '';
		switch ($this->type)
		{
			case CCCF_CLUB:
				if ($this->id < 0)
				{
					$this->value = get_label('All');
				}
				else if ($this->id == 0)
				{
					$this->value = get_label('My clubs');
					if ($_profile == NULL)
					{
						$loc = UserLocation::get();
			/*			echo '<pre>';
						print_r($loc);
						echo '</pre>';*/
						$this->type = CCCF_CITY;
						$this->id = $loc->get_region_id(true);
						if ($this->id <= 0)
						{
							$this->type = CCCF_COUNTRY;
							$this->id = $loc->get_country_id();
						}
					}
				}
				else
				{
					list ($this->value) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $this->id);
				}
				break;
			case CCCF_CITY:
				if ($this->id <= 0)
				{
					$this->value = get_label('All');
				}
				else
				{
					list ($this->value) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE c.id = ?', $this->id);
				}
				break;
			case CCCF_COUNTRY:
				if ($this->id <= 0)
				{
					$this->value = get_label('All');
				}
				else
				{
					list ($this->value) = Db::record(get_label('country'), 'SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE c.id = ?', $this->id);
				}
				break;
		}
	}
	
	function show($title, $on_select = NULL)
	{
		echo '<input type="text" class="dropdown" id="' . $this->name . '" value="' . $this->value . '" title="' . $title . '"/>';
		echo '<input type="image" class="dropdown-btn" src="images/dropdown.png" onclick="cccDrop()"/>';
//		echo '<button class="dropdown-btn" onclick="cccDrop()"><img src="images/down.png" width="16" height="16"></button>';
?>
		<script>
		$.widget( "custom.catcomplete", $.ui.autocomplete,
		{
			_renderMenu: function(ul, items)
			{
				var that = this,
				currentCategory = "";
				$.each( items, function( index, item )
				{
					if ( item.category != currentCategory )
					{
						ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
						currentCategory = item.category;
					}
					that._renderItemData( ul, item );
				});
			}
		});
		
		function cccDrop()
		{
			$("#<?php echo $this->name; ?>").focus().select();
			
		}
	
		$("#<?php echo $this->name; ?>").catcomplete(
		{ 
			source: function( request, response )
			{
				$.getJSON("api/control/ccc.php",
				{
					flags: "<?php echo $this->flags; ?>",
					term: $("#<?php echo $this->name; ?>").val()
				}, response);
			},	
			select: function(event, ui) { <?php if (is_null($on_select)) echo 'goTo({ ccc: ui.item.code, page: undefined });'; else echo $on_select . '(ui.item.code)'; ?>; },
			minLength: 0,
		})
		.on("focus", function() { $(this).catcomplete("search", ''); });
		</script>
<?php
	}
}

?>