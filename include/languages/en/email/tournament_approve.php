<?php

return array
(
	PRODUCT_NAME,
	"<p>Hi [user_name],</p>\r\n<p>[sender] from [club_name] requested to hold a [stars] stars (<big>[stars_str]</big>) tournament \"[tournament_name]\" in [league_name]. <a href=\"[root]/tournament_info.php?id=[tournament_id]&_login_=[user_id]&approve=[league_id]\">Please confirm</a>.</p>",
	"Hi [user_name],\r\n\r\n[sender] requested to hold a [stars] stars ([stars_str]) tournament \"[tournament_name]\" in [league_name]. Please confirm here [root]/tournament_info.php?id=[tournament_id]&_login_=[user_id]&approve=[league_id].\r\n"
);

?>