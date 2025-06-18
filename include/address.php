<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/geo.php';

function addr_label($addr, $addr_city, $addr_country)
{
	if ($addr == '')
	{
		return $addr_city . ', ' . $addr_country;
	}
	return $addr . ', ' . $addr_city . ', ' . $addr_country;
}

function load_map_info($addr_id, $picture_dir = NULL)
{
	list($address, $club_id, $old_flags, $old_map_url, $old_lat, $old_lon, $city_id, $city_name, $country_name, $city_lat, $city_lon) = Db::record(
		get_label('address'), 
		'SELECT a.address, a.club_id, a.flags, a.map_url, a.lat, a.lon, a.city_id, ni.name, no.name, i.lat, i.lon FROM addresses a' . 
		' JOIN cities i ON i.id = a.city_id' .
		' JOIN countries o ON o.id = i.country_id' . 
		' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . LANG_ENGLISH . ') <> 0 ' .
		' JOIN names no ON no.id = o.name_id AND (no.langs & ' . LANG_ENGLISH . ') <> 0 ' .
		' WHERE a.id = ?', 
		$addr_id);
	$address = str_replace(' ', '+', $address . ',' . $city_name . ',' . $country_name);
	//echo $address . '<br>';
	
	try
	{
		$coord = get_address_coordinates($address);
	}
	catch (Throwable $ex)
	{
		$coord = new stdClass();
		$coord->lat = $city_lat;
		$coord->lng = $city_lon;
	}
	$map_url = get_address_url($address, $coord);
	
	$flags = $old_flags;
	if (!is_null($picture_dir))
	{
		$image_url = get_address_image_url($address, TNAIL_WIDTH, TNAIL_HEIGHT, $coord);
		$image = file_get_contents($image_url);
		if ($image === false)
		{
			return get_label('Failed to get map image from google maps');
		}
			
		//	'../../'
		// $dir = $root_dir . ADDRESS_PICS_DIR; // this function is called from api/ops only. Make it a param if it is not so.
		if (file_put_contents($picture_dir . $addr_id . '.png', $image) === false)
		{
			return get_label('Failed to get map image from google maps');
		}
			
		build_pic_tnail($picture_dir, $addr_id);
			
		$icon_version = (($flags & ADDRESS_ICON_MASK) >> ADDRESS_ICON_MASK_OFFSET) + 1;
		if ($icon_version > ADDRESS_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~ADDRESS_ICON_MASK) + ($icon_version << ADDRESS_ICON_MASK_OFFSET);
		$flags |= ADDRESS_FLAG_GENERATED;
	}
			
	Db::exec(get_label('address'), 'UPDATE addresses SET map_url = ?, flags = ?, lat = ?, lon = ? WHERE id = ?', $map_url, $flags, $coord->lat, $coord->lng, $addr_id);
	if (Db::affected_rows() > 0)
	{
		$log_details = new stdClass();
		if ($map_url != $old_map_url)
		{
			$log_details->map_url = $map_url;
		}
		if ($flags != $old_flags)
		{
			$log_details->flags = $flags;
		}
		if (abs($old_lat - $coord->lat) > 0.001)
		{
			$log_details->lat = $coord->lat;
		}
		if (abs($old_lon - $coord->lng) > 0.001)
		{
			$log_details->lon = $coord->lng;
		}
		db_log(LOG_OBJECT_ADDRESS, 'generated', $log_details, $addr_id, $club_id);
	}
	return NULL;
}

function show_address_buttons($id, $name, $flags, $club_id)
{
	if (($flags & ADDRESS_FLAG_NOT_USED) != 0)
	{
		echo '<button class="icon" onclick="mr.restoreAddr(' . $id . ')" title="' . get_label('Mark [0] as used', $name) . '"><img src="images/undelete.png" border="0"></button>';
		$no_buttons = false;
	}
	else 
	{
		echo '<button class="icon" onclick="mr.editAddr(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
		echo '<button class="icon" onclick="mr.genAddr(' . $id . ')" title="' . get_label('Locate [0] in google maps and generate map image.', $name) . '"><img src="images/map.png" border="0"></button>';
		echo '<button class="icon" onclick="mr.retireAddr(' . $id . ')" title="' . get_label('Mark [0] as not used', $name) . '"><img src="images/delete.png" border="0"></button>';
		$no_buttons = false;
	}
}

// Not used right now, but we keep the code in case it will be needed in the future
// Requests and shows a map directly from google.
/*$_address_script = 'showAddressMap()';

function show_address_script($address)
{
?>
    <script src="//maps.google.com/maps?file=api&amp;v=3.x&amp;key=AIzaSyDVDj1_g_SQjSvWpCe9DQSyOi5PaypPOl4" type="text/javascript"></script>
    <script type="text/javascript">

    function showAddressMap()
	{
		var map = new GMap2(document.getElementById("map_canvas"));
		var geocoder = new GClientGeocoder();

		geocoder.getLatLng(
			"<?php echo $address; ?>",
			function(point)
			{
				if (point)
				{
					map.setCenter(point, 13);
					var marker = new GMarker(point);
					map.addOverlay(marker);
				}
			});
	}
    </script>
<?php
}

function show_address_map($width, $height)
{
	echo '<div id="map_canvas" style="width: ' . $width . 'px; height: ' . $height . 'px"></div>';
}*/

class AddressPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $address;
	protected $url;
	protected $flags;
	protected $club_id;
	protected $club_name;
	protected $club_langs;
	protected $scoring_id;
	protected $city_id;
	protected $city_name;
	protected $timezone;
	protected $country_id;
	protected $country_name;
	protected $is_manager;
	
	protected function prepare()
	{
		global $_lang, $_profile;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('club')));
		}
		$this->id = $_REQUEST['id'];

		list ($this->name, $this->address, $this->url, $this->flags, $this->club_id, $this->club_name, $this->club_langs, $this->scoring_id, $this->club_flags, $this->city_id, $this->city_name, $this->timezone, $this->country_id, $this->country_name) = 
			Db::record(
				get_label('address'),
				'SELECT a.name, a.address, a.map_url, a.flags, a.club_id, c.name, c.langs, c.scoring_id, c.flags, a.city_id, nct.name, ct.timezone, ct.country_id, ncr.name FROM addresses a' .
				' JOIN clubs c ON c.id = a.club_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN countries cr ON cr.id = ct.country_id' .
				' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
				' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
				' WHERE a.id = ?', $this->id);
				
		$this->is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $this->club_id);
	}

	protected function show_title()
	{
		$menu = array
		(
			new MenuItem('address_info.php?id=' . $this->id, get_label('Address'), get_label('[0] information', $this->name))
			, new MenuItem('address_tournaments.php?id=' . $this->id, get_label('Tournaments'), get_label('[0] tournaments history', $this->name))
			, new MenuItem('address_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->name))
			, new MenuItem('address_games.php?id=' . $this->id, get_label('Games'), get_label('Games list at [0]', $this->name))
			, new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('address_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME))
				, new MenuItem('address_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.'))
				, new MenuItem('address_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.'))
				, new MenuItem('address_referees.php?id=' . $this->id, get_label('Referees'), get_label('Referees statistics of [0]', $this->name))
			))
			, new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('address_albums.php?id=' . $this->id, get_label('Photos'), get_label('[0] photo albums', $this->name))
				, new MenuItem('address_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos from various events.'))
				// , new MenuItem('address_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.'))
				// , new MenuItem('address_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.'))
				// , new MenuItem('address_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.'))
			))
		);
		
		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="4" style="height: 36px;">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		$address_pic = new Picture(ADDRESS_PICTURE);
		
		echo '<tr><td rowspan="2" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->flags & ADDRESS_FLAG_NOT_USED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		if ($this->is_manager)
		{
			show_address_buttons($this->id, $this->name, $this->flags, $this->club_id);
		}
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		$address_pic->set($this->id, $this->name, $this->flags);
		if ($this->url != '')
		{
			echo '<a href="' . $this->url . '" target="blank">';
			$address_pic->show(TNAILS_DIR, false);
			echo '</a>';
		}
		else
		{
			$address_pic->show(TNAILS_DIR, false);
		}
		echo '</td></tr></table></td>';
		
		echo '<td rowspan="2" valign="top"><h2 class="address">' . $this->name . '</h2><br><h3>' . $this->_title . '</h3><p class="subtitle">' . addr_label($this->address, $this->city_name, $this->country_name) . '</p></td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom" width="' . ICON_WIDTH . '"><table><tr><td>';
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$this->club_pic->show(ICONS_DIR, true, 48);
		echo '</td></tr></table></td></tr>';
		
		echo '</table>';
	}
}

?>