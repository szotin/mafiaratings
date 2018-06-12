<?php
require_once '../include/api.php';
?>

<!DOCTYPE HTML>
<html>
<head>
<title><?php echo PRODUCT_NAME; ?> API reference</title>
<META content="text/html; charset=utf-8" http-equiv=Content-Type>
<link rel="stylesheet" href="api.css" type="text/css" media="screen" />
</head><body>

<h1><?php echo PRODUCT_NAME; ?> API refence</h1>

<table class="bordered light" width="100%">
<tr class="darker"><th width="200">Service</th><th>Link</th><th>Description</th></tr>

<tr>
	<td>Getting data from <?php echo PRODUCT_NAME; ?></td>
	<td><a href="get/"><?php echo PRODUCT_URL; ?>/api/get/</a></td>
	<td>
		<p>A set of services returning <?php echo PRODUCT_NAME; ?> data.</p>
	</td>
</tr>

<tr>
	<td>Changing <?php echo PRODUCT_NAME; ?> data</td>
	<td><a href="ops/"><?php echo PRODUCT_URL; ?>/api/ops/</a></td>
	<td>
		<p>A set of services manipulating <?php echo PRODUCT_NAME; ?> data.</p>
	</td>
</tr>

</table>

</body></html>