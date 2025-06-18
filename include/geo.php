<?php

require_once __DIR__ . '/google_geo_key.php';

define('GEO_METERS', 6371000);
define('GEO_KILOMETERS', 6371);
define('GEO_MILES', 3959);

function get_address_coordinates($address)
{
    $address = urlencode($address);
	$apiUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '&key=' . GOOGLE_API_KEY;
    $response = file_get_contents($apiUrl);
	if ($response === FALSE)
	{
		throw new Exc(get_label('Bad google maps responce'));
	}
	
    $data = json_decode($response);
	if (!isset($data->status))
	{
		throw new Exc(get_label('Bad google maps responce'));
	}
	
    if ($data->status != 'OK')
	{
		throw new Exc(get_label('Google maps failed to find the address: [0]', isset($data->error_message) ? $data->error_message : $data->status));
    }
	
	if (!isset($data->results) || 
		count($data->results) <= 0 ||
		!isset($data->results[0]->geometry) || 
		!isset($data->results[0]->geometry->location))
	{
		throw new Exc(get_label('Google maps failed to find the address: [0]', formatted_json($data)));
	}
	
	$coord = $data->results[0]->geometry->location;
	if (!isset($coord->lat) || !isset($coord->lng))
	{
		throw new Exc(get_label('Google maps failed to find the address: [0]', formatted_json($coord)));
	}
	return $coord;
}

function get_address_url($address, $coord = NULL)
{
	if (is_null($coord))
	{
		$coord = get_address_coordinates($address);
	}
	return 'http://maps.google.com/maps?q=' . urlencode($address) . '&hl=en&sll=' . $coord->lat . ',' . $coord->lng . '&t=m&z=13';
}

function get_address_image_url($address, $width, $height, $coord = NULL)
{
	if (is_null($coord))
	{
		$coord = get_address_coordinates($address);
	}
	return 'https://maps.googleapis.com/maps/api/staticmap?center=' . urlencode($address) . '&zoom=12&size=' . $width . 'x' . $height . '&maptype=roadmap&markers=color:blue%7Clabel:S%7C' . $coord->lat . ',' . $coord->lng . '&format=PNG&key=' . GOOGLE_API_KEY;	
}

// // Gets distance between two cities in meters
// function get_distance($city_name1, $city_name2)
// {
	// if ($city_name1 == $city_name2)
	// {
		// return 0;
	// }
	
    // $city_name1 = urlencode($city_name1);
    // $city_name2 = urlencode($city_name2);
    // $apiUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json?units=imperial&origins=' . $city_name1 . '&destinations=' . $city_name2 . '&key=' . GOOGLE_API_KEY;
    // $response = file_get_contents($apiUrl);
    // $data = json_decode($response);
    // if ($data->status != 'OK')
	// {
		// if (isset($data->error_message))
		// {
			// throw new Exc($data->error_message);
		// }
		// throw new Exc($data->status);
    // }
	// if (!isset($data->rows) || 
		// !isset($data->rows[0]->elements) || 
		// !isset($data->rows[0]->elements[0]->distance) || 
		// !isset($data->rows[0]->elements[0]->distance->value))
	// {
// //		print_json($data);
		// return 6377000; // maximum possible distance on Earth
	// }
	// return $data->rows[0]->elements[0]->distance->value;
// }

function get_distance($lat1, $lon1, $lat2, $lon2, $unit = GEO_METERS)
{
	// Convert degrees to radians
	$lat1Rad = deg2rad($lat1);
	$lon1Rad = deg2rad($lon1);
	$lat2Rad = deg2rad($lat2);
	$lon2Rad = deg2rad($lon2);

	// Differences in coordinates
	$deltaLat = $lat2Rad - $lat1Rad;
	$deltaLon = $lon2Rad - $lon1Rad;

	// Haversine formula
	$a = sin($deltaLat / 2) * sin($deltaLat / 2) +
		cos($lat1Rad) * cos($lat2Rad) *
		sin($deltaLon / 2) * sin($deltaLon / 2);
	$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
	
	return $c * $unit;
}

// Not used right now, but we keep the code in case it will be needed in the future
// Requests and shows a map directly from google.
/*$_address_script = 'showAddressMap()';

function show_address_script($address)
{
?>
    <script src="//maps.google.com/maps?file=api&amp;v=3.x&amp;key=<?php echo GOOGLE_API_KEY; >" type="text/javascript"></script>
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
}*/

?>