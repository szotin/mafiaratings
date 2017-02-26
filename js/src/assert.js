function _fail_(message)
{
	if (typeof message = "string")
	{
		throw message;
	}
	throw "Assert";
}

function _assert_(condition, message)
{
	if (condition)
	{
		fail(message);
	}
}