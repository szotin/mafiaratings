<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/ccc_filter.php';

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

		$delim = '?';
		$ccc = '';
		if (isset($_REQUEST['ccc']))
		{
			$ccc = '?ccc=' . $_REQUEST['ccc'];
			$delim = '&';
		}
		
		$menu = array
		(
			new MenuItem('index.php' . $ccc, get_label('Home'), get_label('Main page')),
			new MenuItem('ratings.php' . $ccc, get_label('Ratings'), get_label('Players ratings')),
			new MenuItem('competition.php' . $ccc, get_label('Competition chart'), get_label('Competition chart at the top of the rating.')),
			// new MenuItem('leagues.php' . $ccc, get_label('Leagues'), get_label('Leagues list')),
			new MenuItem('clubs.php' . $ccc, get_label('Clubs'), get_label('Clubs list')),
			new MenuItem('tournaments.php' . $ccc, get_label('Tournaments'), get_label('Tournaments history')),
			new MenuItem('events.php' . $ccc, get_label('Events'), get_label('Events history')),
			new MenuItem('games.php' . $ccc, get_label('Games'), get_label('List of all played games')),
			// new MenuItem('adverts.php' . $ccc, get_label('Adverts'), get_label('Mafia adverts')),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('stats.php' . $ccc, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('by_numbers.php' . $ccc, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('nominations.php' . $ccc, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('moderators.php' . $ccc, get_label('Moderators'), get_label('Moderators statistics')),
			)),
			// new MenuItem('photo_albums.php' . $ccc, get_label('Photos'), get_label('Photo albums')),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('rules.php', get_label('Rulebook'), get_label('Rules of the game in [0]', PRODUCT_NAME)),
				new MenuItem('photo_albums.php' . $ccc, get_label('Photos'), get_label('Photo albums')),
				new MenuItem('videos.php' . $ccc . $delim . 'vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.')),
				new MenuItem('videos.php' . $ccc . $delim . 'vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.')),
				// new MenuItem('tasks.php' . $ccc, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('articles.php' . $ccc, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('links.php' . $ccc, get_label('Links'), get_label('Links to custom mafia web sites.')),
				// new MenuItem('rulebook.php' . $ccc, get_label('Rules'), get_label('Mafia rulebook')),
			)),
			// new MenuItem('calendar.php' . $ccc, get_label('Calendar'), get_label('Where and when can I play')),
		);
			
		if ($_profile != NULL && $_profile->is_admin())
		{
			$menu[] = new MenuItem('#site', get_label('Management'), NULL, array
			(
				new MenuItem('users.php' . $ccc, get_label('Users'), get_label('Manage [0] users', PRODUCT_NAME)),
				new MenuItem('countries.php' . $ccc, get_label('Countries'), get_label('Manage countries')),
				new MenuItem('cities.php' . $ccc, get_label('Cities'), get_label('Manage cities')),
				new MenuItem('scorings.php' . $ccc, get_label('Scoring systems'), get_label('Manage scoring systems')),
				new MenuItem('club_requests.php' . $ccc, get_label('Club requests'), get_label('Requests for creating a club')),
				new MenuItem('repairs.php' . $ccc, get_label('Repairs'), get_label('Repairing broken things')),
				new MenuItem('duplicated_games.php' . $ccc, get_label('Duplicated games'), get_label('Search for duplicated games suspects.')),
				new MenuItem('duplicated_users.php' . $ccc, get_label('Duplicated users'), get_label('Merge duplicated user accounts.')),
				new MenuItem('sounds.php?mode=shared' . $ccc, get_label('Game sounds'), get_label('Sounds in the game for prompting players on speech end.')),
				new MenuItem('log.php' . $ccc, get_label('Log'), get_label('Log')),
			));
		}
		
		// $menu[] = new MenuItem('#about', get_label('About'), NULL, array
		// (
			// new MenuItem('welcome.php' . $ccc, get_label('Welcome'), get_label('Welcome to the [0]!', PRODUCT_NAME)),
			// new MenuItem('about.php' . $ccc, get_label('About'), get_label('About [0]', PRODUCT_NAME)),
			// new MenuItem('rulebook.php' . $ccc, get_label('Rules'), get_label('Mafia rulebook')),
			// new MenuItem('tactics.php' . $ccc, get_label('Tactics'), get_label('Game tactics')),
			// new MenuItem('downloads.php' . $ccc, get_label('Downloads'), get_label('Download client software for Mafia.')),
		// ));
		
		echo '<p><table class="head" width="100%">';
		
		echo '<tr><td colspan="2">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td><p>' . $this->standard_title() . '</p></td><td align="right" valign="top">';
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
		echo '</table></p>';
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