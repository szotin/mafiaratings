<?php

define('PAGE_THUMBNAILS_COUNT', 11);

$_page = 0;
if (isset($_REQUEST['page']))
{
	$_page = $_REQUEST['page'];
}

function get_last_page($page_size, $count)
{
	$last_page = floor($count / $page_size);
	if ($last_page * $page_size == $count)
	{
		--$last_page;
	}
	return $last_page;
}

function show_pages_navigation($page_size, $count, $exclude_param = NULL)
{
	global $_page;

	if ($count <= $page_size)
	{
		echo '<p></p>';
		return;
	}
	
	$base_url = '';
	$delim = '?';
	foreach ($_GET as $key => $value)
	{
		if ($key != 'page' && ($exclude_param == NULL || $key != $exclude_param))
		{
			$base_url .= $delim . $key . '=' . $value;
			$delim = '&';
		}
	}
	$base_url .= $delim . 'page=';
	
/*	$base_url = get_page_url();
	$page_pos = strpos($base_url, '?page=');
	if ($page_pos === false)
	{
		$page_pos = strpos($base_url, '&page=');
	}
	if ($page_pos === false)
	{
		if (strpos($base_url, '?') === false)
		{
			$base_url .= '?page=';
		}
		else
		{
			$base_url .= '&page=';
		}
	}
	else
	{
		$page_end = strpos($base_url, '&', $page_pos + 6);
		if ($page_end === false)
		{
			$base_url = substr($base_url, 0, $page_pos + 1) . 'page=';
		}
		else
		{
			$base_url = substr($base_url, 0, $page_pos + 1) . substr($base_url, $page_end + 1) . '&page=';
		}
	}*/

	$current = (int)$_page;
	$middle = floor(PAGE_THUMBNAILS_COUNT / 2);
	$last_page = get_last_page($page_size, $count);
	if ($current > $middle)
	{
		$min_page = $current - $middle;
		if ($min_page + PAGE_THUMBNAILS_COUNT - 1 > $last_page)
		{
			$min_page = $last_page - PAGE_THUMBNAILS_COUNT + 1;
		}
		if ($min_page < 0)
		{
			$min_page = 0;
		}
	}
	else
	{
		$min_page = 0;
	}
	$base_url = htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8');
	
	// The range of rows a page shows, for the hint over its link.
	$range = function($p) use ($page_size, $count)
	{
		$rmin = $p * $page_size + 1;
		return $rmin . '-' . min($rmin + $page_size - 1, $count);
	};
	// A link to a page, or a dead arrow when there is no such page.
	$arrow = function($p, $enabled, $text, $title, $class) use ($base_url, $range)
	{
		if (!$enabled)
		{
			return '<span class="pg ' . $class . ' is-off" aria-hidden="true">' . $text . '</span>';
		}
		return '<a class="pg ' . $class . '" href="' . $base_url . $p . '" title="' . $title . ' ' . ($p + 1) . ': ' . $range($p) . '">' . $text . '</a>';
	};
	
	echo '<nav class="pager" aria-label="' . get_label('Pages') . '">';
	echo $arrow(0, $current > 0, '&laquo;', get_label('First page'), 'pg-end');
	echo $arrow($current - 1, $current > 0, '&lsaquo;', get_label('Previous page'), 'pg-step');
	if ($min_page > 0)
	{
		echo '<span class="pg-gap">&hellip;</span>';
	}
	$p = $min_page;
	for ($i = 0; $i < PAGE_THUMBNAILS_COUNT; ++$i)
	{
		if ($p != $current)
		{
			echo '<a class="pg" href="' . $base_url . $p . '" title="Page ' . ($p + 1) . ': ' . $range($p) . '">' . ($p + 1) . '</a>';
		}
		else
		{
			echo '<span class="pg" aria-current="page">' . ($p + 1) . '</span>';
		}
		++$p;
		if ($p * $page_size >= $count)
		{
			break;
		}
	}
	if ($p * $page_size < $count)
	{
		echo '<span class="pg-gap">&hellip;</span>';
	}
	$has_next = ($current + 1) * $page_size < $count;
	echo $arrow($current + 1, $has_next, '&rsaquo;', get_label('Next page'), 'pg-step');
	echo $arrow($last_page, $has_next, '&raquo;', get_label('Last page'), 'pg-end');
	echo '</nav>';
}

?>