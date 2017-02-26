<?php

function db_string($str)
{
	return mysql_real_escape_string($str);
}

function db_query($sql)
{
	return mysql_query($sql);
}

function db_error()
{
	return mysql_error();
}

function db_fetch_row($query)
{
	return mysql_fetch_row($query);
}

function db_num_rows($query)
{
	return mysql_num_rows($query);
}

function db_connect($url, $user, $password, $database)
{
	return mysql_connect($url, $user, $password) && mysql_select_db($database);
}

function db_disconnect()
{
	return mysql_close();
}

function db_begin()
{
	return mysql_query('BEGIN');
}

function db_commit()
{
	return mysql_query('COMMIT');
}

function db_rollback()
{
	return mysql_query('ROLLBACK');
}

function db_affected_rows()
{
	return mysql_affected_rows();
}

?>