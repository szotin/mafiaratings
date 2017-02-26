<?php

require_once 'include/session.php';
require_once 'include/forum.php';

try
{
	initiate_session();
	if (check_permissions(U_PERM_ADMIN) && isset($_REQUEST['id']))
	{
		Db::begin();
		ForumMessage::delete($_REQUEST['id']);
		Db::commit();
	}
	redirect_back();
	header('location: index.php');
}
catch (RedirectExc $e)
{
	header('location: ' . $e->get_url());
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
}

?>