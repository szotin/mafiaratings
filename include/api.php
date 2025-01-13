<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/api_help.php';

define('API_DEFAULT_PAGE_SIZE', 30);

class ApiPageBase
{
	protected $version;
	protected $latest_version;
	protected $response;
	protected $title;
	
	function __construct()
	{
		initiate_session();
	}
	
	protected function show_query($query, $param_name = 'sql')
	{
		if (isset($_REQUEST['sql']) && is_permitted(PERMISSION_ADMIN))
		{
			$this->response[$param_name] = $query->get_parsed_sql();
		}
	}
	
	private function _doLogin($user_name, $password)
	{
		$found = false;
		$query = new DbQuery('SELECT u.id, u.password FROM users u JOIN names n ON n.id = u.name_id WHERE n.name = ? OR u.email = ? ORDER BY games DESC, id', $user_name, $user_name);
		while ($row = $query->next())
		{
			list ($user_id, $password_hash) = $row;
			if (md5($password) == $password_hash && login($user_id, false))
			{
				$found = true;
				break;
			}
		}
		
		if (!$found)
		{
			throw new Exc('Authorization failed');
		}
	}
	
	protected function _run($title, $version)
	{
		global $_profile;

		$this->title = $title;
		$this->latest_version = $this->version = (int)$version;
		if ($this->version >= 0 && isset($_REQUEST['version']))
		{
			$this->version = (int)$_REQUEST['version'];
		}
		
		if (isset($_REQUEST['help']))
		{
			echo '<!DOCTYPE HTML>';
			echo '<html>';
			echo '<head>';
			echo '<title>' . PRODUCT_NAME . ' ' . $title . ' API</title>';
			echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
			echo '<link rel="stylesheet" href="../api.css" type="text/css" media="screen" />';
			echo '</head><body>';
			try
			{
				if ($this->version > $version)
				{
					throw new FatalExc('Version ' . $this->version . ' is not supported by ' . $title . ' API. Current version is ' . $version . '.');
				}
				$this->show_help();
			}
			catch (RedirectExc $e)
			{
				$url = $e->get_url();
				header('location: ' . $url);
				echo '<p>Redirecting to ' . $url . '</p>';
			}
			catch (Exception $e)
			{
				Exc::log($e, true);
				echo '<p>Error: ' . $e->getMessage() . '</p>';
			}
			echo '</body></html>';
		}
		else
		{
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Allow-Headers: *');
			header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
			header('Content-Type: application/json');
			header('Access-Control-Max-Age: 10000');
			
			ob_start();
			try
			{
				// Admins should able to make requests during the maintanence. 
				// Because they are the ones who is doing the maintanence.
				if ($_profile == NULL || !$_profile->is_admin())
				{					
					check_maintenance();
				}
				
				if ($this->version > $version)
				{
					// No localization because this is an assert. The calling code must fix it.
					throw new FatalExc('Version ' . $this->version . ' is not supported by ' . $title . ' API. Current version is ' . $version . '.');
				}
				
				$this->response = array();
				
				// Authorization
				if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
				{
					$user_name = $_SERVER['PHP_AUTH_USER'];
					$password = $_SERVER['PHP_AUTH_PW'];
					if ($_profile == NULL || ($user_name != $_profile->user_name && $user_name != $_profile->user_email))
					{
						$this->_doLogin($user_name, $password);
						$this->response['logged_in'] = $user_name;
					}
				}
				$this->prepare_response();
			}
			catch (LoginExc $e)
			{
				if ($e->user_name != null)
				{
					$this->response['login'] = $e;
				}
				$this->response['error'] = $e->getMessage();
			}
			catch (RedirectExc $e)
			{
				Db::rollback();
				$url = $e->get_url();
				header('location: ' . $url);
				$this->response['redirect'] = $url;
			}
			catch (Exception $e)
			{
				Db::rollback();
				Exc::log($e, true);
				$this->response['error'] = $e->getMessage();
			}
			
			if ($this->version >= 0)
			{
				$this->response['version'] = $this->version;
			}
			
			$message = ob_get_contents();
			ob_end_clean();
			if ($message != '')
			{
				if (isset($this->response['message']))
				{
					$message = $this->response['message'] . '<hr>' . $message;
				}
				$this->response['message'] = $message;
			}
			echo json_encode($this->response);
		}
	}
	
	protected function prepare_response()
	{
	}
	
	protected function add_default_help_params($help)
	{
		if ($this->version >= 0)
		{
			$descr = 'Required ' . $this->title . ' API version. It is recommended to set it. It guarantees that the format of a data you receive is never changed. 
				Note that <q>version</q> is the only parameter that can be used together with <q>help</q>. Current version is ' . $this->latest_version . '.';
			if ($this->latest_version != $this->version)
			{
				$descr .= ' This help shows data format for version ' . $this->version . '.';
			}
			
			$help->request_param('version', $descr, 'latest version is used'); 
		}
		$help->request_param('lod', 'Level of details. The greater it is the more fields are added to the response. When LOD is not 0 for a response field, it is specified in the field description.', '0.');
		$help->request_param('help', 'Shows this screen.', '-');
		
		$help->response_param('error', 
			'Error message. Successful requests never have this field. If a caller wants to check if the request is successful, 
			it is enough to check if "error" is missing.
			<p>Normally error messages are using account default language or language specified by "lang" parameter. However 
			some of them are just in English. For example, a missing required parameter generates an English message because 
			this is rather an assert. It is caused by a bug in a calling code. Users never see it if the caller code is correct.</p>');
		$help->response_param('version', $this->title . ' API version used for proceeding the request.');
		
		return $help;
	}
	
	protected function show_permissions($help)
	{
		echo '<p><strong>Permitted to:</strong> ';
		$perm = $help->permissions;
		$next_perm = ($perm & ($perm - 1));
		if ($perm != $next_perm)
		{
			echo '<em>' . permission_name($perm - $next_perm) . '</em>';
			$perm = $next_perm;
			while ($perm != 0)
			{
				$next_perm = ($perm & ($perm - 1));
				echo ', or <em>' . permission_name($perm - $next_perm) . '</em>';
				$perm = $next_perm;
			}
		}
		echo '.</p>';
	}
	
	protected function show_help()
	{
		echo '<h1>' . $this->title . ' API</h1>';
		echo '<p><a href="index.php">Back to the service list.</a></p>';
		$help = $this->add_default_help_params($this->get_help());
		
		$this->show_permissions($help);
		echo '<p>' . $help->text . '</p>';
		echo '<h2>Request Parameters:</h2><dl>';
		foreach ($help->request as $param)
		{
			$param->show();
		}
		echo '</dl>';
		
		echo '<h2>Response Parameters:</h2><dl>';
		foreach ($help->response as $param)
		{
			$param->show();
		}
		echo '</dl>';
	}
	
	protected function get_help()
	{
		throw new Exc('Help is not available for ' . $this->title);
	}
}

class GetApiPageBase extends ApiPageBase
{
	function __construct()
	{
		if (isset($_REQUEST['lang']))
		{
			initiate_session($_REQUEST['lang']);
		}
		else
		{
			initiate_session();
		}
	}
	
	final function run($title, $version)
	{
		$this->_run($title, $version);
	}
}

class ControlApiPageBase extends ApiPageBase
{
	final function run($title, $version = -1)
	{
		$this->_run($title, $version);
	}
}

class OpsApiPageBase extends ApiPageBase
{
	final function run($title, $version)
	{
		$this->_run($title, $version);
	}
	
	protected function prepare_response()
	{
		if (!isset($_REQUEST['op']))
		{
			// No localization because this is an assert. The calling code must fix it.
			throw new Exc('Operation is not specified for the ' . $this->title . ' web request.');
		}
		$op = $_REQUEST['op'];
		
		$func = $op . '_op';
		if (!method_exists($this, $func))
		{
			// No localization because this is an assert. The calling code must fix it.
			throw new Exc('Unknown operation "' . $op . '" in the ' . $this->title . ' web request.');
		}
		$this->$func();
	}
	
	protected function show_help()
	{
		echo '<h1>' . $this->title . ' API</h1>';
		echo '<p><a href="index.php">Back to the service list.</a></p>';
		
		$current_op = NULL;
		if (isset($_REQUEST['op']))
		{
			$current_op = $_REQUEST['op'];
			if ($current_op == 'show')
			{
				$current_op = NULL;
			}
		}
		
		$methods = get_class_methods(get_class($this));
		// echo '<pre>';
		// print_r($methods);
		// echo '</pre>';
		
		echo '<form name="op_form" method="get" action="' . $_SERVER['SCRIPT_NAME'] . '"><input type="hidden" name="help" value="">' . $this->title . ': ';
		echo '<select name="op"  onchange="document.op_form.submit()">';
		foreach ($methods as $method)
		{
			if (substr($method, -8) == '_op_help')
			{
				$op = substr($method, 0, -8);
				if ($op == 'show')
				{
					continue;
				}
				if ($current_op == NULL)
				{
					$current_op = $op;
				}
				show_option($op, $current_op, $op);
			}
		}
		echo '</select></form>';
		
		if ($current_op == NULL)
		{
			throw new Exc('No help availible for ' . $this->title);
		}
		$help_func = $current_op . '_op_help';
		if (!method_exists($this, $help_func))
		{
			throw new Exc('No help availible for operation "' . $current_op . '" in ' . $this->title);
		}
		$help = $this->add_default_help_params($this->$help_func());
		
		
		echo '<h1>Operation: ' . $current_op . '</h1>';
		echo '<p>' . $help->text . '</p>';
		
		$this->show_permissions($help);
		
		echo '<h2>Request Parameters:</h2><dl>';
		foreach ($help->request as $param)
		{
			$param->show();
		}
		echo '</dl>';
		
		echo '<h2>Response Parameters:</h2><dl>';
		foreach ($help->response as $param)
		{
			$param->show();
			// echo '<dt>' . $param->name . '</dt><dd>' . $param->description . '</dd>';
		}
		echo '</dl>';
	}
}

function get_required_param($param)
{
	if (!isset($_REQUEST[$param]))
	{
		// No localization because this is an assert. The calling code must fix it.
		throw new Exc('"' . $param . '" must be set in ' . $_SERVER['REQUEST_URI']);
	}
	return $_REQUEST[$param];
}

function get_optional_param($param, $def_value = '')
{
	if (!isset($_REQUEST[$param]))
	{
		return $def_value;
	}
	return $_REQUEST[$param];
}

?>