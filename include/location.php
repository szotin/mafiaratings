<?php

class Location
{
	public $ip;
	public $country_code;
	public $country;
	public $state;
	public $city;
	public $zip;
	public $latitude;
	public $longtitude;
	public $timezone;
	
	private static function parse_param($input, &$offset)
	{
		// OK;;173.183.102.96;CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// OK;;62.63.127.2;RU;RUSSIAN FEDERATION;MOSCOW CITY;MOSCOW;-;55.7522;37.6156;+04:00
		$next = strpos($input, ';', $offset);
		if ($next === false)
		{
			$str  = substr($input, $offset);
			$offset = strlen($str);
		}
		else
		{
			$str  = substr($input, $offset, $next - $offset);
			$offset = $next + 1;
		}
		if ($str === false)
		{
			$str = '';
		}
		return $str;
	}
	
	function __construct()
	{
		$input = file_get_contents('http://api.ipinfodb.com/v3/ip-city/?key=605d6328a1ffdde0eae819a0f14849fb50244600b882214fe8a36ea017467436&ip=' . $_SERVER['REMOTE_ADDR']);
		//$input = 'OK;;173.183.102.96;CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00';
		//$input = 'OK;;69.31.255.2;CA;CANADA;ALBERTA;CALGARY;T2H 0S6;51.0501;-114.085;-06:00';
		//$input = 'OK;;62.63.127.2;RU;RUSSIAN FEDERATION;MOSCOW CITY;MOSCOW;-;55.7522;37.6156;+04:00';
		//$input = 'OK;;41.205.63.25;AO;ANGOLA;LUANDA;LUANDA;-;-8.83682;13.2343;+01:00';
		//$input = 'OK;;81.16.15.255;AM;ARMENIA;YEREVAN;YEREVAN;-;40.1811;44.5136;+05:00';
		//$input = 'OK;;64.119.207.2;BB;BARBADOS;ST. MICHAEL;BRIDGETOWN;-;13.1;-59.6167;-04:00';
		//$input = "OK;;80.94.239.2;BY;BELARUS;MINSKAYA VOBLASTS';MINSK;-;53.9;27.5667;+02:00";
		//$input = 'OK;;62.84.63.2;KZ;KAZAKHSTAN;ALMATY CITY;ALMATY;-;43.25;76.95;+06:00';
		//$input = 'OK;;62.72.191.2;UA;UKRAINE;-;-;-;50.45;30.5233;+03:00';
		
		//echo $input . '<br>';
		
		$offset = 0;
		Location::parse_param($input, $offset); // OK;;173.183.102.96;CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		Location::parse_param($input, $offset); // ;173.183.102.96;CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		$this->ip = Location::parse_param($input, $offset); // 173.183.102.96;CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->ip . '<br>';
		$this->country_code = Location::parse_param($input, $offset); // CA;CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->country_code . '<br>';
		$this->country = Location::parse_param($input, $offset); // CANADA;BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->country . '<br>';
		$this->state = Location::parse_param($input, $offset); // BRITISH COLUMBIA;VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->state . '<br>';
		$this->city = Location::parse_param($input, $offset); // VANCOUVER;V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->city . '<br>';
		$this->zip = Location::parse_param($input, $offset); // V5K 0A1;49.2497;-123.119;-07:00
		// echo $this->zip . '<br>';
		$this->latitude = Location::parse_param($input, $offset); // 49.2497;-123.119;-07:00
		// echo $this->latitude . '<br>';
		$this->longtitude = Location::parse_param($input, $offset); // -123.119;-07:00
		// echo $this->longtitude . '<br>';
		$this->timezone = Location::parse_param($input, $offset); // -07:00
		// echo $this->timezone . '<br>';
	}
	
	static function get()
	{
		if (!isset($_SESSION['location']))
		{
			$_SESSION['location'] = new Location();
		}
		return $_SESSION['location'];
	}
}

?>