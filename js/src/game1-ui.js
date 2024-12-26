mafia.ui = new function()
{
	this.start = function(eventId, tableNum, roundNum)
	{
		mafia.start(eventId, tableNum, roundNum);
		var html = '<p>Event: ' + eventId + '. Table: ' + (tableNum + 1) + '. Round: ' + (roundNum + 1) + '.</p><p><img src="images/repairs.png" width="160"></p><p><b><big>Under Construction</big></b></p>'
		$('#game-area').html(html);
	}
} // mafia.ui
