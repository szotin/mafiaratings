<?php

$mysqli = NULL;

function db_string($str)
{
	global $mysqli;
	return $mysqli->real_escape_string($str);
}

function db_query($sql)
{
	global $mysqli;
	return $mysqli->query($sql);
}

function db_error()
{
	global $mysqli;
	return $mysqli->error();
}

function db_fetch_row($query)
{
	global $mysqli;
	return $mysqli->fetch_row($query);
}

function db_num_rows($query)
{
	global $mysqli;
	return $mysqli->num_rows($query);
}

function db_connect($url, $user, $password, $database)
{
	global $mysqli;
	return $mysqli->connect($url, $user, $password) && $mysqli->select_db($database);
}

function db_disconnect()
{
	global $mysqli;
	return $mysqli->close();
}

function db_begin()
{
	global $mysqli;
	return $mysqli->query('BEGIN');
}

function db_commit()
{
	global $mysqli;
	return $mysqli->query('COMMIT');
}

function db_rollback()
{
	global $mysqli;
	return $mysqli->query('ROLLBACK');
}

function db_affected_rows()
{
	global $mysqli;
	return $mysqli->affected_rows();
}

?>