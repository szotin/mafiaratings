var theChart = null;
var chartParams =
{
	type: "",
	name: "", 
	players: "", 
	id: 0,
	scoring_id: 0,
	scoring_version: 0,
	scoring_options: "{}",
	charts:5
};

function updateChart(players)
{
	if (typeof players == "string")
	{
		chartParams.players = players;
	}
	json.post("api/control/chart.php", chartParams, 
		function(data)
		{
			theChart.data.datasets = data;
			theChart.update();
			
			var ctx = $("#chart-legend");
			if (ctx.length > 0)
			{
				html.post("chart_legend.php", chartParams, function(text, title)
				{
					ctx.html(text);
					for (var i = 0; i < chartParams.charts; ++i)
					{
						var ctrl = $("#chart-player-" + i);
						if (ctrl.length > 0)
						{
							ctrl.autocomplete(
							{ 
								source: function( request, response )
								{
									var autocompleteParams = 
									{
										term: request.term
									};
									if (chartParams.type == "event")
									{
										autocompleteParams["event"] = chartParams.id;
									}
									else if (chartParams.type == "club")
									{
										autocompleteParams["club"] = chartParams.id;
									}
									$.getJSON("api/control/user.php", autocompleteParams, response);
								},
								select: function(event, ui) 
								{
									var index = event.target.id.substring(13);
									var players = chartParams.players;
									var newPlayers = "";
									var id = "" + ui.item.id;
									var currentId;
									var pos = 0;
									while (true)
									{
										var end = players.indexOf(',', pos);
										if (pos > 0)
										{
											newPlayers += ",";
										}
										
										if (index == 0)
										{
											newPlayers += id;
											if (end < 0)
											{
												break;
											}
										}
										else if (end < 0)
										{
											currentId = players.substring(pos);
											if (currentId != id)
											{
												newPlayers += currentId;
											}
											break;
										}
										else
										{
											currentId = players.substring(pos, end);
											if (currentId != id)
											{
												newPlayers += currentId;
											}
										}
										--index;
										pos = end + 1;
									}
									console.log(newPlayers);
									chartParams.players = newPlayers;
									updateChart();
								},
								minLength: 0
							})
							.on("focus", function () { $(this).autocomplete("search", ''); });
						}
					}
				});
			}
		}
	);
}

function initChart(name)
{
	var ctx = document.getElementById("chart");
	theChart = new Chart(ctx,
	{
		type: 'line',
		data:
		{
			datasets: null,
			borderWidth: 1
		},
		options: 
		{
			responsive: false,
			legend:
			{
				display: false
			},
			scales: 
			{
				xAxes: 
				[
					{
						type: 'time',
						time: 
						{
							format: 'MM/DD/YYYY HH:mm',
							//  round: 'day',
							tooltipFormat: 'MM/DD/YYYY HH:mm'
						}
					}
				],
				yAxes: 
				[
					{
					scaleLabel: 
					{
						display: true,
						labelString: name
					}
				}]
			},
			animation: 
			{
				duration: 0
			},		
			maintainAspectRatio: false
		}
	});
	
	updateChart();
}
