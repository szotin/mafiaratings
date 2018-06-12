<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	private $video;
	private $videos;
	
	protected function prepare()
	{
		parent::prepare();
		$this->video = 0;
		if (isset($_REQUEST['vid']))
		{
			$this->video = $_REQUEST['vid'];
		}
		
		$this->videos = array(
			array(get_label('What is [0]', PRODUCT_NAME), 'http://www.youtube.com/embed/CG3v9U6oCm0'),
			array(get_label('How to create new club in [0]', PRODUCT_NAME), 'http://www.youtube.com/embed/V25SXvKxL3U'));
		
	}
	
	protected function show_body()
	{
		global $_lang_code;
		if ($_lang_code == 'ru')
		{
			if ($this->video < 0)
			{
				$this->video = 0;
			}
			else if ($this->video >= count($this->videos))
			{
				$this->video = count($this->videos) - 1;
			}
		
			echo '<table class="transp" align="center" width="100%"><tr><td>';
			if ($this->video > 0)
			{
				echo '<input type="submit" value="' . get_label('Previous video') . '" class="btn long" onclick="prevVideo()">';
			}
			echo '</td><td align="right">';
			if ($this->video < 1)
			{
				echo '<input type="submit" value="' . get_label('Next video') . '" class="btn long" onclick="nextVideo()">';
			}
			echo '</td></tr></table>';
			
			echo '<p align="center"><iframe width="750" height="600" src="' . $this->videos[$this->video][1] . '" frameborder="0" allowfullscreen></iframe></p>';
		}
		else
		{
			include_once("include/languages/".$_lang_code."/welcome.php");
		}
	}

	protected function show_filter_fields()
	{
		echo '<select id="vid" onChange="filter()">';
		$video_count = count($this->videos);
		for ($i = 0; $i < $video_count; ++$i)
		{
			show_option($i, $this->video, $this->videos[$i][0]);
		}
		echo '</select>';
	}
	
	protected function get_filter_js()
	{
		return '+ "&vid=" + $("#vid").val()';
	}
	
	protected function js()
	{
		parent::js();
?>
		function prevVideo()
		{
			window.location.replace("?ccc=" + cccCode + '&vid=' + (parseInt($("#vid").val()) - 1));
		}
		
		function nextVideo()
		{
			window.location.replace("?ccc=" + cccCode + '&vid=' + (parseInt($("#vid").val()) + 1));
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Welcome to the [0]!', PRODUCT_NAME));

?>