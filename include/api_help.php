<?php

class ApiHelpRequestParam
{
	public $name;
	public $description;
	
	function __construct($name, $description, $when_missing = NULL)
	{
		$this->name = $name;
		$this->description = $description;
		if ($when_missing != NULL)
		{
			$this->when_missing = $when_missing;
		}
	}
	
	function sub_param($name, $description, $when_missing = NULL)
	{
		$param = new ApiHelpRequestParam($name, $description, $when_missing);
		if (!isset($this->params))
		{
			$this->params = array();
		}
		$this->params[] = $param;
		return $param;
	}
	
	function show()
	{
		$when_missing = NULL;
		if (isset($this->when_missing))
		{
			$when_missing = $this->when_missing;
		}
		echo '<dt>' . $this->name;
		if ($when_missing == NULL)
		{
			echo ' <small>(required)</small>';
		}
		echo '</dt><dd>' . $this->description;
		if ($when_missing != NULL && $when_missing != '-')
		{
			echo '<p><dfn>When missing:</dfn> ' . $when_missing . '</p>';
		}
		
		if (isset($this->params))
		{
			echo '<h3>Structure:</h3><dl>';
			foreach ($this->params as $param)
			{
				$param->show();
			}
			echo '</dl>';
		}
		echo '</dd>';
	}
}

class ApiHelpResponseParam
{
	public $name;
	public $description;
	public $default_value;
	
	function __construct($name, $description, $default_value = NULL)
	{
		$this->name = $name;
		$this->description = $description;
		$this->default_value = $default_value;
	}
	
	function sub_param($name, $description, $default_value = NULL)
	{
		$param = new ApiHelpResponseParam($name, $description, $default_value);
		if (!isset($this->params))
		{
			$this->params = array();
		}
		$this->params[] = $param;
		return $param;
	}
	
	function show()
	{
		echo '<dt>' . $this->name;
		if ($this->default_value != NULL)
		{
			echo ' <small>(optional)</small>';
		}
		echo '</dt><dd>' . $this->description;
		if (isset($this->params))
		{
			echo '<h3>Structure:</h3><dl>';
			foreach ($this->params as $param)
			{
				$param->show();
			}
			echo '</dl>';
		}
		if ($this->default_value != NULL)
		{
			echo '<p><dfn>When missing use ' . $this->default_value . ' </dfn></p>';
		}
		echo '</dd>';
	}
}

class ApiHelp
{
	public $text;
	public $request;
	public $response;
	public $permissions;
	
	function __construct($permissions, $text = '')
	{
		$this->permissions = $permissions;
		$this->text = $text;
		$this->request = array();
		$this->response = array();
	}
	
	function request_param($name, $description, $when_missing = NULL)
	{
		$param = new ApiHelpRequestParam($name, $description, $when_missing);
		$this->request[] = $param;
		return $param;
	}
	
	function response_param($name, $description, $default_value = NULL)
	{
		$param = new ApiHelpResponseParam($name, $description, $default_value);
		$this->response[] = $param;
		return $param;
	}
}
