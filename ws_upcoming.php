<?php

require_once 'include/ws.php';

class Coming
{
	public $id;
	public $name;
	public $odds;
	public $bringing;
	public $late;
	
	function __construct($row)
	{
		list($this->id, $this->name, $this->odds, $this->bringing, $this->late) = $row;
	}
}

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
	public $coming;
	public $date_str;
	public $time_str;
	
	function __construct($row, $date_format, $time_format)
	{
		list(
			$this->id, $this->name, $this->price, $this->club_id, $this->club_name, $club_flags, $this->club_url, $this->start, $this->duration,
			$this->addr_id, $this->addr, $this->addr_url, $this->timezone, $addr_flags, $this->city, $this->country,
			$this->notes, $this->langs, $flags, $this->rules_id) = $row;
		
		$base = get_server_url() . '/';
			
		$this->addr_image = '';
		if (($addr_flags & ADDR_ICON_MASK) != 0)
		{
			$this->addr_image = $base . ADDRESS_PICS_DIR . TNAILS_DIR . $this->addr_id . '.jpg';
		}
		
		$this->page = $base . 'event_info.php?id=' . $this->id;
		$this->attend_page = $base . 'event_info.php?attend&id=' . $this->id;
		$this->decline_page = $base . 'event_info.php?decline&id=' . $this->id;
		$this->club_page = $base . 'club_main.php?id=' . $this->club_id;
		
		$this->coming = array();
		$query = new DbQuery('SELECT u.id, u.name, e.coming_odds, e.people_with_me, e.late FROM event_users e JOIN users u ON u.id = e.user_id WHERE e.event_id = ?', $this->id);
		while ($row = $query->next())
		{
			$this->coming[] = new Coming($row);
		}
		
		$this->date_str = format_date($date_format, $this->start, $this->timezone);
		$this->time_str = format_date($time_format, $this->start, $this->timezone);
	}
}

class Upcoming
{
	public $count;
	public $events;
	
	function __construct($pos, $len, $date_format, $time_format)
	{
		global $club_id, $_lang_code;
	
		$time = time();
		list($this->count) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE start_time + duration > ' . $time . ' AND club_id = ?', $club_id);
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.price, c.id, c.name, c.flags, c.web_site, e.start_time, e.duration, a.id, a.address, a.map_url, ct.timezone, a.flags, ct.name_' . $_lang_code . ', cr.name_' . $_lang_code . ', e.notes, e.languages, e.flags, e.rules_id FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
			' WHERE e.start_time + e.duration > ' . $time . ' AND e.club_id = ? ORDER BY e.start_time LIMIT ' . $pos . ',' . $len, $club_id);

		$this->events = array();
		while ($row = $query->next())
		{
			$this->events[] = new Event($row, $date_format, $time_format);
		}
	}
}

$pos = 0;
if (isset($_REQUEST['start']))
{
	$pos = $_REQUEST['start'];
}

$len = 5;
if (isset($_REQUEST['count']))
{
	$len = $_REQUEST['count'];
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
	echo json_encode(new Upcoming($pos, $len, $date_format, $time_format));
}
catch (Exception $e)
{
	send_error($e);
}

?>