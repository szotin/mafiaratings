<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
?>
		<textarea id="req" cols="60" rows="4"></textarea></br>
		<input type="submit" onclick="submitGame()" value="Submit">
		<script>
			function submitGame()
			{
				var r = JSON.parse($('#req').val());
				console.log(r);
				json.post('game_ops.php', r, function(data)
				{
					console.log(data);
					if (typeof data.fail == 'string')
					{
						dlg.error(data.fail);
					}
					else
					{
						$('#req').val('');
					}
				});
			}
		</script>
<?php
	}
}

$page = new Page();
$page->run('Submit game', U_PERM_ADMIN);

?>