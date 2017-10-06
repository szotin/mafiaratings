<?php

require_once 'include/languages.php';
require_once 'include/url.php';

class Tag
{
	public $open_value;
	public $close_value;
	
	// Examples:
	// 'a' => new Tag('<hr>') - replaces all occurances of [a] with <hr>; [/a] - is left as is.
	// 'b' => new Tag('<b>', '</b>') - replaces all occurances of [b] with <b> ; all occurances of [/b] with </b>. If [b] does not have a corresponding [/b] it auto-adds </b> at the end.
	// 'img' => new Tag('<img src="#" />') - replaces all occurances of [img=image.jpg] with <img src="image.jpg"> ; [/img] is left as is.
	// # is treated as a parameter. If # needed as a symbol, use \# - !!! not implemented yet
	// 'url' => new Tag('<a href="#">', '</a>') - '[url=http://www.google.com]Google[/url] -> <a href="http://www.google.com">Google</a>. If [url] does not have a corresponding [/url] it auto-adds </a> at the end.
	//
	// Nested tags are allowed. For example:
	//
	// 	$tags = array(
	// 		'mysite' => new Tag('http://www.mysite.com'),
	// 		'url' => new Tag('<a href="#">', '</a>');
	//	parse_tags('[url=[mysite]]My site[/url]', $tags);
	//
	//  returns: '<a href="http://www.mysite.com">My site</a>'
	// 
	// Even the recursion is possible (How can it be used? I have no idea - this is a side effect :)
	// 
	// 	$tags = array(
	// 		'a' => new Tag('AAA'),
	// 		'b' => new Tag('a');
	//	parse_tags('[[b]]', $tags);
	//
	// returns 'AAA'
	function __construct($open_value, $close_value = NULL)
	{
		$this->open_value = $open_value;
		$this->close_value = $close_value;
	}
}

// returns 0 if tag not found; 1 - found no recursion; 2 - found with recursion
function find_next_tag($message, &$beg, &$end)
{
	$pos = strpos($message, '[', $beg);
	if ($pos === false)
	{
		return 0;
	}
	
	$cur_beg = $pos + 1;
	$cur_end = 0;
	
	$next_close = strpos($message, ']', $cur_beg);
	if ($next_close === false)
	{
		return 0;
	}
	
	$next_open = strpos($message, '[', $cur_beg);
	if ($next_open === false || $next_open > $next_close)
	{
		$beg = $cur_beg;
		$end = $next_close;
		return 1;
	}
	
	$cur_beg = $next_open;
	if (!find_next_tag($message, $cur_beg, $cur_end))
	{
		return 0;
	}
	
	$beg = $cur_beg;
	$end = $cur_end;
	while (true)
	{
		$next_close = strpos($message, ']', $end + 1);
		if ($next_close === false)
		{
			$beg = $cur_beg;
			$end = $cur_end;
			break;
		}
		
		$next_open = strpos($message, '[', $end + 1);
		if ($next_open === false || $next_open > $next_close)
		{
			$beg = $pos + 1;
			$end = $next_close;
			break;
		}
		
		if (!find_next_tag($message, $next_open, $end))
		{
			$beg = $cur_beg;
			$end = $cur_end;
			break;
		}
	}
	return 2;
}

function parse_tags($message, $tags)
{
	$beg = 0;
	$end = 0;
	$pos = 0;
	$find_result = find_next_tag($message, $beg, $end);
	if ($find_result == 0)
	{
		return $message;
	}
	
	$row = '';
	$opened_tags = array();
	while (true)
	{
		switch ($find_result)
		{
			case 0:
				$row .= substr($message, $beg);
//				echo '<br>' . substr($message, $beg);
				return $row;
			case 1:
				$content = substr($message, $beg, $end - $beg);
//				echo '<br>' . substr($message, $beg, $end - $beg);
				break;
			case 2:
//				echo '<br>' . substr($message, $beg, $end - $beg);
				$content = parse_tags(substr($message, $beg, $end - $beg), $tags);
				break;
		}
		
		
		$row .= substr($message, $pos, $beg - $pos - 1);
		
		$param_separator = strpos($content, '=', 0);
		if ($param_separator === false)
		{
			$tag_name = $content;
			$param = NULL;
		}
		else
		{
			$tag_name = substr($content, 0, $param_separator);
			$param = substr($content, $param_separator + 1);
		}

		$found = false;
		foreach ($tags as $key => $tag)
		{
			if ($tag_name == $key)
			{
				if ($param === NULL)
				{
					$row .= $tag->open_value;
				}
				else
				{
					$row .= str_replace('#', $param, $tag->open_value);
				}
				$found = true;
				if ($tag->close_value != NULL)
				{
					$opened_tags[] = $key;
				}
				break;
			}
			else if ($tag_name == '/' . $key)
			{
				foreach ($opened_tags as $opened_tag_index => $opened_tag_name)
				{
					if ($opened_tag_name == $key)
					{
						unset($opened_tags[$opened_tag_index]);
						$found = true;
						break;
					}
				}
				if ($found)
				{
					if ($param === NULL)
					{
						$row .= $tag->close_value;
					}
					else
					{
						$row .= str_replace('#', $param, $tag->$close_value);
					}
				}
				break;
			}
		}
		
		if (!$found)
		{
			$row .= '[' . $content . ']';
		}
		
		$pos = $beg = $end + 1;
		$find_result = find_next_tag($message, $beg, $end);
	}
	
	foreach ($opened_tags as $tag)
	{
		$row .= $tag->close_value;
	}
	return $row;
}

function get_bbcode_tags()
{
	return array(
		'p' => new Tag('<p>', '</p>'),
		'hr' => new Tag('<hr>'),
		'b' => new Tag('<b>', '</b>'),
		'i' => new Tag('<i>', '</i>'),
		'u' => new Tag('<u>', '</u>'),
		's' => new Tag('<s>', '</s>'),
		'quote' => new Tag('<blockquote>', '</blockquote>'),
		'code' => new Tag('<pre>', '</pre>'),
		'img' => new Tag('<img src="#">'),
		'size' => new Tag('<span style="font-size:#px;">', '</span>'),
		'color' => new Tag('<span style="color:#;">', '</span>'),
		'url' => new Tag('<a href="#" target="_blank">', '</a>'));
}

function replace_returns($message)
{
//	$message = str_replace("\r\n", '<br>', $message);
	return str_replace("\n", '<br>', $message);
}

function parse_message_urls($message)
{
	$array = preg_split('/[\(\)\[\]\{\},<> \r\n]/', $message, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
	$urls = array();
	foreach ($array as $word_info)
	{
		$word = $word_info[0];
		$flags = is_url($word);
		if ($flags != IS_URL_NO)
		{
			$urls[] = array($word_info[1], strlen($word), $flags);
		}
	}
	
	for ($i = count($urls) - 1; $i >= 0; --$i)
	{
		$url_info = $urls[$i];
		$beg = $url_info[0];
		$len = $url_info[1];
		$flags = $url_info[2];
		
		$before = substr($message, 0, $beg);
		$inside = substr($message, $beg, $len);
		$after = substr($message, $beg + $len);
		
		$url = $inside;
		if (($flags & IS_URL_NO_PROTOCOL) != 0)
		{
			$url = 'http://' . $inside;
		}
		
		if (($flags & IS_URL_IMG) != 0)
		{
			$message = $before . '<img src="' . $url . '" />' . $after;
		}
		else 
		{
			$message = $before;
			if (($flags & IS_URL_VIDEO) != 0)
			{
				$pos = strpos($url, 'v=');
				if ($pos !== false)
				{
					$end = strpos($url, '&', $pos + 2);
					if ($end === false)
					{
						$code = substr($url, $pos + 2);
					}
					else
					{
						$code = substr($url, $pos + 2, $end - $pos - 2);
					}
					$message .= '<iframe title="YouTube video player" width="400" height="300" src="https://www.youtube.com/embed/' . $code . '" frameborder="0" allowfullscreen></iframe><br>';
				}
			}
			$message .= '<a href="' . $url . '" target="_blank">' . $inside . '</a>' . $after;
		}
	}
	return $message;
}

function prepare_message($message, $tags)
{
	$message = stripslashes($message);
	$message = htmlspecialchars($message, ENT_QUOTES, "UTF-8");
	$message = parse_tags($message, $tags);
	$message = parse_message_urls($message);
	$message = replace_returns($message);
	return $message;
}

?>