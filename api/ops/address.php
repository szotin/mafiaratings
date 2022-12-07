<?php

require_once '../../include/session.php';
require_once '../../include/club.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/address.php';
require_once '../../include/image.php';

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$address = get_required_param('address');
		if (empty($address))
		{
			throw new Exc(get_label('Please enter [0].', get_label('address')));
		}
		$address = htmlspecialchars($address, ENT_QUOTES);
		
		$name = get_optional_param('name');
		if (empty($name))
		{
			$name = $address;
		}
		$name = htmlspecialchars($name, ENT_QUOTES);
		
		$city_id = (int)get_optional_param('city_id', 0);
		
		Db::begin();
		if ($city_id <= 0)
		{
			$city_id = retrieve_city_id(get_required_param('city'), retrieve_country_id(get_required_param('country'), $club->timezone));
		}

		check_address_name($name, $club_id);

		Db::exec(
			get_label('address'), 
			'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
			$name, $club_id, $address, $city_id);
		list ($addr_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
		list ($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->address = $address;
		$log_details->city = $city_name;
		$log_details->city_id = $city_id;
		db_log(LOG_OBJECT_ADDRESS, 'created', $log_details, $addr_id, $club_id);

		$warning = load_map_info($addr_id);
		if ($warning != NULL)
		{
			echo '<p>' . $warning . '</p>';
		}

		Db::commit();
		
		$this->response['address_id'] = $addr_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Create address.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('address', 'Street address.');
		$help->request_param('name', 'Address name.', '<q>address</q> is used as a name.');
		$help->request_param('city_id', 'City id.', '<q>city</q> and <q>country</q> are used to find/create city.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', '<q>city_id</q> must be set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', '<q>city_id</q> must be set.');
		$help->response_param('address_id', 'Newly created address id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$address_id = (int)get_required_param('address_id');
		list($club_id, $old_name, $old_address, $old_city_id, $old_flags) = Db::record(get_label('club'), 'SELECT club_id, name, address, city_id, flags FROM addresses WHERE id = ?', $address_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		
		if (isset($_REQUEST['address']))
		{
			$address = htmlspecialchars($_REQUEST['address'], ENT_QUOTES);
			if (empty($address))
			{
				throw new Exc(get_label('Please enter [0].', get_label('address')));
			}
		}
		else
		{
			$address = $old_address;
		}
		
		if (isset($_REQUEST['name']))
		{
			$name = htmlspecialchars($_REQUEST['name'], ENT_QUOTES);
			if (empty($name))
			{
				$name = $address;
			}
		}
		else
		{
			$name = $old_name;
		}
		Db::begin();
		check_address_name($name, $club_id, $address_id);
		if (isset($_REQUEST['city_id']))
		{
			$city_id = (int)$_REQUEST['city_id'];
		}
		else if (isset($_REQUEST['city']) && isset($_REQUEST['country']))
		{
			$city_id = retrieve_city_id($_REQUEST['city'], retrieve_country_id($_REQUEST['country']), $_profile->clubs[$club_id]->timezone);
		}
		else
		{
			$city_id = $old_city_id;
		}
	
		$flags = $old_flags;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . ADDRESS_PICS_DIR, $address_id);
			
			$icon_version = (($flags & ADDRESS_ICON_MASK) >> ADDRESS_ICON_MASK_OFFSET) + 1;
			if ($icon_version > ADDRESS_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~ADDRESS_ICON_MASK) + ($icon_version << ADDRESS_ICON_MASK_OFFSET);
		}
		
		Db::exec(
			get_label('address'), 
			'UPDATE addresses SET name = ?, address = ?, city_id = ?, club_id = ?, flags = ? WHERE id = ?',
			$name, $address, $city_id, $club_id, $flags, $address_id);
		if (Db::affected_rows() > 0)
		{
			list($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($address != $old_address)
			{
				$log_details->address = $address;
			}
			if ($city_id != $old_city_id)
			{
				$log_details->city_id = $city_id;
			}
			if ($old_flags != $flags)
			{
				$log_details->flags = $flags;
				$log_details->logo_uploaded = true;
			}
			db_log(LOG_OBJECT_ADDRESS, 'changed', $log_details, $address_id, $club_id);
		}
	
		Db::commit();
		
		if ($address != $old_address || $city_id != $old_city_id)
		{
			$this->response['changed'] = 1;
		}
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Change address.');
		$help->request_param('address_id', 'Address id.');
		$help->request_param('address', 'Street address.', 'remains the same.');
		$help->request_param('name', 'Address name.', 'remains the same.');
		$help->request_param('city_id', 'City id.', '<q>city</q> and <q>country</q> are checked. If at least one of them is missing, city remains the same.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', 'city remains the same unless <q>city_id</q> is set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', 'city remains the same unless <q>city_id</q> is set.');
		$help->response_param('changed', '1 if the address was changed. Whan everything remains the same, or only name is changed, it is 0.', '0.');
		$help->request_param('logo', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// retire
	//-------------------------------------------------------------------------------------------------------
	function retire_op()
	{
		$address_id = (int)get_required_param('address_id');
		list($club_id, $name, $address, $city_id) = Db::record(get_label('club'), 'SELECT club_id, name, address, city_id FROM addresses WHERE id = ?', $address_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		
		Db::begin();
		Db::exec(get_label('address'), 'UPDATE addresses SET flags = (flags | ' . ADDRESS_FLAG_NOT_USED . ') WHERE id = ?', $address_id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $address_id);
			db_log(LOG_OBJECT_ADDRESS, 'marked as not used', NULL, $address_id, $club_id);
		}
		Db::commit();
	}
	
	function retire_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Mark address as retired. So it no longer appear in the list of addresses for the new events of the club. This means that this address is no longer used.');
		$help->request_param('address_id', 'Address id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$address_id = (int)get_required_param('address_id');
		list($club_id, $name, $address, $city_id) = Db::record(get_label('club'), 'SELECT club_id, name, address, city_id FROM addresses WHERE id = ?', $address_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		
		Db::begin();
		Db::exec(get_label('address'), 'UPDATE addresses SET flags = (flags & ~' . ADDRESS_FLAG_NOT_USED . ') WHERE id = ?', $address_id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $address_id);
			db_log(LOG_OBJECT_ADDRESS, 'marked as used', NULL, $address_id, $club_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Restore the retired address. So it can be used for new events again.');
		$help->request_param('address_id', 'Address id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// google_map
	//-------------------------------------------------------------------------------------------------------
	function google_map_op()
	{
		$address_id = (int)get_required_param('address_id');
		$change_picture = (int)get_optional_param('picture', 1);
		$set_url = (int)get_optional_param('url', 1);
		
		Db::begin();
		list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM addresses WHERE id = ?', $address_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
		$warning = load_map_info($address_id, $set_url, $change_picture);
		Db::commit();
		
		if ($warning != NULL)
		{
			echo '<p>' . $warning . '</p>';
		}
	}
	
	function google_map_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Generate and save google map URL for the address. And also generates an icon/logo for the address using snapshot from google maps.');
		$help->request_param('address_id', 'Address id.');
		$help->request_param('picture', '1 if address picture should be changed to the one from google maps; 0 - for leaving the existing picture.', '1.');
		$help->request_param('url', '1 to add google maps link to the address; 0 - for leaving existing google maps link.', '1.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Address Operations', CURRENT_VERSION);

?>