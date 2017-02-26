<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Google Maps API Example: Simple Geocoding</title>
    <script src="//maps.google.com/maps?file=api&amp;v=3.x&amp;key=AIzaSyDVDj1_g_SQjSvWpCe9DQSyOi5PaypPOl4"></script>
    <script>

    var map = null;
    var geocoder = null;

    function initialize()
	{
		if (GBrowserIsCompatible())
		{
			map = new GMap2(document.getElementById("map_canvas"));
//			map.setCenter(new GLatLng(37.4419, -122.1419), 13);
			geocoder = new GClientGeocoder();
		}
	}

    function showAddress(address)
	{
		if (geocoder)
		{
			geocoder.getLatLng(
				address,
				function(point)
				{
					if (!point)
					{
						alert(address + " not found");
					}
					else
					{
						map.setCenter(point, 13);
						var marker = new GMarker(point);
						map.addOverlay(marker);
					}
				});
		}
    }
    </script>
  </head>

  <body onload="initialize()" onunload="GUnload()">
    <form action="#" onsubmit="showAddress(this.address.value); return false">
      <p>
        <input type="text" size="60" name="address" value="1600 Amphitheatre Pky, Mountain View, CA" />
        <input type="submit" value="Go!" />
      </p>
      <div id="map_canvas" style="width: 250px; height: 160px"></div>
    </form>
  </body>
</html>

