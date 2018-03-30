<?php

require_once 'include/general_page_base.php';
require_once 'include/scoring.php';

class Page extends PageBase
{
	private $scoring;
	
	protected function prepare()
	{
		parent::prepare();
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
		}
		$this->scoring = new ScoringSystem($_REQUEST['id']);
		$this->_title = get_label('Scoring system') . ': ' . $this->scoring->name;
	}
	
	protected function show_body()
	{
		$this->scoring->show_rules(true);
	}
}

$page = new Page();
$page->run('', UC_PERM_MANAGER);

?>