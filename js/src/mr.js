var mr = new function()
{
	//--------------------------------------------------------------------------------------
	// profile
	//--------------------------------------------------------------------------------------
	this.createAccount = function(name, email)
	{
		dlg.form("form/account_create.php", function(){}, 400);
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
		if (typeof prefix == "undefined")
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
		return langs;
	}

	this.setLangs = function(langs, prefix)
	{
		if (typeof prefix == "undefined")
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

		if (typeof confirmMessage == "string")
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

		if (typeof confirmMessage == "string")
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

		if (typeof confirmMessage == "string")
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
	// Club Season
	//--------------------------------------------------------------------------------------
	this.editClubSeason = function(id)
	{
		dlg.form("form/club_season_edit.php?season=" + id, refr);
	}

	this.deleteClubSeason = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/club_season.php", { op: 'delete', season_id: id }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createClubSeason = function(clubId)
	{
		dlg.form("form/club_season_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// League Season
	//--------------------------------------------------------------------------------------
	this.editLeagueSeason = function(id)
	{
		dlg.form("form/league_season_edit.php?season=" + id, refr);
	}

	this.deleteLeagueSeason = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/league_season.php", { op: 'delete', season_id: id }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createLeagueSeason = function(leagueId)
	{
		dlg.form("form/league_season_create.php?league=" + leagueId, refr);
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
			var changed = typeof obj.changed != "undefined" && obj.changed;
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
		dlg.form("form/club_create.php", refr, 600);
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
		json.post("api/ops/user.php", { op: 'join_club', club_id: id }, refr);
	}

	this.quitClub = function(id, confirmMessage)
	{
		function proceed()
		{
			json.post("api/ops/user.php", { op: 'quit_club', club_id: id }, refr);
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, proceed);
		}
		else
		{
			proceed();
		}
	}

	this.playClub = function(id)
	{
		window.location.replace("game.php?club=" + id);
	}

	this.acceptClub = function(id)
	{
		dlg.form("form/club_accept.php?id=" + id, refr, 600);
	}

	this.declineClub = function(id)
	{
		dlg.form("form/club_decline.php?id=" + id, refr);
	}
	
	this.addClubMember = function(id, onSuccess)
	{
		if (typeof onSuccess == "undefined")
			onSuccess = refr;
		dlg.form("form/add_user.php?club_id=" + id, onSuccess, 400);
	}

	this.removeClubMember = function(userId, clubId)
	{
		json.post("api/ops/user.php", { op: "quit_club", club_id: clubId, user_id: userId }, refr);
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
		
		if (typeof confirmMessage == "string")
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
	this.createEvent = function(clubId)
	{
		dlg.form("form/event_create.php?club_id=" + clubId, function(obj)
		{
			if (typeof obj.mailing != "undefined")
			{
				dlg.form("form/event_mailing_create.php?events=" + obj.events + '&type=' + obj.mailing, refr, 500, refr);
			}
			else
			{
				refr();
			}
		});
	}
	
	this.createRound = function(tournamentId)
	{
		dlg.form("form/round_create.php?tournament_id=" + tournamentId, refr);
	}
	
	this.restoreEvent = function(id)
	{
		json.post("api/ops/event.php", { op: "restore", event_id: id }, function(obj)
		{
			dlg.form("form/event_mailing_create.php?events=" + id + '&type=4', refr, 500, refr);
		});
	}

	this.cancelEvent = function(id, confirmMessage)
	{
		function _cancel()
		{
			json.post("api/ops/event.php", { op: "cancel", event_id: id }, function()
			{
				dlg.form("form/event_mailing_create.php?events=" + id + '&type=1', refr, 500, refr);
			});
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _cancel);
		}
		else
		{
			_cancel();
		}
	}

	this.editEvent = function(id)
	{
		dlg.form("form/event_edit.php?event_id=" + id, function(obj)
		{
			if (typeof obj.mailing != "undefined")
			{
				dlg.form("form/event_mailing_create.php?events=" + id + '&type=' + obj.mailing, refr, 500, refr);
			}
			else
			{
				refr();
			}
		});
	}

	this.eventMailing = function(id)
	{
		window.location.replace("event_mailings.php?bck=1&id=" + id);
	}
	
	this.createEventMailing = function(events, mailingType)
	{
		var url = "form/event_mailing_create.php?events=" + events;
		if (typeof mailingType == "number")
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
			if (typeof message == "undefined")
				goTo(url);
			else
				dlg.info(message, null, null, function() { goTo(url); });
		});
	}

	this.playEvent = function(id)
	{
		window.location.replace("game.php?event=" + id);
	}

	this.extendEvent = function(id)
	{
		dlg.form("form/event_extend.php?id=" + id, refr, 400);
	}
	
	this.convertEventToTournament = function(id, confirmMessage)
	{
		function _convert()
		{
			json.post("api/ops/event.php", { op: "convert_to_tournament", event_id: id }, function(data)
			{
				goTo("tournament_info.php?id=" + data.tournament_id);
			});
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _convert);
		}
		else
		{
			_cancel();
		}
	}
	
	this.showEventToken = function(id)
	{
		dlg.infoForm("form/event_token.php?event_id=" + id, 400);
	}
	
	this.addEventUser = function(id, onSuccess)
	{
		if (typeof onSuccess == "undefined")
			onSuccess = refr;
		dlg.form("form/add_user.php?event_id=" + id, onSuccess, 400);
	}

	this.removeEventUser = function(userId, eventId)
	{
		json.post("api/ops/user.php", { op: "quit_event", event_id: eventId, user_id: userId }, refr);
	}
	
	//--------------------------------------------------------------------------------------
	// tournament
	//--------------------------------------------------------------------------------------
	this.createTournament = function(clubId, leagueId)
	{
		var formLink = "form/tournament_create.php?club_id=" + clubId;
		if (typeof leagueId == "number")
		{
			formLink += "&league_id=" + leagueId;
		}
		dlg.form(formLink, refr, 900);
	}
	
	this.restoreTournament = function(id)
	{
		json.post("api/ops/tournament.php", { op: "restore", tournament_id: id }, refr);
	}

	this.cancelTournament = function(id, confirmMessage)
	{
		function _cancel()
		{
			json.post("api/ops/tournament.php", { op: "cancel", tournament_id: id }, r)
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _cancel);
		}
		else
		{
			_cancel();
		}
	}

	this.editTournament = function(id)
	{
		dlg.form("form/tournament_edit.php?id=" + id, refr);
	}
	
	this.approveTournament = function(id, leagueId)
	{
		dlg.form("form/tournament_approve.php?tournament_id=" + id + "&league_id=" + leagueId, function ()
		{
			goTo("tournament_info.php?id=" + id);
		}, 600);
	}
	
	this.showTournamentToken = function(id)
	{
		dlg.infoForm("form/tournament_token.php?tournament_id=" + id, 400);
	}

	this.addTournamentUser = function(id, onSuccess)
	{
		if (typeof onSuccess == "undefined")
			onSuccess = refr;
		dlg.form("form/add_user.php?tournament_id=" + id, onSuccess, 400);
	}

	this.removeTournamentUser = function(userId, tournamentId)
	{
		json.post("api/ops/user.php", { op: "quit_tournament", tournament_id: tournamentId, user_id: userId }, refr);
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
			"?sid=" + $('#' + name + '-sel').val() + 
			"&sver=" + $('#' + name + '-ver').val() +
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
			var night1Disabled = true;
			var difDisabled = true;
			for (var s in scoring.rules)
			{
				for (var p of scoring.rules[s])
				{
					if (p.min_difficulty || p.max_difficulty)
					{
						difDisabled = false;
					}
					else if (p.min_night1 || p.max_night1 || p.figm_first_night_score)
					{
						night1Disabled = false;
					}
				}
			}
			if (night1Disabled) 
			{
				$('#' + name + '-night1').prop("disabled", true).prop("checked", false);
			}
			else
			{
				$('#' + name + '-night1').prop("disabled", false);
			}
			if (difDisabled) 
			{
				$('#' + name + '-difficulty').prop("checked", false).prop("disabled", true);
			}
			else
			{
				$('#' + name + '-difficulty').prop("disabled", false);
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
	// rules
	//--------------------------------------------------------------------------------------
	this.createRules = function(clubId, leagueId)
	{
		var u = "form/rules_edit.php?create&club_id=" + clubId;
		if (typeof leagueId != "undefined")
			u += "&league_id=" + leagueId;
		dlg.form(u, refr);
	}

	this.editRules = function(clubId, leagueId, rulesId)
	{
		var u = "form/rules_edit.php?club_id=" + clubId;
		if (typeof leagueId != "undefined")
			u += "&league_id=" + leagueId;
		if (typeof rulesId != "undefined")
			u += "&rules_id=" + rulesId;
		dlg.form(u, refr);
	}

	this.deleteRules = function(rulesId, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/rules.php", { op: 'delete', rules_id: rulesId }, refr);
		}

		if (typeof confirmMessage == "string")
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
		if (typeof onSuccess == "undefined")
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
	
	this.figmGameForm = function(gameId)
	{
		window.open('game_figm_form.php?game_id=' + gameId, '_blank').focus();
	}
	
	this.gameExtraPoints = function(gameId, userId)
	{
		dlg.form("form/game_extra_points.php?game_id=" + gameId + '&user_id=' + userId, refr, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// game objections
	//--------------------------------------------------------------------------------------
	this.gotoObjections = function(gameId, back)
	{
		var url = "view_game_objections.php?auto&gametime=-1&id=" + gameId;
		if (typeof back != "boolean" || back)
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

		if (typeof confirmMessage == "string")
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
	this.banUser = function(userId, clubId)
	{
		var params = { op: 'ban', user_id: userId  };
		if (typeof clubId != "undefined")
		{
			json.post("api/ops/user.php", { op: 'ban', user_id: userId, club_id: clubId }, refr);
		}
		else
		{
			json.post("api/ops/user.php", { op: 'site_ban', user_id: userId }, refr);
		}
	}
	
	this.unbanUser = function(userId, clubId)
	{
		var params = { op: 'ban', user_id: userId  };
		if (typeof clubId != "undefined")
		{
			json.post("api/ops/user.php", { op: 'unban', user_id: userId, club_id: clubId }, refr);
		}
		else
		{
			json.post("api/ops/user.php", { op: 'site_unban', user_id: userId }, refr);
		}
	}
	
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
	
	this.editUser = function(userId)
	{
		dlg.form("form/account_edit.php?user_id=" + userId, refr, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// comments
	//--------------------------------------------------------------------------------------
	this.showComments = function(object_name, object_id, limit, show_all, edit_class)
	{
		var url = "show_comments.php?" + object_name + "=" + object_id;
		if (typeof limit == "number")
		{
			url += "&limit=" + limit;
		}
		
		if (typeof edit_class != "undefined")
		{
			url += "&class=" + edit_class;
		}
		
		if (typeof show_all != "undefined" && show_all)
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
		if (typeof clubId != "undefined" && clubId != null)
		{
			url += '&club_id=' + clubId;
		}
		if (typeof eventId != "undefined" && eventId != null)
		{
			url += '&event_id=' + eventId;
		}
		if (typeof tournamentId != "undefined" && tournamentId != null)
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
			if (typeof tagControl == "object")
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
