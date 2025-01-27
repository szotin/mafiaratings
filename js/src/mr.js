var mr = new function()
{
	//--------------------------------------------------------------------------------------
	// profile
	//--------------------------------------------------------------------------------------
	this.createAccount = function(name, email)
	{
		dlg.form("form/account_create.php", function(){}, 600);
	}

	this.initProfile = function()
	{
		dlg.form("form/profile_init.php", refr, 600);
	}

	this.changePassword = function()
	{
		dlg.form("form/password_change.php", refr, 400);
	}

	this.mobileStyleChange = function()
	{
		json.post("api/ops/account.php", { op: "site_style", style: $('#mobile').val() }, refr);
	}

	this.browserLangChange = function(l)
	{
		json.post("api/ops/account.php", { op: "browser_lang", lang: l }, refr);
	}

	this.resetPassword = function()
	{
		dlg.form("form/password_reset.php", refr, 400);
	}

	//--------------------------------------------------------------------------------------
	// administration
	//--------------------------------------------------------------------------------------
	this.lockSite = function(val)
	{
		if (val)
		{
			json.post("api/ops/repair.php", { op: 'lock' }, refr);
		}
		else
		{
			json.post("api/ops/repair.php", { op: 'unlock' }, refr);
		}
	}
	
	//--------------------------------------------------------------------------------------
	// langs
	//--------------------------------------------------------------------------------------
	this.getLangs = function(prefix)
	{
		if (!isSet(prefix))
		{
			prefix = "";
		}
		
		var elem = $('#' + prefix + 'langs');
		if (elem.length > 0)
		{
			return elem.val();
		}
		
		var langs = 0;
		elem = $('#' + prefix + 'en');
		if (elem.length > 0 && elem.attr('checked'))
		{
			langs |= 1;
		}
		
		elem = $('#' + prefix + 'ru');
		if (elem.length > 0 && elem.attr('checked'))
		{
			langs |= 2;
		}
		
		elem = $('#' + prefix + 'ua');
		if (elem.length > 0 && elem.attr('checked'))
		{
			langs |= 4;
		}
		return langs;
	}

	this.setLangs = function(langs, prefix)
	{
		if (!isSet(prefix))
		{
			prefix = "";
		}
		
		var elem = $('#' + prefix + 'en');
		if (elem.length > 0)
		{
			elem.prop('checked', (langs & 1) != 0);
		}
		
		elem = $('#' + prefix + 'ru');
		if (elem.length > 0)
		{
			elem.prop('checked', (langs & 2) != 0);
		}
		
		elem = $('#' + prefix + 'ua');
		if (elem.length > 0)
		{
			elem.prop('checked', (langs & 4) != 0);
		}
	}

	//--------------------------------------------------------------------------------------
	// note
	//--------------------------------------------------------------------------------------
	this.editNote = function(id)
	{
		dlg.form("form/note_edit.php?note=" + id, refr);
	}

	this.deleteNote = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/note.php", { op: 'delete', note_id: id }, refr);
		}

		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.upNote = function(id)
	{
		json.post("api/ops/note.php", { op: 'up', note_id: id, up: "" }, refr);
	}

	this.createNote = function(clubId)
	{
		dlg.form("form/note_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// advert
	//--------------------------------------------------------------------------------------
	this.editAdvert = function(id)
	{
		dlg.form("form/advert_edit.php?advert=" + id, refr);
	}

	this.deleteAdvert = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/advert.php", { op: 'delete', advert_id: id }, refr);
		}

		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createAdvert = function(clubId)
	{
		dlg.form("form/advert_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// Sounds
	//--------------------------------------------------------------------------------------
	this.editSound = function(id)
	{
		dlg.form("form/sound_edit.php?sound_id=" + id, refr, 500);
	}

	this.deleteSound = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/sound.php", { op: 'delete', sound_id: id }, refr);
		}

		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createSound = function(clubId, userId)
	{
		var url = "form/sound_create.php";
		var delim = '?';
		if (clubId)
		{
			url += delim + "club_id=" + clubId
			delim = '&';
		}
		if (userId)
		{
			url += delim + 'user_id=' + userId;
		}
		dlg.form(url, refr, 500);
	}

	//--------------------------------------------------------------------------------------
	// address
	//--------------------------------------------------------------------------------------
	this.createAddr = function(clubId)
	{
		dlg.form("form/address_create.php?club=" + clubId, refr, 600);
	}

	this.restoreAddr = function(addrId)
	{
		json.post("api/ops/address.php", { op: "restore", address_id: addrId }, refr);
	}

	this.retireAddr = function(addrId)
	{
		json.post("api/ops/address.php", { op: "retire", address_id: addrId }, refr);
	}

	this.genAddr = function(addrId)
	{
		dlg.form("form/address_geo.php?id=" + addrId, refr, 400);
	}

	this.editAddr = function(addrId)
	{
		dlg.form("form/address_edit.php?id=" + addrId, function(obj)
		{
			var changed = isSet(obj.changed) && obj.changed;
			if (changed)
			{
				dlg.form("form/address_geo.php?id=" + addrId, refr, 400, refr);
			}
			else
			{
				refr();
			}
		}, 600);
	}

	//--------------------------------------------------------------------------------------
	// city
	//--------------------------------------------------------------------------------------
	this.createCity = function()
	{
		dlg.form("form/city_create.php", refr);
	}

	this.deleteCity = function(id)
	{
		dlg.form("form/city_delete.php?id=" + id, refr);
	}

	this.editCity = function(id)
	{
		dlg.form("form/city_edit.php?id=" + id, refr);
	}

	//--------------------------------------------------------------------------------------
	// club
	//--------------------------------------------------------------------------------------
	this.createClub = function()
	{
		dlg.form("form/club_create.php", function(data) { goTo("club_main.php?bck=1&id=" + data.club_id); }, 600);
	}

	this.restoreClub = function(id)
	{
		json.post("api/ops/club.php", { op: "restore", club_id: id }, refr);
	}

	this.retireClub = function(id)
	{
		json.post("api/ops/club.php", { op: "retire", club_id: id }, refr);
	}

	this.editClub = function(id)
	{
		dlg.form("form/club_edit.php?id=" + id, refr, 600);
	}

	this.joinClub = function(id)
	{
		json.post("api/ops/club.php", { op: 'add_user', club_id: id }, refr);
	}

	this.quitClub = function(id, confirmMessage)
	{
		function proceed()
		{
			json.post("api/ops/club.php", { op: 'remove_user', club_id: id }, refr);
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, proceed);
		}
		else
		{
			proceed();
		}
	}

	this.addClubMember = function(id, onSuccess)
	{
		if (!isSet(onSuccess))
			onSuccess = refr;
		dlg.form("form/add_user.php?club_id=" + id, onSuccess, 400);
	}

	this.removeClubMember = function(userId, clubId)
	{
		json.post("api/ops/club.php", { op: "remove_user", club_id: clubId, user_id: userId }, refr);
	}
	
	//--------------------------------------------------------------------------------------
	// league
	//--------------------------------------------------------------------------------------
	this.createLeague = function()
	{
		dlg.form("form/league_create.php", refr, 600);
	}

	this.restoreLeague = function(id)
	{
		json.post("api/ops/league.php", { op: "restore", league_id: id }, refr);
	}

	this.retireLeague = function(id)
	{
		json.post("api/ops/league.php", { op: "retire", league_id: id }, refr);
	}

	this.editLeague = function(id)
	{
		dlg.form("form/league_edit.php?id=" + id, refr, 600);
	}

	this.acceptLeague = function(id)
	{
		dlg.form("form/league_accept.php?id=" + id, refr, 600);
	}

	this.declineLeague = function(id)
	{
		dlg.form("form/league_decline.php?id=" + id, refr);
	}
	
	this.addLeagueManager = function(leagueId)
	{
		dlg.form("form/league_add_manager.php?league_id=" + leagueId, refr, 500);
	}
	
	this.removeLeagueManager = function(leagueId, userId, confirmMessage)
	{
		function _remove()
		{
			json.post("api/ops/league.php", { op: "remove_manager", league_id: leagueId, user_id: userId }, refr);
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _remove);
		}
		else
		{
			_remove();
		}
	}
	
	this.editLeagueRules = function(leagueId)
	{
		dlg.form("form/league_rules_edit.php?league_id=" + leagueId, refr);
	}
	
	this.addLeagueClub = function(leagueId)
	{
		dlg.form("form/league_add_club.php?league_id=" + leagueId, refr, 500);
	}
	
	this.removeLeagueClub = function(leagueId, clubId)
	{
		dlg.form("form/league_remove_club.php?league_id=" + leagueId + "&club_id=" + clubId, refr, 500);
	}
	
	this.acceptLeagueClub = function(leagueId, clubId)
	{
		json.post("api/ops/league.php", { op: "add_club", league_id: leagueId, club_id: clubId}, refr);
	}
	
	//--------------------------------------------------------------------------------------
	// country
	//--------------------------------------------------------------------------------------
	this.createCountry = function()
	{
		dlg.form("form/country_create.php", refr);
	}

	this.deleteCountry = function(id)
	{
		dlg.form("form/country_delete.php?id=" + id, refr);
	}

	this.editCountry = function(id)
	{
		dlg.form("form/country_edit.php?id=" + id, refr);
	}

	//--------------------------------------------------------------------------------------
	// event
	//--------------------------------------------------------------------------------------
	this.createEvent = function(clubId, now)
	{
		if (clubId)
		{
			var url = "form/event_create.php?club_id=" + clubId;
			if (now)
				url += '&now=1';
			
			dlg.form(url, function(obj)
			{
				if (!now && isSet(obj.mailing))
					dlg.form("form/event_mailing_create.php?events=" + obj.events + '&type=' + obj.mailing, refr, 500, refr);
				else
					refr();
			});
		}
		else
			dlg.infoForm("form/event_create_select_club.php?now=" + (now ? 1 : 0));
	}
	
	this.createRound = function(tournamentId, now)
	{
		dlg.form("form/round_create.php?tournament_id=" + tournamentId + "&now=" + (now ? 1 : 0), refr);
	}
	
	this.restoreEvent = function(id)
	{
		json.post("api/ops/event.php", { op: "restore", event_id: id }, function(obj)
		{
			dlg.form("form/event_mailing_create.php?events=" + id + '&type=4', refr, 500, refr);
		});
	}

	this.deleteEvent = function(id, backUrl)
	{
		dlg.form("form/event_delete.php?event_id=" + id, function() { goTo(backUrl); }, 400);
	}

	this.editEvent = function(id)
	{
		dlg.form("form/event_edit.php?event_id=" + id, function(obj)
		{
			if (isSet(obj.mailing))
			{
				dlg.form("form/event_mailing_create.php?events=" + id + '&type=' + obj.mailing, refr, 500, refr);
			}
			else
			{
				refr();
			}
		});
	}

	this.createEventMailing = function(events, mailingType)
	{
		var url = "form/event_mailing_create.php?events=" + events;
		if (isNumber(mailingType))
		{
			url += mailingType;
		}
		dlg.form(url, refr, 500, refr);
	}
	
	this.editEventMailing = function(mailingId)
	{
		dlg.form('form/event_mailing_edit.php?mailing_id=' + mailingId, refr, 500);
	}
	
	this.deleteEventMailing = function(mailingId)
	{
		json.post("api/ops/event.php", { op: "delete_mailing", mailing_id: mailingId }, refr);
	}

	this.attendEvent = function(id, url)
	{
		dlg.form("form/event_attend.php?id=" + id, function()
		{
			goTo(url);
		});
	}

	this.passEvent = function(id, url, message)
	{
		json.post("api/ops/event.php", { op: "attend", event_id: id, odds: 0 }, function()
		{
			if (isSet(message))
				dlg.info(message, null, null, function() { goTo(url); });
			else
				goTo(url);
		});
	}

	this.extendEvent = function(id)
	{
		dlg.form("form/event_extend.php?id=" + id, refr, 400);
	}
	
    this.convertEventToTournament = function(id, confirmMessage)
	{
		function _convert()
		{
			json.post("api/ops/event.php", { op: "to_tournament", event_id: id }, function(data)
			{
				goTo("tournament_info.php?id=" + data.tournament_id);
			});
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _convert);
		}
		else
		{
			_cancel();
		}
	}
	
	this.addEventUser = function(id, onSuccess)
	{
		if (!isSet(onSuccess))
			onSuccess = refr;
		dlg.form("form/add_user.php?event_id=" + id, onSuccess, 400);
	}

	this.removeEventUser = function(userId, eventId)
	{
		json.post("api/ops/event.php", { op: "remove_user", event_id: eventId, user_id: userId }, refr);
	}
	
	this.eventObs = function(eventId, userId)
	{
		var url = "form/obs_urls.php?event_id=" + eventId;
		if (userId)
			url += '&user_id=' + userId;
		dlg.infoForm(url, 600);
	}
	
	this.eventCreateBroadcast = function(eventId)
	{
		dlg.form("form/event_add_broadcast.php?event_id=" + eventId, refr, 400);
	}
	
	this.eventEditBroadcast = function(eventId, day, table, part)
	{
		dlg.form("form/event_edit_broadcast.php?event_id=" + eventId + '&day=' + day + '&table=' + table + '&part=' + part, refr, 400);
	}
	
	this.eventDeleteBroadcast = function(eventId, day, table, part, confirmMessage)
	{
		function _finish()
		{
			json.post("api/ops/event.php", { op: "remove_broadcast", event_id: eventId, day: day, table: table, part: part }, refr);
		}
		
		if (confirmMessage)
		{
			dlg.yesNo(confirmMessage, null, null, _finish);
		}
		else
		{
			_finish();
		}
	}
	
	//--------------------------------------------------------------------------------------
	// tournament
	//--------------------------------------------------------------------------------------
	this.createTournament = function(clubId, leagueId)
	{
		var formLink = "form/tournament_create.php?club_id=" + clubId;
		if (isNumber(leagueId))
		{
			formLink += "&league_id=" + leagueId;
		}
		dlg.form(formLink, refr, 900);
	}
	
	this.restoreTournament = function(id)
	{
		json.post("api/ops/tournament.php", { op: "restore", tournament_id: id }, refr);
	}
	
	this.deleteTournament = function(id, backUrl)
	{
		dlg.form("form/tournament_delete.php?tournament_id=" + id, function() { goTo(backUrl); }, 400);
	}

	this.finishTournament = function(id, confirmMessage, doneMessage)
	{
		function _finish()
		{
			json.post("api/ops/tournament.php", { op: "finish", tournament_id: id }, function()
			{
				if (isString(doneMessage))
				{
					dlg.info(doneMessage, null, null, refr);
				}
				else
				{
					refr();
				}
			});
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _finish);
		}
		else
		{
			_finish();
		}
	}

	this.editTournament = function(id)
	{
		dlg.form("form/tournament_edit.php?id=" + id, refr, 1000);
	}
	
	this.approveTournament = function(id, leagueId)
	{
		dlg.form("form/tournament_approve.php?tournament_id=" + id + "&league_id=" + leagueId, function ()
		{
			goTo("tournament_info.php?id=" + id);
		}, 600);
	}
	
	this.addTournamentUser = function(tournamentId)
	{
		dlg.form("form/add_user.php?tournament_id=" + tournamentId, refr, 400);
	}

	this.removeTournamentUser = function(tournamentId, userId)
	{
		json.post("api/ops/tournament.php", { op: "remove_user", tournament_id: tournamentId, user_id: userId }, refr);
	}
	
	this.acceptTournamentUser = function(tournamentId, userId)
	{
		json.post("api/ops/tournament.php", { op: "accept_user", tournament_id: tournamentId, user_id: userId }, refr);
	}
	
	this.attendTournament = function(tournamentId, isTeam)
	{
		if (isTeam)
			dlg.form("form/add_user.php?self=1&tournament_id=" + tournamentId, refr, 400);
		else
			json.post("api/ops/tournament.php", { op: "add_user", tournament_id: tournamentId }, refr);
	}
	
	this.unattendTournament = function(tournamentId)
	{
		json.post("api/ops/tournament.php", { op: "remove_user", tournament_id: tournamentId }, refr);
	}
	
	this.tournamentObs = function(tournamentId, userId)
	{
		var url = "form/obs_urls.php?tournament_id=" + tournamentId;
		if (userId)
			url += '&user_id=' + userId;
		dlg.infoForm(url, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// series
	//--------------------------------------------------------------------------------------
	this.createSeries = function(leagueId)
	{
		dlg.form("form/series_create.php?league_id=" + leagueId, refr, 900);
	}
	
	this.restoreSeries = function(id)
	{
		json.post("api/ops/series.php", { op: "restore", series_id: id }, refr);
	}

	this.cancelSeries = function(id, confirmMessage)
	{
		function _cancel()
		{
			json.post("api/ops/series.php", { op: "cancel", series_id: id }, refr)
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _cancel);
		}
		else
		{
			_cancel();
		}
	}

	this.editSeries = function(id)
	{
		dlg.form("form/series_edit.php?id=" + id, refr);
	}
	
	this.finishSeries = function(id, confirmMessage, doneMessage)
	{
		function _finish()
		{
			json.post("api/ops/series.php", { op: "finish", series_id: id }, function()
			{
				if (isString(doneMessage))
				{
					dlg.info(doneMessage, null, null, refr);
				}
				else
				{
					refr();
				}
			});
		}
		
		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _finish);
		}
		else
		{
			_finish();
		}
	}

	//--------------------------------------------------------------------------------------
	// scoring system
	//--------------------------------------------------------------------------------------
	this.deleteScoringSystem = function(id, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/scoring.php", { op: 'delete', scoring_id: id }, refr);
		});
	}

	this.createScoringSystem = function(clubId, leagueId)
	{
		var url = "form/scoring_create.php";
		var delim = "?";
		if (clubId)
		{
			url += delim + "club=" + clubId;
			delim = "&";
		}
		if (leagueId)
		{
			url += delim + "league=" + leagueId;
		}
		dlg.form(url, refr, 400);
	}

	this.editScoringSystem = function(id)
	{
		goTo("scoring.php?bck=1&id=" + id);
	}
	
	this.showScoring = function(name)
	{
		var n = $('#' + name + '-night1');
		var d = $('#' + name + '-difficulty');
		var flags = 0;
		if (n.length > 0 || d.length > 0)
		{
			if (!n.prop('checked'))
			{
				flags |= /*SCORING_OPTION_NO_NIGHT_KILLS*/1;
			}
			if (!d.prop('checked'))
			{
				flags |= /*SCORING_OPTION_NO_GAME_DIFFICULTY*/2;
			}
		}
		dlg.infoForm("form/scoring_show.php" + 
			"?id=" + $('#' + name + '-sel').val() + 
			"&version=" + $('#' + name + '-ver').val() +
			"&nid=" + $('#' + name + '-norm-sel').val() + 
			"&nver=" + $('#' + name + '-norm-ver').val() +
			"&ops_flags=" + flags);
	}
	
	this.showPlayerTournamentNorm = function(playerId, tournamentId)
	{
		dlg.infoForm("form/scoring_normalization_show.php" + 
			"?nid=" + $('#' + name + '-norm-sel').val() + 
			"&nver=" + $('#' + name + '-norm-ver').val() +
			"&pid=" + playerId +
			"&tid=" + tournamentId);
	}
	
	this.onChangeScoring = function(name, version, changed)
	{
		var scoringId = $('#' + name + '-sel').val();
		json.post("api/get/scorings.php", { scoring_id: scoringId }, function(data)
		{
			var s = data.scorings[0];
			var c = $('#' + name + '-ver');
			c.find('option').remove();
			var v1 = null;
			for (var v of s.versions)
			{
				c.append($('<option>').val(v.version).text(v.version));
				if (v.version == version)
				{
					v1 = v;
				}
			}
			if (!v1)
			{
				v1 = v;
			}
			c.val(v1.version);
			mr.onChangeScoringVersion(name, v1, changed);
		});
	}
	
	this.onChangeScoringVersion = function(name, scoring, changed)
	{
		if (scoring)
		{
			var night1Disabled = !isSet(scoring.rules.night1) || !Array.isArray(scoring.rules.night1) || scoring.rules.night1.length == 0;
			var difDisabled = true;
			for (var s in scoring.rules)
			{
				for (var p of scoring.rules[s])
				{
					if (isString(p.points) && p.points.toLowerCase().includes('difficulty'))
					{
						difDisabled = false;
						break;
					}
				}
			}
			
			var d = $('#' + name + '-night1').prop("disabled", night1Disabled);
			if (night1Disabled) 
			{
				d.prop("checked", false);
			}
			d = $('#' + name + '-difficulty').prop("disabled", difDisabled);
			if (difDisabled) 
			{
				d.prop("checked", false);
			}
			mr.onChangeScoringOptions(name, changed);
		}
		else
		{
			json.post("api/get/scorings.php", { scoring_id: $('#' + name + '-sel').val(), scoring_version: $('#' + name + '-ver').val() }, function(data)
			{
				mr.onChangeScoringVersion(name, data.scorings[0].versions[0], changed);
			});
		}
	}
	
	this.onChangeNormalizer = function(name, version, changed)
	{
		var normalizerId = $('#' + name + '-norm-sel').val();
		var versionDiv = $('#' + name + '-norm-version');
		if (normalizerId > 0)
		{
			json.post("api/get/normalizers.php", { normalizer_id: normalizerId }, function(data)
			{
				var s = data.normalizers[0];
				var c = $('#' + name + '-norm-ver');
				c.find('option').remove();
				var v1 = null;
				for (var v of s.versions)
				{
					c.append($('<option>').val(v.version).text(v.version));
					if (v.version == version)
					{
						v1 = v;
					}
				}
				if (!v1)
				{
					v1 = v;
				}
				c.val(v1.version);
				versionDiv.css('visibility', 'visible');
				mr.onChangeNormalizerVersion(name, changed);
			});
		}
		else
		{
			versionDiv.css('visibility', 'hidden');
			mr.onChangeNormalizerVersion(name, changed);
		}
	}
	
	this.onChangeNormalizerVersion = function(name, changed)
	{
		mr.onChangeScoringOptions(name, changed);
	}
	
	this.onChangeScoringOptions = function(name, changed)
	{
		if (changed)
		{
			var ops = {};
			var n = $('#' + name + '-night1');
			var d = $('#' + name + '-difficulty');
			var w = $('#' + name + '-weight');
			var g = $('#' + name + '-group');
			if (n.length > 0 || d.length > 0)
			{
				var flags = 0;
				if (!n.prop('checked'))
				{
					flags |= /*SCORING_OPTION_NO_NIGHT_KILLS*/1;
				}
				if (!d.prop('checked'))
				{
					flags |= /*SCORING_OPTION_NO_GAME_DIFFICULTY*/2;
				}
				ops.flags = flags;
			}
			if (w.length > 0)
			{
				var weight = parseFloat(w.val());
				if (Math.abs(weight - 1) > Number.EPSILON)
				{
					ops.weight = weight;
				}
			}
			if (g.length > 0 && g.val())
			{
				ops.group = g.val();
			}
			changed(
			{
				sId: $('#' + name + '-sel').val(),
				sVer: $('#' + name + '-ver').val(), 
				nId: $('#' + name + '-norm-sel').val(),
				nVer: $('#' + name + '-norm-ver').val(), 
				ops: ops
			});
		}
	}
	
	this.functionHelp = function()
	{
		dlg.infoForm("form/function_help.php", 600);
	}
	
	//--------------------------------------------------------------------------------------
	// scoring normalizers
	//--------------------------------------------------------------------------------------
	this.deleteNormalizer = function(id, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/normalizer.php", { op: 'delete', normalizer_id: id }, refr);
		});
	}

	this.createNormalizer = function(clubId, leagueId)
	{
		var url = "form/normalizer_create.php";
		var delim = "?";
		if (clubId)
		{
			url += delim + "club=" + clubId;
			delim = "&";
		}
		if (leagueId)
		{
			url += delim + "league=" + leagueId;
		}
		dlg.form(url, refr, 400);
	}

	this.editNormalizer = function(id)
	{
		goTo("normalizer.php?bck=1&id=" + id);
	}
	
	//--------------------------------------------------------------------------------------
	// gaining system
	//--------------------------------------------------------------------------------------
	this.deleteGainingSystem = function(id, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/gaining.php", { op: 'delete', gaining_id: id }, refr);
		});
	}

	this.createGainingSystem = function(leagueId)
	{
		var url = "form/gaining_create.php";
		if (leagueId)
		{
			url += "?league=" + leagueId;
		}
		dlg.form(url, refr, 400);
	}

	this.editGainingSystem = function(id)
	{
		dlg.form("form/gaining_edit.php?gaining_id=" + id, refr, 1200);
	}
	
	this.showGaining = function(name)
	{
		dlg.infoForm("form/gaining_show.php" + 
			"?id=" + $('#' + name + '-sel').val() + 
			"&version=" + $('#' + name + '-ver').val());
	}
	
	//--------------------------------------------------------------------------------------
	// rules
	//--------------------------------------------------------------------------------------
	this.createRules = function(clubId, leagueId)
	{
		var u = "form/rules_edit.php?create&club_id=" + clubId;
		if (isSet(leagueId))
			u += "&league_id=" + leagueId;
		dlg.form(u, refr);
	}

	this.editRules = function(clubId, leagueId, rulesId)
	{
		var u = "form/rules_edit.php?club_id=" + clubId;
		if (isSet(leagueId))
			u += "&league_id=" + leagueId;
		if (isSet(rulesId))
			u += "&rules_id=" + rulesId;
		dlg.form(u, refr);
	}

	this.deleteRules = function(rulesId, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/rules.php", { op: 'delete', rules_id: rulesId }, refr);
		}

		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}
	
	//--------------------------------------------------------------------------------------
	// game
	//--------------------------------------------------------------------------------------
	this.deleteGame = function(gameId, confirmMessage, onSuccess)
	{
		if (!isSet(onSuccess))
			onSuccess = refr;
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/game.php", { op: 'delete', game_id: gameId }, onSuccess);
		});
	}
	
	this.editGame = function(gameId)
	{
		dlg.form("form/game_raw_edit.php?game_id=" + gameId, refr, 1200);
	}
	
	this.setGameVideo = function(gameId)
	{
		dlg.form("form/game_video_edit.php?game=" + gameId, refr, 600);
	}
	
	this.watchGameVideo = function(gameId)
	{
		dlg.page("form/game_video.php?game=" + gameId);
	}
	
	this.fiimGameForm = function(gameId)
	{
		window.open('game_fiim_form.php?game_id=' + gameId, '_blank').focus();
	}
	
	this.gameBonus = function(gameId, playerNum)
	{
		dlg.form("form/game_bonus.php?game_id=" + gameId + '&player_num=' + playerNum, refr, 500);
	}
	
	this.ownGame = function(eventId, table, round, userId, promptStr)
	{
		function _own()
		{
			json.post(
				"api/ops/game.php",
				{op:'own_current', event_id: eventId, "table": table, "round": round},
				goTo('game1.php?event_id=' + eventId + '&table=' + table + '&round=' + round));
		}

		if (isString(promptStr))
		{
			dlg.yesNo(promptStr, null, null, _own);
		}
		else
		{
			_own();
		}
	}
	
	//--------------------------------------------------------------------------------------
	// game objections
	//--------------------------------------------------------------------------------------
	this.gotoObjections = function(gameId, back)
	{
		var url = "view_game_objections.php?auto&gametime=-1&id=" + gameId;
		if (!isBool(back) || back)
			url += "&bck=1";
		goTo(url);
	}
	
	this.createObjection = function(gameId)
	{
		dlg.form("form/objection_create.php?game_id=" + gameId, refr, 600);
	}
	
	this.editObjection = function(objectionId)
	{
		dlg.form("form/objection_edit.php?objection_id=" + objectionId, refr, 600);
	}
	
	this.replyObjection = function(objectionId)
	{
		dlg.form("form/objection_create.php?parent_id=" + objectionId, refr, 600);
	}
	
	this.deleteObjection = function(objectionId, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/objection.php", { op: 'delete', objection_id: objectionId }, refr);
		}

		if (isString(confirmMessage))
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}
	
	this.respondObjection = function(objectionId)
	{
		dlg.form("form/objection_respond.php?objection_id=" + objectionId, refr, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// find
	//--------------------------------------------------------------------------------------
	this.gotoFind = function(data)
	{
		goTo({ page: -data.id });
	}
	
	//--------------------------------------------------------------------------------------
	// user
	//--------------------------------------------------------------------------------------
	this.editUserAccess = function(userId)
	{
		dlg.form("form/user_access.php?user_id=" + userId, refr, 400);
	}
	
	this.editClubAccess = function(userId, clubId)
	{
		dlg.form("form/user_access.php?user_id=" + userId + "&club_id=" + clubId, refr, 400);
	}
	
	this.editEventAccess = function(userId, eventId)
	{
		dlg.form("form/user_access.php?user_id=" + userId + "&event_id=" + eventId, refr, 400);
	}
	
	this.editTournamentAccess = function(userId, tournamentId)
	{
		dlg.form("form/user_access.php?user_id=" + userId + "&tournament_id=" + tournamentId, refr, 400);
	}
	
	this.eventUserPhoto = function(userId, eventId)
	{
		dlg.infoForm("form/user_custom_photo.php?user_id=" + userId + "&event_id=" + eventId, 400);
	}
	
	this.tournamentUserPhoto = function(userId, tournamentId)
	{
		dlg.infoForm("form/user_custom_photo.php?user_id=" + userId + "&tournament_id=" + tournamentId, 400);
	}
	
	this.clubUserPhoto = function(userId, clubId)
	{
		dlg.infoForm("form/user_custom_photo.php?user_id=" + userId + "&club_id=" + clubId, 400);
	}
	
	this.editUser = function(userId)
	{
		dlg.form("form/account_edit.php?user_id=" + userId, refr, 800);
	}
	
	//--------------------------------------------------------------------------------------
	// comments
	//--------------------------------------------------------------------------------------
	this.showComments = function(object_name, object_id, limit, show_all, edit_class)
	{
		var url = "show_comments.php?" + object_name + "=" + object_id;
		if (isNumber(limit))
		{
			url += "&limit=" + limit;
		}
		
		if (isSet(edit_class))
		{
			url += "&class=" + edit_class;
		}
		
		if (isSet(show_all) && show_all)
		{
			url += "&all";
		}
		
		html.get(url, function(text, title)
		{
			$('#comments').html(text);
			$("#comment").keypress(function (e)
			{
				if (e.which == 13 && !e.shiftKey) 
				{
					var message = $("#comment").val().trim();
					if (message.length > 0)
					{
						json.post("api/ops/" + object_name + ".php", { op: "comment", id: object_id, comment: message }, function()
						{
							$("#comment").val("");
							mr.showComments(object_name, object_id, limit, show_all, edit_class);
						});
					}
					return false;
				}
			});			
		});
	}
	
    this.checkCommentArea = function()
	{
		var elem = document.getElementById("comment");
		var val = elem.scrollHeight;
		var h = elem.offsetHeight;
		var cal = parseInt(h) - 2;
		if (val > cal)
		{
			var fontSize = parseInt($('#comment').css("fontSize"));
			cal = cal + fontSize;
			$('#comment').css('height', cal + 'px');
		}
    }
	
	//--------------------------------------------------------------------------------------
	// videos
	//--------------------------------------------------------------------------------------
	this.createVideo = function(vtype, clubId, eventId, tournamentId)
	{
		var url = "form/video_create.php?vtype=" + vtype;
		if (clubId)
		{
			url += '&club_id=' + clubId;
		}
		if (eventId)
		{
			url += '&event_id=' + eventId;
		}
		if (tournamentId)
		{
			url += '&tournament_id=' + tournamentId;
		}
		dlg.form(url, refr, 600);
	}
	
	this.editVideo = function(videoId)
	{
		dlg.form("form/video_edit.php?id=" + videoId, refr, 600);
	}
	
	this.deleteVideo = function(videoId, confirmMessage, urlToGo)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/video.php", { 'op': 'delete', 'video_id': videoId  }, function() { goTo(urlToGo); });
		});
	}
	
	this.showVideoUsers = function(videoId)
	{
		var url = "form/video_users.php?id=" + videoId;
		html.get(url, function(text, title)
		{
			$('#tagged').html(text);
			var tagControl = $("#tag_user");
			if (isObject(tagControl))
			{
				tagControl.autocomplete(
				{ 
					source: function( request, response )
					{
						$.getJSON("api/control/user.php",
						{
							num: 8,
							term: tagControl.val()
						}, response);
					}
					, select: function(event, ui) { mr.tagVideo(ui.item.id, videoId); }
					, minLength: 0
				})
				.on("focus", function () { $(this).autocomplete("search", ''); });
			}
		});
	}
	
	this.tagVideo = function(userId, videoId)
	{
		json.post("api/ops/video.php", { 'op': 'tag', 'video_id': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
	
	this.untagVideo = function(userId, videoId)
	{
		json.post("api/ops/video.php", { 'op': 'untag', 'video_id': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
}

var swfu = null;

//--------------------------------------------------------------------------------------
// name control with languages support
//--------------------------------------------------------------------------------------
class NameControl
{
	constructor(values, controlId, varName)
	{
		this.controlId = controlId;
		this.varName = varName;
		this.langs = [];
		if (values)
		{
			for (const code in values)
			{
				if (code != 'name')
				{
					this.langs.push(code);
				}
			}
		}
		this.draw(values);
	}
	
	getValues()
	{
		var values = { name: $('#' + this.controlId).val() };
		for (const i in this.langs)
		{
			var code = this.langs[i];
			var control = $('#' + this.controlId + '-' + code);
			if (control.length)
			{
				values[code] = control.val();
			}
		}
		return values;
	}
	
	setValues(values)
	{
		$('#' + this.controlId).val(values.name);
		for (const i in this.langs)
		{
			var code = this.langs[i];
			var control = $('#' + this.controlId + '-' + code);
			if (control.length && values[code])
			{
				control.val(values[code]);
			}
		}
	}
	
	draw(values)
	{
		var oldValues = values;
		if (!oldValues)
		{
			oldValues = this.getValues();
		}
		var html = 
			'<table class="transp"><tr><td width="24"><img src="images/sync.png" width="20"></td><td><input id="' + this.controlId + 
			'"></td><td><button id="' + this.controlId + 
			'-lang" class="icon" onMouseEnter="' + this.varName + 
			'.showLangMenu()"><img src="images/create.png"></button></td></tr>';
		for (const i in this.langs)
		{
			var code = this.langs[i];
			html += 
				'<tr><td><img src="images/' + code + 
				'.png" width="20"></td><td><input id="' + this.controlId + 
				'-' + code + 
				'"></td><td><button class="icon" onClick="' + this.varName + 
				'.removeLang(\'' + code + '\')"><img src="images/delete.png"></button></td></tr>';
		}
		html += '</table>';
		$('#' + this.controlId + '-div').html(html);
		this.setValues(oldValues);
	}
	
	addLang(code)
	{
		var exists = false;
		for (const i in this.langs)
		{
			if (this.langs[i] == code)
			{
				exists = true;
				break;
			}
		}
		if (!exists)
		{
			this.langs.push(code);
			this.draw();
		}
		$(this.controlId + '-' + code).focus();
	}

	removeLang(code)
	{
		for (const i in this.langs)
		{
			if (this.langs[i] == code)
			{
				this.langs.splice(i, 1);
				this.draw();
				break;
			}
		}
	}

	showLangMenu()
	{
		setCurrentMenu('#' + this.controlId + '-menu');
		var langMenu = $('#' + this.controlId + '-menu').menu();
		var b = $('#' + this.controlId + '-lang');
		langMenu.show(0, function()
		{
			langMenu.position(
			{
				my: "left top",
				at: "left bottom",
				of: b
			});
			$(document).one("click", function() { setCurrentMenu(null); });
		});
	}
	
	fillRequest(request, name)
	{
		if (!isSet(name))
		{
			name = 'name';
		}
		var str = $('#' + this.controlId).val();
		if (str.trim().length > 0)
		{
			request[name] = str;
		}
		for (const i in this.langs)
		{
			var code = this.langs[i];
			str = $('#' + this.controlId + '-' + code).val();
			if (str.trim().length > 0)
			{
				request[name + '_' + code] = str;
			}
		}
	}
}
