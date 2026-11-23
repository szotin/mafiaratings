<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/location.php';

define('DEFAULT_CURRENCY', 1);

function format_currency($amount, $pattern, $zero_as_free = true)
{
	if (is_null($pattern))
	{
		return '';
	}
	
	if ($zero_as_free && $amount == 0)
	{
		return get_label('free');
	}
	
	$pos = 0;
	while ($pos < strlen($pattern))
	{
		$pos = strpos($pattern, '#');
		if ($pos === false)
		{
			break;
		}
		
		if ($pos + 1 < strlen($pattern) && $pattern[$pos + 1] == '#')
		{
			$pos = $pos + 2;
		}
		else
		{
			return substr($pattern, 0, $pos) . number_format($amount) . substr($pattern, $pos + 1);
		}
	}
	return number_format($amount);
}

// The row of a form with the admission rate: "unknown", the amount, the currency - the ids the
// scripts of the forms use (form-fee-unknown, form-fee, form-currency). $fee NULL is unknown. Each
// currency carries its pattern ("$#") in data-pattern, for writing the amount as it is written:
// the summary of include/details_tab.php shows "$30", not "30, US Dollars".
function show_fee_row($label, $fee, $currency_id)
{
	global $_lang;

	echo '<tr><td>' . $label . ':</td><td style="white-space: nowrap;">';
	echo '<input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"' . (is_null($fee) ? ' checked' : '') . '> ' . get_label('unknown') . ' ';
	echo '<input type="number" min="0" style="width: 64px;" id="form-fee" value="' . (is_null($fee) ? '' : $fee) . '" onchange="feeChanged()"> ';
	echo '<select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	$query = new DbQuery('SELECT c.id, n.name, c.pattern FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & ' . $_lang . ') <> 0 ORDER BY n.name');
	while ($row = $query->next())
	{
		list($cid, $cname, $cpattern) = $row;
		$cid = (int)$cid;
		echo '<option value="' . $cid . '" data-pattern="' . htmlspecialchars($cpattern, ENT_QUOTES, 'UTF-8') . '"' . ($cid == $currency_id ? ' selected' : '') . '>' . $cname . '</option>';
	}
	echo '</select></td></tr>';
}

?>