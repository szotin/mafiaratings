<?php

require_once 'include/ws.php';

class Event
{
	public $id;
	public $name;
	public $price;
	public $page;
	public $attend_page;
	public $decline_page;
	public $club_id;
	public $club_name;
	public $club_url;
	public $club_page;
	public $start;
	public $duration;
	public $addr_id;
	public $addr;
	public $addr_url;
	public $addr_image;
	public $timezone;
	public $city;
	public $country;
	public $notes;
	public $langs;
	public $rules_id;
	public $scoring_id;
	public $date_str;
	public $time_str;
	public $hour;
	public $minute;
	public $flags;
	
	function __construct($id, $date_format, $time_format)
	{
		global $_lang_code;
	
		list(
			$this->id, $this->name, $this->price, $this->club_id, $this->club_name, $club_flags, $this->club_url, $this->start, $this->duration,
			$this->addr_id, $this->addr, $this->addr_url, $this->timezone, $addr_flags, $this->city, $this->country,
			$this->notes, $this->langs, $this->flags, $this->rules_id, $this->scoring_id) = Db::record(get_label('event'),
				'SELECT e.id, e.name, e.price, c.id, c.name, c.flags, c.web_site, e.start_time, e.duration, a.id, a.address, a.map_url, ct.timezone, a.flags, ct.name_' . $_lang_code . ', cr.name_' . $_lang_code . ', e.notes, e.languages, e.flags, e.rules_id, e.scoring_id FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN clubs c ON e.club_id = c.id' .
				' JOIN cities ct ON a.city_id = ct.id' .
				' JOIN countries cr ON ct.country_id = cr.id' .
				' WHERE e.id = ?', $id);
		
		$base = get_server_url() . '/';
		$this->addr_image = '';
		if (($addr_flags & ADDR_ICON_MASK) != 0)
		{
			$this->addr_image = $base . ADDRESS_PICS_DIR . TNAILS_DIR . $this->addr_id . '.jpg';
		}
		
		$this->page = $base . 'event_info.php?id=' . $this->id;
		$this->attend_page = $base . 'attend.php?id=' . $this->id;
		$this->decline_page = $base . 'pass.php?id=' . $this->id;
		$this->club_page = $base . 'club_main.php?id=' . $this->club_id;
		
		$this->date_str = format_date($date_format, $this->start, $this->timezone);
		$this->time_str = format_date($time_format, $this->start, $this->timezone);
		
		date_default_timezone_set($this->timezone);
		$this->hour = date('G', $this->start);
		$this->minute = round(date('i', $this->start) / 10) * 10;
	}
}

$date_format = '';
if (isset($_REQUEST['df']))
{
	$date_format = $_REQUEST['df'];
}

$time_format = '';
if (isset($_REQUEST['tf']))
{
	$time_format = $_REQUEST['tf'];
}

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$id = $_REQUEST['id'];

	echo json_encode(new Event($id, $date_format, $time_format));
}
catch (Exception $e)
{
	send_error($e);
}

?>