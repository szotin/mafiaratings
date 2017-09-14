<?php

require_once 'include/session.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/address.php';

ob_start();
$result = array();

try
{
	initiate_session();
	check_maintenance();
	if ($_profile == NULL)
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('address')));
	}
	
	$id = $_REQUEST['id'];
	
/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';*/
	
	list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $id);
	if ($_profile == NULL || !$_profile->is_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (isset($_REQUEST['retire']))
	{
		Db::begin();
		Db::exec(get_label('address'), 'UPDATE addresses SET flags = (flags | ' . ADDR_FLAG_NOT_USED . ') WHERE id = ?', $id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $id);
			db_log('address', 'Marked as not used', NULL, $id, $club_id);
		}
		Db::commit();
	}
	else if (isset($_REQUEST['restore']))
	{
		Db::begin();
		Db::exec(get_label('address'), 'UPDATE addresses SET flags = (flags & ~' . ADDR_FLAG_NOT_USED . ') WHERE id = ?', $id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $id);
			db_log('address', 'Marked as used', NULL, $id, $club_id);
		}
		Db::commit();
	}
	else if (isset($_REQUEST['update']))
	{
		$address = $_REQUEST['address'];
	
		$name = $_REQUEST['name'];
		if ($name == '')
		{
			$name = $address;
		}
		$sc_name = htmlspecialchars($name, ENT_QUOTES);
		$sc_address = htmlspecialchars($address, ENT_QUOTES);
	
		Db::begin();
		check_address_name($sc_name, $club_id, $id);
		$city_id = retrieve_city_id($_REQUEST['city'], retrieve_country_id($_REQUEST['country']), $_profile->clubs[$club_id]->timezone);
	
		Db::exec(
			get_label('address'), 
			'UPDATE addresses SET name = ?, address = ?, city_id = ?, club_id = ? WHERE id = ?',
			$name, $address, $city_id, $club_id, $id);
		if (Db::affected_rows() > 0)
		{
			list($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
			$log_details =
				'name=' . $name .
				"<br>address=" . $address .
				"<br>city=" . $city_name . ' (' . $city_id . ')';
			db_log('address', 'Changed', $log_details, $id, $club_id);
		}
	
		list ($flags) = Db::record(get_label('address'), 'SELECT flags FROM addresses WHERE id = ?', $id);
		Db::commit();
	}
	else if (isset($_REQUEST['gen']))
	{
		Db::begin();
		$warning = load_map_info($id);
		if ($warning != NULL)
		{
			echo '<p>' . $warning . '</p>';
		}
		Db::commit();
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>