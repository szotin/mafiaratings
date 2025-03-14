var _renderCallback = null;

//-----------------------------------------------------------
// Private API. Don't use it outside of this file.
//-----------------------------------------------------------
function _uiOption(value, current, text)
{
	if (value == current)
	{
		return '<option value="' + value + '" selected>' + text + '</option>';
	}
	return '<option value="' + value + '">' + text + '</option>';
}

function _uiPlayerTitle(index)
{
	let name = game.players[index].name;
	let result = '' + (index + 1);
	if (isSet(name) && name.length > 0)
		result += ' (' + name + ')';
	return result;
}

function _uiGenerateNoms()
{
	let noms = gameGetNominees();
	let nomsStr = '';
	let delim = '';
	for (const n of noms)
	{
		nomsStr += delim + n;
		delim = ', ';
	}
	return nomsStr ? l('ShowNoms', nomsStr) : nomsStr;
}

function _uiShowOnRecordButtons()
{
	let player = game.players[game.time.speaker - 1];
	let record = [];
	if (isSet(player.record) && player.record.length > 0)
	{
		let r = player.record[player.record.length - 1];
		if (
			r.time == game.time.time && r.round == game.time.round &&
			(!isSet(game.time.votingRound) || !isSet(r.votingRound) || game.time.votingRound == r.votingRound))
		{
			record = r.record;
		}
	}
	game.players.forEach(function(p,i)
	{
		let n = i + 1;
		if (n != game.time.speaker)
		{
			let checked = 0;
			for (const r of record)
			{
				if (r == n)
					checked = 1;
				else if (r == -n)
					checked = -1;
			}
			$('#controlx'+i).html(
				'<button class="icon" onclick="gameSetOnRecord(' +  n + ')" title="' + l('RecordCiv', n) + '"' + (checked > 0 ? ' checked' : '') + '><img class="role-icon" src="images/civ.png"></button>' +
				'<button class="icon" onclick="gameSetOnRecord(-' + n + ')" title="' + l('RecordMaf', n) + '"' + (checked < 0 ? ' checked' : '') + '><img class="role-icon" src="images/maf.png"></button>');
		}
	});
}

function _uiNominate(playerIndex)
{
	gameChangeNomination(playerIndex, parseInt($('#nominated' + playerIndex).val()) - 1);
}

function _uiShoot(shooter)
{
	gameShoot($('#shot' + shooter).val(), shooter);
}

function _uiRender(resetTimer)
{
	let timerTime = 60;
	let html = '<option value="0"></option>';
	for (let i in regs)
	{
		let r = regs[i];
		html += '<option value="' + r.id + '">' + r.name + '</option>';;
	}
	
	let dStyle = gameIsNight() ? 'night-' : 'day-';
	let eStyle = dStyle + 'empty';

	$('#r-1').removeClass().addClass(eStyle);
	$('#head').removeClass().addClass(eStyle);
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		$('#r' + i).removeClass().addClass(dStyle + (isSet(game.players[i].death) ? 'dead' : 'alive'));
		$('#num' + i).removeClass();
		$('#panel' + i).html('').removeClass();
		$('#control' + i).html('').removeClass();
		$('#controlx' + i).html('').removeClass();
		$('#player' + i).html(html).val(player.id ? player.id : 0);
		
		if (gameIsPlayerAtTheTable(i))
		{
			$('#btns-' + i).html(
					'<button class="icon" onclick="gamePlayerWarning(' + i + ')"><img src="images/warn.png" title="' + l('Warn') + '"></button>' +
					'<button class="icon" onclick="uiPlayerActions(' + i + ')"><img src="images/more.png" title="' + l('GiveUp') + '"></button>');
		}
		else
		{
			$('#btns-' + i).html('');
		}
	}
	
	let status = '';
	let control1Html = '';
	let nomsStr = '';
	let noms = null;
	if (!isSet(game.time))
	{
		status = l('StartGame');
		$('#info').html('');
		control1Html = '<button class="day-vote" onclick="gameRandomizeSeats()">' + l('RandSeats') + '</button>';
	}
	else
	{
		let info = 'Day';
		switch (game.time.time)
		{
		case 'start':
			status = l('AssignRoles');
			control1Html = '<button class="day-vote" onclick="gameGenerateRoles()">' + l('GenRoles') + '</button>';
			for (let i = 0; i < 10; ++i)
			{
				let p = game.players[i];
				let r = isSet(p.role) ? p.role : 'civ';
				$('#controlx' + i).html(
					'<button class="night-char" id="role-' + i + '-civ" onclick="uiSetRole(' + i + ', \'civ\')"><img class="role-icon" src="images/civ.png"></button>' +
					'<button class="night-char" id="role-' + i + '-sheriff" onclick="uiSetRole(' + i + ', \'sheriff\')" title="' + l('sheriff') + '"><img class="role-icon" src="images/sheriff.png"></button>' +
					'<button class="night-char" id="role-' + i + '-maf" onclick="uiSetRole(' + i + ', \'maf\')" title="' + l('mafia') + '"><img class="role-icon" src="images/maf.png"></button>' +
					'<button class="night-char" id="role-' + i + '-don" onclick="uiSetRole(' + i + ', \'don\')" title="' + l('don') + '"><img class="role-icon" src="images/don.png"></button>');
				$('#panel' + i).html(
					'<select id="role-' + i + '" style="width:100px;" onchange="uiSetRole(' + i + ')">' +
					_uiOption('civ', r, '') +
					_uiOption('sheriff', r, l('sheriff')) +
					_uiOption('maf', r, l('mafia')) +
					_uiOption('don', r, l('don')) +
					'</select>');
				$('#role-' + i + '-' + r).attr('checked', '');
			}
			break;
		case 'arrangement':
			status = l('Arrange');
			for (let i = 0; i < 10; ++i)
			{
				let player = game.players[i];
				let role = isSet(player.role) ? player.role : 'civ';
				$('#panel' + i).html(
					'<button class="night-char" id="arr-' + i + '-x" onclick="uiArrangePlayer(' + i + ', 0)">x</button>' +
					'<button class="night-char" id="arr-' + i + '-1" onclick="uiArrangePlayer(' + i + ', 1)">1</button>' +
					'<button class="night-char" id="arr-' + i + '-2" onclick="uiArrangePlayer(' + i + ', 2)">2</button>' +
					'<button class="night-char" id="arr-' + i + '-3" onclick="uiArrangePlayer(' + i + ', 3)">3</button>');
				$('#control' + i).html(
					'<select id="arr-' + i + '" onchange="uiArrangePlayer(' + i + ')" style="width:120px;">' +
					_uiOption(0, 0, '') +
					_uiOption(1, 0, l('ArrNight', 1)) +
					_uiOption(2, 0, l('ArrNight', 2)) +
					_uiOption(3, 0, l('ArrNight', 3)) +
					'</select>');
				$('#controlx' + i).html(
					'<button class="night-char" id="role-' + i + '-civ" onclick="uiSetRole(' + i + ', \'civ\')"><img class="role-icon" src="images/civ.png"></button>' +
					'<button class="night-char" id="role-' + i + '-sheriff" onclick="uiSetRole(' + i + ', \'sheriff\')" title="' + l('sheriff') + '"><img class="role-icon" src="images/sheriff.png"></button>' +
					'<button class="night-char" id="role-' + i + '-maf" onclick="uiSetRole(' + i + ', \'maf\')" title="' + l('mafia') + '"><img class="role-icon" src="images/maf.png"></button>' +
					'<button class="night-char" id="role-' + i + '-don" onclick="uiSetRole(' + i + ', \'don\')" title="' + l('don') + '"><img class="role-icon" src="images/don.png"></button>');
				if (isSet(player.arranged) && player.arranged > 0)
				{
					$('#arr-' + i + '-' + player.arranged).attr('checked', '');
				}
				$('#arr-' + i).val(player.arranged);
				
				if (player.role == 'maf')
				{
					$('#num' + i).addClass('night-mark');
				}
				else if (player.role == 'don')
				{
					$('#r' + i).removeClass().addClass('night-mark');
				}
				$('#role-' + i + '-' + role).attr('checked', '');
			}
			info = 'Night0';
			break;
		case 'relaxed sitting':
			status = l('RelaxedSitting');
			for (let i = 0; i < 10; ++i)
			{
				let player = game.players[i];
				let role = isSet(player.role) ? player.role : 'civ';
				$('#controlx' + i).html(
					'<button class="night-char" id="role-' + i + '-civ" onclick="uiSetRole(' + i + ', \'civ\')"><img class="role-icon" src="images/civ.png"></button>' +
					'<button class="night-char" id="role-' + i + '-sheriff" onclick="uiSetRole(' + i + ', \'sheriff\')" title="' + l('sheriff') + '"><img class="role-icon" src="images/sheriff.png"></button>' +
					'<button class="night-char" id="role-' + i + '-maf" onclick="uiSetRole(' + i + ', \'maf\')" title="' + l('mafia') + '"><img class="role-icon" src="images/maf.png"></button>' +
					'<button class="night-char" id="role-' + i + '-don" onclick="uiSetRole(' + i + ', \'don\')" title="' + l('don') + '"><img class="role-icon" src="images/don.png"></button>');
				$('#role-' + i + '-' + role).attr('checked', '');
			}
			timerTime = 20;
			info = 'Night0';
			break;
		case 'night kill speaking':
			$('#r' + (game.time.speaker - 1)).removeClass().addClass('day-mark');
			let p = game.players[game.time.speaker - 1];
			status = l('GoodMorning') +
				' ' + l('NightKill', _uiPlayerTitle(game.time.speaker - 1), l('KilledMale')) +
				' ' + l('LastSpeech', l('He'), l('his')) +
				l('NextFloor', _uiPlayerTitle(gameWhoSpeaksFirst()))
			if (game.time.round == 1)
			{
				for (var i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					var c = $('#control' + i);
					html = '<button class="day-vote" onclick="gameSetLegacy(' + i + ')"';
					if (gameIsInLegacy(i))
					{
						html += ' checked';
					}
					html += '> ' + l('guess', i + 1) + '</button>';
					c.html(html);
				}
				control1Html = '<button class="day-vote" onclick="gameSetLegacy(-1)">' + l('noGuess') + '</button>';
			}
			_uiShowOnRecordButtons();
			break;
		case 'speaking':
			let player = game.players[game.time.speaker - 1];
			if (!isSet(player.warnings) ||
				player.warnings.length != 3 ||
				(game.time.round != 0 &&
				gameCompareTimes(player.warnings[2], { time: game.time.time, speaker: game.time.speaker, round: game.time.round - 1 }) < 0))
			{
				status = l('Speaking', _uiPlayerTitle(game.time.speaker - 1));
			}
			else if (gamePlayersCount() > 4)
			{
				status = l('MissingSpeech', _uiPlayerTitle(game.time.speaker - 1));
				timerTime = 0;
			}
			else
			{
				status = l('SpeakingShort', _uiPlayerTitle(game.time.speaker - 1));
				timerTime = 30;
			}
			
			let n = gameNextSpeaker();
			if (n >= 0)
			{
				status += ' ' + l('NextFloor', _uiPlayerTitle(n));
			}
			else
			{
				status += ' ' + l('NextVoting');
			}
			
			_uiShowOnRecordButtons();
			
			if (!gameIsVotingCanceled())
			{
				let noNom = true;
				for (let i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					if (!isSet(p.death))
					{
						let c = $('#control' + i);
						let n = gameIsPlayerNominated(i);
						if (n == 1)
						{
							c.addClass('day-grey');
							c.html('<center>' + l('Nominated') + '</center>');
						}
						else
						{
							html = '<button class="day-vote" onclick="gameNominatePlayer(' + i + ')"';
							
							if (n == 2)
							{
								html += ' checked';
								noNom = false;
							}
							html += '>' + l('Nominate', i + 1) + '</button>';
							c.html(html);
						}
					}
				}
				html = '<button class="day-vote" onclick="gameNominatePlayer(-1)"';
				if (noNom)
					html += ' checked';
				html += '>' + l('NoNom') + '</button>';
				control1Html = html;
			}
			$('#r' + (game.time.speaker - 1)).removeClass().addClass('day-mark');
			nomsStr = _uiGenerateNoms();
			break;
		case 'voting start':
			nomsStr = _uiGenerateNoms();
			status = l('VotingStart', nomsStr);
			html = _uiOption(0, 0, '');
			for (let i = 0; i < 10; ++ i)
			{
				let p = game.players[i];
				if (!isSet(p.death))
				{
					html += _uiOption(i + 1, 0, i + 1);
				}
			}
			for (let i = 0; i < 10; ++ i)
			{
				let p = game.players[i];
				if (!isSet(p.death))
				{
					$('#control' + i).html('<center>' + l('HasNom') + ': <select id="nominated' + i + '" onclick="_uiNominate(' + i + ')">' + html + '</select></center>');
					if (isSet(p.nominating) && game.time.round < p.nominating.length && p.nominating[game.time.round] != null)
					{
						$('#nominated' + i).val(p.nominating[game.time.round]);
					}
				}
			}
			break;
		case 'voting':
			noms = gameGetNominees();
			if (isSet(game.time.nominee))
			{
				let index = 0;
				for (let i = 0; i < noms.length; ++i)
				{
					$('#panel' + (noms[i] - 1)).html('<center>' + gameGetVotesCount(noms[i]) + '</center>').addClass('day-mark');
					if (noms[i] == game.time.nominee)
					{
						index = i;
					}
				}
				$('#r' + (game.time.nominee - 1)).removeClass().addClass('day-mark');
				
				let chState = '';
				if (index < noms.length - 1)
				{
					status = l('Voting',
						_uiPlayerTitle(noms[index] - 1),
						_uiPlayerTitle(noms[index + 1] - 1));
				}
				else
				{
					status = l('VotingLast', _uiPlayerTitle(noms[index] - 1));
					chState = ' checked';
				}
				
				for (let i = 0; i < 10; ++i)
				{
					let player = game.players[i];
					if (!isSet(player.death))
					{
						let vote = 0;
						if (isSet(player.voting) && game.time.round < player.voting.length)
						{
							let v = player.voting[game.time.round];
							if (isArray(v) && game.time.votingRound < v.length)
							{
								vote = v[game.time.votingRound];
							}
							else if (isNumber(v))
							{
								vote = v;
							}
						}
						
						if (vote == game.time.nominee)
						{
							$('#control' + i).html('<button class="day-vote" onclick="gameVote(' + i + ', 1)" checked>' + l('vote', i + 1, game.time.nominee) + '</button>');
						}
						else
						{
							let j = 0;
							for (; j < index; ++j)
							{
								if (noms[j] == vote)
								{
									break;
								}
							}
							if (j == index)
							{
								$('#control' + i).html('<button class="day-vote" onclick="gameVote(' + i + ', 1)" ' + chState + '>' + l('vote', i + 1, game.time.nominee) + '</button>');
							}
						}
					}
				}
				
				control1Html = 
					'<button class="day-half-vote" onclick="gameVoteAll(1)">' + l('voteAll', game.time.nominee) + '</button>' +
					'<button class="day-half-vote" onclick="gameVoteAll(0)">' + l('voteNone', game.time.nominee) + '</button>';
			}
			else // isSet(game.time.speaker) should always be true
			{
				let index = 0;
				for (let i = 0; i < noms.length; ++i)
				{
					$('#panel' + (noms[i] - 1)).html('<center>' + gameGetVotesCount(noms[i]) + '</center>').addClass('day-mark');
					if (noms[i] == game.time.speaker)
					{
						index = i;
					}
				}
				$('#r' + (game.time.speaker - 1)).removeClass().addClass('day-mark');
				
				
				if (index == 0)
				{
					status = l('RepeatVoting', noms.length) + '<br>'
				}
				status += l('Speaking', _uiPlayerTitle(noms[index] - 1)) + ' ';
				if (index < noms.length - 1)
				{
					status += l('NextFloor', _uiPlayerTitle(noms[index + 1] - 1));
				}
				else
				{
					status += l('NextVoting');
				}
				
				_uiShowOnRecordButtons();
				timerTime = 30;
			}
			nomsStr = _uiGenerateNoms();
			break;
		case 'voting kill all':
			status = l('KillAll');

			noms = gameGetNominees();
			for (let i = 0; i < noms.length; ++i)
			{
				$('#panel' + (noms[i] - 1)).html('<center>' + gameGetVotesCount(noms[i]) + '</center>').addClass('day-mark');
			}
			for (let i = 0; i < 10; ++i)
			{
				let player = game.players[i];
				if (!isSet(player.death))
				{
					let checked = player.voting[game.time.round][game.time.votingRound];
					$('#control' + i).html('<button class="day-vote" onclick="gameVoteToKillAll(' + i + ')"' + (checked ? ' checked' : '') + '>' + (checked ? l('yes') : l('no')) + '</button>');
				}
			}
			control1Html = 
				'<button class="day-half-vote" onclick="gameAllVoteToKillAll(true)">' + l('voteAll', game.time.nominee) + '</button>' +
				'<button class="day-half-vote" onclick="gameAllVoteToKillAll(false)">' + l('voteNone', game.time.nominee) + '</button>';
			break;
		case 'day kill speaking':
			noms = gameGetVotingWinners();
			for (let nom of noms)
			{
				$('#panel' + (nom - 1)).addClass('day-mark');
			}
			$('#r' + (game.time.speaker - 1)).removeClass().addClass('day-mark');
			
			
			status = l('DayKill', _uiPlayerTitle(game.time.speaker - 1), l('KilledMale')) + ' ' + l('LastSpeech', l('He'), l('his'));
			_uiShowOnRecordButtons();
			break;
		case 'night start':
			status = l('NightStart');
			info = 'Night';
			break;
		case 'shooting':
			let shots = gameGetShots();
			status = l('Shooting');
			html = ')"><option value="-1"></option>';
			for (let i = 0; i < 10; ++i)
			{
				let p = game.players[i];
				if (!isSet(p.death))
				{
					html += '<option value="' + i + '">' + _uiPlayerTitle(i) + '</option>';
					var str = '<button class="night-char" onclick="gameShoot(' + i + ')">x</button>';
					for (let j = 0; j < shots.length; ++j)
					{
						let s = shots[j];
						str += '<button class="night-char"';
						if (s[1] == i)
						{
							str += ' checked';
						}
						str += ' onclick="gameShoot(' + i + ', ' + s[0] + ')">' + (s[0] + 1) + '</button>';
					}
					$('#panel' + i).html(str);
					if (isSet(p.role))
					{
						if (p.role == 'maf')
						{
							$('#num' + i).addClass('night-mark');
						}
						else if (p.role == 'don')
						{
							$('#r' + i).removeClass().addClass('night-mark');
						}
					}
				}
			}
			html += '</select>';
			for (let i = 0; i < shots.length; ++i)
			{
				$('#control' + shots[i][0]).html('<select id="shot' + shots[i][0] + '" onchange="_uiShoot(' + shots[i][0] + html);
				$('#shot' + shots[i][0]).val(shots[i][1]);
			}
			info = 'Night';
			break;
		case 'don':
			if (gameIsDonAlive())
			{
				status = l('DonCheck');
				let n = true;
				for (let i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					if (isSet(p.don) && p.don == game.time.round)
					{
						let a = isSet(p.role) && p.role == 'sheriff' ? 'yes' : 'no';
						$('#control' + i).html('<button class="night-vote" onclick="gameDonCheck(' + i + ')" checked> ' + l(a) + '</button>');
						n = false;
					}
					else
					{
						$('#control' + i).html('<button class="night-vote" onclick="gameDonCheck(' + i + ')"> ' + l('Check', i + 1) + '</button>');
					}
					if (isSet(p.role) && p.role == 'don')
					{
						$('#r' + i).removeClass().addClass('night-mark');
					}
				}
				control1Html = '<button class="day-vote" onclick="gameDonCheck(-1)"' + (n ? ' checked' : '') + '> ' + l('NoCheck') + '</button>';
			}
			else
			{
				status = l('NoDon');
			}
			info = 'Night';
			break;
		case 'sheriff':
			if (gameIsSheriffAlive())
			{
				status = l('SheriffCheck');
				let n = true;
				for (let i = 0; i < 10; ++i)
				{
					let p = game.players[i];
					if (isSet(p.sheriff) && p.sheriff == game.time.round)
					{
						let a = isSet(p.role) && (p.role == 'maf' || p.role == 'don') ? 'yes' : 'no';
						$('#control' + i).html('<button class="night-vote" onclick="gameSheriffCheck(' + i + ')" checked> ' + l(a) + '</button>');
						n = false;
					}
					else
					{
						$('#control' + i).html('<button class="night-vote" onclick="gameSheriffCheck(' + i + ')"> ' + l('Check', i + 1) + '</button>');
					}
					if (isSet(p.role) && p.role == 'sheriff')
					{
						$('#r' + i).removeClass().addClass('night-mark');
					}
				}
				control1Html = '<button class="day-vote" onclick="gameSheriffCheck(-1)"' + (n ? ' checked' : '') + '> ' + l('NoCheck') + '</button>';
			}
			else
			{
				status = l('NoSheriff');
			}
			info = 'Night';
			break;
		case 'end':
			status = '<h3>' + (game.winner == 'maf' ? l('MafWin') : l('CivWin')) + '</h3>' + l('Finish');
			info = 'Day';
			for (let i = 0; i < 10; ++i)
			{
				let player = game.players[i];
				$('#r' + i).removeClass().addClass(dStyle + 'alive');
				html = '<center>';
				switch (player.role)
				{
				case 'sheriff':
					html += '<img src="images/sheriff.png" class="role-icon" title="' + l('sheriff') + '">';
					break
				case 'maf':
					html += '<img src="images/maf.png" class="role-icon" title="' + l('mafia') + '">';
					break
				case 'don':
					html += '<img src="images/don.png" class="role-icon" title="' + l('don') + '">';
					break
				}
				$('#panel' + i).html(html + '</center>').removeClass();
				$('#control' + i).html('<button class="extra-pts" onclick="uiBonusPoints(' + i + ')"> ' + l('ExtraPoints', i + 1) + '</button>').removeClass();
						
				html = '<table width="100%" class="transp"><tr';
				if (player.comment)
				{
					html += ' title="' + player.comment + '"';
				}
				html += '>';
				if (isSet(player.legacy))
				{
					let leg = '';
					let dlm = '';
					for (let j = 0; j < player.legacy.length && j < 3; ++j)
					{
						leg += dlm + player.legacy[j];
						dlm = ', ';
					}
					html += '<td width="60">' + l('legacy', leg) + '</td>';
				}
				html += '<td align="right">';
				
				if (player.bonus)
				{
					let points = 0;
					let title = null;
					if (isArray(player.bonus))
					{
						for (let j = 0; j < player.bonus.length; ++j)
						{
							if (isNumber(player.bonus[j]))
							{
								points = player.bonus[j];
							}
							else
							{
								title = player.bonus[j];
							}
						}
					}
					else if (isNumber(player.bonus))
					{
						points = player.bonus;
					}
					else
					{
						title = player.bonus;
					}
					
					switch (title)
					{
					case 'bestPlayer':
						html += '<img src="images/best_player.png" width="24"></td>';
						break;
					case 'bestMove':
						html += '<img src="images/best_move.png" width="24"></td>';
						break;
					case 'worstMove':
						html += '<img src="images/worst_move.png" width="24"></td>';
						break;
					}
					html += '<td width="48" align="right">';
					if (points)
					{
						html += '<big><b>' + (points > 0 ? '+' : '') + points + '</b></big></td>';
					}
				}
				html += '</td></tr></table>';
				$('#controlx' + i).html(html);
			}
			break;
		}
		$('#info').html(l(info, game.time.round));
	}
	
	$('#status').html(status);
	$('#control-1').html(control1Html);
	$('#game-next').prop('disabled', !gameCanGoNext());
	$('#game-back').prop('disabled', !gameCanGoBack());
	$('#noms').html(nomsStr);
	
	for (let i = 0; i < 10; ++i)
	{
		let player = game.players[i];
		html = '';
		if (isSet(player.warnings) && player.warnings.length > 0)
		{
			html = '<font color="#808000" size="4"><b><table width="100%" class="transp"><tr><td>';
			let j = 0;
			for (; j < player.warnings.length; ++j)
			{
				if (gameCompareTimes(player.warnings[j], game.time, true) >= 0)
				{
					break;
				}
				html += '✔';
			}
			html += '</td><td align="right">'
			for (; j < player.warnings.length; ++j)
			{
				html += '✔';
			}
			html += '</td></tr></table></b></font>';
		}
		$('#warn' + i).html(html);
	}

	if (resetTimer) // game time changed - timer has to be reset
	{
		timer.reset(timerTime);
	}
	
	if (_renderCallback != null)
	{
		_renderCallback();
	}
}

function _uiErrorListener(type, message, data)
{
	if (data)
	{
		console.log(data);
	}
	
	switch (type)
	{
	case 0: // error getting data
		// dlg.error(text, title, width, onClose)
		dlg.error(message);
		break;
	case 1: // error setting data
		// nothing to do - connection listener takes care
		break;
	case 2: // version mismatch
		var _errorDialog = false;
		if (!_errorDialog)
		{
			_errorDialog = true;
			dlg.error(message, undefined, undefined, function()
			{
				_errorDialog = false;
				window.location.reload(true);
			});
		}
		break;
	}
}

function _uiConnectionListener(state)
{
	let url = "images/connected.png";
	if (state == 1)
		url = "images/save.png";
	else if (state == 2)
		url = "images/disconnected.png";
	else if (state == 3)
		url = "images/warn.png";
	$('#saving-img').attr("src", url);
}

function _uiPlayerAction(num, action)
{
	let dlgId = dlg.curId();
	action(num);
	dlg.close(dlgId);
}

function _uiChangeNomination(num)
{
	let dlgId = dlg.curId();
	gameChangeNomination(num, parseInt($('#dlg-nominate').val()));
	dlg.close(dlgId);
}

function _uiNextRole(index, back)
{
	let p = game.players[index];
	if (!isSet(p.role) || p.role == 'civ')
	{
		uiSetRole(index, back ? 'don' : 'sheriff');
	}
	else if (p.role == 'sheriff')
	{
		uiSetRole(index, back ? 'civ' : 'maf');
	}
	else if (p.role == 'maf')
	{
		uiSetRole(index, back ? 'sheriff' : 'don');
	}
	else if (p.role == 'don')
	{
		uiSetRole(index, back ? 'maf' : 'civ');
	}
}

function _uiOnKey(e)
{
	let dlgId = dlg.curId();
	if (dlgId >= 0)
	{
		return;
	}
	
	var code = e.keyCode;
	if (!timer._hidden)
	{
		if (code == /*space*/32)
		{
			timer.toggle();
			return;
		}
		else if (code == /*up*/38)
		{
			timer.inc(5);
			return;
		}
		else if (code == /*down*/40)
		{
			timer.inc(-5);
			return;
		}
	}
	
	var index = -1;
	if (code >= /*1*/49 && code <= /*9*/57)
		index = code - /*1*/49;
	else if (code >= /*1*/97 && code <= /*9*/105)
		index = code - /*1*/97;
	else if (code == /*0*/48 || code == /*0*/96)
		index = 9;

	if (code == /*enter*/13 || code == /*right*/39)
	{
		uiNext();
	}
	else if (code == /*back*/8 || code == /*left*/37)
	{
		uiBack();
	}
	else if (code == /*b*/66)
	{
		uiBugReport();
	}
	else if (isSet(game.time))
	{
		if (index >= 0)
		{
			if (e.altKey)
			{
				if (e.shiftKey || e.ctrlKey)
					uiPlayerActions(index);
				else 
					gamePlayerWarning(index);
			}
			else switch (game.time.time)
			{
			case 'start':
				_uiNextRole(index, e.shiftKey);
				break;
			case 'arrangement':
				uiArrangePlayer(index, e.shiftKey ? 0 : 1);
				break;
			case 'speaking':
				gameNominatePlayer(gameIsPlayerNominated(index) == 2 ? -1 : index);
				break;
			case 'voting':
				gameVote(index, 0);
				break;
			case 'voting kill all':
				gameVoteToKillAll(index);
				break;
			case 'shooting':
				gameShoot(index);
				break;
			case 'don':
				gameDonCheck(index);
				break;
			case 'sheriff':
				gameSheriffCheck(index);
				break;
			case 'night kill speaking':
				if (game.time.round == 1)
				{
					gameSetLegacy(index);
				}
				break;
			}
		}
		else switch (game.time.time)
		{
		case 'start':
			if (code == /*g*/71)
			{
				gameGenerateRoles();
			}
			break;
		case 'speaking':
			if (code == /*-*/189)
			{
				gameNominatePlayer(-1);
			}
			break;
		case 'voting':
			if (code == /*+*/187)
			{
				gameVoteAll(1);
			}
			else if (code == /*-*/189)
			{
				gameVoteAll(0);
			}
			break;
		case 'voting kill all':
			if (code == /*+*/187)
			{
				gameAllVoteToKillAll(true);
			}
			else if (code == /*-*/189)
			{
				gameAllVoteToKillAll(false);
			}
			break;
		case 'shooting':
			if (code == /*-*/189)
			{
				let shots = gameGetShots();
				for (let i = 0; i < shots.length; ++i)
				{
					gameShoot(-1, shots[i][0]);
				}
			}
			break;
		case 'don':
			if (code == /*-*/189)
			{
				gameDonCheck(-1);
			}
			break;
		case 'sheriff':
			if (code == /*-*/189)
			{
				gameSheriffCheck(-1);
			}
			break;
		case 'night kill speaking':
			if (code == /*-*/189 && game.time.round == 1)
			{
				gameSetLegacy(-1);
			}
			break;
		}
	}
	else if (index >= 0)
	{
		uiRegisterPlayer(index);
	}
	else if (code == /*s*/83)
	{
		gameRandomizeSeats();
	}
}

//-----------------------------------------------------------
// Timer
//-----------------------------------------------------------
var timer = new function()
{
	var _start = 0;
	var _max = 0;
	var _cur = 0;
	var _prompt = 0;
	var _blinkCount = 0;
	var _html = '<table id="t-area" class="timer timer-0" width="100%"><tr><td width="1"><button id="timerBtn" class="timer" onclick="timer.toggle()"><img id="timerImg" src="images/resume_big.png" class="timer"></button></td><td><div id="timer" class="timer"></div></td><td width="1"><button class="timer" onclick="timer.inc(-10)"><img src="images/dec_big.png" class="timer"></button></td><td width="1"><button class="timer" onclick="timer.inc(10)"><img src="images/inc_big.png" class="timer"></button></td></tr></table>';
	var _hidden = true;
	
	function _blink()
	{
		if (_blinkCount <= 0) return;
		try
		{
			let a = $('#t-area');
			let c = a.attr('class');
			let i = c.indexOf('timer-') + 6;
			let n = (i >= 0 ? parseInt(c.substr(i, 1)) + 1 : 1);
			if (isNaN(n) || n > 1)
			{
				n = 0;
				--_blinkCount;
			}
			a.attr('class', 'timer timer-' + n);
			setTimeout(_blink, 60);
		}
		catch (err)
		{
		}
	}
	
	function _set(val)
	{
		if (val < 0) val = 0;
		let m = Math.floor(val / 60);
		let s = val % 60;
		if (s < 10)
		{
			s = '0' + s;
		}
		try
		{
			$('#timer').html(m + ':' + s);
		}
		catch (err)
		{
		}
	}
	
	function _get()
	{
		let v = 0;
		try
		{
			let a = $('#timer').html().split(':');
			if (a.length > 0)
			{
				let m = 0;
				if (a.length == 1)
				{
					v = parseInt(a[0]);
				}
				else
				{
					m = parseInt(a[0]);
					if (isNaN(m)) m = 0;
					v = parseInt(a[1]);
				}
				if (isNaN(v)) v = 0;
				v += m * 60;
			}
		}
		catch (err)
		{
		}
		return v;
	}
	
	this.hide = function(clockHtml)
	{
		if (!clockHtml)
			clockHtml = '';
		$('#clock').html(clockHtml);
		_hidden = true;
		_start = 0;
	}
	
	this.reset = function(total)
	{
		if (_start > 0)
		{
			$('#timerImg').attr('src', "images/resume_big.png");
			_start = 0;
		}
		_cur = _max = total;
		_prompt = total / 6;
		_set(_cur);
	}
	
	this.show = function()
	{
		if (_hidden)
		{
			$('#clock').html(_html);
			_hidden = false;
		}
		
		$('#t-area').attr('class', 'timer timer-0');
		
		_blinkCount = 0;
		_max = _cur;
		if (_start > 0)
		{
			$('#timerImg').attr('src', "images/pause_big.png");
			_start = (new Date()).getTime();
		}
		_set(_cur);
		
		// todo: imlement user settings
		// if (mafia.data().user.settings.flags & /*S_FLAG_START_TIMER*/0x2)
			// timer.start();
	}
	
	this.stop = function()
	{
		if (_start > 0)
		{
			try
			{
				$('#timerImg').attr('src', "images/resume_big.png");
			}
			catch (err)
			{
			}
			_start = 0;
		}
	}
	
	this.start = function()
	{
		if (_start <= 0)
		{
			_cur = _max = _get();
			if (_max > 0)
			{
				try
				{
					$('#timerImg').attr('src', "images/pause_big.png");
				}
				catch (err)
				{
				}
				_start = (new Date()).getTime();
			}
		}
	}
	
	this.toggle = function()
	{
		if (_start > 0)
		{
			timer.stop();
		}
		else
		{
			timer.start();
		}
	}

	this.inc = function(s)
	{
		let t;
		if (_start > 0)
		{
			if (isNaN(_max)) _max = 0;
			_max += s;
			if (_max < 0) _max = 0;
			_cur = t = _max - Math.round(((new Date()).getTime() - _start) / 1000);
		}
		else
		{
			t = _get() + s;
		}
		_set(t);
	}

	this.tick = function()
	{
		if (_start > 0)
		{
			let t = _max - Math.round(((new Date()).getTime() - _start) / 1000);
			// todo: implement user settings
			// let f = mafia.data().user.settings.flags;
			let f = 0;
			if (t <= 0)
			{
				document.getElementById('prompt-snd').pause();
				document.getElementById('end-snd').play();
				if ((f & /*S_FLAG_NO_BLINKING*/0x8) == 0)
				{
					_blinkCount = 15;
					_blink();
				}
				_cur = 0;
				_set(_cur);
				timer.stop();
			}
			else
			{
				if (_get() > _prompt && t <= _prompt)
				{
					document.getElementById('prompt-snd').play();
					if ((f & /*S_FLAG_NO_BLINKING*/0x8) == 0)
					{
						_blinkCount = 2;
						_blink();
					}
				}
				_cur = t;
				_set(t);
			}
		}
	}
} // timer

setInterval(timer.tick, 1000);

//-----------------------------------------------------------
// Public API
//-----------------------------------------------------------
function uiStart(eventId, tableNum, roundNum)
{
	$('#ops').click(function()
	{
		let menu = $('#ops-menu').menu();
		menu.show(0, function()
		{
			menu.position(
			{
				my: "left top",
				at: "left bottom",
				of: $('#ops')
			});
			$(document).one("click", function() { menu.hide(); });
		});
		return false;
	});
	
	timer.show();
	
	// Todo: when the old client will be removed, make demo unhidden in css and remove this code
	if (eventId <= 0)
	{
		$('#demo').show();
	}
	
	document.addEventListener("keyup", _uiOnKey); 
	
	gameInit(eventId, tableNum, roundNum, _uiRender, _uiErrorListener, _uiConnectionListener);
}
	
// Call this to change a player at num. Don't use gameSetPlayer.	
// User with userId must be registered for the event. Use uiRegisterPlayer instead if uncertain.
function uiSetPlayer(num, userId)
{
	if (userId)
	{
		$('#player' + num).val(userId);
	}
	else
	{
		userId = $('#player' + num).val();
	}
	
	let n = gameSetPlayer(num, userId);
	if (n >= 0)
	{
		$('#player' + n).val(0);
	}
}

function uiRegisterPlayer(num, data)
{
	if (data)
	{
		regs = data.regs;
		uiSetPlayer(num, data.user_id);
	}
	else
	{
		dlg.infoForm("form/event_register_player.php?num=" + num + "&event_id=" + game.eventId, 800);
	}
}

function uiCreatePlayer(num)
{
	dlg.form("form/event_create_player.php?event_id=" + game.eventId, function(data) { uiRegisterPlayer(num, data); }, 500);
}

function uiConfig(txt, onClose)
{
	function _genHtml()
	{
		let html = '<table class="dialog_form" width="100%">';
		let moderatorId = isSet(game.moderator) && isSet(game.moderator.id) ? game.moderator.id : 0;
		
		if (txt)
		{
			html += '<tr><td colspan="2" align="center"><p><b>' + txt + '</b></p></td></tr>';
		}
		
		html += '<tr><td>' + l('Moder') + ':</td><td><table class="transp" width="100%"><tr><td width="30"><button class="icon" onclick="uiRegisterPlayer(10)"><img src="images/user.png" class="icon"></button></td><td><select id="referee">'
		html += _uiOption(0, moderatorId, '');
		for (let i in regs)
		{
			let r = regs[i];
			html += _uiOption(r.id, moderatorId, r.name);
		}
		html += '</select></td></tr></table></td></tr>';
		if (langs.length > 1)
		{
			html += '<tr><td>' + l('Lang') + ':</td><td><select id="dlg-lang">';
			for (let i in langs)
			{
				let lang = langs[i];
				html += _uiOption(lang.code, game.language, lang.name);
			}
			html += '</select></td></tr>';
		}
		
		html += '<tr><td colspan="2"><input type="checkbox" id="dlg-rating"';
		if (!isSet(game.rating) || game.rating)
		{
			html += ' checked';
		}
		html += '> ' + l('Rating') + '</td></tr>';

		html += '</table>';
		return html;
	}
	
	function _refresh()
	{
		$('#content').html(_genHtml());
	}
	
	let html = '<div id="content">' + _genHtml() + '</div>';
	_renderCallback = _refresh;
	dlg.okCancel(html, $('#game-id').text(), 500, function()
	{
		_renderCallback = null;
		gameSetIsRating($('#dlg-rating').attr('checked') ? 1 : 0);
		if (langs.length > 1)
		{
			gameSetLang($('#dlg-lang').val());
		}
		gameSetPlayer(10, $('#referee').val());
		if (!isSet(game.moderator) || !isSet(game.moderator.id) || game.moderator.id == 0)
		{
			dlg.error(l('EnterModer'), undefined, undefined, function() { uiConfig(txt, onClose); });
		}			
		else if (onClose)
		{
			onClose();
		}
	});
}

function uiSetRole(num, role)
{
	if (!role)
	{
		role = $('#role-' + num).val();
	}
	gameSetRole(num, role);
}

function uiPlayerActions(num)
{
	let player = game.players[num];
	if (!isSet(player.death))
	{
		let html = '<center>';
		if (isSet(game.time) && ((game.time.time == 'speaking' && gameCompareTimes({ time: 'speaking', speaker: num + 1, round: game.time.round }, game.time) <= 0) || game.time.time == 'voting start'))
		{
			let nom = -1;
			if (isSet(player.nominating) && game.time.round < player.nominating.length && player.nominating[game.time.round] != null)
			{
				nom = player.nominating[game.time.round] - 1;
			}
			html += '<p><center>' + l("DidNom") + ': <select id="dlg-nominate" onchange="_uiChangeNomination(' + num + ')">' + _uiOption(-1, nom, '');
			for (let i = 0; i < 10; ++i)
			{
				if (!isSet(game.players[i].death))
				{
					html += _uiOption(i, nom, i + 1);
				}
			}
			html += '</select></p><br>';
		}
		html += 
			'<p><button class="leave" onclick="_uiPlayerAction(' + num + ', gamePlayerGiveUp)"><table class="transp" width="100%"><tr><td width="30"><img src="images/suicide.png"></td><td>' + l('GiveUp') + '</td></tr></table></button></p>' +
			'<p><button class="leave" onclick="_uiPlayerAction(' + num + ', gamePlayerKickOut)"><table class="transp" width="100%"><tr><td width="30"><img src="images/delete.png"></td><td>' + l('KickOut') + '</td></tr></table></button></p>' +
			'<p><button class="leave" onclick="_uiPlayerAction(' + num + ', gamePlayerTeamKickOut)"><table class="transp" width="100%"><tr><td width="30"><img src="images/skull.png"></td><td>' + l('TeamKickOut') + '</td></tr></table></button></p>';
		if (isSet(player.warnings) && player.warnings.length > 0)
		{
			html += '<p><button class="leave" onclick="_uiPlayerAction(' + num + ', gamePlayerRemoveWarning)"><table class="transp" width="100%"><tr><td width="30"><img src="images/warn-minus.png"></td><td>' + l('RemoveWarning') + '</td></tr></table></button></p>';
		}
		html += '</center>';
		dlg.custom(html, l('PlayerActions', num + 1), 360, {});
	}
}

function uiCancelGame()
{
	dlg.yesNo(l('CancelGame'), null, null, gameCancel);
}

function uiArrangePlayer(num, night)
{
	if (!isSet(night))
	{
		night = parseInt($('#arr-' + num).val());
	}
	gameArrangePlayer(num, night);
}

function _uiBestClicked(type)
{
	switch (type)
	{
		case 0:
			$('#dlg-bp').attr("checked", !$('#dlg-bp').attr("checked"));
			$('#dlg-bm').attr("checked", false);
			$('#dlg-wm').attr("checked", false);
			break;
		case 1:
			$('#dlg-bp').attr("checked", false);
			$('#dlg-bm').attr("checked", !$('#dlg-bm').attr("checked"));
			$('#dlg-wm').attr("checked", false);
			break;
		case 2:
			$('#dlg-bp').attr("checked", false);
			$('#dlg-bm').attr("checked", false);
			$('#dlg-wm').attr("checked", !$('#dlg-wm').attr("checked"));
			break;
	}
}

function uiBonusPoints(num, bonusObj)
{
	let p = game.players[num];
	
	if (!bonusObj)
	{
		bonusObj = { points: 0, title: null, comment: '' };
		if (isSet(p.comment))
		{
			bonusObj.comment = p.comment;
		}
		if (isSet(p.bonus))
		{
			if (isArray(p.bonus))
			{
				for (let i = 0; i < p.bonus.length; ++i)
				{
					if (isNumber(p.bonus[i]))
					{
						bonusObj.points = parseFloat(p.bonus[i]);
					}
					else
					{
						bonusObj.title = p.bonus[i];
					}
				}
			}
			else if (isNumber(p.bonus))
			{
				bonusObj.points = parseFloat(p.bonus);
			}
			else
			{
				bonusObj.title = p.bonus;
			}
		}
	}
	
	let html = '<table class="dialog_form" width="100%">';
	
	html += '<tr><td>' + l('ExtraPoints') + ':</td><td><table width="100%" class="transp"><tr><td><input type="number" style="width: 45px;" step="0.1" id="dlg-points"';
	if (bonusObj.points)
	{
		html += ' value="' + bonusObj.points + '"';
	}
	html += '></td><td align="right"><button id="dlg-bp" class="best" onclick="_uiBestClicked(0)"';
	if (bonusObj.title == 'bestPlayer') html += ' checked';
	html += '><img src="images/best_player.png" width="24" title="' + l('BestPlayer') + '"></button><button id="dlg-bm" class="best" onclick="_uiBestClicked(1)"';
	if (bonusObj.title == 'bestMove') html += ' checked';
	html += '><img src="images/best_move.png" width="24" title="' + l('BestMove') + '"></button><button id="dlg-wm" class="best" onclick="_uiBestClicked(2)"';
	if (bonusObj.title == 'worstMove') html += ' checked';
	html += '><img src="images/worst_move.png" width="24" title="' + l('WorstMove') + '"></button></td></tr></table></td></tr>';
	html += '<tr><td valign="top">' + l('Comment') + ':</td><td><textarea id="dlg-comment" placeholder="' + l('CommentPh') + '" cols="50" rows="8">';
	if (bonusObj.comment)
	{
		html += bonusObj.comment;
	}
	html += '</textarea></td></tr>';
	html += '</table>';
	
	dlg.okCancel(html, l('ExtraPointsFor', p.name), 500, function()
	{
		bonusObj.points = parseFloat($("#dlg-points").val());
		bonusObj.comment = $('#dlg-comment').val();
		bonusObj.title = null;
		if ($("#dlg-bp").attr("checked"))
			bonusObj.title = "bestPlayer";
		else if ($("#dlg-bm").attr("checked"))
			bonusObj.title = "bestMove";
		else if ($("#dlg-wm").attr("checked"))
			bonusObj.title = "worstMove";
		if (!gameSetBonus(num, bonusObj.points, bonusObj.title, bonusObj.comment))
		{
			uiBonusPoints(num, bonusObj);
		}
	});
}

function uiBugReport()
{
	let html = '<table class="dialog_form" width="100%"><tr><td align="center"><textarea id="dlg-comment" placeholder="' + l('BugReportPh') + '" cols="60" rows="8"></textarea></td></tr></table>';
	dlg.okCancel(html, l('BugReport'), 500, function()
	{
		let txt = $('#dlg-comment').val().trim();
		if (txt.length == 0)
		{
			uiBugReport();
		}
		else
		{
			gameBugReport(txt, function()
			{
				dlg.info(l('BugReported'));
			});
		}
	});
}
	
function uiNext()
{
	if (isSet(game.time)) 
	{
		if (game.time.time == 'end')
		{
			uiConfig(l('Confirm'), gameNext);
		}
		else
		{
			gameNext();
		}
	}
	else
	{
		uiConfig(undefined, gameNext);
	}
}

var uiBack = gameBack;
