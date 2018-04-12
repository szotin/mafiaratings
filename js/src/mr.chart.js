var theChart = null;

function updateChart(params)
{
	json.post("chart_ops.php", params, 
		function(data)
		{
			theChart.data.datasets = data;
			theChart.update();
			
			var ctx = $("#chart-legend");
			if (ctx.length > 0)
			{
				html.post("chart_legend.php", params, function(text, title)
				{
					ctx.html(text);
					for (var i = 0; i < params.charts; ++i)
					{
						var ctrl = $("#chart-player-" + i);
						if (ctrl.length > 0)
						{
							ctrl.autocomplete(
							{ 
								source: function( request, response )
								{
									$.getJSON("user_ops.php",
									{
										list: '',
										term: request.term
									}, response);
								},
								select: function(event, ui) 
								{
									var index = event.target.id.substring(13);
									var players = params.players;
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
									params.players = newPlayers;
									updateChart(params);
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

function initChart(name, params)
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
			maintainAspectRatio: false
		}
	});
	
	updateChart(params);
}
