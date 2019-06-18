<?php

return array
(
	PRODUCT_NAME,
	"<p>Hi [user_name],</p><p>[sender] from [club_name] wants to change tournament stars from [old_stars] (<big>[old_stars_str]</big>) to [stars] (<big>[stars_str]</big>) for \"[tournament_name]\" in [league_name]. <a href=\"[root]/tournament_info.php?id=[tournament_id]&_login_=[user_id]&approve=[league_id]\">Please confirm</a>.</p>",
	"Hi [user_name],\r\n\r\n[sender] from [club_name] wants to change tournament stars from [old_stars] ([old_stars_str]) to [stars] ([stars_str]) for \"[tournament_name]\" in [league_name]. Please confirm here [root]/tournament_info.php?id=[tournament_id]&_login_=[user_id]&approve=[league_id].\r\n"
);

?>