//------------------------------------------------------------------------------------------
// game data
//------------------------------------------------------------------------------------------
var mafia = new function()
{
	this.start = function(eventId, tableNum, roundNum)
	{
		json.post('api/ops/game.php', { op: 'get_current', event_id: eventId, table: tableNum, round: roundNum }, function(data)
		{
			console.log(data);
		},
		function ()
		{
			console.log('error');
		});
	}
}
