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

	echo '<p><table class="transp" width="0"><tr><td class="nav" width="60">';
	$middle = floor(PAGE_THUMBNAILS_COUNT / 2);
	$last_page = get_last_page($page_size, $count);
	if ($_page > $middle)
	{
		$min_page = $_page - $middle;
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

	if ($_page > 0)
	{
		echo '<a href="' . $base_url . '0" title="'.get_label('First page').' 1: 1-' . $page_size . '"><img src="images/first.png" border="0"></a>';
		$rmin = ($_page - 1) * $page_size + 1;
		$rmax = $rmin + $page_size - 1;
		echo '&nbsp;<a href="' . $base_url . ($_page - 1) . '" title="'.get_label('Previous page').' ' . $_page . ': ' . $rmin . '-' . $rmax . '"><img src="images/prev.png" border="0"></a>';
	}
	else
	{
		echo '<img src="images/first_d.png" border="0">&nbsp;<img src="images/prev_d.png" border="0">';
	}
	echo '</td><td width="0" class="nav">';
	
	if ($min_page > 0)
	{
		echo '&nbsp;|&nbsp;...';
	}
	
	$p = $min_page;
	for ($i = 0; $i < PAGE_THUMBNAILS_COUNT; ++$i)
	{
		if ($p != $_page)
		{
			$rmin = $p * $page_size + 1;
			$rmax = $rmin + $page_size - 1;
			if ($rmax > $count)
			{
				$rmax = $count;
			}
			echo '&nbsp;|&nbsp;<a href="' . $base_url . $p . '" title="Page ' . ($p + 1) . ': ' . $rmin . '-' . $rmax . '">' . ($p + 1) . '</a>';
		}
		else
		{
			echo '&nbsp;|&nbsp;' . ($p + 1);
		}
		++$p;
		if ($p * $page_size >= $count)
		{
			break;
		}
	}
	
	if ($p * $page_size < $count)
	{
		echo '&nbsp;|&nbsp;...';
	}
	
	echo '&nbsp;|&nbsp;</td><td width="60" class="nav">';
	if (($_page + 1) * $page_size < $count)
	{
		$rmin = ($_page + 1) * $page_size + 1;
		$rmax = $rmin + $page_size - 1;
		if ($rmax > $count)
		{
			$rmax = $count;
		}
		echo '<a href="' . $base_url . ($_page + 1) . '" title="'.get_label('Next page').' ' . ($_page + 2) . ': ' . $rmin . '-' . $rmax . '"><img src="images/next.png" border="0"></a>';
		echo '&nbsp;<a href="' . $base_url . $last_page . '" title="'.get_label('Last page').' ' . ($last_page + 1) . ': ' . ($last_page * $page_size + 1) . '-' . $count . '"><img src="images/last.png" border="0"></a>';
	}
	else
	{
		echo '<img src="images/next_d.png" border="0">&nbsp;<img src="images/last_d.png" border="0">';
	}
	echo '</td></tr></table></p>';
}

?>