<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/club.php';
require_once 'include/ccc_filter.php';

define('CCCS_MY', 0);
define('CCCS_ALL', 1);
define('CCCS_NO', 2);

class GeneralPageBase extends PageBase
{
	protected $ccc_filter;
	protected $ccc_state = CCCS_ALL;
	protected $ccc_title = '';
	
	public function set_ccc($ccc_state) { $this->ccc_state = $ccc_state; }
	
	protected function prepare()
	{
		parent::prepare();
		
		switch ($this->ccc_state)
		{
		case CCCS_MY:
			$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_MY);
			break;
		case CCCS_ALL:
			$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
			break;
		}
	}
	
	protected function show_title()
	{
		global $_profile;

		$ccc = '';
		if (isset($_REQUEST['ccc']))
		{
			$ccc = '?ccc=' . $_REQUEST['ccc'];
		}
		$menu = array(
			new MenuItem('index.php' . $ccc, get_label('Home'), get_label('Main page')),
			new MenuItem('clubs.php' . $ccc, get_label('Clubs'), get_label('Clubs list')),
			new MenuItem('ratings.php' . $ccc, get_label('Ratings'), get_label('Players ratings')),
			new MenuItem('stats.php' . $ccc, get_label('Stats'), get_label('Games statistics')),
			new MenuItem('history.php' . $ccc, get_label('Events'), get_label('Events history')),
			// new MenuItem('adverts.php' . $ccc, get_label('Adverts'), get_label('Mafia adverts')),
			new MenuItem('photo_albums.php' . $ccc, get_label('Photos'), get_label('Photo albums')),
			new MenuItem('moderators.php' . $ccc, get_label('Moderators'), get_label('Moderators statistics')),
			new MenuItem('games.php' . $ccc, get_label('Games'), get_label('List of all played games'))
			// new MenuItem('calendar.php' . $ccc, get_label('Calendar'), get_label('Where and when can I play')),
			// new MenuItem('forum.php' . $ccc, get_label('Forum'), get_label('Mafia forum'))
			);
			
		if ($_profile != NULL && $_profile->is_admin())
		{
			$menu[] = new MenuItem('#site', get_label('Management'), NULL, array(
				new MenuItem('users.php' . $ccc, get_label('Users'), get_label('Manage [0] users', PRODUCT_NAME)),
				new MenuItem('countries.php' . $ccc, get_label('Countries'), get_label('Manage countries')),
				new MenuItem('cities.php' . $ccc, get_label('Cities'), get_label('Manage cities')),
				new MenuItem('scorings.php' . $ccc, get_label('Scoring systems'), get_label('Manage scoring systems')),
				new MenuItem('club_requests.php' . $ccc, get_label('Club requests'), get_label('Requests for creating a club')),
				new MenuItem('repairs.php' . $ccc, get_label('Repairs'), get_label('Repairing broken things')),
				new MenuItem('duplicated_games.php' . $ccc, get_label('Duplicated games'), get_label('Search for duplicated games suspects.')),
				new MenuItem('log.php' . $ccc, get_label('Log'), get_label('Log'))));
		}
		
		$menu[] = new MenuItem('#about', get_label('About'), NULL, array(
//			new MenuItem('welcome.php' . $ccc, get_label('Welcome'), get_label('Welcome to the [0]!', PRODUCT_NAME)),
//			new MenuItem('about.php' . $ccc, get_label('About'), get_label('About [0]', PRODUCT_NAME)),
			new MenuItem('rulebook.php' . $ccc, get_label('Rules'), get_label('Mafia rulebook')),
			new MenuItem('tactics.php' . $ccc, get_label('Tactics'), get_label('Game tactics')),
//			new MenuItem('downloads.php' . $ccc, get_label('Downloads'), get_label('Download client software for Mafia.'))
		));
		
		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="2">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td>' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr>';
		echo '</table>';
		
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		if ($this->ccc_filter != NULL)
		{
			$this->ccc_filter->show('onCCC', $this->ccc_title);
		}
		echo ' ';
		$this->show_filter_fields();
		echo '</td><td align="right">';
		$this->show_search_fields();
		echo '</table>';
	}
	
	protected function show_filter_fields()
	{
	}
	
	protected function show_search_fields()
	{
	}
	
	protected function get_filter_js()
	{
		return '';
	}
	
	protected function js()
	{
		if ($this->ccc_filter != NULL)
		{
?>
			var cccCode = "<?php echo $this->ccc_filter->get_code(); ?>";
			function onCCC(code)
			{
				cccCode = code;
				filter();
			}
			
			function filter()
			{
				window.location.replace("?ccc=" + cccCode <?php echo $this->get_filter_js(); ?>);
			}
<?php
		}
	}
}

?>