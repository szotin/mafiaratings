<?php

// NEW_LOOK: the new_look branch is served from public_html/new_look on production, beside the
// main site and not instead of it. Everything that names where the site lives goes through
// SITE_SUBDIR (see get_site_subdir() in server.php). Rolled back before the merge into master -
// see _new_look_rollback.md.
define('SITE_SUBDIR', '/new_look');

define('PRODUCT_NAME', 'Mafia Ratings');
define('PRODUCT_TERM', 'MafiaRatings');
define('PRODUCT_URL', 'https://www.mafiaratings.com' . SITE_SUBDIR);
define('PRODUCT_URL_HTTP', 'http://www.mafiaratings.com' . SITE_SUBDIR);
define('PRODUCT_SITE', 'www.mafiaratings.com');
define('PRODUCT_EMAIL', 'admin@mafiaratings.com');
define('PRODUCT_FB_ADMINS', '1339983926');

?>